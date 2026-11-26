<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\Log;
use App\Models\PackingList;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\ShipmentLines;
use App\Support\StockPicker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Delivery Order — acuan pengiriman project dari satu gudang ke customer.
 *
 * Dua jalur, dipilih saat membuat DO (tidak boleh campur):
 *   - Langsung (barang sedikit): tiap baris dokumen (deskripsi dari item
 *     Quotation / bebas) berisi baris stok milik project (wajib minimal satu).
 *   - Dengan Packing List (barang banyak): memuat PKL Siap Kirim dengan project
 *     & gudang yang sama; baris dokumen terisi dari koli (bisa diubah).
 * Draft boleh diubah/dihapus (PKL lepas kembali). Posting oleh gudang (tanpa
 * approval) mengeluarkan stok (log out) dari isi baris / isi koli; PKL menjadi
 * Terkirim.
 */
class DeliveryOrderController extends Controller
{
    public function index()
    {
        return view('delivery-order.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = DeliveryOrder::withCount('items')->with(['project', 'warehouse', 'packingLists:id,delivery_order_id,packing_number']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('do_number', 'like', "%{$searchValue}%")
                    ->orWhere('recipient_name', 'like', "%{$searchValue}%")
                    ->orWhere('customer_ref', 'like', "%{$searchValue}%")
                    ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")->orWhere('project_name', 'like', "%{$searchValue}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $orders = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = $orders->map(fn (DeliveryOrder $order, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $order->id,
            'do_number' => $order->do_number,
            'delivery_date' => $order->delivery_date?->format('d/m/Y') ?? '—',
            'project' => $order->project ? $order->project->project_number.' — '.$order->project->project_name : '—',
            'recipient_name' => $order->recipient_name,
            'warehouse_name' => $order->warehouse?->name ?? '—',
            'mode' => $order->uses_packing_list ? 'Packing List: '.$order->packingLists->pluck('packing_number')->implode(', ') : 'Langsung',
            'item_count' => $order->items_count,
            'status' => $order->status,
            'status_badge' => $order->statusBadgeHtml(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        return view('delivery-order.form', $this->formData(null, $request->integer('project_id') ?: null));
    }

    /**
     * Gudang (stok project / PKL Siap Kirim), default penerima & PO customer,
     * dan item Quotation project.
     */
    public function projectStock(Request $request): JsonResponse
    {
        $validated = $request->validate(['project_id' => 'required|integer|exists:projects,id']);
        $project = Project::with('company')->findOrFail($validated['project_id']);
        $packingWarehouses = PackingList::where('project_id', $project->id)->where('status', PackingList::STATUS_READY)->pluck('warehouse_id')->all();
        $pos = ShipmentLines::pos($project);

        return response()->json([
            'success' => true,
            'warehouses' => ShipmentLines::projectWarehouses($project->id, $packingWarehouses),
            'recipient' => ShipmentLines::projectDefaults($project),
            'quotation_items' => ShipmentLines::quotationItems($project),
            'pos_number' => $pos?->pos_number,
            'pos_items' => ShipmentLines::posItems($pos),
        ]);
    }

    /**
     * PKL Siap Kirim project + gudang yang belum dimuat DO lain (beserta koli,
     * untuk mengisi baris DO).
     */
    public function packingLists(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'order_id' => 'nullable|integer',
        ]);

        $rows = PackingList::with('boxes')
            ->where('project_id', $validated['project_id'])
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('status', PackingList::STATUS_READY)
            ->where(fn ($q) => $q->whereNull('delivery_order_id')->when($validated['order_id'] ?? null, fn ($w, $id) => $w->orWhere('delivery_order_id', $id)))
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $rows->map(fn (PackingList $pl) => [
            'id' => $pl->id,
            'packing_number' => $pl->packing_number,
            'packing_date' => $pl->packing_date?->format('d/m/Y'),
            'box_count' => $pl->boxes->count(),
            'weight' => $pl->totalWeight(),
            'boxes' => $pl->boxes->values()->map(fn ($box, $i) => [
                'no' => $i + 1,
                'quotation_item_id' => $box->quotation_item_id,
                'description' => $box->description,
                'qty' => $box->qty,
                'unit' => $box->unit,
            ]),
        ])]);
    }

    /** Baris stok milik project di satu gudang (jalur Langsung). */
    public function stockOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'q' => 'nullable|string|max:100',
        ]);

        return response()->json(['success' => true, 'data' => StockPicker::options([
            'warehouse_id' => $validated['warehouse_id'],
            'project' => (int) $validated['project_id'],
            'q' => $validated['q'] ?? null,
            'limit' => 300,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateOrder($request);

        $order = DB::transaction(function () use ($validated) {
            $order = DeliveryOrder::create($this->headerAttributes($validated) + [
                'do_number' => DeliveryOrder::generateNumber($validated['delivery_date']),
                'status' => DeliveryOrder::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);
            $this->syncLines($order, $validated);

            return $order;
        });

        Log::record('create_delivery_order', 'Delivery Order '.$order->do_number.' dibuat oleh '.Auth::user()->display_name, 'MOD_DELIVERY_ORDER', $order);

        return response()->json(['success' => true, 'message' => 'Delivery Order '.$order->do_number.' disimpan sebagai draft.', 'id' => $order->id]);
    }

    public function show($id)
    {
        $order = DeliveryOrder::with([
            'items.stocks.stock.masterProduct', 'items.quotationItem', 'packingLists.boxes',
            'project.company', 'warehouse', 'creator', 'postedBy',
        ])->findOrFail($id);

        return view('delivery-order.show', compact('order'));
    }

    public function edit($id)
    {
        $order = DeliveryOrder::with(['items.stocks', 'packingLists'])->findOrFail($id);
        abort_unless($order->isDraft(), 403, 'Delivery Order yang sudah terkirim tidak bisa diubah.');

        return view('delivery-order.form', $this->formData($order, $order->project_id));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $order = DeliveryOrder::findOrFail($id);
        if (! $order->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Delivery Order yang sudah terkirim tidak bisa diubah.'], 422);
        }

        $validated = $this->validateOrder($request, $order->id);

        DB::transaction(function () use ($order, $validated) {
            $order->update($this->headerAttributes($validated));
            $order->items()->delete();
            $this->syncLines($order, $validated);
        });

        return response()->json(['success' => true, 'message' => 'Delivery Order '.$order->do_number.' diperbarui.', 'id' => $order->id]);
    }

    public function destroy($id): JsonResponse
    {
        $order = DeliveryOrder::findOrFail($id);
        if (! $order->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Delivery Order yang sudah terkirim tidak bisa dihapus.'], 422);
        }

        DB::transaction(function () use ($order) {
            PackingList::where('delivery_order_id', $order->id)->update(['delivery_order_id' => null]);
            $order->delete();
        });

        return response()->json(['success' => true, 'message' => 'Delivery Order dihapus. Packing List yang dimuat kembali Siap Kirim.']);
    }

    /**
     * Posting oleh gudang: stok keluar dari isi baris (Langsung) atau isi koli
     * PKL yang dimuat (PKL dijadikan Terkirim lebih dulu agar pesanannya lepas).
     */
    public function post($id): JsonResponse
    {
        $order = DeliveryOrder::with(['items.stocks', 'packingLists.items'])->findOrFail($id);
        if (! $order->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Delivery Order sudah terkirim.'], 422);
        }

        $service = app(StockService::class);
        $date = $order->delivery_date?->toDateString() ?? now()->toDateString();

        try {
            DB::transaction(function () use ($order, $service, $date) {
                if ($order->uses_packing_list) {
                    $lists = PackingList::whereIn('id', $order->packingLists->pluck('id'))->lockForUpdate()->get();
                    if ($lists->isEmpty()) {
                        throw new StockException('Delivery Order belum memuat Packing List.');
                    }
                    foreach ($lists as $pl) {
                        if (! $pl->isReady()) {
                            throw new StockException('Packing List '.$pl->packing_number.' tidak lagi Siap Kirim.');
                        }
                        $pl->update(['status' => PackingList::STATUS_SHIPPED]);
                    }
                    $picks = $order->packingLists->flatMap->items
                        ->map(fn ($item) => [$item->stock_id, $item->qty, StockLog::REF_PACKING_LIST_ITEM, $item->id]);
                } else {
                    $picks = $order->items->flatMap->stocks
                        ->map(fn ($row) => [$row->stock_id, $row->qty, StockLog::REF_DELIVERY_ORDER_ITEM_STOCK, $row->id]);
                }
                if ($picks->isEmpty()) {
                    throw new StockException('Delivery Order belum berisi barang dari stok.');
                }

                $stocks = $service->lock($picks->pluck(0));
                foreach ($picks as [$stockId, $qty, $refType, $refId]) {
                    $stock = $stocks->get($stockId);
                    if (! $stock) {
                        throw new StockException('Baris stok tidak ditemukan.');
                    }
                    $service->assertPosition($stock, (int) $order->warehouse_id, (int) $order->project_id);
                    $service->take($stock, (float) $qty, StockLog::TYPE_OUT, [
                        'date' => $date,
                        'ref_type' => $refType,
                        'ref_id' => $refId,
                        'note' => 'Delivery Order '.$order->do_number,
                    ]);
                }

                $order->update([
                    'status' => DeliveryOrder::STATUS_POSTED,
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                ]);
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('post_delivery_order', 'Delivery Order '.$order->do_number.' diposting (terkirim) oleh '.Auth::user()->display_name, 'MOD_DELIVERY_ORDER', $order);

        return response()->json(['success' => true, 'message' => 'Delivery Order '.$order->do_number.' diposting. Stok project berkurang.']);
    }

    public function pdf($id)
    {
        $order = DeliveryOrder::with(['items.stocks.stock.masterProduct', 'packingLists', 'project.company', 'warehouse', 'creator'])->findOrFail($id);

        return Pdf::loadView('delivery-order.pdf', compact('order'))
            ->setPaper('a4', 'portrait')
            ->stream('DeliveryOrder-'.$order->do_number.'.pdf');
    }

    private function formData(?DeliveryOrder $order, ?int $projectId): array
    {
        $projectIds = Stock::open()->whereNotNull('project_id')->distinct()->pluck('project_id')
            ->merge(PackingList::where('status', PackingList::STATUS_READY)->pluck('project_id'))->unique();

        return [
            'order' => $order,
            'preselectProjectId' => $projectId,
            'projects' => Project::where(fn ($q) => $q->where('status', Project::STATUS_ACTIVE)->whereIn('id', $projectIds))
                ->when($projectId, fn ($q) => $q->orWhere('id', $projectId))
                ->orderByDesc('id')
                ->get(['id', 'project_number', 'project_name']),
            'initialLines' => $order ? $order->items->map(fn ($item) => [
                'quotation_item_id' => $item->quotation_item_id,
                'description' => $item->description,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'reference' => $item->reference,
                'stocks' => $item->stocks->map(fn ($s) => ['stock_id' => $s->stock_id, 'qty' => $s->qty])->values(),
            ])->values() : collect(),
            'initialPackingLists' => $order ? $order->packingLists->pluck('id')->values() : collect(),
        ];
    }

    private function validateOrder(Request $request, ?int $orderId = null): array
    {
        $usesPacking = $request->boolean('uses_packing_list');
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'uses_packing_list' => 'required|boolean',
            'delivery_date' => 'required|date',
            'recipient_name' => 'required|string|max:150',
            'recipient_phone' => 'nullable|string|max:50',
            'recipient_attn' => 'nullable|string|max:150',
            'shipping_address' => 'required|string|max:2000',
            'customer_ref' => 'nullable|string|max:100',
            'customer_ref_date' => 'nullable|date',
            'courier' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:2000',
            'checked_by_name' => 'nullable|string|max:150',
            'acknowledged_by_name' => 'nullable|string|max:255',
            'sent_by_name' => 'nullable|string|max:150',
            'packing_list_ids' => $usesPacking ? 'required|array|min:1' : 'prohibited',
            'packing_list_ids.*' => 'integer|distinct',
            'lines' => 'required|array|min:1',
            'lines.*.quotation_item_id' => 'nullable|integer|exists:quotation_items,id',
            'lines.*.description' => 'required|string|max:5000',
            'lines.*.qty' => 'required|numeric|gt:0',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.reference' => 'nullable|string|max:255',
            'lines.*.remark' => 'nullable|string|max:255',
            'lines.*.stocks' => $usesPacking ? 'prohibited' : 'required|array|min:1',
            'lines.*.stocks.*.stock_id' => 'required|integer',
            'lines.*.stocks.*.qty' => 'required|numeric|gt:0',
        ], [
            'packing_list_ids.required' => 'Pilih minimal satu Packing List Siap Kirim.',
            'packing_list_ids.prohibited' => 'DO Langsung tidak memuat Packing List.',
            'lines.required' => 'Tambahkan minimal satu baris barang.',
            'lines.*.description.required' => 'Deskripsi setiap baris wajib diisi.',
            'lines.*.stocks.required' => 'Setiap baris DO Langsung wajib berisi minimal satu barang dari stok.',
            'lines.*.stocks.min' => 'Setiap baris DO Langsung wajib berisi minimal satu barang dari stok.',
            'lines.*.stocks.prohibited' => 'DO dengan Packing List tidak berisi barang langsung — isi barang ada di Packing List.',
        ]);

        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));

        if ($usesPacking) {
            $lists = PackingList::whereIn('id', $validated['packing_list_ids'])->get();
            if ($lists->count() !== count($validated['packing_list_ids'])) {
                $fail('Packing List tidak ditemukan.');
            }
            foreach ($lists as $pl) {
                if (! $pl->isReady()) {
                    $fail('Packing List '.$pl->packing_number.' belum Siap Kirim.');
                }
                if ((int) $pl->project_id !== (int) $validated['project_id'] || (int) $pl->warehouse_id !== (int) $validated['warehouse_id']) {
                    $fail('Packing List '.$pl->packing_number.' bukan untuk project dan gudang DO ini.');
                }
                if ($pl->delivery_order_id && (int) $pl->delivery_order_id !== (int) $orderId) {
                    $fail('Packing List '.$pl->packing_number.' sudah dimuat Delivery Order lain.');
                }
            }
            $validated['units'] = [];
        } else {
            try {
                $validated['units'] = ShipmentLines::assertStocks($validated['lines'], (int) $validated['project_id'], (int) $validated['warehouse_id']);
            } catch (StockException $e) {
                $fail($e->getMessage());
            }
        }

        return $validated;
    }

    private function headerAttributes(array $validated): array
    {
        return collect($validated)->only([
            'project_id', 'warehouse_id', 'uses_packing_list', 'delivery_date', 'recipient_name', 'recipient_phone', 'recipient_attn',
            'shipping_address', 'customer_ref', 'customer_ref_date', 'courier', 'note', 'checked_by_name', 'acknowledged_by_name', 'sent_by_name',
        ])->all();
    }

    /** Baris dokumen (+ isi stok jalur Langsung) dan PKL yang dimuat. */
    private function syncLines(DeliveryOrder $order, array $validated): void
    {
        foreach (array_values($validated['lines']) as $i => $row) {
            $item = $order->items()->create([
                'quotation_item_id' => $row['quotation_item_id'] ?? null,
                'description' => trim($row['description']),
                'qty' => StockService::round((float) $row['qty']),
                'unit' => $row['unit'] ?? null,
                'reference' => $validated['uses_packing_list'] ? ($row['reference'] ?? null) : null,
                'remark' => $row['remark'] ?? null,
                'sort_order' => $i + 1,
            ]);
            foreach ($row['stocks'] ?? [] as $pick) {
                $item->stocks()->create([
                    'stock_id' => (int) $pick['stock_id'],
                    'qty' => StockService::round((float) $pick['qty']),
                    'unit' => $validated['units'][(int) $pick['stock_id']] ?? null,
                ]);
            }
        }

        PackingList::where('delivery_order_id', $order->id)->update(['delivery_order_id' => null]);
        if ($validated['uses_packing_list']) {
            PackingList::whereIn('id', $validated['packing_list_ids'])->update(['delivery_order_id' => $order->id]);
        }
    }
}

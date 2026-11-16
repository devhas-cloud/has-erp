<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\Log;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use App\Support\StockPicker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Delivery Order (Surat Jalan) — kirim stok milik project dari satu gudang ke
 * customer. Item = baris stok yang dipilih user (material boleh sebagian; unit
 * ber-SN wajib sudah ber-SN). Draft boleh diubah/dihapus; Posting oleh gudang
 * (tanpa approval) mengunci & mengecek ulang baris stok lalu mencatat log 'out'.
 * Stok bebas harus dialokasikan dulu (Pemenuhan dari Stok / Transfer).
 */
class DeliveryOrderController extends Controller
{
    public function index()
    {
        return view('delivery-order.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = DeliveryOrder::withCount('items')->with(['project', 'warehouse']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('do_number', 'like', "%{$searchValue}%")
                    ->orWhere('recipient_name', 'like', "%{$searchValue}%")
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
     * Stok milik project per gudang (saldo > 0) + data penerima default.
     */
    public function projectStock(Request $request): JsonResponse
    {
        $validated = $request->validate(['project_id' => 'required|integer|exists:projects,id']);
        $project = Project::with('company')->findOrFail($validated['project_id']);

        return response()->json([
            'success' => true,
            'warehouses' => $this->projectWarehouses($project->id),
            'recipient' => [
                'name' => $project->company?->account_name,
                'phone' => $project->company?->phone,
                'address' => DeliveryOrder::defaultAddress($project->company),
            ],
        ]);
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
            $this->syncItems($order, $validated['items'], $validated['units']);

            return $order;
        });

        Log::record('create_delivery_order', 'Delivery Order '.$order->do_number.' dibuat oleh '.Auth::user()->display_name, 'MOD_DELIVERY_ORDER', $order);

        return response()->json(['success' => true, 'message' => 'Delivery Order '.$order->do_number.' disimpan sebagai draft.', 'id' => $order->id]);
    }

    public function show($id)
    {
        $order = DeliveryOrder::with(['items.stock.masterProduct', 'project.company', 'warehouse', 'creator', 'postedBy'])->findOrFail($id);

        return view('delivery-order.show', compact('order'));
    }

    public function edit($id)
    {
        $order = DeliveryOrder::with('items')->findOrFail($id);
        abort_unless($order->isDraft(), 403, 'Delivery Order yang sudah terkirim tidak bisa diubah.');

        return view('delivery-order.form', $this->formData($order, $order->project_id));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $order = DeliveryOrder::findOrFail($id);
        if (! $order->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Delivery Order yang sudah terkirim tidak bisa diubah.'], 422);
        }

        $validated = $this->validateOrder($request);

        DB::transaction(function () use ($order, $validated) {
            $order->update($this->headerAttributes($validated));
            $order->items()->delete();
            $this->syncItems($order, $validated['items'], $validated['units']);
        });

        return response()->json(['success' => true, 'message' => 'Delivery Order '.$order->do_number.' diperbarui.', 'id' => $order->id]);
    }

    public function destroy($id): JsonResponse
    {
        $order = DeliveryOrder::findOrFail($id);
        if (! $order->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Delivery Order yang sudah terkirim tidak bisa dihapus.'], 422);
        }

        $order->delete();

        return response()->json(['success' => true, 'message' => 'Delivery Order dihapus.']);
    }

    /**
     * Posting oleh gudang: cek ulang saldo stok project, lalu catat stok keluar.
     */
    public function post($id): JsonResponse
    {
        $order = DeliveryOrder::with('items')->findOrFail($id);
        if (! $order->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Delivery Order sudah terkirim.'], 422);
        }

        $service = app(StockService::class);
        $date = $order->delivery_date?->toDateString() ?? now()->toDateString();

        try {
            DB::transaction(function () use ($order, $service, $date) {
                $stocks = $service->lock($order->items->pluck('stock_id'));
                foreach ($order->items as $item) {
                    $stock = $stocks->get($item->stock_id);
                    if (! $stock) {
                        throw new StockException('Baris stok item '.$item->sort_order.' tidak ditemukan.');
                    }
                    $service->assertPosition($stock, (int) $order->warehouse_id, (int) $order->project_id);
                    $service->take($stock, (float) $item->qty, StockLog::TYPE_OUT, [
                        'date' => $date,
                        'ref_type' => StockLog::REF_DELIVERY_ORDER_ITEM,
                        'ref_id' => $item->id,
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
        $order = DeliveryOrder::with(['items.stock.masterProduct', 'project.company', 'warehouse', 'creator'])->findOrFail($id);

        return Pdf::loadView('delivery-order.pdf', compact('order'))
            ->setPaper('a4', 'portrait')
            ->stream('SuratJalan-'.$order->do_number.'.pdf');
    }

    private function formData(?DeliveryOrder $order, ?int $projectId): array
    {
        $projectIds = Stock::open()->whereNotNull('project_id')->distinct()->pluck('project_id');

        return [
            'order' => $order,
            'preselectProjectId' => $projectId,
            'projects' => Project::where(fn ($q) => $q->where('status', Project::STATUS_ACTIVE)->whereIn('id', $projectIds))
                ->when($projectId, fn ($q) => $q->orWhere('id', $projectId))
                ->orderByDesc('id')
                ->get(['id', 'project_number', 'project_name']),
            'warehouses' => Warehouse::with('branch')->orderBy('name')->get(),
            // Pilihan awal pemilih stok saat edit: {stock_id: qty}.
            'selected' => $order?->items->mapWithKeys(fn ($item) => [$item->stock_id => $item->qty]) ?? collect(),
        ];
    }

    private function validateOrder(Request $request): array
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'delivery_date' => 'required|date',
            'recipient_name' => 'required|string|max:150',
            'recipient_phone' => 'nullable|string|max:50',
            'shipping_address' => 'required|string|max:2000',
            'courier' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.stock_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.remark' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Pilih minimal satu baris stok yang dikirim.',
            'items.*.stock_id.distinct' => 'Baris stok yang sama tidak boleh dipilih dua kali.',
        ]);

        // Cek awal (posting mengecek ulang dengan mengunci baris).
        $service = app(StockService::class);
        $stocks = Stock::with('masterProduct')->whereIn('id', collect($validated['items'])->pluck('stock_id'))->get()->keyBy('id');
        try {
            foreach (array_values($validated['items']) as $i => $row) {
                $stock = $stocks->get((int) $row['stock_id']);
                if (! $stock || ! $stock->is_open) {
                    throw new StockException('Baris '.($i + 1).': stok sudah tidak tersedia.');
                }
                $service->assertPosition($stock, (int) $validated['warehouse_id'], (int) $validated['project_id']);
                if ((float) $row['qty'] > $stock->qty + Stock::EPSILON) {
                    throw new StockException('Qty '.$service->label($stock).' melebihi sisa ('.StockCost::qty($stock->qty).').');
                }
                if (! $stock->canLeave()) {
                    throw new StockException('Unit '.$service->label($stock).' belum punya SN. Lengkapi SN dulu sebelum dikirim.');
                }
                if ($stock->isSerialized() && abs((float) $row['qty'] - $stock->qty) >= Stock::EPSILON) {
                    throw new StockException($service->label($stock).' adalah barang ber-SN dan harus dikirim utuh.');
                }
            }
        } catch (StockException $e) {
            abort(response()->json(['success' => false, 'message' => $e->getMessage()], 422));
        }

        $validated['units'] = $stocks->map(fn (Stock $stock) => $stock->unit)->all();

        return $validated;
    }

    private function headerAttributes(array $validated): array
    {
        return collect($validated)->only([
            'project_id', 'warehouse_id', 'delivery_date', 'recipient_name', 'recipient_phone', 'shipping_address', 'courier', 'note',
        ])->all();
    }

    /**
     * @param  array<int, ?string>  $units  satuan per stock_id
     */
    private function syncItems(DeliveryOrder $order, array $items, array $units): void
    {
        foreach (array_values($items) as $i => $row) {
            $order->items()->create([
                'stock_id' => $row['stock_id'],
                'qty' => StockService::round((float) $row['qty']),
                'unit' => $units[(int) $row['stock_id']] ?? null,
                'remark' => $row['remark'] ?? null,
                'sort_order' => $i + 1,
            ]);
        }
    }

    /**
     * Baris stok milik project di satu gudang (untuk pemilih stok form DO).
     */
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

    /**
     * Gudang yang menyimpan stok milik project (untuk pilihan gudang asal).
     */
    private function projectWarehouses(int $projectId): Collection
    {
        $ids = Stock::open()->where('project_id', $projectId)->distinct()->pluck('warehouse_id');

        return Warehouse::whereIn('id', $ids)->orderBy('name')->get()
            ->map(fn (Warehouse $w) => ['id' => $w->id, 'label' => $w->code.' — '.$w->name])->values();
    }
}

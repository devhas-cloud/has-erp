<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Delivery Order (Surat Jalan) — kirim stok milik project dari satu gudang
 * (umumnya transit) ke customer. Draft boleh diubah/dihapus; Posting oleh
 * gudang (tanpa approval) mengecek ulang saldo lalu mencatat stok keluar
 * 'delivery'. Stok bebas harus dialokasikan dulu (Pemenuhan dari Stok /
 * Transfer) agar tercatat sebagai barang project.
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
            'items' => $this->projectBalances($project->id)->values(),
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
        $order = DeliveryOrder::with(['items.masterProduct', 'project.company', 'warehouse', 'creator', 'postedBy'])->findOrFail($id);

        $balances = $order->isDraft()
            ? $this->projectBalances($order->project_id, $order->warehouse_id)->keyBy('master_product_id')
            : collect();

        return view('delivery-order.show', compact('order', 'balances'));
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
        $order = DeliveryOrder::with(['items.masterProduct'])->findOrFail($id);
        if (! $order->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Delivery Order sudah terkirim.'], 422);
        }

        $error = DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $balance = StockMovement::balance($order->warehouse_id, $item->master_product_id, $order->project_id);
                if ($item->qty > $balance) {
                    return 'Stok project '.($item->masterProduct?->code ?? 'item').' di gudang tidak cukup (tersedia '.$balance.', dikirim '.$item->qty.').';
                }
            }

            foreach ($order->items as $item) {
                StockMovement::record([
                    'warehouse_id' => $order->warehouse_id,
                    'master_product_id' => $item->master_product_id,
                    'project_id' => $order->project_id,
                    'type' => StockMovement::TYPE_DELIVERY,
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'source_type' => StockMovement::SOURCE_DELIVERY_ORDER,
                    'source_id' => $order->id,
                    'source_line_id' => $item->id,
                    'moved_at' => $order->delivery_date?->toDateString() ?? now()->toDateString(),
                    'note' => 'Delivery Order '.$order->do_number,
                ]);
            }

            $order->update([
                'status' => DeliveryOrder::STATUS_POSTED,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ]);

            return null;
        });

        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        Log::record('post_delivery_order', 'Delivery Order '.$order->do_number.' diposting (terkirim) oleh '.Auth::user()->display_name, 'MOD_DELIVERY_ORDER', $order);

        return response()->json(['success' => true, 'message' => 'Delivery Order '.$order->do_number.' diposting. Stok project berkurang.']);
    }

    public function pdf($id)
    {
        $order = DeliveryOrder::with(['items.masterProduct', 'project.company', 'warehouse', 'creator'])->findOrFail($id);

        return Pdf::loadView('delivery-order.pdf', compact('order'))
            ->setPaper('a4', 'portrait')
            ->stream('SuratJalan-'.$order->do_number.'.pdf');
    }

    private function formData(?DeliveryOrder $order, ?int $projectId): array
    {
        $projectIds = StockMovement::whereNotNull('project_id')->distinct()->pluck('project_id');

        return [
            'order' => $order,
            'preselectProjectId' => $projectId,
            'projects' => Project::where(fn ($q) => $q->where('status', Project::STATUS_ACTIVE)->whereIn('id', $projectIds))
                ->when($projectId, fn ($q) => $q->orWhere('id', $projectId))
                ->orderByDesc('id')
                ->get(['id', 'project_number', 'project_name']),
            'warehouses' => Warehouse::orderBy('type', 'desc')->orderBy('name')->get(),
            'items' => $order?->items->map(fn ($item) => ['master_product_id' => $item->master_product_id, 'qty' => $item->qty, 'remark' => $item->remark])->values() ?? [],
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
            'items.*.master_product_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.remark' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Isi qty minimal satu barang yang dikirim.',
            'items.*.master_product_id.distinct' => 'Produk yang sama tidak boleh muncul dua kali.',
        ]);

        $available = $this->projectBalances((int) $validated['project_id'], (int) $validated['warehouse_id'])->keyBy('master_product_id');
        foreach (array_values($validated['items']) as $i => $row) {
            $stock = $available->get((int) $row['master_product_id']);
            if (! $stock) {
                abort(response()->json(['success' => false, 'message' => 'Baris '.($i + 1).': produk tidak ada di stok project pada gudang ini.'], 422));
            }
            if ((int) $row['qty'] > $stock['balance']) {
                abort(response()->json(['success' => false, 'message' => 'Qty '.$stock['code'].' melebihi stok project di gudang (tersedia '.$stock['balance'].').'], 422));
            }
        }

        $validated['units'] = $available->map(fn ($stock) => $stock['unit'])->all();

        return $validated;
    }

    private function headerAttributes(array $validated): array
    {
        return collect($validated)->only([
            'project_id', 'warehouse_id', 'delivery_date', 'recipient_name', 'recipient_phone', 'shipping_address', 'courier', 'note',
        ])->all();
    }

    /**
     * @param  array<int, ?string>  $units  satuan per master_product_id dari kartu stok
     */
    private function syncItems(DeliveryOrder $order, array $items, array $units): void
    {
        foreach (array_values($items) as $i => $row) {
            $order->items()->create([
                'master_product_id' => $row['master_product_id'],
                'qty' => (int) $row['qty'],
                'unit' => $units[(int) $row['master_product_id']] ?? null,
                'remark' => $row['remark'] ?? null,
                'sort_order' => $i + 1,
            ]);
        }
    }

    /**
     * Saldo stok milik project (> 0) per gudang & produk.
     */
    private function projectBalances(int $projectId, ?int $warehouseId = null): Collection
    {
        $rows = StockMovement::balancesQuery()
            ->where('project_id', $projectId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->havingRaw('balance > 0')
            ->get();

        $products = MasterProduct::whereIn('id', $rows->pluck('master_product_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $warehouses = Warehouse::whereIn('id', $rows->pluck('warehouse_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'warehouse_id' => (int) $row->warehouse_id,
            'warehouse' => ($warehouses[$row->warehouse_id]->code ?? '').' — '.($warehouses[$row->warehouse_id]->name ?? ''),
            'master_product_id' => (int) $row->master_product_id,
            'code' => $products[$row->master_product_id]->code ?? '—',
            'name' => $products[$row->master_product_id]->name ?? '—',
            'balance' => (int) $row->balance,
            'unit' => $row->unit,
        ])->sortBy('code')->values();
    }
}

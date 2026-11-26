<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Stok — saldo per (produk, gudang, kepemilikan) langsung dari baris
 * stok terbuka (stocks), nilai = SUM(qty x harga modal baris). Material = satu
 * baris saldo (harga rata-rata tertimbang); barang ber-SN = jumlah unit. Kartu Stok per
 * produk dari stock_logs: saldo awal periode dihitung mundur dari saldo
 * sekarang (saldo sekarang - log sejak tanggal awal), tanpa membaca seluruh riwayat.
 * Filter kepemilikan: '' = semua, 'free' = stok bebas, angka = project id.
 */
class StockReportController extends Controller
{
    public function index()
    {
        return view('stock-report.index', array_merge($this->filterOptions(), [
            'branches' => Branch::orderBy('name')->get(['id', 'code', 'name']),
            'inTransit' => $this->inTransit(),
        ]));
    }

    /**
     * Branch Transfer yang sedang dalam pengiriman (stok sudah keluar dari gudang asal).
     */
    private function inTransit()
    {
        $transfers = BranchTransfer::visibleTo(Auth::user())
            ->where('status', BranchTransfer::STATUS_IN_TRANSIT)
            ->with(['fromBranch', 'toBranch', 'items.stock'])
            ->orderBy('shipped_at')
            ->get();

        // Nilai = harga saat dikirim (log ship_out), karena harga rata-rata saldo material bisa berubah.
        $shipCosts = StockLog::where('ref_type', StockLog::REF_BRANCH_TRANSFER_ITEM)
            ->where('type', StockLog::TYPE_SHIP_OUT)
            ->whereIn('ref_id', $transfers->flatMap->items->pluck('id')->all() ?: [0])
            ->pluck('unit_cost', 'ref_id');

        return $transfers->map(fn (BranchTransfer $t) => [
                'id' => $t->id,
                'number' => $t->transfer_number,
                'from' => $t->fromBranch?->name,
                'to' => $t->toBranch?->name,
                'shipped_at' => $t->shipped_at?->format('d/m/Y'),
                'items' => $t->items->count(),
                'value' => round($t->items->sum(fn ($item) => $item->qty * (float) ($shipCosts[$item->id] ?? $item->stock?->unit_cost ?? 0)), 2),
            ]);
    }

    public function data(Request $request): JsonResponse
    {
        $balances = $this->applyFilters(DB::table('stocks as s'), $request, 's.')
            ->join('master_products as mp', 'mp.id', '=', 's.master_product_id')
            ->when(! $request->boolean('include_zero'), fn ($q) => $q->where('s.is_open', true))
            ->groupBy('s.master_product_id', 's.warehouse_id', 's.project_id')
            ->selectRaw('s.master_product_id, s.warehouse_id, s.project_id, SUM(s.qty) AS balance, SUM(s.qty * s.unit_cost) AS value, MAX(s.unit) AS unit,
                SUM(CASE WHEN s.qty > 0 THEN 1 ELSE 0 END) AS open_rows, MAX(s.updated_at) AS last_change,
                SUM(CASE WHEN s.qty > 0 AND s.serial_number IS NULL AND mp.is_material = 0 THEN 1 ELSE 0 END) AS missing_serial');

        $query = DB::query()->fromSub($balances, 'b')
            ->join('master_products as p', 'p.id', '=', 'b.master_product_id')
            ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->leftJoin('projects as pr', 'pr.id', '=', 'b.project_id')
            ->leftJoin('branches as br', 'br.id', '=', 'w.branch_id')
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('w.branch_id', $id))
            ->when($request->input('kind') === 'material', fn ($q) => $q->where('p.is_material', true))
            ->when($request->input('kind') === 'serial', fn ($q) => $q->where('p.is_material', false))
            ->when($request->boolean('missing_sn'), fn ($q) => $q->where('b.missing_serial', '>', 0))
            ->select('b.*', 'p.code', 'p.name as product_name', 'p.is_material', 'w.code as warehouse_code', 'w.name as warehouse_name', 'br.name as branch_name', 'pr.project_number', 'pr.project_name');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('p.code', 'like', "%{$searchValue}%")
                    ->orWhere('p.name', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $query)->count();
        $totalValue = (float) (clone $query)->sum('b.value');
        // select() mengganti kolom baris (b.*, p.code, ...) — MySQL menolak campuran kolom & agregat tanpa GROUP BY.
        $summary = (clone $query)->reorder()->select(DB::raw('
                COALESCE(SUM(CASE WHEN p.is_material = 1 THEN b.value ELSE 0 END), 0) AS material_value,
                COALESCE(SUM(CASE WHEN p.is_material = 1 THEN 1 ELSE 0 END), 0) AS material_positions,
                COALESCE(SUM(CASE WHEN p.is_material = 0 THEN b.value ELSE 0 END), 0) AS serial_value,
                COALESCE(SUM(CASE WHEN p.is_material = 0 THEN b.open_rows ELSE 0 END), 0) AS serial_units,
                COALESCE(SUM(b.missing_serial), 0) AS missing_serial,
                COUNT(DISTINCT b.master_product_id) AS products'))->first();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $rows = $query->orderBy('p.code')->orderBy('w.name')->orderBy('pr.project_number')->offset($start)->limit($length)->get();

        $data = $rows->map(fn ($row, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'master_product_id' => (int) $row->master_product_id,
            'code' => $row->code,
            'product_name' => $row->product_name,
            'serialized' => ! $row->is_material,
            'warehouse_id' => (int) $row->warehouse_id,
            'warehouse' => $row->warehouse_code.' — '.$row->warehouse_name,
            'branch' => $row->branch_name,
            'project_id' => $row->project_id ? (int) $row->project_id : null,
            'ownership' => $row->project_id ? $row->project_number.' — '.$row->project_name : 'Stok bebas',
            'balance' => round((float) $row->balance, 4),
            'units' => (int) $row->open_rows,
            'last_change' => $row->last_change ? Carbon::parse($row->last_change)->format('d/m/Y') : null,
            'unit' => $row->unit,
            'average_cost' => (float) $row->balance > 0 ? round($row->value / $row->balance, 2) : 0,
            'value' => round((float) $row->value, 2),
            'missing_serial' => (int) $row->missing_serial,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'total_value' => round($totalValue, 2),
            'summary' => [
                'products' => (int) $summary->products,
                'material_value' => round((float) $summary->material_value, 2),
                'material_positions' => (int) $summary->material_positions,
                'serial_value' => round((float) $summary->serial_value, 2),
                'serial_units' => (int) $summary->serial_units,
                'missing_serial' => (int) $summary->missing_serial,
            ],
            'data' => $data,
        ]);
    }

    /**
     * Kartu Stok satu produk pada rentang tanggal — filter gudang & kepemilikan opsional.
     */
    public function card(Request $request, $productId)
    {
        $product = MasterProduct::findOrFail($productId);
        $from = $this->date($request->input('from')) ?? now()->subMonthsNoOverflow(3)->startOfMonth();
        $to = $this->date($request->input('to')) ?? now();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // Saldo sekarang (posisi) lalu dihitung mundur ke awal periode.
        $current = $this->applyFilters(Stock::query(), $request)->where('master_product_id', $product->id)
            ->selectRaw('COALESCE(SUM(qty), 0) AS total_qty, COALESCE(SUM(qty * unit_cost), 0) AS total_value')->first();
        $logBase = $this->applyFilters(StockLog::query(), $request)->where('master_product_id', $product->id)->where('qty', '!=', 0);
        $sinceFrom = (clone $logBase)->whereDate('log_date', '>=', $from->toDateString())
            ->selectRaw('COALESCE(SUM(qty), 0) AS total_qty, COALESCE(SUM(qty * unit_cost), 0) AS total_value')->first();

        $openingQty = round((float) $current->total_qty - (float) $sinceFrom->total_qty, 4);
        $openingValue = round((float) $current->total_value - (float) $sinceFrom->total_value, 2);

        $logs = (clone $logBase)
            ->with(['warehouse:id,code', 'project:id,project_number', 'creator', 'stock:id,serial_number,batch_number,master_product_id'])
            ->whereDate('log_date', '>=', $from->toDateString())
            ->whereDate('log_date', '<=', $to->toDateString())
            ->orderBy('log_date')->orderBy('id')
            ->get();
        StockLog::attachDocuments($logs);

        $running = $openingQty;
        $runningValue = $openingValue;
        $rows = $logs->map(function (StockLog $log) use (&$running, &$runningValue) {
            $running = round($running + $log->qty, 4);
            $runningValue = round($runningValue + $log->qty * $log->unit_cost, 2);

            return [
                'log' => $log,
                'in' => $log->qty > 0 ? $log->qty : null,
                'out' => $log->qty < 0 ? -$log->qty : null,
                'balance' => $running,
                'value' => $runningValue,
            ];
        });

        return view('stock-report.card', array_merge($this->filterOptions(), [
            'product' => $product,
            'openingQty' => $openingQty,
            'openingValue' => $openingValue,
            'rows' => $rows,
            'filters' => [
                'warehouse_id' => $request->input('warehouse_id'),
                'ownership' => $request->input('ownership'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]));
    }

    private function applyFilters($query, Request $request, string $prefix = '')
    {
        $warehouseId = $request->integer('warehouse_id');
        $ownership = (string) $request->input('ownership', '');

        return $query
            ->when($warehouseId, fn ($q) => $q->where($prefix.'warehouse_id', $warehouseId))
            ->when($ownership === 'free', fn ($q) => $q->whereNull($prefix.'project_id'))
            ->when(ctype_digit($ownership), fn ($q) => $q->where($prefix.'project_id', (int) $ownership));
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? rescue(fn () => Carbon::parse($value)->startOfDay(), null, false) : null;
    }

    private function filterOptions(): array
    {
        $projectIds = Stock::whereNotNull('project_id')->distinct()->pluck('project_id');

        return [
            'warehouses' => Warehouse::with('branch')->orderBy('name')->get(),
            'projects' => Project::whereIn('id', $projectIds)->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockValuation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Stok — saldo per (produk, gudang, kepemilikan) dari kartu stok, dan
 * Kartu Stok (riwayat pergerakan + saldo berjalan) per produk.
 * Filter kepemilikan: '' = semua, 'free' = stok bebas, angka = project id.
 */
class StockReportController extends Controller
{
    public function index()
    {
        return view('stock-report.index', $this->filterOptions());
    }

    public function data(Request $request): JsonResponse
    {
        $balances = $this->applyFilters(StockMovement::balancesQuery(), $request);
        if (! $request->boolean('include_zero')) {
            $balances->havingRaw('balance <> 0');
        }

        $query = DB::query()->fromSub($balances, 'b')
            ->join('master_products as p', 'p.id', '=', 'b.master_product_id')
            ->join('warehouses as w', 'w.id', '=', 'b.warehouse_id')
            ->leftJoin('projects as pr', 'pr.id', '=', 'b.project_id')
            ->select('b.*', 'p.code', 'p.name as product_name', 'w.code as warehouse_code', 'w.name as warehouse_name', 'w.type as warehouse_type', 'pr.project_number', 'pr.project_name');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('p.code', 'like', "%{$searchValue}%")
                    ->orWhere('p.name', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        // Nilai persediaan = saldo x harga rata-rata produk (lintas gudang/kepemilikan).
        $filtered = (clone $query)->get(['b.master_product_id', 'b.balance']);
        $averages = $this->averageCosts($filtered->pluck('master_product_id')->unique()->all());
        $totalValue = $filtered->sum(fn ($row) => (int) $row->balance * ($averages[$row->master_product_id] ?? 0));

        $rows = $query->orderBy('p.code')->orderBy('w.name')->orderBy('pr.project_number')->offset($start)->limit($length)->get();

        $data = $rows->map(fn ($row, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'master_product_id' => (int) $row->master_product_id,
            'code' => $row->code,
            'product_name' => $row->product_name,
            'warehouse_id' => (int) $row->warehouse_id,
            'warehouse' => $row->warehouse_code.' — '.$row->warehouse_name,
            'warehouse_type' => $row->warehouse_type,
            'project_id' => $row->project_id ? (int) $row->project_id : null,
            'ownership' => $row->project_id ? $row->project_number.' — '.$row->project_name : 'Stok bebas',
            'balance' => (int) $row->balance,
            'unit' => $row->unit,
            'average_cost' => round($averages[$row->master_product_id] ?? 0, 2),
            'value' => round((int) $row->balance * ($averages[$row->master_product_id] ?? 0), 2),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'total_value' => round($totalValue, 2),
            'data' => $data,
        ]);
    }

    /**
     * Kartu Stok satu produk — filter gudang & kepemilikan opsional.
     */
    public function card(Request $request, $productId)
    {
        $product = MasterProduct::findOrFail($productId);

        $movements = $this->applyFilters(StockMovement::query(), $request)
            ->with(['warehouse', 'project', 'creator'])
            ->where('master_product_id', $product->id)
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        $running = 0;
        $runningValue = 0.0;
        $rows = $movements->map(function (StockMovement $movement) use (&$running, &$runningValue) {
            $signed = $movement->isIn() ? $movement->qty : -$movement->qty;
            $running += $signed;
            $runningValue += $signed * (float) $movement->unit_cost;
            [$sourceLabel, $sourceUrl] = $movement->sourceReference();

            return [
                'movement' => $movement,
                'in' => $signed > 0 ? $movement->qty : null,
                'out' => $signed < 0 ? $movement->qty : null,
                'balance' => $running,
                'value' => round($runningValue, 2),
                'source_label' => $sourceLabel,
                'source_url' => $sourceUrl,
            ];
        });

        return view('stock-report.card', array_merge($this->filterOptions(), [
            'product' => $product,
            'averageCost' => StockValuation::averageCost($product->id),
            'rows' => $rows,
            'filters' => [
                'warehouse_id' => $request->input('warehouse_id'),
                'ownership' => $request->input('ownership'),
            ],
        ]));
    }

    /**
     * Harga rata-rata per produk: [master_product_id => cost].
     */
    private function averageCosts(array $productIds): array
    {
        if (! $productIds) {
            return [];
        }

        return StockMovement::valuationQuery()
            ->whereIn('master_product_id', $productIds)
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->master_product_id => $row->qty > 0
                    ? $row->value / $row->qty
                    : StockValuation::averageCost((int) $row->master_product_id),
            ])
            ->all();
    }

    private function applyFilters($query, Request $request)
    {
        $warehouseId = $request->integer('warehouse_id');
        $ownership = (string) $request->input('ownership', '');

        return $query
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($ownership === 'free', fn ($q) => $q->whereNull('project_id'))
            ->when(ctype_digit($ownership), fn ($q) => $q->where('project_id', (int) $ownership));
    }

    private function filterOptions(): array
    {
        $projectIds = StockMovement::whereNotNull('project_id')->distinct()->pluck('project_id');

        return [
            'warehouses' => Warehouse::orderBy('type')->orderBy('name')->get(),
            'projects' => Project::whereIn('id', $projectIds)->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
        ];
    }
}

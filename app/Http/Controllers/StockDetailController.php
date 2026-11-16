<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\Log;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lengkapi Data Stok: SN, batch / No. MFG, kedaluwarsa, garansi supplier, dan
 * catatan barang yang sudah masuk stok (boleh menyusul saat penerimaan).
 * Unit barang ber-SN tanpa SN belum bisa dikirim/diretur/dipakai sampai
 * SN-nya diisi di sini. Juga menampilkan riwayat per baris stok / SN.
 */
class StockDetailController extends Controller
{
    public const FILTER_MISSING_SERIAL = 'missing_serial';

    public const FILTER_INCOMPLETE = 'incomplete';

    public const FILTER_ALL = 'all';

    public function index(Request $request)
    {
        return view('stock-detail.index', [
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'warehouse_id' => $request->integer('warehouse_id') ?: null,
                'product_id' => $request->integer('product_id') ?: null,
                'goods_receipt_id' => $request->integer('goods_receipt_id') ?: null,
                'show' => in_array($request->input('show'), [self::FILTER_INCOMPLETE, self::FILTER_ALL], true) ? $request->input('show') : self::FILTER_MISSING_SERIAL,
            ],
            'receipt' => $request->integer('goods_receipt_id') ? GoodsReceipt::find($request->integer('goods_receipt_id')) : null,
            'missingTotal' => Stock::missingSerial()->count(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->filtered($request);
        $recordsTotal = (clone $query)->count();

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function (Builder $w) use ($search) {
                $w->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('masterProduct', fn (Builder $p) => $p->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = min(200, max(1, (int) $request->input('length', 50)));
        $rows = $query->with(['masterProduct:id,code,name,is_material', 'warehouse:id,code,name', 'project:id,project_number'])
            ->orderBy('master_product_id')->orderBy('id')
            ->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn (Stock $s) => [
                'id' => $s->id,
                'product_code' => $s->masterProduct?->code,
                'product_name' => $s->masterProduct?->name,
                'serialized' => $s->isSerialized(),
                'warehouse' => $s->warehouse?->code,
                'project' => $s->project?->project_number,
                'qty' => StockCost::qty($s->qty).' '.($s->unit ?? ''),
                'serial_number' => $s->serial_number,
                'batch_number' => $s->batch_number,
                'expiry_date' => $s->expiry_date?->toDateString(),
                'warranty_until' => $s->warranty_until?->toDateString(),
                'note' => $s->note,
                'received_date' => $s->received_date?->format('d/m/Y'),
            ]),
        ]);
    }

    /**
     * Simpan banyak baris sekaligus. Baris yang tidak berubah dilewati.
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:500',
            'rows.*.id' => 'required|integer|distinct',
            'rows.*.serial_number' => 'nullable|string|max:100',
            'rows.*.batch_number' => 'nullable|string|max:100',
            'rows.*.expiry_date' => 'nullable|date',
            'rows.*.warranty_until' => 'nullable|date',
            'rows.*.note' => 'nullable|string|max:255',
        ]);

        $service = app(StockService::class);
        $rows = collect($validated['rows'])->keyBy('id');

        try {
            $changed = DB::transaction(function () use ($service, $rows) {
                $changed = 0;
                $stocks = $service->lock($rows->keys());
                foreach ($rows as $id => $row) {
                    $stock = $stocks->get((int) $id);
                    if (! $stock) {
                        throw new StockException('Baris stok #'.$id.' tidak ditemukan.');
                    }
                    if (array_key_exists('note', $row) && (string) $stock->note !== (string) ($row['note'] ?? '')) {
                        $stock->update(['note' => ($row['note'] ?? '') !== '' ? $row['note'] : null]);
                        $changed++;
                    }
                    $log = $service->updateDetails($stock, $row, [
                        'date' => now()->toDateString(),
                        'ref_type' => null,
                        'ref_id' => null,
                    ]);
                    $changed += $log ? 1 : 0;
                }

                return $changed;
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($changed) {
            Log::record('stock_detail_update', $changed.' data stok dilengkapi oleh '.Auth::user()->display_name, 'MOD_STOCK_DETAIL', null);
        }

        return response()->json([
            'success' => true,
            'message' => $changed ? $changed.' perubahan disimpan.' : 'Tidak ada perubahan.',
            'missing_total' => Stock::missingSerial()->count(),
        ]);
    }

    /**
     * Riwayat satu baris stok (mis. satu SN): asal penerimaan, pecahan, dan semua pergerakan.
     */
    public function show($id)
    {
        $stock = Stock::with(['masterProduct', 'warehouse', 'project', 'supplier', 'parent', 'children.warehouse', 'children.project'])->findOrFail($id);

        // Jejak sampai baris asal (pecahan transfer/alokasi menyimpan parent_id).
        $lineage = collect();
        $cursor = $stock;
        while ($cursor->parent_id && $lineage->count() < 20) {
            $cursor = Stock::find($cursor->parent_id);
            if (! $cursor) {
                break;
            }
            $lineage->prepend($cursor);
        }

        $logs = StockLog::with(['warehouse:id,code', 'project:id,project_number', 'creator'])
            ->whereIn('stock_id', $lineage->pluck('id')->push($stock->id))
            ->orderBy('log_date')->orderBy('id')
            ->get();
        StockLog::attachDocuments($logs);

        return view('stock-detail.show', compact('stock', 'lineage', 'logs'));
    }

    private function filtered(Request $request): Builder
    {
        $show = $request->input('show', self::FILTER_MISSING_SERIAL);

        $query = $show === self::FILTER_MISSING_SERIAL ? Stock::missingSerial() : Stock::query()->open();

        if ($show === self::FILTER_INCOMPLETE) {
            $query->where(function (Builder $w) {
                $w->whereNull('batch_number')->orWhereNull('expiry_date')->orWhereNull('warranty_until')
                    ->orWhere(fn (Builder $x) => $x->whereNull('serial_number')->whereHas('masterProduct', fn (Builder $p) => $p->where('is_material', false)));
            });
        }

        if ($request->integer('goods_receipt_id')) {
            $itemIds = GoodsReceipt::find($request->integer('goods_receipt_id'))?->items()->pluck('id') ?? collect();
            $query->where('source_type', Stock::SOURCE_GOODS_RECEIPT_ITEM)->whereIn('source_id', $itemIds->all() ?: [0]);
        }

        return $query
            ->when($request->integer('warehouse_id'), fn (Builder $b, $id) => $b->where('warehouse_id', $id))
            ->when($request->integer('product_id'), fn (Builder $b, $id) => $b->where('master_product_id', $id));
    }
}

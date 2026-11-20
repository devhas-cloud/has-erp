<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan baris stok (hanya ditambah). qty bertanda: plus masuk,
 * minus keluar, 0 untuk perubahan data (detail_update).
 */
class StockLog extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_IN = 'in';                       // penerimaan, masuk lain-lain

    public const TYPE_OUT = 'out';                     // DO, pemakaian internal

    public const TYPE_ALLOCATE = 'allocate';           // stok bebas -> project

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_ADJUST = 'adjust';               // stock opname

    public const TYPE_RETURN_SUPPLIER = 'return_supplier';

    public const TYPE_DETAIL_UPDATE = 'detail_update'; // SN/batch/kedaluwarsa/garansi diisi atau dikoreksi

    public const TYPE_SHIP_OUT = 'ship_out';           // Branch Transfer dikirim (masuk "dalam pengiriman")

    public const TYPE_SHIP_IN = 'ship_in';             // Branch Transfer diterima di gudang tujuan

    public const TYPE_MERGE = 'merge';                 // penggabungan baris material lama ke satu saldo

    public const TYPE_LABELS = [
        self::TYPE_IN => 'Masuk',
        self::TYPE_OUT => 'Keluar',
        self::TYPE_ALLOCATE => 'Alokasi Project',
        self::TYPE_TRANSFER_OUT => 'Transfer Keluar',
        self::TYPE_TRANSFER_IN => 'Transfer Masuk',
        self::TYPE_ADJUST => 'Penyesuaian Opname',
        self::TYPE_RETURN_SUPPLIER => 'Retur ke Supplier',
        self::TYPE_DETAIL_UPDATE => 'Lengkapi Data',
        self::TYPE_SHIP_OUT => 'Kirim Antar Branch',
        self::TYPE_SHIP_IN => 'Terima Antar Branch',
        self::TYPE_MERGE => 'Penggabungan Saldo',
    ];

    public const REF_GOODS_RECEIPT_ITEM = 'goods_receipt_item';

    public const REF_STOCK_ENTRY_ITEM = 'stock_entry_item';

    public const REF_STOCK_OPNAME_ITEM = 'stock_opname_item';

    public const REF_STOCK_TRANSFER_ITEM = 'stock_transfer_item';

    /** Isi koli Packing List yang keluar saat DO diposting. */
    public const REF_PACKING_LIST_ITEM = 'packing_list_item';

    /** Isi baris DO jalur Langsung yang keluar saat DO diposting. */
    public const REF_DELIVERY_ORDER_ITEM_STOCK = 'delivery_order_item_stock';

    public const REF_SUPPLIER_RETURN_ITEM = 'supplier_return_item';

    public const REF_GOODS_REQUEST_ITEM = 'goods_request_item';

    public const REF_BRANCH_TRANSFER_ITEM = 'branch_transfer_item';

    public const REF_MATERIAL_HANDOVER_ITEM = 'material_handover_item';

    /** Barang jadi masuk / batal produksi (ref_id = productions.id). */
    public const REF_PRODUCTION = 'production';

    /** ref_type => [model item, relasi header (boleh bertingkat; null = model itu sendiri), route show]. */
    private const REF_MAP = [
        self::REF_GOODS_RECEIPT_ITEM => [GoodsReceiptItem::class, 'goodsReceipt', 'goods-receipt.show'],
        self::REF_STOCK_ENTRY_ITEM => [StockEntryItem::class, 'stockEntry', 'stock-entry.show'],
        self::REF_STOCK_OPNAME_ITEM => [StockOpnameItem::class, 'stockOpname', 'stock-opname.show'],
        self::REF_STOCK_TRANSFER_ITEM => [StockTransferItem::class, 'stockTransfer', 'stock-transfer.show'],
        self::REF_PACKING_LIST_ITEM => [PackingListItem::class, 'packingList.deliveryOrder', 'delivery-order.show'],
        self::REF_DELIVERY_ORDER_ITEM_STOCK => [DeliveryOrderItemStock::class, 'deliveryOrderItem.deliveryOrder', 'delivery-order.show'],
        self::REF_SUPPLIER_RETURN_ITEM => [SupplierReturnItem::class, 'supplierReturn', 'supplier-return.show'],
        self::REF_GOODS_REQUEST_ITEM => [GoodsRequestItem::class, 'goodsRequest', 'goods-request.show'],
        self::REF_BRANCH_TRANSFER_ITEM => [BranchTransferItem::class, 'branchTransfer', 'branch-transfer.show'],
        self::REF_MATERIAL_HANDOVER_ITEM => [MaterialHandoverItem::class, 'materialHandover', 'material-handover.show'],
        self::REF_PRODUCTION => [Production::class, null, 'production.show'],
    ];

    protected $fillable = [
        'stock_id',
        'master_product_id',
        'warehouse_id',
        'project_id',
        'is_material',
        'log_date',
        'type',
        'qty',
        'qty_after',
        'unit_cost',
        'ref_type',
        'ref_id',
        'goods_request_item_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_material' => 'boolean',
            'log_date' => 'date',
            'qty' => 'float',
            'qty_after' => 'float',
            'unit_cost' => 'float',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function goodsRequestItem(): BelongsTo
    {
        return $this->belongsTo(GoodsRequestItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getValueAttribute(): float
    {
        return round($this->qty * $this->unit_cost, 2);
    }

    /**
     * Isi atribut "ref_document" ([label, url]) untuk sekumpulan log dengan
     * query sekelompok per jenis dokumen (tanpa N+1).
     */
    public static function attachDocuments(Collection $logs): Collection
    {
        foreach ($logs->whereNotNull('ref_type')->groupBy('ref_type') as $type => $group) {
            [$class, $header, $route] = self::REF_MAP[$type] ?? [null, null, null];
            if (! $class) {
                continue;
            }
            $items = $class::with($header ? [$header] : [])->whereIn('id', $group->pluck('ref_id')->unique())->get()->keyBy('id');
            foreach ($group as $log) {
                $item = $items[$log->ref_id] ?? null;
                $doc = $header ? data_get($item, $header) : $item;
                $log->setAttribute('ref_document', $doc ? [self::documentNumber($doc), route($route, $doc->id)] : ['#'.$log->ref_id, null]);
            }
        }

        foreach ($logs->whereNull('ref_type') as $log) {
            $log->setAttribute('ref_document', ['—', null]);
        }

        return $logs;
    }

    private static function documentNumber(Model $doc): string
    {
        foreach (['receipt_number', 'entry_number', 'opname_number', 'transfer_number', 'do_number', 'return_number', 'request_number', 'handover_number', 'production_number'] as $column) {
            // transfer_number dipakai Stock Transfer maupun Branch Transfer.
            if (! empty($doc->{$column})) {
                return $doc->{$column};
            }
        }

        return '#'.$doc->getKey();
    }
}

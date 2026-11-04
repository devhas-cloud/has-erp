<?php

namespace App\Models;

use App\Support\StockValuation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Kartu stok — satu-satunya sumber kebenaran stok. Setiap baris adalah satu
 * pergerakan (qty selalu positif; arah ditentukan type). Saldo per (produk,
 * gudang, project) dihitung SUM(masuk) - SUM(keluar). project_id kosong = stok
 * bebas; terisi = stok milik/teralokasi project.
 */
class StockMovement extends Model
{
    // Masuk
    public const TYPE_RECEIPT = 'receipt';           // Penerimaan Barang (PO / DP)

    public const TYPE_ALLOCATE_IN = 'allocate_in';   // alokasi ke project (masuk transit)

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_MISC_IN = 'misc_in';           // masuk stok lain-lain

    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';

    // Keluar
    public const TYPE_ISSUE = 'issue';               // keluar stok untuk Permintaan internal

    public const TYPE_ALLOCATE_OUT = 'allocate_out'; // stok bebas dialokasikan ke project

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_DELIVERY = 'delivery';         // Delivery Order ke customer

    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';

    public const TYPE_RETURN_OUT = 'return_out';       // retur ke supplier

    public const IN_TYPES = [
        self::TYPE_RECEIPT, self::TYPE_ALLOCATE_IN, self::TYPE_TRANSFER_IN, self::TYPE_MISC_IN, self::TYPE_ADJUSTMENT_IN,
    ];

    public const OUT_TYPES = [
        self::TYPE_ISSUE, self::TYPE_ALLOCATE_OUT, self::TYPE_TRANSFER_OUT, self::TYPE_DELIVERY, self::TYPE_ADJUSTMENT_OUT, self::TYPE_RETURN_OUT,
    ];

    /**
     * Pergerakan yang memenuhi item Permintaan Barang dari stok (dihitung sekali:
     * sisi keluar dari stok bebas).
     */
    public const FULFILLMENT_TYPES = [self::TYPE_ISSUE, self::TYPE_ALLOCATE_OUT];

    public const TYPE_LABELS = [
        self::TYPE_RECEIPT => 'Penerimaan',
        self::TYPE_ALLOCATE_IN => 'Alokasi Masuk',
        self::TYPE_TRANSFER_IN => 'Transfer Masuk',
        self::TYPE_MISC_IN => 'Masuk Lain-lain',
        self::TYPE_ADJUSTMENT_IN => 'Penyesuaian (+)',
        self::TYPE_ISSUE => 'Keluar Stok',
        self::TYPE_ALLOCATE_OUT => 'Alokasi Keluar',
        self::TYPE_TRANSFER_OUT => 'Transfer Keluar',
        self::TYPE_DELIVERY => 'Delivery Order',
        self::TYPE_ADJUSTMENT_OUT => 'Penyesuaian (−)',
        self::TYPE_RETURN_OUT => 'Retur ke Supplier',
    ];

    public const SOURCE_GOODS_RECEIPT = 'goods_receipt';

    public const SOURCE_STOCK_FULFILLMENT = 'stock_fulfillment';

    public const SOURCE_STOCK_TRANSFER = 'stock_transfer';

    public const SOURCE_STOCK_ENTRY = 'stock_entry';

    public const SOURCE_DELIVERY_ORDER = 'delivery_order';

    public const SOURCE_STOCK_OPNAME = 'stock_opname';

    public const SOURCE_SUPPLIER_RETURN = 'supplier_return';

    protected $fillable = [
        'warehouse_id',
        'master_product_id',
        'project_id',
        'type',
        'qty',
        'unit',
        'unit_cost',
        'source_type',
        'source_id',
        'source_line_id',
        'goods_request_item_id',
        'moved_at',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_cost' => 'float',
            'moved_at' => 'date',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
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

    public function isIn(): bool
    {
        return in_array($this->type, self::IN_TYPES, true);
    }

    /**
     * Catat satu pergerakan stok. unit_cost (mata uang base) wajib dikirim
     * untuk barang masuk berharga (penerimaan, masuk lain-lain); selain itu
     * otomatis memakai harga rata-rata produk saat ini.
     */
    public static function record(array $attributes): self
    {
        if (($attributes['unit_cost'] ?? null) === null) {
            $attributes['unit_cost'] = StockValuation::averageCost((int) $attributes['master_product_id']);
        }

        return static::create(array_merge([
            'moved_at' => now()->toDateString(),
            'created_by' => Auth::id(),
        ], $attributes));
    }

    /**
     * Saldo per (produk, gudang, project). $projectId null = stok bebas.
     */
    public static function balance(int $warehouseId, int $masterProductId, ?int $projectId = null): int
    {
        $query = static::where('warehouse_id', $warehouseId)
            ->where('master_product_id', $masterProductId)
            ->when($projectId, fn (Builder $q) => $q->where('project_id', $projectId), fn (Builder $q) => $q->whereNull('project_id'));

        return self::sumSigned($query);
    }

    /**
     * Saldo stok bebas per gudang untuk satu produk: [warehouse_id => qty] (hanya > 0).
     */
    public static function freeBalancesByWarehouse(int $masterProductId): array
    {
        return static::where('master_product_id', $masterProductId)
            ->whereNull('project_id')
            ->get(['warehouse_id', 'type', 'qty'])
            ->groupBy('warehouse_id')
            ->map(fn ($rows) => $rows->sum(fn ($m) => $m->isIn() ? $m->qty : -$m->qty))
            ->filter(fn ($qty) => $qty > 0)
            ->all();
    }

    /**
     * Query saldo agregat per (produk, gudang, project) — basis Laporan Stok
     * dan pilihan stok di Transfer. Kolom: master_product_id, warehouse_id,
     * project_id, balance, unit.
     */
    public static function balancesQuery(): QueryBuilder
    {
        $inTypes = implode(',', array_map(fn ($type) => DB::getPdo()->quote($type), self::IN_TYPES));

        return DB::table('stock_movements')
            ->selectRaw("master_product_id, warehouse_id, project_id, SUM(CASE WHEN type IN ({$inTypes}) THEN qty ELSE -qty END) AS balance, MAX(unit) AS unit")
            ->groupBy('master_product_id', 'warehouse_id', 'project_id');
    }

    /**
     * Nama & tautan dokumen asal pergerakan (untuk Kartu Stok).
     *
     * @return array{0: string, 1: ?string}
     */
    public function sourceReference(): array
    {
        return match ($this->source_type) {
            self::SOURCE_GOODS_RECEIPT => ['Penerimaan', route('goods-receipt.show', $this->source_id)],
            self::SOURCE_STOCK_FULFILLMENT => ['Permintaan '.(GoodsRequest::whereKey($this->source_id)->value('request_number') ?: '#'.$this->source_id), route('goods-request.show', $this->source_id)],
            self::SOURCE_STOCK_TRANSFER => ['Transfer', route('stock-transfer.show', $this->source_id)],
            self::SOURCE_STOCK_ENTRY => ['Masuk Lain-lain', route('stock-entry.show', $this->source_id)],
            self::SOURCE_DELIVERY_ORDER => ['Delivery Order', route('delivery-order.show', $this->source_id)],
            self::SOURCE_STOCK_OPNAME => ['Stock Opname', route('stock-opname.show', $this->source_id)],
            self::SOURCE_SUPPLIER_RETURN => ['Retur ke Supplier', route('supplier-return.show', $this->source_id)],
            default => [$this->source_type ? ucfirst(str_replace('_', ' ', $this->source_type)) : '—', null],
        };
    }

    /**
     * Qty & nilai persediaan per produk (mata uang base): master_product_id,
     * qty, value. Harga rata-rata = value / qty.
     */
    public static function valuationQuery(): QueryBuilder
    {
        $inTypes = implode(',', array_map(fn ($type) => DB::getPdo()->quote($type), self::IN_TYPES));

        return DB::table('stock_movements')
            ->selectRaw("master_product_id, SUM(CASE WHEN type IN ({$inTypes}) THEN qty ELSE -qty END) AS qty, SUM(CASE WHEN type IN ({$inTypes}) THEN qty ELSE -qty END * COALESCE(unit_cost, 0)) AS value")
            ->groupBy('master_product_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    private static function sumSigned(Builder $query): int
    {
        $in = (int) (clone $query)->whereIn('type', self::IN_TYPES)->sum('qty');
        $out = (int) (clone $query)->whereIn('type', self::OUT_TYPES)->sum('qty');

        return $in - $out;
    }
}

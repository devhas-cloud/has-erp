<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Retur ke Supplier — barang yang sudah diterima dikembalikan (rusak / salah
 * kirim). Langsung terposting seperti Penerimaan Barang: stok keluar
 * (return_out) untuk baris yang dulu masuk gudang, qty diterima PO berkurang
 * sehingga barang retur tidak bisa ditagih dan PO kembali menunggu pengganti.
 */
class SupplierReturn extends Model
{
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'return_number',
        'supplier_id',
        'purchase_order_id',
        'warehouse_id',
        'return_date',
        'reason',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class)->orderBy('sort_order');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('supplier_returns', 'return_number', $date);
    }
}

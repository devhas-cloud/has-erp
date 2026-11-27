<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Satu unit ber-SN dalam peminjaman. return_status null = masih dipinjam
 * (unit ditahan: tidak tersedia untuk dokumen lain). good / damaged = kembali
 * ke stok; lost = stok berkurang.
 */
class ItemLoanItem extends Model
{
    public const RETURN_GOOD = 'good';

    public const RETURN_DAMAGED = 'damaged';

    public const RETURN_LOST = 'lost';

    public const RETURN_LABELS = [
        self::RETURN_GOOD => 'Good',
        self::RETURN_DAMAGED => 'Damaged',
        self::RETURN_LOST => 'Lost',
    ];

    protected $fillable = [
        'item_loan_id',
        'stock_id',
        'master_product_id',
        'condition_out',
        'return_status',
        'returned_at',
        'returned_by',
        'return_note',
    ];

    protected function casts(): array
    {
        return ['returned_at' => 'date'];
    }

    public function itemLoan(): BelongsTo
    {
        return $this->belongsTo(ItemLoan::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /** Dipakai Document::storeFor(). */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Foto kondisi unit (saat dipinjam / dikembalikan). */
    public function photos(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->where('type', Document::TYPE_RECEIPT_PHOTO)->orderBy('id');
    }

    public function isOut(): bool
    {
        return $this->return_status === null;
    }

    /**
     * Qty baris stok yang sedang dipinjam (unit ber-SN: 0 atau 1). Peminjaman
     * yang dibatalkan tidak dihitung.
     */
    public static function onLoanQty(int $stockId): float
    {
        return static::query()
            ->where('stock_id', $stockId)
            ->whereNull('return_status')
            ->whereHas('itemLoan', fn ($q) => $q->where('status', ItemLoan::STATUS_ON_LOAN))
            ->exists() ? 1.0 : 0.0;
    }

    /** Subquery SQL qty dipinjam per baris stok (untuk StockPicker). */
    public static function onLoanSql(string $stockColumn = 'stocks.id'): string
    {
        return '(SELECT COUNT(*) FROM item_loan_items ili'
            .' INNER JOIN item_loans il ON il.id = ili.item_loan_id'
            ." WHERE ili.stock_id = {$stockColumn} AND ili.return_status IS NULL AND il.status = '".ItemLoan::STATUS_ON_LOAN."')";
    }

    /** Peminjaman yang sedang menahan baris stok ini (untuk pesan & penanda). */
    public static function activeFor(int $stockId): ?self
    {
        return static::with('itemLoan.borrowerUser')
            ->where('stock_id', $stockId)
            ->whereNull('return_status')
            ->whereHas('itemLoan', fn ($q) => $q->where('status', ItemLoan::STATUS_ON_LOAN))
            ->latest('id')
            ->first();
    }
}

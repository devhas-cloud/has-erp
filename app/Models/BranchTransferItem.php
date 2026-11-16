<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Barang yang dikirim = satu baris stok asal (material boleh sebagian, barang
 * ber-SN per unit). Penerima mengisi qty diterima, kondisi, catatan, dan foto
 * (documents type receipt_photo). received_stock_id = baris stok di tujuan.
 */
class BranchTransferItem extends Model
{
    protected $fillable = [
        'branch_transfer_id',
        'stock_id',
        'qty',
        'unit',
        'received_qty',
        'condition_ok',
        'receive_note',
        'checked_at',
        'received_stock_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'received_qty' => 'float',
            'condition_ok' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function branchTransfer(): BelongsTo
    {
        return $this->belongsTo(BranchTransfer::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function receivedStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'received_stock_id');
    }

    /** Dipakai Document::storeFor(). */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->where('type', Document::TYPE_RECEIPT_PHOTO)->orderBy('id');
    }

    /**
     * Sudah diperiksa dan sesuai: qty diterima = qty dikirim dan kondisi baik.
     */
    public function isMatched(): bool
    {
        return $this->received_qty !== null
            && abs($this->received_qty - $this->qty) < Stock::EPSILON
            && $this->condition_ok === true;
    }

    /**
     * Status pemeriksaan untuk tampilan: unchecked | matched | mismatch.
     */
    public function checkState(): string
    {
        if ($this->checked_at === null) {
            return 'unchecked';
        }

        return $this->isMatched() ? 'matched' : 'mismatch';
    }
}

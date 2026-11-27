<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat perpanjangan tanggal kembali peminjaman. */
class ItemLoanExtension extends Model
{
    protected $fillable = ['item_loan_id', 'old_due_date', 'new_due_date', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['old_due_date' => 'date', 'new_due_date' => 'date'];
    }

    public function itemLoan(): BelongsTo
    {
        return $this->belongsTo(ItemLoan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

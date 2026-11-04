<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'fax',
        'email',
        'attn_name',
        'payment_term_days',
        'max_advance_percent',
        'npwp',
        'is_pkp',
        'default_pph_percent',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payment_term_days' => 'integer',
            'max_advance_percent' => 'float',
            'is_pkp' => 'boolean',
            'default_pph_percent' => 'float',
        ];
    }

    /**
     * Label termin untuk dokumen/PDF, mis. "Net 30 Days" atau "Cash" (0 hari).
     */
    public static function termLabel(?int $days): ?string
    {
        if ($days === null) {
            return null;
        }

        return $days === 0 ? 'Cash / Tunai' : 'Net '.$days.' Days';
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }
}

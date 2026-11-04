<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Delivery Order (Surat Jalan) — kirim stok milik project dari satu gudang ke
 * customer. Draft -> Posted (oleh gudang, tanpa approval). Posting mencatat
 * stok keluar 'delivery' dari (gudang, project); dokumen posted terkunci.
 */
class DeliveryOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Terkirim',
    ];

    protected $fillable = [
        'do_number',
        'project_id',
        'warehouse_id',
        'delivery_date',
        'recipient_name',
        'recipient_phone',
        'shipping_address',
        'courier',
        'note',
        'status',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class)->orderBy('sort_order');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('delivery_orders', 'do_number', $date);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Draft</span>',
            self::STATUS_POSTED => '<span class="status-badge status-active">Terkirim</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }

    /**
     * Alamat kirim default dari customer project (alamat shipping, fallback billing).
     */
    public static function defaultAddress(?AccountCompany $company): string
    {
        if (! $company) {
            return '';
        }

        foreach (['shipping', 'billing'] as $kind) {
            $parts = array_filter([
                $company->{"address_{$kind}_street"},
                $company->{"address_{$kind}_city"},
                $company->{"address_{$kind}_province"},
                $company->{"address_{$kind}_postal_code"},
            ]);
            if ($parts) {
                return implode(', ', $parts);
            }
        }

        return '';
    }
}

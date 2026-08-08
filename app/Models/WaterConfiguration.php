<?php

namespace App\Models;

use App\Traits\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterConfiguration extends Model
{
    use Notifiable;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_WAITING_APPROVAL => 'Waiting Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'quotation_number',
        'to_name',
        'address',
        'location',
        'pic_name',
        'pic_phone',
        'pic_email',
        'sales_name',
        'quotation_date',
        'parameter_note',
        'notes',
        'status',
        'created_by',
        'final_checked_by',
        'approval_note',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(WaterConfigurationItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_checked_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Draft</span>',
            self::STATUS_WAITING_APPROVAL => '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Waiting Approval</span>',
            self::STATUS_APPROVED => '<span class="status-badge status-active">Approved</span>',
            self::STATUS_REJECTED => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Rejected</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }

    public function itemsGroupedByCategory(): array
    {
        return $this->items
            ->groupBy(fn ($item) => $item->category ?: 'Lainnya')
            ->map(fn ($group) => $group->values())
            ->all();
    }

    public function totalQty(): int
    {
        return (int) $this->items->sum('qty');
    }

    public static function nextQuotationNumber(): string
    {
        $year = now()->format('Y');

        $numbers = static::query()
            ->where('quotation_number', 'like', "WC-{$year}-%")
            ->pluck('quotation_number');

        $maxSequence = 0;
        foreach ($numbers as $number) {
            $sequence = (int) substr($number, strrpos($number, '-') + 1);
            $maxSequence = max($maxSequence, $sequence);
        }

        return sprintf('WC-%s-%04d', $year, $maxSequence + 1);
    }
}

<?php

namespace App\Models;

use App\Traits\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteConfiguration extends Model
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

    protected $table = 'quote_configurations';

    protected $fillable = [
        'opportunity_id',
        'task_id',
        'date',
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
            'date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteConfigurationItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_checked_by');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    // ── Data derived dari task/opportunity ──

    public function getToNameAttribute(): ?string
    {
        return $this->contact()?->full_name ?? $this->task?->lead?->accountContact?->full_name;
    }

    public function getLocationAttribute(): ?string
    {
        return $this->company()?->account_name ?? $this->task?->lead?->accountCompany?->account_name;
    }

    public function getAddressAttribute(): ?string
    {
        $company = $this->company() ?? $this->task?->lead?->accountCompany;

        if (! $company) {
            return null;
        }

        return collect([
            $company->address_billing_street,
            $company->address_billing_city,
            $company->address_billing_province,
            $company->address_billing_postal_code,
            $company->address_billing_country,
        ])->filter()->join(', ') ?: null;
    }

    public function getPicNameAttribute(): ?string
    {
        return $this->contact()?->full_name ?? $this->task?->lead?->accountContact?->full_name;
    }

    public function getPicPhoneAttribute(): ?string
    {
        $contact = $this->contact() ?? $this->task?->lead?->accountContact;

        return $contact?->mobile ?: $contact?->phone;
    }

    public function getPicEmailAttribute(): ?string
    {
        return $this->contact()?->email ?? $this->task?->lead?->accountContact?->email;
    }

    public function getSalesNameAttribute(): ?string
    {
        return $this->task?->creator?->username;
    }

    private function company(): ?AccountCompany
    {
        return $this->opportunity?->accountCompany;
    }

    private function contact(): ?AccountContact
    {
        return $this->opportunity?->accountContact;
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
}

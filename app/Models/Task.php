<?php

namespace App\Models;

use App\Traits\Loggable;
use App\Traits\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use Loggable;
    use Notifiable;

    public const STATUS_CANCELLED = 'cancelled';

    public const CANCELLABLE_STATUSES = ['todo', 'in_progress'];

    protected $fillable = [
        'creator_id',
        'lead_id',
        'opportunity_id',
        'project_id',
        'activity_id',
        'category_id',
        'account_contact_id',
        'handling_group_id',
        'whatsapp_group_id',
        'title',
        'description',
        'due_date',
        'time',
        'status',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
        'requires_approval',
        'alert_type',
        'alert_target',
        'alert_time',
        'is_alert_sent',
    ];

    protected $casts = [
        'due_date' => 'date',
        'alert_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'requires_approval' => 'boolean',
        'is_alert_sent' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    /**
     * Kontak yang dikunjungi — hanya relevan untuk task kategori "Visit".
     */
    public function accountContact(): BelongsTo
    {
        return $this->belongsTo(AccountContact::class);
    }

    public function handlingGroup(): BelongsTo
    {
        return $this->belongsTo(HandlingGroup::class, 'handling_group_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function whatsappGroup(): BelongsTo
    {
        return $this->belongsTo(WhatsAppGroup::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Task milik suatu divisi: pembuat atau salah satu assignee berada di divisi itu.
     */
    public function scopeForDivision($query, string $divisionName)
    {
        $inDivision = fn ($u) => $u->whereHas('division', fn ($d) => $d->where('division_name', $divisionName));

        return $query->where(fn ($q) => $q->whereHas('creator', $inDivision)->orWhereHas('assignees', $inDivision));
    }

    /**
     * Project terkait (opsional), mis. task instalasi / maintenance.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function accountCompanyId(): ?int
    {
        return $this->lead?->account_companies_id
            ?? $this->opportunity?->account_companies_id
            ?? $this->accountContact?->account_companies_id
            ?? $this->project?->company_id
            ?? $this->sourceActivity?->accountCompanyId();
    }

    public function accountContactId(): ?int
    {
        return $this->account_contact_id
            ? (int) $this->account_contact_id
            : ($this->lead?->account_contacts_id
                ?? $this->opportunity?->account_contacts_id
                ?? $this->sourceActivity?->accountContactId());
    }

    public function accountCompanyName(): ?string
    {
        $companyId = $this->accountCompanyId();

        return $companyId ? AccountCompany::find($companyId)?->account_name : null;
    }

    /**
     * Link absolut untuk notifikasi WhatsApp.
     */
    public function whatsappUrl(): string
    {
        return route('task-planner.show', $this->id);
    }

    /**
     * Kolom DB bertipe TIME tersimpan "HH:MM:SS"; tampilkan & gunakan "HH:MM"
     * agar input time lintas browser konsisten.
     */
    public function getTimeAttribute($value)
    {
        return $value ? substr($value, 0, 5) : null;
    }

    public function sourceActivity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(TaskVisit::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(TaskProposal::class);
    }

    public function quoteConfigurations(): HasMany
    {
        return $this->hasMany(QuoteConfiguration::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}

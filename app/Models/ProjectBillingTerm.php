<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Satu baris timeline penagihan project (mis. "DP 30% — ditagih untuk proses").
 *
 * Gerbang tagihan: termin baru Terkunci dan baru bisa dibuatkan invoice setelah
 * dibuka (unlock) oleh pemegang can_update Project; bisa dikunci lagi selama
 * belum ada invoice aktif. Tanpa invoice aktif (belum ada / dibatalkan) termin
 * yang terbuka berstatus Open. Persen terkunci dan termin tidak bisa dihapus
 * selama ada invoice aktif (hasActiveInvoice()).
 */
class ProjectBillingTerm extends Model
{
    protected $fillable = [
        'project_id',
        'sort_order',
        'name',
        'percent',
        'description',
        'target_date',
        'unlocked_at',
        'unlocked_by',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'float',
            'target_date' => 'date',
            'unlocked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function unlocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'billing_term_id')->orderByDesc('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->orderBy('id');
    }

    /**
     * Invoice yang sedang berjalan (bukan Cancelled) untuk termin ini.
     */
    public function activeInvoice(): ?Invoice
    {
        $invoices = $this->relationLoaded('invoices') ? $this->invoices : $this->invoices()->get();

        return $invoices->first(fn (Invoice $invoice) => $invoice->status !== Invoice::STATUS_CANCELLED);
    }

    public function isUnlocked(): bool
    {
        return $this->unlocked_at !== null;
    }

    /**
     * Sudah ada invoice aktif: persen tidak bisa diubah, termin tidak bisa dihapus atau dikunci lagi.
     */
    public function hasActiveInvoice(): bool
    {
        return $this->activeInvoice() !== null;
    }

    /**
     * Open = sudah dibuka untuk ditagih dan belum punya invoice aktif.
     */
    public function isOpen(): bool
    {
        return $this->isUnlocked() && ! $this->hasActiveInvoice();
    }

    /**
     * Dokumen pendukung boleh dihapus selama invoice termin belum Approved.
     */
    public function documentsDeletable(): bool
    {
        $invoice = $this->activeInvoice();

        return ! $invoice || ! $invoice->isApproved();
    }

    /**
     * Label & gaya status gabungan untuk tabel timeline.
     *
     * @return array{label: string, style: string}
     */
    public function statusMeta(): array
    {
        $invoice = $this->activeInvoice();

        if (! $invoice) {
            return $this->isUnlocked()
                ? ['label' => 'Open', 'style' => 'background:#dcfce7;color:#166534;']
                : ['label' => 'Terkunci', 'style' => 'background:#f3f4f6;color:#4b5563;'];
        }

        return match (true) {
            $invoice->status === Invoice::STATUS_PAID => ['label' => 'Lunas', 'style' => 'background:#dcfce7;color:#166534;'],
            $invoice->isApproved() => ['label' => 'Belum Dibayar', 'style' => 'background:#fef3c7;color:#92400e;'],
            $invoice->approval_status === Invoice::APPROVAL_WAITING_APPROVAL => ['label' => 'Menunggu Approval', 'style' => 'background:#ede9fe;color:#5b21b6;'],
            $invoice->approval_status === Invoice::APPROVAL_REJECTED => ['label' => 'Ditolak', 'style' => 'background:var(--danger-soft);color:#7f1d1d;'],
            default => ['label' => 'Draft', 'style' => 'background:var(--info-soft);color:#1e40af;'],
        };
    }
}

<?php

namespace App\Models;

use App\Support\NumberSequence;
use App\Traits\Loggable;
use App\Traits\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Project extends Model
{
    use Loggable;
    use Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const TYPE_PROJECT = 'project';

    public const TYPE_RETAIL = 'retail';

    public const TYPE_LABELS = [
        self::TYPE_PROJECT => 'Project',
        self::TYPE_RETAIL => 'Retail',
    ];

    protected $fillable = [
        'project_number',
        'project_name',
        'project_description',
        'quotation_id',
        'company_id',
        'po_number_company',
        'po_date',
        'po_date_received',
        'po_due_date',
        'total_sale_amount',
        'net_sale_amount',
        'is_retention',
        'type_of_achievement',
        'status',
        'po_document_path',
        'po_document_name',
        'po_uploaded_by',
        'po_uploaded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'po_date_received' => 'date',
            'po_due_date' => 'date',
            'is_retention' => 'boolean',
            'total_sale_amount' => 'float',
            'net_sale_amount' => 'float',
            'po_uploaded_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function poUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'po_uploaded_by');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(ProjectAchievement::class)->orderBy('id');
    }

    public function salesAchievements(): HasMany
    {
        return $this->hasMany(SalesAchievement::class)->orderBy('id');
    }

    public function billingTerms(): HasMany
    {
        return $this->hasMany(ProjectBillingTerm::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Dokumen kontrak/perjanjian project (PO tetap di kolom po_document_*).
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->orderBy('id');
    }

    /**
     * Dokumen milik project ini: kontrak project atau dokumen pendukung termin project.
     */
    public function ownsDocument(Document $document): bool
    {
        if ($document->documentable_type === $this->getMorphClass()) {
            return (int) $document->documentable_id === (int) $this->id;
        }

        return $document->documentable_type === (new ProjectBillingTerm)->getMorphClass()
            && ProjectBillingTerm::whereKey($document->documentable_id)->where('project_id', $this->id)->exists();
    }

    /**
     * Dasar persentase penagihan: nilai sebelum PPN. Project dari quotation =
     * sub total - diskon quotation; project manual (tanpa quotation) = net sale
     * amount, karena total sale amount sudah termasuk PPN.
     */
    public function billingBaseAmount(): float
    {
        $quotation = $this->quotation;

        if (! $quotation) {
            return (float) $this->net_sale_amount;
        }

        $subtotal = (float) $quotation->subtotal;
        $discount = (float) $quotation->discount_percent > 0
            ? round($subtotal * (float) $quotation->discount_percent / 100, 2)
            : round((float) $quotation->discount_amount, 2);

        return round($subtotal - $discount, 2);
    }

    /**
     * Alasan project tidak boleh dihapus: sudah punya dokumen keuangan,
     * pembelian, atau stok. Project seperti ini dibatalkan lewat status
     * Cancelled agar riwayatnya tetap utuh. Invoice Draft/Rejected, timeline,
     * achievement, dan approval PO supplier ikut terhapus bersama project.
     *
     * @return array<int, string>
     */
    public function deletionBlockers(): array
    {
        $issuedInvoices = $this->invoices()
            ->where(fn ($q) => $q->whereNotIn('approval_status', [Invoice::APPROVAL_DRAFT, Invoice::APPROVAL_REJECTED])
                ->orWhereHas('receipts'))
            ->count();

        $checks = [
            'invoice yang sudah diajukan / disetujui' => $issuedInvoices,
            'Delivery Order' => $this->deliveryOrders()->count(),
            'Permintaan Barang' => DB::table('goods_requests')->where('project_id', $this->id)->count(),
            'Direct Purchase' => DB::table('direct_purchases')->where('project_id', $this->id)->count(),
            'tagihan supplier' => DB::table('supplier_bill_items')->where('project_id', $this->id)->distinct()->count('supplier_bill_id'),
            'pergerakan stok' => DB::table('stock_movements')->where('project_id', $this->id)->count(),
            'Transfer Stok' => DB::table('stock_transfers')->where('from_project_id', $this->id)->orWhere('to_project_id', $this->id)->count(),
            'Masuk Stok Lain-lain' => DB::table('stock_entries')->where('project_id', $this->id)->count(),
            'Stock Opname' => DB::table('stock_opname_items')->where('project_id', $this->id)->distinct()->count('stock_opname_id'),
        ];

        return collect($checks)->filter()->map(fn (int $count, string $label) => $count.' '.$label)->values()->all();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('invoice_date')->orderBy('id');
    }

    /**
     * Approval "boleh lanjut PO ke supplier" (modul PO Supplier Approval),
     * BUKAN approval quotation (lihat Quotation::finalChecker()).
     */
    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class)->orderByDesc('id');
    }

    public function poSupplierApprovals(): HasMany
    {
        return $this->hasMany(ProjectPoSupplierApproval::class);
    }

    /**
     * Purchasing boleh lanjut PO barang ke supplier = kuorum 2 approver
     * berbeda di modul PO Supplier Approval sudah terpenuhi.
     */
    public function isReadyForSupplierPo(): bool
    {
        return $this->poSupplierApprovals()->count() >= 2;
    }

    public function opportunity(): ?Opportunity
    {
        return $this->quotation?->opportunity;
    }

    public function hasPoDocument(): bool
    {
        return ! empty($this->po_document_path);
    }

    /**
     * Format angka project: 1000000 -> "1,000,000"; 1000000.5 -> "1,000,000.5".
     */
    public static function formatNumber($value, int $decimals = 2): string
    {
        $formatted = number_format((float) $value, $decimals, '.', ',');

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    }

    public static function formatMoney($value, int $decimals = 2): string
    {
        return 'Rp '.self::formatNumber($value, $decimals);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type_of_achievement] ?? ucfirst($this->type_of_achievement);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => '<span class="status-badge status-active">Active</span>',
            self::STATUS_COMPLETED => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Completed</span>',
            self::STATUS_CANCELLED => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Cancelled</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }

    /**
     * Nomor project: PRJ-YYMM + 4 digit urut (config/document_numbers.php).
     * Urutan dimulai dari 0001 dan reset setiap tahun.
     * Contoh: PRJ-26100001 untuk project pertama Oktober 2026.
     */
    public function generateProjectNumber(): string
    {
        return NumberSequence::make('projects', 'project_number');
    }
}

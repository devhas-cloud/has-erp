<?php

namespace App\Models;

use App\Support\Number;
use App\Support\NumberSequence;
use App\Traits\Loggable;
use App\Traits\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'manual_subtotal',
        'manual_discount_percent',
        'manual_discount_amount',
        'manual_ppn_percent',
        'manual_ppn_amount',
        'manual_referral_percent',
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
            'manual_subtotal' => 'float',
            'manual_discount_percent' => 'float',
            'manual_discount_amount' => 'float',
            'manual_ppn_percent' => 'float',
            'manual_ppn_amount' => 'float',
            'manual_referral_percent' => 'float',
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

    public function warranty(): HasOne
    {
        return $this->hasOne(ProjectWarranty::class);
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
     * Hitung nilai project manual (tanpa quotation), skema sama dengan quotation:
     *   diskon        = subtotal x persen (bila persen diisi), selain itu nominal
     *   sesudah diskon= subtotal − diskon  (dasar timeline penagihan)
     *   PPN           = sesudah diskon x persen (bila persen diisi), selain itu nominal
     *   total sale    = sesudah diskon + PPN
     *   referen       = sesudah diskon x persen referen
     *   net sale      = sesudah diskon − referen (dasar achievement sales)
     *
     * @param  array{subtotal: float|int|string|null, discount_percent?: mixed, discount_amount?: mixed, ppn_percent?: mixed, ppn_amount?: mixed, referral_percent?: mixed}  $input
     */
    public static function computeManualValues(array $input): array
    {
        $subtotal = round((float) ($input['subtotal'] ?? 0), 2);
        $discountPercent = (float) ($input['discount_percent'] ?? 0) > 0 ? (float) $input['discount_percent'] : null;
        $discount = $discountPercent
            ? round($subtotal * $discountPercent / 100, 2)
            : round((float) ($input['discount_amount'] ?? 0), 2);
        $base = round($subtotal - $discount, 2);

        $ppnPercent = (float) ($input['ppn_percent'] ?? 0) > 0 ? (float) $input['ppn_percent'] : null;
        $ppn = $ppnPercent
            ? round($base * $ppnPercent / 100, 2)
            : round((float) ($input['ppn_amount'] ?? 0), 2);

        $referralPercent = (float) ($input['referral_percent'] ?? 0) > 0 ? (float) $input['referral_percent'] : null;
        $referral = $referralPercent ? round($base * $referralPercent / 100, 2) : 0.0;

        return [
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discount,
            'base' => $base,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppn,
            'total_sale' => round($base + $ppn, 2),
            'referral_percent' => $referralPercent,
            'referral_amount' => $referral,
            'net_sale' => round($base - $referral, 2),
        ];
    }

    /**
     * Rincian input project manual untuk form. Project lama (belum punya rincian)
     * dibentuk dari nilai tersimpan: subtotal = net sale, PPN = total − net,
     * tanpa diskon/referen, sehingga nilai dan dasar penagihannya tidak berubah.
     */
    public function manualValues(): array
    {
        if ($this->manual_subtotal !== null) {
            return self::computeManualValues([
                'subtotal' => $this->manual_subtotal,
                'discount_percent' => $this->manual_discount_percent,
                'discount_amount' => $this->manual_discount_amount,
                'ppn_percent' => $this->manual_ppn_percent,
                'ppn_amount' => $this->manual_ppn_amount,
                'referral_percent' => $this->manual_referral_percent,
            ]);
        }

        return self::computeManualValues([
            'subtotal' => $this->net_sale_amount,
            'ppn_amount' => max(0, round((float) $this->total_sale_amount - (float) $this->net_sale_amount, 2)),
        ]);
    }

    public function hasManualBreakdown(): bool
    {
        return ! $this->quotation_id && $this->manual_subtotal !== null;
    }

    /**
     * Dasar persentase penagihan: nilai sesudah diskon, sebelum PPN. Project dari
     * quotation = sub total − diskon quotation; project manual = subtotal − diskon
     * manual; project manual lama tanpa rincian = net sale amount.
     */
    public function billingBaseAmount(): float
    {
        $quotation = $this->quotation;

        if (! $quotation) {
            return $this->hasManualBreakdown()
                ? $this->manualValues()['base']
                : (float) $this->net_sale_amount;
        }

        $subtotal = (float) $quotation->subtotal;
        $discount = (float) $quotation->discount_percent > 0
            ? round($subtotal * (float) $quotation->discount_percent / 100, 2)
            : round((float) $quotation->discount_amount, 2);

        return round($subtotal - $discount, 2);
    }

    /**
     * Rincian dasar penagihan (timeline termin): subtotal quotation − diskon =
     * nilai sesudah diskon (DPP, sebelum PPN). PPN hanya ditampilkan sebagai
     * informasi; tidak mengubah dasar penagihan. Project manual: Net Sale Amount.
     *
     * @return array{source: string, subtotal: ?float, discount_percent: ?float, discount: ?float, base: float, ppn: ?float, grand_total: ?float}
     */
    public function billingBaseBreakdown(): array
    {
        $quotation = $this->quotation;

        if (! $quotation) {
            $manual = $this->hasManualBreakdown() ? $this->manualValues() : null;

            return [
                'source' => 'manual',
                'subtotal' => $manual['subtotal'] ?? null,
                'discount_percent' => $manual['discount_percent'] ?? null,
                'discount' => $manual['discount_amount'] ?? null,
                'base' => $this->billingBaseAmount(),
                'ppn' => round((float) $this->total_sale_amount - (float) $this->net_sale_amount, 2),
                'grand_total' => (float) $this->total_sale_amount,
            ];
        }

        $base = $this->billingBaseAmount();
        $subtotal = round((float) $quotation->subtotal, 2);

        return [
            'source' => 'quotation',
            'subtotal' => $subtotal,
            'discount_percent' => (float) $quotation->discount_percent > 0 ? (float) $quotation->discount_percent : null,
            'discount' => round($subtotal - $base, 2),
            'base' => $base,
            'ppn' => round((float) $quotation->ppn, 2),
            'grand_total' => round((float) $quotation->grand_total, 2),
        ];
    }

    /**
     * Rincian Total Sale Amount → Net Sale Amount. Project dari quotation memakai
     * Estimasi PL (Nilai Awal → PPN → Discount → Referen); project manual hanya
     * Total Sale − PPN = Net Sale. Null bila PL quotation tidak tersedia.
     *
     * @return array<string, mixed>|null
     */
    public function saleValueBreakdown(): ?array
    {
        if (! $this->quotation_id) {
            if ($this->hasManualBreakdown()) {
                $m = $this->manualValues();

                return [
                    'source' => 'manual',
                    'total_sale' => (float) $this->total_sale_amount,
                    'ppn' => $m['ppn_amount'],
                    'base' => $m['base'],
                    'referral_percent' => $m['referral_percent'],
                    'referral' => $m['referral_amount'],
                    'net_sale' => (float) $this->net_sale_amount,
                ];
            }

            return [
                'source' => 'manual',
                'total_sale' => (float) $this->total_sale_amount,
                'ppn' => round((float) $this->total_sale_amount - (float) $this->net_sale_amount, 2),
                'net_sale' => (float) $this->net_sale_amount,
            ];
        }

        $estimate = $this->quotation?->profitEstimate;
        if (! $estimate) {
            return null;
        }

        return [
            'source' => 'profit_estimate',
            'total_sale' => (float) $estimate->nilai_awal,
            'ppn' => (float) $estimate->ppn_amount,
            'subtotal_1' => (float) $estimate->subtotal_1,
            'discount' => (float) $estimate->discount_amount,
            'subtotal_2' => (float) $estimate->subtotal_2,
            'referral_percent' => (float) $estimate->referral_percent,
            'referral' => (float) $estimate->referral_amount,
            'net_sale' => (float) $estimate->real_project_value,
            'estimate_id' => $estimate->id,
            // Nilai project tersimpan bisa berbeda bila PL disinkron ulang setelah project dibuat.
            'differs' => abs((float) $estimate->real_project_value - (float) $this->net_sale_amount) >= 0.01
                || abs((float) $estimate->nilai_awal - (float) $this->total_sale_amount) >= 0.01,
        ];
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
    /** Angka gaya US tanpa nol di belakang: 1,000,000 / 1,000,000.5 (App\Support\Number). */
    public static function formatNumber($value, int $decimals = 2): string
    {
        return Number::format($value, $decimals);
    }

    /** Rupiah: tanpa desimal kecuali ada sen — Rp 1,000,000 / Rp 17,112,643.56. */
    public static function formatMoney($value, int $decimals = 2): string
    {
        return 'Rp '.Number::money($value);
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

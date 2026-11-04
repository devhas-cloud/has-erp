<?php

namespace App\Models;

use App\Models\Concerns\HasPurchaseDocuments;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasPurchaseDocuments;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Status yang boleh diterima barangnya (Penerimaan Barang) dan dibatalkan.
     */
    public const RECEIVABLE_STATUSES = [self::STATUS_APPROVED, self::STATUS_PARTIALLY_RECEIVED];

    public const TYPE_MANUFACTURE = 'manufacture';

    public const TYPE_LOCAL = 'local';

    public const TYPE_CALIBRATION = 'calibration';

    public const TYPE_LABELS = [
        self::TYPE_MANUFACTURE => 'Manufacture',
        self::TYPE_LOCAL => 'Local',
        self::TYPE_CALIBRATION => 'Calibration',
    ];

    /**
     * Suffix nomor PO per jenis. Urutan angka dipakai bersama lintas jenis,
     * sehingga nomor tetap unik walau suffix diabaikan.
     */
    public const TYPE_SUFFIXES = [
        self::TYPE_MANUFACTURE => '',
        self::TYPE_LOCAL => '-L',
        self::TYPE_CALIBRATION => '-C',
    ];

    public const DEFAULT_PPN_PERCENT = 11;

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_WAITING_APPROVAL => 'Waiting Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PARTIALLY_RECEIVED => 'Diterima Sebagian',
        self::STATUS_RECEIVED => 'Diterima',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'supplier_name',
        'supplier_id',
        'supplier_address',
        'supplier_phone',
        'supplier_fax',
        'supplier_attn',
        'po_number',
        'po_type',
        'revision_of_id',
        'date',
        'terms',
        'payment_term_days',
        'delivery',
        'your_ref',
        'discount_percent',
        'ppn_percent',
        'status',
        'notes',
        'request_by_name',
        'finance_name',
        'accounting_name',
        'created_by',
        'final_checked_by',
        'approval_note',
        'approved_at',
        'rejected_at',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'payment_term_days' => 'integer',
            'discount_percent' => 'float',
            'ppn_percent' => 'float',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_checked_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class)->orderBy('id');
    }

    public function documentTypes(): array
    {
        return self::purchaseDocumentTypes([
            Document::TYPE_SUPPLIER_QUOTE, Document::TYPE_SUPPLIER_CONFIRMATION, Document::TYPE_SUPPLIER_INVOICE, Document::TYPE_OTHER,
        ]);
    }

    /**
     * Setelah diputuskan (approved/rejected/...) dokumen menjadi arsip — hanya bisa ditambah.
     */
    public function canDeleteDocument(Document $document, User $user): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_WAITING_APPROVAL], true);
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, self::RECEIVABLE_STATUSES, true);
    }

    /**
     * Batalkan hanya PO yang sudah Approved & belum diterima penuh; sisa yang
     * belum diterima dilepas kembali ke Permintaan Barang.
     */
    public function canBeCancelled(): bool
    {
        return $this->isReceivable();
    }

    /**
     * Status penerimaan dari total qty diterima (dipanggil setelah posting Penerimaan Barang).
     */
    public function refreshReceiptStatus(): void
    {
        // Received ikut dihitung ulang: retur ke supplier bisa membuka kembali penerimaan.
        if (! $this->isReceivable() && $this->status !== self::STATUS_RECEIVED) {
            return;
        }

        $items = $this->items()->with('goodsReceiptItems.goodsReceipt')->get();
        $received = $items->sum(fn (PurchaseOrderItem $item) => min($item->receivedQty(), (int) $item->qty));
        $ordered = (int) $items->sum('qty');

        $status = match (true) {
            $received <= 0 => self::STATUS_APPROVED,
            $received >= $ordered => self::STATUS_RECEIVED,
            default => self::STATUS_PARTIALLY_RECEIVED,
        };

        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }
    }

    /**
     * Nomor PO baru: YYMM + 4 digit (reset tiap tahun, acuan tanggal dibuat)
     * ditambah suffix jenis.
     */
    public static function generatePoNumber(string $type, ?string $date = null): string
    {
        return NumberSequence::make('purchase_orders', 'po_number', $date, true).(self::TYPE_SUFFIXES[$type] ?? '');
    }

    /**
     * Ganti jenis hanya mengganti suffix; bagian angka nomor tetap.
     */
    /**
     * Nomor revisi dari PO Rejected: nomor dasar (8 digit) yang sama + REV,
     * REV2, REV3, ... + suffix jenis. Contoh: 26110005REV, 26110005REV2-L.
     */
    public static function revisionNumber(string $sourceNumber, string $type): string
    {
        $base = substr($sourceNumber, 0, 8);

        $last = static::where('po_number', 'like', $base.'REV%')
            ->pluck('po_number')
            ->map(fn ($number) => preg_match('/^\d{8}REV(\d*)/', $number, $m) ? max(1, (int) $m[1]) : 0)
            ->max() ?? 0;

        $next = $last + 1;

        return $base.'REV'.($next > 1 ? $next : '').(self::TYPE_SUFFIXES[$type] ?? '');
    }

    public function supplierBills(): HasMany
    {
        return $this->hasMany(SupplierBill::class)->orderByDesc('id');
    }

    public const BILLING_LABELS = [
        'none' => 'Belum Ditagih',
        'partial' => 'Ditagih Sebagian',
        'full' => 'Ditagih Penuh',
    ];

    /**
     * Status penagihan dari qty ditagih vs qty yang akhirnya dibeli (PO batal:
     * hanya yang sudah diterima).
     */
    public function billingStatus(): string
    {
        $billable = $this->items->sum(fn (PurchaseOrderItem $item) => $this->status === self::STATUS_CANCELLED ? $item->receivedQty() : (int) $item->qty);
        $billed = $this->items->sum(fn (PurchaseOrderItem $item) => $item->billedQty());

        return match (true) {
            $billed <= 0 => 'none',
            $billed >= $billable => 'full',
            default => 'partial',
        };
    }

    public const PAYMENT_LABELS = [
        'unbilled' => 'Belum Ditagih',
        'unpaid' => 'Belum Dibayar',
        'partial' => 'Dibayar Sebagian',
        'advance' => 'Uang Muka Dibayar',
        'refund' => 'Menunggu Refund',
        'paid' => 'Lunas',
    ];

    public function supplierRefunds(): HasMany
    {
        return $this->hasMany(SupplierRefund::class)->orderByDesc('id');
    }

    /** Batas uang muka PO = nilai PO x maks % supplier. */
    public function advanceLimit(): float
    {
        return round($this->netTotal() * (float) ($this->supplier?->max_advance_percent ?? 0) / 100, 2);
    }

    private function activeBills()
    {
        return $this->supplierBills->where('status', '!=', SupplierBill::STATUS_VOID);
    }

    /** Total tagihan uang muka (selain void) — untuk batas. */
    public function advanceBilled(?int $exceptBillId = null): float
    {
        return round((float) $this->activeBills()->where('bill_type', SupplierBill::TYPE_ADVANCE)
            ->reject(fn (SupplierBill $bill) => $bill->id === $exceptBillId)->sum('total'), 2);
    }

    /**
     * Kurs rata-rata tertimbang tagihan uang muka PO (nilai buku uang muka).
     * Dipakai untuk menilai refund uang muka; tanpa uang muka -> kurs master.
     */
    public function advanceRate(): float
    {
        $advances = $this->activeBills()->where('bill_type', SupplierBill::TYPE_ADVANCE);
        $total = (float) $advances->sum('total');

        return $total > 0
            ? round($advances->sum(fn (SupplierBill $bill) => $bill->total * $bill->exchange_rate) / $total, 6)
            : Currency::rateOf($this->currencyCode());
    }

    /** Uang muka yang sudah benar-benar dibayar (pembayaran Posted). */
    public function advancePaid(): float
    {
        return round((float) $this->activeBills()->where('bill_type', SupplierBill::TYPE_ADVANCE)
            ->sum(fn (SupplierBill $bill) => $bill->paidAmount()), 2);
    }

    /** Uang muka yang sudah dipotongkan di tagihan reguler (draft ikut, agar tidak dobel). */
    public function advanceApplied(?int $exceptBillId = null): float
    {
        return round((float) $this->activeBills()->where('bill_type', SupplierBill::TYPE_REGULAR)
            ->reject(fn (SupplierBill $bill) => $bill->id === $exceptBillId)->sum('advance_deduction'), 2);
    }

    public function refundedAmount(?int $exceptRefundId = null, bool $postedOnly = true): float
    {
        return round((float) $this->supplierRefunds
            ->filter(fn (SupplierRefund $refund) => $postedOnly ? $refund->status === SupplierRefund::STATUS_POSTED : $refund->status !== SupplierRefund::STATUS_VOID)
            ->reject(fn (SupplierRefund $refund) => $refund->id === $exceptRefundId)->sum('amount'), 2);
    }

    /** Saldo uang muka yang belum terpakai = dibayar - dipotongkan - direfund. */
    public function advanceBalance(?int $exceptBillId = null): float
    {
        return round(max(0, $this->advancePaid() - $this->advanceApplied($exceptBillId) - $this->refundedAmount()), 2);
    }

    /**
     * Refund jatuh tempo bila uang muka tidak akan terpakai lagi: PO batal, atau
     * semua barang sudah diterima dan ditagih.
     */
    public function refundDue(): float
    {
        $settled = $this->status === self::STATUS_CANCELLED
            || ($this->status === self::STATUS_RECEIVED && $this->billingStatus() === 'full');

        return $settled ? $this->advanceBalance() : 0.0;
    }

    /**
     * Status bayar dari tagihan terverifikasi (draft & void diabaikan).
     */
    public function paymentStatus(): string
    {
        $bills = $this->supplierBills->whereNotIn('status', [SupplierBill::STATUS_DRAFT, SupplierBill::STATUS_VOID]);
        if ($bills->isEmpty()) {
            return 'unbilled';
        }
        if ($this->refundDue() > 0) {
            return 'refund';
        }

        $paid = $bills->sum(fn (SupplierBill $bill) => $bill->paidAmount() + $bill->advance_deduction);
        $allPaid = $bills->every(fn (SupplierBill $bill) => $bill->status === SupplierBill::STATUS_PAID);
        $onlyAdvance = $bills->every(fn (SupplierBill $bill) => $bill->isAdvance());

        return match (true) {
            $paid <= 0 => 'unpaid',
            $onlyAdvance && $allPaid => 'advance',
            $allPaid => 'paid',
            default => 'partial',
        };
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of_id')->orderByDesc('id');
    }

    /**
     * Hanya PO Rejected yang belum punya revisi (revisi draft yang dihapus melepasnya lagi).
     */
    public function canBeRevised(): bool
    {
        return $this->status === self::STATUS_REJECTED && ! $this->revisions()->exists();
    }

    public static function numberWithType(string $number, string $type): string
    {
        return preg_replace('/-[A-Z]+$/', '', $number).(self::TYPE_SUFFIXES[$type] ?? '');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->po_type] ?? ucfirst((string) $this->po_type);
    }

    public function typeBadgeHtml(): string
    {
        $style = match ($this->po_type) {
            self::TYPE_LOCAL => 'background:#dcfce7;color:#166534;',
            self::TYPE_CALIBRATION => 'background:#ede9fe;color:#5b21b6;',
            default => 'background:#dbeafe;color:#1e40af;',
        };

        return '<span class="status-badge" style="'.$style.'">'.e($this->type_label).'</span>';
    }

    /**
     * Label grup item: nama project Permintaan Barang asal, atau "Stok / Internal".
     */
    public static function projectLabelFor(PurchaseOrderItem $item): string
    {
        $goodsRequest = $item->goodsRequest();

        return $goodsRequest && $goodsRequest->isForProject()
            ? ($goodsRequest->project?->project_name ?: 'Project #'.$goodsRequest->project_id)
            : GoodsRequest::PURPOSE_LABELS[GoodsRequest::PURPOSE_INTERNAL];
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
            self::STATUS_PARTIALLY_RECEIVED => '<span class="status-badge" style="background:#ede9fe;color:#5b21b6;">Diterima Sebagian</span>',
            self::STATUS_RECEIVED => '<span class="status-badge" style="background:#dcfce7;color:#166534;">Diterima</span>',
            self::STATUS_REJECTED => '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Rejected</span>',
            self::STATUS_CANCELLED => '<span class="status-badge" style="background:#f3f4f6;color:#4b5563;">Dibatalkan</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }

    /**
     * Total nilai item PO dalam mata uang PO (qty x harga satuan), tanpa kurs.
     */
    public function grandTotal(): float
    {
        return (float) $this->items->sum(fn (PurchaseOrderItem $item) => $item->amount());
    }

    /**
     * Kode mata uang PO (satu PO satu mata uang); PO kosong -> mata uang dasar.
     */
    public function currencyCode(): string
    {
        return $this->printCurrency() ?? Currency::baseName();
    }

    /**
     * Rincian total dari sub total item: Diskon (persen) -> Sub Total 1
     * (setelah diskon) -> PPN (persen dari Sub Total 1) -> Total.
     */
    public function totalsFrom(float $subtotal): array
    {
        $discount = round($subtotal * (float) $this->discount_percent / 100, 2);
        $afterDiscount = round($subtotal - $discount, 2);
        $ppn = round($afterDiscount * (float) $this->ppn_percent / 100, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => $discount,
            'after_discount' => $afterDiscount,
            'ppn' => $ppn,
            'total' => round($afterDiscount + $ppn, 2),
        ];
    }

    /**
     * Total akhir PO dalam mata uang PO (setelah diskon & PPN).
     */
    public function netTotal(): float
    {
        return $this->totalsFrom($this->grandTotal())['total'];
    }

    /**
     * Rincian total untuk dicetak, dalam mata uang cetak (lihat printTotal()).
     */
    public function printTotals(): array
    {
        return $this->totalsFrom($this->printTotal());
    }

    /**
     * Divisi unik yang terwakili di PO ini (dari goods_request masing-masing
     * item) — untuk menunjukkan bahwa satu PO bisa menggabungkan beberapa
     * Permintaan Barang lintas divisi.
     */
    public function divisionNames(): array
    {
        return $this->items
            ->map(fn (PurchaseOrderItem $item) => $item->goodsRequest()?->division?->division_name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function itemCount(): int
    {
        return $this->items->count();
    }

    /**
     * Item dikelompokkan per tujuan (project Permintaan Barang asal, atau
     * "Stok / Internal") untuk form/show/PDF. Urutan grup mengikuti kemunculan
     * pertama item.
     */
    public function projectGroups(): array
    {
        $groups = [];

        foreach ($this->items as $item) {
            $groups[self::projectLabelFor($item)][] = $item;
        }

        return collect($groups)
            ->map(fn ($items, $label) => ['label' => $label, 'items' => collect($items)])
            ->values()
            ->all();
    }

    /**
     * Alias lama — PO tidak lagi menyimpan baris group manual.
     */
    public function hierarchyGroups(): array
    {
        return $this->projectGroups();
    }

    /**
     * Daftar label grup unik (dipakai pada blok "PROJECT :" di dokumen PO).
     */
    public function projectLabels(): array
    {
        return collect($this->hierarchyGroups())->pluck('label')->all();
    }

    /**
     * Kode mata uang item PO (satu PO satu mata uang; divalidasi saat simpan).
     * PO kosong -> mata uang dasar; data lama bercampur -> null.
     */
    public function printCurrency(): ?string
    {
        $currencies = $this->items->pluck('currency')->filter()->unique()->values();

        return match ($currencies->count()) {
            0 => Currency::baseName(),
            1 => $currencies->first(),
            default => null,
        };
    }

    /**
     * Total untuk dicetak (qty x harga satuan, mata uang PO).
     */
    public function printTotal(): float
    {
        return $this->grandTotal();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasPurchaseDocuments;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tagihan Supplier — pengakuan hutang. Tagihan reguler dicocokkan 3-way
 * (PO x Penerimaan x Tagihan): qty ditagih <= diterima - sudah ditagih, harga
 * dalam toleransi harga PO. Draft -> Diverifikasi Finance (Belum Dibayar) ->
 * Dibayar Sebagian -> Lunas; void hanya dengan alasan dan selama belum dibayar.
 * Nilai mengikuti diskon & PPN PO. Tagihan void tidak dihitung di mana pun.
 */
class SupplierBill extends Model
{
    use HasPurchaseDocuments;

    public const TYPE_REGULAR = 'regular';

    public const TYPE_ADVANCE = 'advance';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_VERIFIED => 'Belum Dibayar',
        self::STATUS_PARTIALLY_PAID => 'Dibayar Sebagian',
        self::STATUS_PAID => 'Lunas',
        self::STATUS_VOID => 'Void',
    ];

    /** Status yang menjadi hutang aktif (sudah diverifikasi, belum lunas). */
    public const OPEN_STATUSES = [self::STATUS_VERIFIED, self::STATUS_PARTIALLY_PAID];

    /**
     * Toleransi selisih harga baris terhadap harga PO (default Finance: 1%).
     */
    public const PRICE_TOLERANCE_PERCENT = 1.0;

    protected $fillable = [
        'bill_number',
        'bill_type',
        'supplier_id',
        'purchase_order_id',
        'direct_purchase_id',
        'supplier_invoice_number',
        'tax_invoice_number',
        'bill_date',
        'due_date',
        'currency',
        'exchange_rate',
        'rate_estimated',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'other_charges',
        'ppn_percent',
        'ppn_amount',
        'pph_label',
        'pph_percent',
        'pph_base',
        'pph_amount',
        'pph_slip_number',
        'pph_slip_date',
        'total',
        'advance_deduction',
        'status',
        'notes',
        'created_by',
        'verified_by',
        'verified_at',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'float',
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'other_charges' => 'float',
            'pph_percent' => 'float',
            'pph_base' => 'float',
            'pph_amount' => 'float',
            'pph_slip_date' => 'date',
            'ppn_percent' => 'float',
            'ppn_amount' => 'float',
            'total' => 'float',
            'advance_deduction' => 'float',
            'exchange_rate' => 'float',
            'rate_estimated' => 'boolean',
            'verified_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class)->orderBy('sort_order');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function directPurchase(): BelongsTo
    {
        return $this->belongsTo(DirectPurchase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('supplier_bills', 'bill_number', $date);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    /**
     * Total dibayar = alokasi dari pembayaran berstatus Posted saja.
     */
    public function paidAmount(): float
    {
        $allocations = $this->relationLoaded('allocations')
            ? $this->allocations
            : $this->allocations()->with('payment')->get();

        return round((float) $allocations->filter(fn (SupplierPaymentAllocation $a) => $a->payment?->status === SupplierPayment::STATUS_POSTED)->sum('amount'), 2);
    }

    /**
     * Status bayar dari total alokasi Posted (dipanggil setelah posting / void pembayaran).
     */
    public function refreshPaymentStatus(): void
    {
        if (! in_array($this->status, [self::STATUS_VERIFIED, self::STATUS_PARTIALLY_PAID, self::STATUS_PAID], true)) {
            return;
        }

        $paid = $this->unsetRelation('allocations')->paidAmount();
        $this->update(['status' => match (true) {
            $paid + 0.005 >= $this->amountDue() => self::STATUS_PAID,
            $paid <= 0 => self::STATUS_VERIFIED,
            default => self::STATUS_PARTIALLY_PAID,
        }]);
    }

    public function isForeign(): bool
    {
        return strtoupper((string) $this->currency) !== Currency::baseName();
    }

    /**
     * Nilai buku Rupiah: nominal mata uang tagihan x kurs tanggal invoice supplier.
     */
    public function toBook(float $amount): float
    {
        return round($amount * (float) ($this->exchange_rate ?: 1), 2);
    }

    /** Sisa hutang menurut kurs tagihan (nilai buku). */
    public function outstandingBase(): float
    {
        return $this->toBook($this->outstanding());
    }

    /**
     * Selisih kurs terealisasi dari pembayaran Posted (positif = rugi kurs).
     */
    public function realizedFx(): float
    {
        $allocations = $this->relationLoaded('allocations') ? $this->allocations : $this->allocations()->with('payment')->get();

        return round((float) $allocations->filter(fn (SupplierPaymentAllocation $a) => $a->payment?->status === SupplierPayment::STATUS_POSTED)->sum('fx_difference'), 2);
    }

    public function isAdvance(): bool
    {
        return $this->bill_type === self::TYPE_ADVANCE;
    }

    /** Jenis PPh yang umum dipotong; tarif tetap diisi bebas per tagihan. */
    public const PPH_LABELS = ['PPh 23', 'PPh 4(2)', 'PPh 21', 'PPh 22', 'PPh 26'];

    /**
     * Yang harus ditransfer = total tagihan - potongan uang muka - PPh dipotong.
     */
    public function amountDue(): float
    {
        return round(max(0, $this->total - $this->advance_deduction - $this->pph_amount), 2);
    }

    /** DPP = barang - diskon + biaya tambahan. */
    public function taxBase(): float
    {
        return round($this->subtotal - $this->discount_amount + $this->other_charges, 2);
    }

    /** PPh sudah dipotong tapi bukti potong belum dicatat. */
    public function needsPphSlip(): bool
    {
        return $this->pph_amount > 0 && ! $this->pph_slip_number && ! $this->isVoid() && ! $this->isDraft();
    }

    public function outstanding(): float
    {
        return $this->isVoid() || $this->isDraft() ? 0.0 : round(max(0, $this->amountDue() - $this->paidAmount()), 2);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true) && $this->due_date?->isPast() && ! $this->due_date->isToday();
    }

    public function canBeVoided(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_VERIFIED], true) && $this->paidAmount() <= 0;
    }

    public function sourceNumber(): string
    {
        return $this->purchase_order_id
            ? ($this->purchaseOrder?->po_number ?: 'PO #'.$this->purchase_order_id)
            : ($this->directPurchase?->dp_number ?: 'DPO #'.$this->direct_purchase_id);
    }

    /**
     * Hitung ulang subtotal -> diskon -> PPN -> total dari baris (persen ikut dokumen sumber).
     */
    public function recalculate(?float $pphBase = null): void
    {
        $items = $this->items()->get();
        $subtotal = round((float) $items->where('line_type', SupplierBillItem::TYPE_ITEM)->sum('amount'), 2);
        $charges = round((float) $items->where('line_type', SupplierBillItem::TYPE_CHARGE)->sum('amount'), 2);
        $discount = round($subtotal * $this->discount_percent / 100, 2);
        $dpp = round($subtotal - $discount + $charges, 2);
        $ppn = round($dpp * $this->ppn_percent / 100, 2);

        // Dasar PPh default = DPP; boleh diisi lain (mis. hanya porsi jasa), maksimal DPP.
        $base = $this->pph_percent ? round(min($pphBase ?? $dpp, $dpp), 2) : 0;

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'other_charges' => $charges,
            'ppn_amount' => $ppn,
            'total' => round($dpp + $ppn, 2),
            'pph_base' => $base,
            'pph_amount' => round($base * (float) $this->pph_percent / 100, 2),
        ]);
    }

    /**
     * Direct Purchase sudah membawa nota supplier: saat DP disetujui, tagihannya
     * dibuat otomatis berstatus Belum Dibayar (verifikasi = approval DP).
     * DP tanpa master supplier tidak dibuatkan tagihan.
     */
    public static function createFromDirectPurchase(DirectPurchase $dp, ?int $verifierId): ?self
    {
        $dp->loadMissing(['items', 'supplier']);
        if (! $dp->supplier_id || $dp->items->isEmpty() || static::where('direct_purchase_id', $dp->id)->where('status', '!=', self::STATUS_VOID)->exists()) {
            return null;
        }

        $billDate = $dp->date?->toDateString() ?? now()->toDateString();
        $term = (int) ($dp->payment_term_days ?? $dp->supplier?->payment_term_days ?? 0);

        $bill = static::create([
            'bill_number' => static::generateNumber($billDate),
            'bill_type' => self::TYPE_REGULAR,
            'supplier_id' => $dp->supplier_id,
            'direct_purchase_id' => $dp->id,
            'supplier_invoice_number' => $dp->dp_number ?: 'DPO #'.$dp->id,
            'bill_date' => $billDate,
            'due_date' => Carbon::parse($billDate)->addDays($term)->toDateString(),
            'currency' => $dp->currencyCode(),
            // Kurs master saat DP disetujui (perkiraan; Finance dapat melihatnya di detail tagihan).
            'exchange_rate' => Currency::rateOf($dp->currencyCode()),
            'rate_estimated' => strtoupper($dp->currencyCode()) !== Currency::baseName(),
            'discount_percent' => 0,
            'ppn_percent' => (float) $dp->ppn_percent,
            'status' => self::STATUS_VERIFIED,
            'notes' => 'Otomatis dari Direct Purchase '.($dp->dp_number ?: '#'.$dp->id).'.',
            'created_by' => $dp->created_by,
            'verified_by' => $verifierId,
            'verified_at' => now(),
        ]);

        foreach ($dp->items->values() as $i => $item) {
            $bill->items()->create([
                'direct_purchase_item_id' => $item->id,
                'master_product_id' => $item->master_product_id,
                'project_id' => $dp->allocationProjectId(),
                'part_number' => $item->part_number,
                'description' => $item->description,
                'qty' => (int) $item->qty,
                'unit' => $item->unit,
                'price' => round((float) $item->price, 2),
                'amount' => round((int) $item->qty * (float) $item->price, 2),
                'sort_order' => $i + 1,
            ]);
        }
        $bill->recalculate();

        return $bill;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Draft</span>',
            self::STATUS_VERIFIED => $this->isOverdue()
                ? '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Terlambat</span>'
                : '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Belum Dibayar</span>',
            self::STATUS_PARTIALLY_PAID => '<span class="status-badge" style="background:#ede9fe;color:#5b21b6;">Dibayar Sebagian</span>',
            self::STATUS_PAID => '<span class="status-badge status-active">Lunas</span>',
            self::STATUS_VOID => '<span class="status-badge" style="background:#f3f4f6;color:#4b5563;">Void</span>',
            default => '<span class="status-badge">'.e($this->status).'</span>',
        };
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public function documentTypes(): array
    {
        return self::purchaseDocumentTypes([
            Document::TYPE_SUPPLIER_INVOICE, Document::TYPE_TAX_INVOICE, Document::TYPE_OTHER,
        ]);
    }

    /**
     * Setelah diverifikasi dokumen menjadi arsip — hanya bisa ditambah.
     */
    public function canDeleteDocument(Document $document, User $user): bool
    {
        return $this->isDraft();
    }
}

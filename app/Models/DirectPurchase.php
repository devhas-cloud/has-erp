<?php

namespace App\Models;

use App\Models\Concerns\HasPurchaseDocuments;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Direct Purchase: pembelian langsung tanpa Permintaan Barang / PO formal.
 * `purpose` menentukan tujuan barang saat diterima (Penerimaan Barang):
 *   - stock   -> masuk gudang stok (stok bebas);
 *   - project -> masuk gudang transit, dialokasikan ke project_id;
 *   - direct  -> langsung pakai (konfirmasi terima, tidak masuk stok).
 * Approval satu approver (can_approve). Satu dokumen satu mata uang, tanpa
 * kurs; total = Subtotal -> PPN -> Total (tanpa diskon/terms/delivery/ref).
 */
class DirectPurchase extends Model
{
    use HasPurchaseDocuments;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const PURPOSE_STOCK = 'stock';

    public const PURPOSE_PROJECT = 'project';

    public const PURPOSE_DIRECT = 'direct';

    public const PURPOSE_LABELS = [
        self::PURPOSE_STOCK => 'Untuk Stok',
        self::PURPOSE_PROJECT => 'Untuk Project',
        self::PURPOSE_DIRECT => 'Langsung Pakai',
    ];

    public const DEFAULT_PPN_PERCENT = 11;

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_WAITING_APPROVAL => 'Waiting Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'dp_number',
        'date',
        'payment_term_days',
        'supplier_id',
        'supplier_name',
        'supplier_phone',
        'purpose',
        'project_id',
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
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'payment_term_days' => 'integer',
            'ppn_percent' => 'float',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DirectPurchaseItem::class)->orderBy('sort_order');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierBills(): HasMany
    {
        return $this->hasMany(SupplierBill::class)->orderByDesc('id');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class)->orderByDesc('id');
    }

    /**
     * Approved dan masih ada item yang belum diterima penuh.
     */
    public function documentTypes(): array
    {
        return self::purchaseDocumentTypes([
            Document::TYPE_SUPPLIER_QUOTE, Document::TYPE_SUPPLIER_INVOICE, Document::TYPE_OTHER,
        ]);
    }

    public function canDeleteDocument(Document $document, User $user): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_WAITING_APPROVAL], true);
    }

    public function isReceivable(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->items->contains(fn (DirectPurchaseItem $item) => $item->remainingReceiptQty() > 0);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_checked_by');
    }

    /**
     * Nomor dokumen: YYMM + 4 digit (reset tiap tahun, acuan tanggal dibuat).
     * Urutan sendiri di tabel direct_purchases.
     */
    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('direct_purchases', 'dp_number', $date);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function getPurposeLabelAttribute(): string
    {
        return self::PURPOSE_LABELS[$this->purpose] ?? ucfirst($this->purpose);
    }

    public function isStockPurchase(): bool
    {
        return $this->purpose === self::PURPOSE_STOCK;
    }

    /**
     * Tujuan default baris Penerimaan Barang: langsung pakai untuk purpose direct.
     */
    public function defaultReceiptDestination(): string
    {
        return $this->purpose === self::PURPOSE_DIRECT
            ? GoodsReceiptItem::DESTINATION_DIRECT
            : GoodsReceiptItem::DESTINATION_WAREHOUSE;
    }

    /**
     * Project alokasi barang saat diterima (purpose project).
     */
    public function allocationProjectId(): ?int
    {
        return $this->purpose === self::PURPOSE_PROJECT ? $this->project_id : null;
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

    /**
     * Total nilai item dalam mata uang dokumen (qty x harga satuan), tanpa kurs.
     */
    public function grandTotal(): float
    {
        return (float) $this->items->sum(fn (DirectPurchaseItem $item) => (float) ($item->qty ?? 0) * (float) ($item->price ?? 0));
    }

    /**
     * Kode mata uang dokumen (satu dokumen satu mata uang); kosong -> dasar.
     */
    public function currencyCode(): string
    {
        return $this->items->pluck('currency')->filter()->unique()->first() ?? Currency::baseName();
    }

    /**
     * Subtotal -> PPN (persen dari subtotal) -> Total.
     */
    public function totalsFrom(float $subtotal): array
    {
        $ppn = round($subtotal * (float) $this->ppn_percent / 100, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'ppn' => $ppn,
            'total' => round($subtotal + $ppn, 2),
        ];
    }

    /**
     * Total akhir dokumen setelah PPN.
     */
    public function netTotal(): float
    {
        return $this->totalsFrom($this->grandTotal())['total'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
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
        'supplier_name',
        'supplier_id',
        'supplier_address',
        'supplier_phone',
        'supplier_fax',
        'supplier_attn',
        'po_number',
        'date',
        'terms',
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
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    /**
     * Total nilai PO (IDR, hasil qty x price per baris).
     */
    public function grandTotal(): float
    {
        return (float) $this->items->sum(fn ($item) => (float) ($item->qty ?? 0) * (float) ($item->price ?? 0));
    }

    /**
     * Divisi unik yang terwakili di PO ini (dari goods_request masing-masing
     * item) — untuk menunjukkan bahwa satu PO bisa menggabungkan beberapa
     * Permintaan Barang lintas divisi.
     */
    public function divisionNames(): array
    {
        return $this->items
            ->pluck('goodsRequest.division.division_name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Item dikelompokkan per "project" untuk dicetak di dokumen PO (label
     * per grup, seperti pada contoh dokumen) — labelnya diambil dari nama
     * opportunity milik Permintaan Barang asal item tersebut. Item tanpa
     * Permintaan Barang (baris manual) masuk grup "Stock". Urutan grup
     * mengikuti urutan kemunculan pertama item terkait (bukan alfabetis).
     */
    public function projectGroups(): array
    {
        $groups = [];

        foreach ($this->items as $item) {
            $label = $item->goodsRequest?->opportunity?->opportunity_name ?: 'Stock';

            if (! isset($groups[$label])) {
                $groups[$label] = [];
            }

            $groups[$label][] = $item;
        }

        return collect($groups)
            ->map(fn ($items, $label) => ['label' => $label, 'items' => collect($items)])
            ->values()
            ->all();
    }

    /**
     * Daftar nama project unik (dipakai pada blok "PROJECT :" di dokumen PO).
     */
    public function projectLabels(): array
    {
        return collect($this->projectGroups())->pluck('label')->all();
    }

    /**
     * Kode mata uang yang dipakai untuk mencetak harga satuan/subtotal per
     * baris pada dokumen PO — hanya jika SEMUA item memakai mata uang yang
     * sama (null = campuran, dokumen jatuh ke total dalam Rupiah/`price`
     * lewat printTotal() supaya tidak menjumlahkan mata uang berbeda).
     */
    public function printCurrency(): ?string
    {
        $currencies = $this->items->pluck('currency')->filter()->unique()->values();

        if ($currencies->count() === 0) {
            return Currency::baseName();
        }

        if ($currencies->count() === 1) {
            return $currencies->first();
        }

        return null;
    }

    /**
     * Total untuk dicetak: dalam mata uang asli bila seragam (jumlah
     * qty x price_currency), atau dalam Rupiah (grandTotal()) bila item
     * memakai mata uang campuran.
     */
    public function printTotal(): float
    {
        if ($this->printCurrency() === null) {
            return $this->grandTotal();
        }

        return (float) $this->items->sum(fn ($item) => (float) ($item->qty ?? 0) * (float) ($item->price_currency ?? 0));
    }
}

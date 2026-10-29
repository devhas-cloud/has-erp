<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

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
        'po_type',
        'date',
        'terms',
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
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'discount_percent' => 'float',
            'ppn_percent' => 'float',
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
     * Label grup "project" untuk item: nama project Permintaan Barang asal,
     * cadangan nama opportunity (permintaan lama tanpa project), lalu "Stock"
     * untuk baris manual.
     */
    public static function projectLabelFor(PurchaseOrderItem $item): string
    {
        $goodsRequest = $item->goodsRequest;

        return $goodsRequest?->project?->project_name
            ?: ($goodsRequest?->opportunity?->opportunity_name ?: 'Stock');
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
     * Total nilai item PO dalam mata uang PO (qty x harga satuan), tanpa
     * konversi kurs. PO lama yang belum punya harga asli jatuh ke selling_price.
     */
    public function grandTotal(): float
    {
        return (float) $this->items->sum(fn ($item) => (float) ($item->qty ?? 0)
            * (float) ($item->selling_price_currency ?? $item->selling_price ?? 0));
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
            ->pluck('goodsRequest.division.division_name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Jumlah item sesungguhnya (baris parent/kategori tidak dihitung).
     */
    public function itemCount(): int
    {
        return $this->items->reject(fn (PurchaseOrderItem $item) => $item->isHeader())->count();
    }

    /**
     * Item dikelompokkan per "project" untuk dicetak di dokumen PO (label
     * per grup, seperti pada contoh dokumen) — labelnya diambil dari nama
     * project Permintaan Barang asal item tersebut (projectLabelFor()). Item tanpa
     * Permintaan Barang (baris manual) masuk grup "Stock". Urutan grup
     * mengikuti urutan kemunculan pertama item terkait (bukan alfabetis).
     */
    public function projectGroups(): array
    {
        $groups = [];

        foreach ($this->items as $item) {
            if ($item->isHeader()) {
                continue;
            }

            $label = self::projectLabelFor($item);

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
     * Group dari hirarki parent yang tersimpan (baris kategori sebagai header).
     * Dipakai form/show/PDF bila PO memakai grup kategori; PO lama (flat, tanpa
     * baris parent) jatuh ke projectGroups() sebagai fallback. Item standalone
     * tanpa parent dikelompokkan per project (sisa "Stock").
     */
    public function hierarchyGroups(): array
    {
        $items = $this->items;

        if ($items->doesntContain(fn (PurchaseOrderItem $item) => $item->isHeader())) {
            return $this->projectGroups();
        }

        $headers = $items->whereNotNull('category')
            ->map(fn (PurchaseOrderItem $h) => [
                'id' => $h->id,
                'label' => $h->category,
                'items' => collect(),
            ])
            ->keyBy('id');

        $leftover = collect();
        foreach ($items as $item) {
            if ($item->isHeader()) {
                continue;
            }

            if ($item->parent_id && isset($headers[$item->parent_id])) {
                $headers[$item->parent_id]['items']->push($item);
            } else {
                $leftover->push($item);
            }
        }

        $groups = $headers
            ->filter(fn ($group) => $group['items']->isNotEmpty())
            ->values()
            ->all();

        $byProject = [];
        foreach ($leftover as $item) {
            $label = self::projectLabelFor($item);
            $byProject[$label][] = $item;
        }

        foreach ($byProject as $label => $projectItems) {
            $groups[] = ['label' => $label, 'items' => collect($projectItems)];
        }

        return $groups;
    }

    /**
     * Daftar label grup unik (dipakai pada blok "PROJECT :" di dokumen PO).
     */
    public function projectLabels(): array
    {
        return collect($this->hierarchyGroups())->pluck('label')->all();
    }

    /**
     * Kode mata uang yang dipakai untuk mencetak harga satuan/subtotal per
     * baris pada dokumen PO — hanya jika SEMUA item memakai mata uang yang
     * sama (null = campuran, dokumen jatuh ke total dalam Rupiah/`price`
     * lewat printTotal() supaya tidak menjumlahkan mata uang berbeda).
     */
    public function printCurrency(): ?string
    {
        // Baris group (kategori) diabaikan: PO lama sempat menyimpan mata uang dasar di sana.
        $currencies = $this->items->reject(fn (PurchaseOrderItem $item) => $item->isHeader())
            ->pluck('currency')->filter()->unique()->values();

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
     * qty x selling_price_currency), atau dalam Rupiah (grandTotal()) bila item
     * memakai mata uang campuran.
     */
    public function printTotal(): float
    {
        if ($this->printCurrency() === null) {
            return $this->grandTotal();
        }

        return (float) $this->items->sum(fn ($item) => (float) ($item->qty ?? 0) * (float) ($item->selling_price_currency ?? 0));
    }
}

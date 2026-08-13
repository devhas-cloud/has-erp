<?php

namespace App\Models;

use App\Traits\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use Notifiable;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_WAITING_APPROVAL = 'waiting_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_WAITING_APPROVAL => 'Waiting Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    /**
     * Tarif PPN (11%) dihitung dari subtotal — mengikuti contoh dokumen
     * QUOTATION PT. HAS ENVIRONMENTAL (PPN = 11% x subtotal).
     */
    public const PPN_RATE = 0.11;

    /**
     * DPP diturunkan dari PPN dengan asumsi PPN dihitung atas DPP dengan
     * tarif 12%, sehingga DPP = subtotal x 11/12 (sesuai contoh dokumen).
     */
    public const DPP_TAX_BASE_RATE = 0.12;

    protected $table = 'quotations';

    protected $fillable = [
        'quotation_number',
        'quote_configuration_id',
        'opportunity_id',
        'task_id',
        'group_id',
        'version',
        'parent_id',
        'is_current',
        'unlocked_by',
        'unlocked_at',
        'date',
        'currency',
        'your_ref',
        'no_of_pages',
        'to_name',
        'address',
        'attn_name',
        'attn_phone',
        'attn_email',
        'from_name',
        'contact_phone',
        'parameter_note',
        'notes',
        'terms',
        'subtotal',
        'dpp',
        'ppn',
        'grand_total',
        'discount_percent',
        'discount_amount',
        'ppn_percent',
        'ppn_amount',
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
            'no_of_pages' => 'integer',
            'is_current' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'subtotal' => 'float',
            'dpp' => 'float',
            'ppn' => 'float',
            'grand_total' => 'float',
            'discount_percent' => 'float',
            'discount_amount' => 'float',
            'ppn_percent' => 'float',
            'ppn_amount' => 'float',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function configItems(): HasMany
    {
        return $this->hasMany(QuotationConfigItem::class)->orderBy('sort_order');
    }

    public function configurations(): BelongsToMany
    {
        return $this->belongsToMany(QuoteConfiguration::class, 'quotation_quote_configurations')
            ->withTimestamps();
    }

    public function quoteConfiguration(): BelongsTo
    {
        return $this->belongsTo(QuoteConfiguration::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_checked_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function revisionChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function groupVersions(): HasMany
    {
        return $this->hasMany(self::class, 'group_id', 'group_id')->orderBy('version');
    }

    /**
     * Terkunci = sudah Approved dan belum dibuka kuncinya.
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_APPROVED && ! $this->unlocked_at;
    }

    public function nextVersion(): int
    {
        return ((int) $this->groupVersions()->max('version')) + 1;
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
            self::STATUS_ARCHIVED => '<span class="status-badge" style="background:#e2e8f0;color:#475569;">Archived</span>',
            default => '<span class="status-badge">'.ucfirst($this->status).'</span>',
        };
    }

    /**
     * Item hierarki (parent-child) diratakan untuk ditampilkan berurutan.
     * Penomoran (item_no) ditentukan manual oleh user, di sini hanya
     * dihitung kedalaman (depth) untuk indentasi tampilan.
     */
    public function flattenTree(): array
    {
        $all = $this->items->keyBy('id');
        $children = $all->groupBy(fn ($item) => $item->parent_id ?: '_root');

        $walk = function ($parentId, int $depth, &$rows) use (&$walk, $children) {
            foreach ($children[$parentId] ?? [] as $item) {
                $rows[] = ['item' => $item, 'depth' => $depth];
                $walk($item->id, $depth + 1, $rows);
            }
        };

        $rows = [];
        $walk('_root', 0, $rows);

        return $rows;
    }

    /**
     * Sanitasi deskripsi: hapus script/style beserta isinya, normalisasi
     * block element (hasil Enter di contenteditable) menjadi <br>, lalu
     * sisakan hanya tag aman (<b><strong><i><em><u><br>).
     */
    public static function sanitizeDescription(?string $text): string
    {
        $text = (string) $text;

        $text = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $text);
        $text = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $text);

        // Block element dari contenteditable: pembuka menjadi <br>, penutup dihapus.
        $text = preg_replace('#<(div|p|section)\b[^>]*>#i', '<br>', $text);
        $text = preg_replace('#</(div|p|section)\s*>#i', '', $text);
        $text = preg_replace('#<li\b[^>]*>#i', '<br>', $text);
        $text = preg_replace('#</li\s*>#i', '', $text);

        // Rapikan <br> ganda dan di ujung teks.
        $text = preg_replace('#(<br\s*/?>\s*){2,}#i', '<br>', $text);
        $text = trim(preg_replace('#^\s*(<br\s*/?>)*\s*#i', '', $text));
        $text = trim(preg_replace('#\s*(<br\s*/?>)*\s*$#i', '', $text));

        return strip_tags($text, '<b><strong><i><em><u><br>');
    }

    /**
     * Render deskripsi item secara aman (whitelist tag HTML ringan untuk
     * mendukung bold/italic/underline dari contenteditable di form).
     * Baris baru literal selalu dikonversi ke <br> (tanpa menyisakan \n)
     * agar enter tampil baik di halaman show/PDF dan tidak berlipat di
     * contenteditable yang memakai white-space: pre-wrap.
     */
    public static function renderDescription(?string $text): string
    {
        $text = self::sanitizeDescription($text);

        if (trim($text) === '') {
            return '';
        }

        return str_replace(["\r\n", "\r", "\n"], '<br>', $text);
    }

    /**
     * Hitung subtotal / diskon / netto (DPP) / PPN / grand total.
     *
     * Aturan:
     *   - subtotal = jumlah(qty x price)
     *   - diskon   = jika discount_percent diisi -> subtotal x persen/100;
     *                jika tidak, pakai discount_amount (manual).
     *   - netto    = subtotal - diskon  (sekaligus menjadi DPP)
     *   - ppn      = jika ppn_percent diisi -> netto x persen/100;
     *                jika tidak, pakai ppn_amount (manual).
     *   - grand total = netto + ppn
     */
    public static function calculateTotals(
        array $items,
        ?float $discountPercent = null,
        ?float $discountAmount = null,
        ?float $ppnPercent = null,
        ?float $ppnAmount = null
    ): array {
        $subtotal = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $subtotal += $qty * $price;
        }

        $subtotal = round($subtotal, 2);

        $discount = $discountPercent
            ? round($subtotal * $discountPercent / 100, 2)
            : round((float) ($discountAmount ?? 0), 2);

        $netto = round($subtotal - $discount, 2);

        $ppn = $ppnPercent
            ? round($netto * $ppnPercent / 100, 2)
            : round((float) ($ppnAmount ?? 0), 2);

        $grandTotal = round($netto + $ppn, 2);

        return [
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discount,
            'dpp' => $netto,
            'ppn_percent' => $ppnPercent,
            'ppn' => $ppn,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Format angka ala dokumen quotation: 123100000 -> "123,100,000".
     */
    public static function formatMoney($value): string
    {
        return number_format((float) $value, 0, '.', ',');
    }

    /**
     * Inisial dari nama (max 2 kata): "Zuri Muriani" -> "ZM".
     */
    public static function initials(?string $name): string
    {
        if (! $name) {
            return 'XX';
        }

        $words = array_values(array_filter(preg_split('/\s+/', trim($name))));

        if (count($words) === 0) {
            return 'XX';
        }

        $initials = strtoupper(mb_substr($words[0], 0, 1));

        if (count($words) > 1) {
            $initials .= strtoupper(mb_substr(end($words), 0, 1));
        }

        return $initials;
    }

    /**
     * Bulan Romawi: 1 -> I, 2 -> II, ..., 12 -> XII.
     */
    public static function romanMonth(int $month): string
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        return $map[$month] ?? 'I';
    }

    /**
     * Generate nomor quotation mengikuti format contoh:
     * "087/HAS/QT-ZM/II/2026" = {seq:03d}/HAS/QT-{inisial}/{bulan romawi}/{tahun}.
     */
    public function generateQuotationNumber(): string
    {
        $date = $this->date?->toDateString() ?: now()->toDateString();
        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);

        // Ambil seq maksimum yang sudah terpakai di tahun berjalan.
        // (Revisi boleh bernomor sama dengan sumber, jadi max+1 memastikan
        // pembuatan baru tetap unik dan tahan penghapusan baris.)
        $numbers = Quotation::whereYear('date', $year)
            ->whereNotNull('quotation_number')
            ->pluck('quotation_number');

        $maxSeq = 0;
        foreach ($numbers as $num) {
            if (preg_match('#^(\d{3})/#', (string) $num, $m)) {
                $maxSeq = max($maxSeq, (int) $m[1]);
            }
        }

        $seq = $maxSeq + 1;

        return sprintf('%03d', $seq)
            .'/HAS/QT-'.self::initials($this->from_name)
            .'/'.self::romanMonth($month)
            .'/'.$year;
    }
}

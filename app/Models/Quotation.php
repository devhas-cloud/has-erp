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

    public const STATUS_ISSUED = 'issued';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ISSUED => 'Issued',
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
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'no_of_pages' => 'integer',
            'subtotal' => 'float',
            'dpp' => 'float',
            'ppn' => 'float',
            'grand_total' => 'float',
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

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusBadgeHtml(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '<span class="status-badge" style="background:var(--info-soft);color:#1e40af;">Draft</span>',
            self::STATUS_ISSUED => '<span class="status-badge status-active">Issued</span>',
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
     * Hitung subtotal / DPP / PPN / grand total dari daftar item
     * (qty x price), mengikuti pola perhitungan pada dokumen contoh:
     *   - subtotal   = jumlah(qty x price)
     *   - ppn        = 11% x subtotal
     *   - dpp        = ppn / 12%  (setara subtotal x 11/12)
     *   - grand total = subtotal + ppn
     */
    public static function calculateTotals(array $items): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $subtotal += $qty * $price;
        }

        $subtotal = round($subtotal, 2);
        $ppn = round($subtotal * self::PPN_RATE, 2);
        $dpp = round($ppn / self::DPP_TAX_BASE_RATE, 2);
        $grandTotal = round($subtotal + $ppn, 2);

        return [
            'subtotal' => $subtotal,
            'dpp' => $dpp,
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

        $query = Quotation::whereYear('date', $year);

        // Jangan hitung dirinya sendiri jika sudah tersimpan (store).
        if ($this->id) {
            $query->where('id', '!=', $this->id);
        }

        $seq = $query->count() + 1;

        return sprintf('%03d', $seq)
            .'/HAS/QT-'.self::initials($this->from_name)
            .'/'.self::romanMonth($month)
            .'/'.$year;
    }
}

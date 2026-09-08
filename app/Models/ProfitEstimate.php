<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Estimasi Perhitungan Pendapatan (PL) satu quotation.
 *
 * Rumus (mengikuti lembar "Estimasi Perhitungan Pendapatan"):
 *   Sub Total I         = Nilai Awal - PPN
 *   Sub Total II        = Sub Total I - Discount
 *   Referen             = Sub Total II x referral_percent
 *   Nilai Real Project  = Sub Total II - Referen
 *   Rp per baris biaya  = amount x kurs (snapshot) ; IDR = amount
 *   HPP per vendor      = Σ amount item per (vendor, mata uang); boleh dikoreksi manual
 *   Baris item (product) menyimpan amount tetapi TIDAK dihitung sebagai biaya,
 *   karena biayanya sudah diwakili baris HPP.
 *   Investment          = Nilai Real Project x investment_percent
 *   Total Biaya         = Σ Rp (HPP + operasional) + Investment
 *   Estimasi Profit     = Nilai Real Project - Total Biaya
 *   Fee Marketing / PM  = Nilai Real Project x persen masing-masing
 *   Real Profit         = Estimasi Profit - Fee Marketing - Fee PM
 *   Prosentase          = nilai / Nilai Real Project x 100
 */
class ProfitEstimate extends Model
{
    public const SECTION_PRODUCT = 'product';

    public const SECTION_HPP = 'hpp';

    public const SECTION_COST_SPENT = 'cost_spent';

    public const SECTION_COST_PLANNED = 'cost_planned';

    public const SECTIONS = [
        self::SECTION_PRODUCT,
        self::SECTION_HPP,
        self::SECTION_COST_SPENT,
        self::SECTION_COST_PLANNED,
    ];

    public const BASE_CURRENCY = 'IDR';

    /** Persentase bawaan. Referen bersifat per-project sehingga default 0. */
    public const DEFAULT_REFERRAL_PERCENT = 0;

    public const DEFAULT_INVESTMENT_PERCENT = 15;

    public const DEFAULT_MARKETING_FEE_PERCENT = 1;

    public const DEFAULT_PM_FEE_PERCENT = 0.5;

    public const DEFAULT_COST_SPENT_LABELS = [
        'Biaya kirim barang masuk',
        'Biaya kirim barang keluar',
        'Biaya operational custom duty tax',
    ];

    public const DEFAULT_COST_PLANNED_LABELS = [
        'Survei, Installation, Komisioning, Kalibrasi',
        'Maintenance 2x visit',
    ];

    protected $table = 'profit_estimates';

    protected $fillable = [
        'quotation_id',
        'task_id',
        'opportunity_id',
        'date',
        'project_name',
        'rates',
        'nilai_awal',
        'ppn_amount',
        'discount_amount',
        'referral_percent',
        'investment_percent',
        'marketing_fee_percent',
        'pm_fee_percent',
        'subtotal_1',
        'subtotal_2',
        'referral_amount',
        'real_project_value',
        'investment_amount',
        'total_cost',
        'estimated_profit',
        'profit_percent',
        'marketing_fee_amount',
        'pm_fee_amount',
        'real_profit',
        'real_profit_percent',
        'notes',
        'sales_person_name',
        'finance_name',
        'accounting_name',
        'is_outdated',
        'outdated_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rates' => 'array',
            'is_outdated' => 'boolean',
            'outdated_at' => 'datetime',
            'nilai_awal' => 'float',
            'ppn_amount' => 'float',
            'discount_amount' => 'float',
            'referral_percent' => 'float',
            'investment_percent' => 'float',
            'marketing_fee_percent' => 'float',
            'pm_fee_percent' => 'float',
            'subtotal_1' => 'float',
            'subtotal_2' => 'float',
            'referral_amount' => 'float',
            'real_project_value' => 'float',
            'investment_amount' => 'float',
            'total_cost' => 'float',
            'estimated_profit' => 'float',
            'profit_percent' => 'float',
            'marketing_fee_amount' => 'float',
            'pm_fee_amount' => 'float',
            'real_profit' => 'float',
            'real_profit_percent' => 'float',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProfitEstimateLine::class)->orderBy('sort_order');
    }

    public function linesOf(string $section): Collection
    {
        return $this->lines->where('section', $section)->values();
    }

    /**
     * Daftar vendor unik dari baris produk + HPP (untuk kotak "Vendor" di PDF).
     */
    public function vendorNames(): array
    {
        return $this->lines
            ->whereIn('section', [self::SECTION_PRODUCT, self::SECTION_HPP])
            ->pluck('vendor')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Mata uang asing yang dipakai pada baris biaya, urut mengikuti urutan kurs.
     */
    public function foreignCurrenciesUsed(): array
    {
        $used = $this->lines
            ->where('section', '!=', self::SECTION_PRODUCT)
            ->pluck('currency')
            ->map(fn ($c) => strtoupper((string) $c))
            ->filter(fn ($c) => $c !== '' && $c !== self::BASE_CURRENCY)
            ->unique()
            ->all();

        $ordered = [];
        foreach (array_keys($this->rates ?? []) as $code) {
            if (in_array($code, $used, true)) {
                $ordered[] = $code;
            }
        }
        foreach ($used as $code) {
            if (! in_array($code, $ordered, true)) {
                $ordered[] = $code;
            }
        }

        return $ordered;
    }

    /**
     * Konversi nominal ke IDR memakai kurs snapshot. IDR / tanpa currency = apa adanya.
     */
    public static function toIdr(float $amount, ?string $currency, array $rates): float
    {
        $code = strtoupper(trim((string) $currency));

        if ($code === '' || $code === self::BASE_CURRENCY) {
            return round($amount, 2);
        }

        return round($amount * (float) ($rates[$code] ?? 0), 2);
    }

    /**
     * Hitung seluruh nilai turunan. Murni (tanpa DB) supaya mudah diuji.
     *
     * @param  array  $header  nilai_awal, ppn_amount, discount_amount, referral_percent,
     *                         investment_percent, marketing_fee_percent, pm_fee_percent
     * @param  array  $lines  baris [section, label, qty, unit, vendor, currency, amount]
     * @param  array  $rates  kurs snapshot {USD: 17500, ...}
     * @return array{header: array, lines: array}
     */
    public static function calculate(array $header, array $lines, array $rates): array
    {
        $nilaiAwal = round((float) ($header['nilai_awal'] ?? 0), 2);
        $ppn = round((float) ($header['ppn_amount'] ?? 0), 2);
        $discount = round((float) ($header['discount_amount'] ?? 0), 2);
        $referralPct = (float) ($header['referral_percent'] ?? 0);
        $investmentPct = (float) ($header['investment_percent'] ?? 0);
        $marketingPct = (float) ($header['marketing_fee_percent'] ?? 0);
        $pmPct = (float) ($header['pm_fee_percent'] ?? 0);

        $subtotal1 = round($nilaiAwal - $ppn, 2);
        $subtotal2 = round($subtotal1 - $discount, 2);
        $referral = round($subtotal2 * $referralPct / 100, 2);
        $real = round($subtotal2 - $referral, 2);

        $linesOut = [];
        $linesTotal = 0.0;

        foreach (array_values($lines) as $i => $line) {
            $section = $line['section'] ?? self::SECTION_PRODUCT;
            $currency = strtoupper(trim((string) ($line['currency'] ?? '')));
            $amount = (float) ($line['amount'] ?? 0);

            $idr = self::toIdr($amount, $currency ?: self::BASE_CURRENCY, $rates);

            // Baris item tidak dihitung sebagai biaya (sudah diwakili baris HPP).
            if ($section !== self::SECTION_PRODUCT) {
                $linesTotal += $idr;
            }

            $linesOut[] = array_merge($line, [
                'section' => $section,
                'currency' => $currency ?: self::BASE_CURRENCY,
                'amount' => $amount,
                'amount_idr' => $idr,
                'is_manual' => $section === self::SECTION_HPP && ! empty($line['is_manual']),
                'sort_order' => $i + 1,
            ]);
        }

        $investment = round($real * $investmentPct / 100, 2);
        $totalCost = round($linesTotal + $investment, 2);
        $profit = round($real - $totalCost, 2);
        $marketing = round($real * $marketingPct / 100, 2);
        $pm = round($real * $pmPct / 100, 2);
        $realProfit = round($profit - $marketing - $pm, 2);

        $pct = fn (float $v) => $real > 0 ? round($v / $real * 100, 2) : 0.0;

        return [
            'header' => [
                'nilai_awal' => $nilaiAwal,
                'ppn_amount' => $ppn,
                'discount_amount' => $discount,
                'referral_percent' => $referralPct,
                'investment_percent' => $investmentPct,
                'marketing_fee_percent' => $marketingPct,
                'pm_fee_percent' => $pmPct,
                'subtotal_1' => $subtotal1,
                'subtotal_2' => $subtotal2,
                'referral_amount' => $referral,
                'real_project_value' => $real,
                'investment_amount' => $investment,
                'total_cost' => $totalCost,
                'estimated_profit' => $profit,
                'profit_percent' => $pct($profit),
                'marketing_fee_amount' => $marketing,
                'pm_fee_amount' => $pm,
                'real_profit' => $realProfit,
                'real_profit_percent' => $pct($realProfit),
            ],
            'lines' => $linesOut,
        ];
    }

    /**
     * Bentuk ulang baris HPP dari baris item: dikelompokkan per (vendor, mata
     * uang), nominal = Σ amount item. Baris HPP masukan yang bertanda
     * is_manual dan cocok kelompoknya dipakai sebagai override nominal;
     * baris HPP lain dibuang. Urutan hasil: item, HPP, baris biaya lainnya.
     */
    public static function deriveHppLines(array $lines): array
    {
        $products = [];
        $others = [];
        $overrides = [];

        foreach ($lines as $line) {
            $section = $line['section'] ?? '';

            if ($section === self::SECTION_PRODUCT) {
                $products[] = $line;
            } elseif ($section === self::SECTION_HPP) {
                if (! empty($line['is_manual'])) {
                    $overrides[self::hppKey($line)] = round((float) ($line['amount'] ?? 0), 4);
                }
            } else {
                $others[] = $line;
            }
        }

        $groups = [];
        foreach ($products as $p) {
            $key = self::hppKey($p);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'section' => self::SECTION_HPP,
                    'label' => null,
                    'qty' => null,
                    'unit' => null,
                    'vendor' => trim((string) ($p['vendor'] ?? '')),
                    'currency' => strtoupper(trim((string) ($p['currency'] ?? ''))) ?: self::BASE_CURRENCY,
                    'amount' => 0.0,
                    'is_manual' => false,
                ];
            }
            $groups[$key]['amount'] += (float) ($p['amount'] ?? 0);
        }

        $hpp = [];
        foreach ($groups as $key => $g) {
            $g['amount'] = round($g['amount'], 4);
            $g['derived_amount'] = $g['amount'];
            if (array_key_exists($key, $overrides)) {
                $g['amount'] = $overrides[$key];
                $g['is_manual'] = true;
            }
            $hpp[] = $g;
        }

        return array_merge($products, $hpp, $others);
    }

    /**
     * Label item (section product) yang belum punya vendor.
     */
    public static function productLabelsWithoutVendor(array $lines): array
    {
        $labels = [];
        foreach ($lines as $line) {
            if (($line['section'] ?? '') === self::SECTION_PRODUCT && trim((string) ($line['vendor'] ?? '')) === '') {
                $labels[] = trim((string) ($line['label'] ?? '')) ?: '(tanpa nama)';
            }
        }

        return $labels;
    }

    private static function hppKey(array $line): string
    {
        $vendor = mb_strtolower(trim((string) ($line['vendor'] ?? '')));
        $currency = strtoupper(trim((string) ($line['currency'] ?? ''))) ?: self::BASE_CURRENCY;

        return $vendor.'|'.$currency;
    }

    /**
     * Tandai PL milik sebuah quotation sebagai outdated (quotation berubah / direvisi).
     */
    public static function markOutdatedForQuotation(int $quotationId): int
    {
        return self::where('quotation_id', $quotationId)
            ->where('is_outdated', false)
            ->update(['is_outdated' => true, 'outdated_at' => now()]);
    }

    /**
     * Format angka ala lembar PL: 1520663100 -> "1,520,663,100.00".
     */
    public static function formatMoney($value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', ',');
    }

    /**
     * Tanggal berbahasa Indonesia: "4 September 2026".
     */
    public static function formatDateId($date): string
    {
        if (! $date) {
            return '—';
        }

        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $date->day.' '.$months[(int) $date->month].' '.$date->year;
    }
}

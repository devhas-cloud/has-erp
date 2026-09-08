<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    protected $fillable = [
        'name',
        'symbol',
        'rate',
        'is_base',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_base' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function isBase(): bool
    {
        return (bool) $this->is_base;
    }

    /**
     * Kode mata uang base (IDR bila belum ada master).
     */
    public static function baseName(): string
    {
        return strtoupper((string) (static::where('is_base', true)->value('name') ?: 'IDR'));
    }

    /**
     * Kurs untuk sebuah kode mata uang (currencies.name). Base, kosong, atau
     * kode yang tidak dikenal -> 1.
     */
    public static function rateOf(?string $name): float
    {
        $code = strtoupper(trim((string) $name));

        if ($code === '') {
            return 1.0;
        }

        $currency = static::whereRaw('UPPER(name) = ?', [$code])->first();

        if (! $currency || $currency->is_base) {
            return 1.0;
        }

        return (float) $currency->rate;
    }

    /**
     * Pilihan mata uang aktif untuk form (base di urutan pertama):
     * [['name' => 'IDR', 'rate' => 1, 'is_base' => true], ...].
     */
    public static function formOptions(): array
    {
        $options = static::active()
            ->orderByDesc('is_base')
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => [
                'name' => strtoupper($c->name),
                'rate' => $c->is_base ? 1 : (float) $c->rate,
                'is_base' => (bool) $c->is_base,
            ])
            ->values()
            ->all();

        return $options ?: [['name' => self::baseName(), 'rate' => 1, 'is_base' => true]];
    }

    public function getSymbolOrDefaultAttribute(): string
    {
        return $this->symbol ?: $this->name;
    }
}

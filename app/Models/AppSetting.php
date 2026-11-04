<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Pengaturan aplikasi key -> nilai (JSON). get() menggabungkan nilai tersimpan
 * dengan default sehingga key baru tidak perlu migrasi data.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, array $defaults = []): array
    {
        return array_merge($defaults, (array) (static::where('key', $key)->first()?->value ?? []));
    }

    public static function put(string $key, array $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => Auth::id()]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Masa garansi project: mulai dihitung (start_date) sampai berakhir (end_date).
 * Status dihitung dari tanggal hari ini, tidak disimpan.
 */
class ProjectWarranty extends Model
{
    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    /** Masa garansi dianggap "segera berakhir" bila sisa hari <= nilai ini. */
    public const EXPIRING_SOON_DAYS = 30;

    protected $fillable = ['project_id', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): string
    {
        $today = now()->startOfDay();

        if ($today->lt($this->start_date)) {
            return self::STATUS_UPCOMING;
        }

        return $today->gt($this->end_date) ? self::STATUS_EXPIRED : self::STATUS_ACTIVE;
    }

    /** Sisa hari sampai end_date (negatif bila sudah lewat). */
    public function daysLeft(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    /** Lama garansi dalam bulan penuh, mis. 12. */
    public function durationMonths(): int
    {
        return (int) $this->start_date->diffInMonths($this->end_date);
    }

    /** Label & gaya badge untuk tampilan. */
    public function statusMeta(): array
    {
        return match ($this->status()) {
            self::STATUS_UPCOMING => ['label' => 'Belum Mulai', 'class' => 'warn'],
            self::STATUS_EXPIRED => ['label' => 'Berakhir', 'class' => 'danger'],
            default => $this->daysLeft() <= self::EXPIRING_SOON_DAYS
                ? ['label' => 'Segera Berakhir', 'class' => 'warn']
                : ['label' => 'Aktif', 'class' => 'ok'],
        };
    }
}

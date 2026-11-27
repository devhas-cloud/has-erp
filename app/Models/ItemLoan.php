<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Peminjaman barang (Item Loan). Dicatat petugas inventory — tanpa pengajuan /
 * approval peminjam. Hanya unit ber-SN; peminjam user internal atau pihak luar.
 *
 * Status:
 *   on_loan   : dibuat = langsung dipinjam; unit ditahan (stok tidak berkurang)
 *   returned  : semua unit selesai (kembali baik / rusak / hilang)
 *   cancelled : salah input, dibatalkan sebelum ada pengembalian
 * Overdue bukan status tersimpan: on_loan yang lewat due_date.
 */
class ItemLoan extends Model
{
    public const STATUS_ON_LOAN = 'on_loan';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_ON_LOAN => 'On Loan',
        self::STATUS_RETURNED => 'Returned',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const BORROWER_INTERNAL = 'internal';

    public const BORROWER_EXTERNAL = 'external';

    protected $fillable = [
        'loan_number',
        'borrower_type',
        'borrower_user_id',
        'borrower_name',
        'borrower_company',
        'borrower_phone',
        'warehouse_id',
        'project_id',
        'purpose',
        'loan_date',
        'due_date',
        'status',
        'note',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'closed_at',
        'last_reminded_on',
    ];

    protected function casts(): array
    {
        return [
            'loan_date' => 'date',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_reminded_on' => 'date',
        ];
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('item_loans', 'loan_number', $date);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemLoanItem::class)->orderBy('id');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(ItemLoanExtension::class)->orderBy('id');
    }

    public function borrowerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_user_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isOnLoan(): bool
    {
        return $this->status === self::STATUS_ON_LOAN;
    }

    public function isExternal(): bool
    {
        return $this->borrower_type === self::BORROWER_EXTERNAL;
    }

    /** Nama peminjam untuk tampilan (user internal atau nama pihak luar). */
    public function borrowerLabel(): string
    {
        return $this->isExternal()
            ? (string) ($this->borrower_name ?: '—')
            : (string) ($this->borrowerUser?->display_name ?? $this->borrower_name ?? '—');
    }

    /** Nomor WhatsApp peminjam: user internal dari profil, pihak luar dari isian. */
    public function borrowerPhone(): ?string
    {
        return $this->isExternal() ? $this->borrower_phone : ($this->borrowerUser?->phone_number ?: $this->borrower_phone);
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->isOnLoan() && $this->due_date->lt(($today ?? now())->copy()->startOfDay());
    }

    /** Selisih hari ke jatuh tempo: negatif = lewat. */
    public function daysToDue(?Carbon $today = null): int
    {
        return (int) ($today ?? now())->copy()->startOfDay()->diffInDays($this->due_date, false);
    }

    /** Label status tampilan; on_loan yang lewat jatuh tempo = Overdue. */
    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Overdue' : (self::STATUS_LABELS[$this->status] ?? $this->status);
    }

    public function statusBadgeHtml(): string
    {
        [$bg, $fg] = match (true) {
            $this->isOverdue() => ['#fee2e2', '#b42318'],
            $this->status === self::STATUS_ON_LOAN => ['#fef3c7', '#92400e'],
            $this->status === self::STATUS_RETURNED => ['#dcfce7', '#065f46'],
            default => ['#f3f4f6', '#4b5563'],
        };

        return '<span class="status-badge" style="background:'.$bg.';color:'.$fg.';">'.e($this->statusLabel()).'</span>';
    }
}

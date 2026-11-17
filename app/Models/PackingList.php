<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Packing List — barang milik project yang dikemas gudang per koli.
 *
 * Draft (boleh diubah / dihapus) -> Siap Kirim (terkunci; isi koli dipesan
 * sehingga tidak bisa diambil modul lain) -> Terkirim (saat Delivery Order
 * yang memuatnya diposting; stok keluar per isi koli). Siap Kirim boleh
 * dibuka kembali ke Draft selama belum dimuat DO.
 */
class PackingList extends Model
{
    public const MODULE_CODE = 'MOD_PACKING_LIST';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_READY => 'Siap Kirim',
        self::STATUS_SHIPPED => 'Terkirim',
    ];

    protected $fillable = [
        'packing_number',
        'project_id',
        'warehouse_id',
        'packing_date',
        'status',
        'delivery_order_id',
        'recipient_name',
        'recipient_address',
        'recipient_phone',
        'recipient_attn',
        'customer_ref',
        'customer_ref_date',
        'marking',
        'signer_name',
        'signer_position',
        'note',
        'created_by',
        'ready_by',
        'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'packing_date' => 'date',
            'customer_ref_date' => 'date',
            'ready_at' => 'datetime',
        ];
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('packing_lists', 'packing_number', $date);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    /** Koli, urut nomor koli. */
    public function boxes(): HasMany
    {
        return $this->hasMany(PackingListBox::class)->orderBy('sort_order')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackingListItem::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function readyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function totalWeight(): float
    {
        return round((float) $this->boxes->sum('weight_kg'), 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeHtml(): string
    {
        [$bg, $fg] = match ($this->status) {
            self::STATUS_READY => ['#fef3c7', '#92400e'],
            self::STATUS_SHIPPED => ['var(--accent-soft)', 'var(--accent)'],
            default => ['#e5e7eb', '#374151'],
        };

        return '<span class="status-badge" style="background:'.$bg.';color:'.$fg.';">'.e($this->status_label).'</span>';
    }
}

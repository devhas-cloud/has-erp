<?php

namespace App\Models;

use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Transfer Stok antar gudang dan/atau antar kepemilikan (project kosong = stok
 * bebas). Bebas -> project = alokasi; project -> bebas = lepas alokasi.
 * Langsung terposting saat disimpan.
 */
class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_number',
        'transfer_date',
        'from_warehouse_id',
        'from_project_id',
        'to_warehouse_id',
        'to_project_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class)->orderBy('sort_order');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function fromProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'from_project_id');
    }

    public function toProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'to_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(?string $date = null): string
    {
        return NumberSequence::make('stock_transfers', 'transfer_number', $date);
    }

    /**
     * Jenis transfer dari sisi kepemilikan.
     */
    public function kindLabel(): string
    {
        return match (true) {
            ! $this->from_project_id && $this->to_project_id => 'Alokasi ke Project',
            $this->from_project_id && ! $this->to_project_id => 'Lepas Alokasi',
            $this->from_project_id !== $this->to_project_id => 'Pindah Project',
            default => 'Pindah Gudang',
        };
    }

    public static function ownershipLabel(?Project $project): string
    {
        return $project ? $project->project_number.' — '.$project->project_name : 'Stok bebas';
    }
}

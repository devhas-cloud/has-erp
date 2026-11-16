<?php

namespace App\Support;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Daftar baris stok yang bisa dipilih user (Transfer, DO, Retur, Stock Out).
 * Hanya baris terbuka (qty > 0), lewat index pick (produk, gudang, is_open, project).
 *
 * Filter:
 *  - warehouse_id   : gudang (wajib di hampir semua dokumen)
 *  - project        : 'free' (stok bebas), 'any', atau id project
 *  - product_id     : satu produk
 *  - product_ids    : beberapa produk (mis. baris PO yang diretur)
 *  - source_ids     : hanya baris dari item GRN tertentu (retur supplier)
 *  - q              : cari kode/nama produk, SN, batch / No. MFG, catatan
 *  - limit          : default 100
 */
class StockPicker
{
    public static function query(array $filters): Builder
    {
        $project = $filters['project'] ?? 'any';
        $q = trim((string) ($filters['q'] ?? ''));

        return Stock::query()
            ->open()
            ->with(['masterProduct:id,code,name,is_material', 'warehouse:id,code,name', 'project:id,project_number,project_name'])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $b, $id) => $b->where('warehouse_id', (int) $id))
            ->when($project === 'free', fn (Builder $b) => $b->whereNull('project_id'))
            ->when(is_numeric($project), fn (Builder $b) => $b->where('project_id', (int) $project))
            ->when($filters['product_id'] ?? null, fn (Builder $b, $id) => $b->where('master_product_id', (int) $id))
            ->when($filters['product_ids'] ?? null, fn (Builder $b, $ids) => $b->whereIn('master_product_id', (array) $ids))
            ->when(array_key_exists('source_ids', $filters), fn (Builder $b) => $b
                ->where('source_type', Stock::SOURCE_GOODS_RECEIPT_ITEM)
                ->whereIn('source_id', (array) ($filters['source_ids'] ?: [0])))
            ->when($q !== '', fn (Builder $b) => $b->where(function (Builder $w) use ($q) {
                $w->where('serial_number', 'like', "%{$q}%")
                    ->orWhere('batch_number', 'like', "%{$q}%")
                    ->orWhere('note', 'like', "%{$q}%")
                    ->orWhereHas('masterProduct', fn (Builder $p) => $p->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            }))
            ->orderBy('master_product_id')
            ->orderBy('received_date')
            ->orderBy('id');
    }

    /**
     * @return Collection<int, array>
     */
    public static function options(array $filters): Collection
    {
        return self::query($filters)->limit((int) ($filters['limit'] ?? 100))->get()->map(fn (Stock $s) => self::row($s));
    }

    public static function row(Stock $s): array
    {
        return [
            'id' => $s->id,
            'master_product_id' => $s->master_product_id,
            'product_code' => $s->masterProduct?->code,
            'product_name' => $s->masterProduct?->name,
            'serialized' => $s->isSerialized(),
            'serial_number' => $s->serial_number,
            'batch_number' => $s->batch_number,
            'identity' => $s->identity_label,
            'can_leave' => $s->canLeave(),
            'received_date' => $s->received_date?->format('d/m/Y'),
            'expiry_date' => $s->expiry_date?->format('d/m/Y'),
            'warranty_until' => $s->warranty_until?->format('d/m/Y'),
            'qty' => $s->qty,
            'qty_label' => StockCost::qty($s->qty),
            'unit' => $s->unit,
            'note' => $s->note,
            'warehouse_id' => $s->warehouse_id,
            'warehouse' => $s->warehouse ? $s->warehouse->code.' — '.$s->warehouse->name : null,
            'project_id' => $s->project_id,
            'project' => $s->project ? $s->project->project_number.' — '.$s->project->project_name : null,
        ];
    }
}

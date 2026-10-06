<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigurationTemplate extends Model
{
    protected $table = 'configuration_templates';

    protected $fillable = [
        'division_id',
        'name',
        'description',
        'created_by',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ConfigurationTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

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

    public function totalQty(): int
    {
        return (int) $this->items->sum('qty');
    }
}
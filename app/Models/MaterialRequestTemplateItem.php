<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequestTemplateItem extends Model
{
    protected $fillable = [
        'material_request_template_id',
        'master_product_id',
        'qty',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestTemplate::class, 'material_request_template_id');
    }

    public function masterProduct(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class);
    }
}

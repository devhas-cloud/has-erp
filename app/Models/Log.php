<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class Log extends Model
{
    protected $fillable = [
        'module_id',
        'user_id',
        'action',
        'description',
        'loggable_type',
        'loggable_id',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(
        string $action,
        string $description,
        ?string $moduleCode = null,
        ?Model $loggable = null,
        ?User $user = null
    ): self {
        $module = $moduleCode
            ? Module::where('module_code', $moduleCode)->first()
            : null;

        return self::create([
            'module_id' => $module?->id,
            'user_id' => $user?->id ?? Auth::id(),
            'action' => $action,
            'description' => $description,
            'loggable_type' => $loggable ? get_class($loggable) : null,
            'loggable_id' => $loggable?->id,
        ]);
    }
}

<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    use Loggable;

    protected $fillable = [
        'lead_id',
        'stage_id',
        'probability',
        'forecast_id',
        'opportunity_name',
        'loss_reasons_id',
        'quote_ready',
        'division_id',
        'account_companies_id',
        'type',
        'account_contacts_id',
        'source_id',
        'next_step',
        'close_date',
        'end_user_id',
        'budget',
        'authorize',
        'timeline',
        'close_won_date',
        'description',
        'owner_id',
    ];

    protected $casts = [
        'close_won_date' => 'date',
        'close_date' => 'date',
        'quote_ready' => 'boolean',
        'budget' => 'boolean',
        'authorize' => 'boolean',
        'timeline' => 'boolean',
        'probability' => 'integer',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(Forecast::class);
    }

    public function lossReason(): BelongsTo
    {
        return $this->belongsTo(LossReason::class, 'loss_reasons_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function accountCompany(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'account_companies_id');
    }

    public function accountContact(): BelongsTo
    {
        return $this->belongsTo(AccountContact::class, 'account_contacts_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function endUser(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'end_user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'opportunity_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'opportunity_id');
    }
}

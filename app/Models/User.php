<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'phone_number',
        'division_id',
        'role',
        'task_role_id',
        'icon',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function accessControls(): HasMany
    {
        return $this->hasMany(UserAccessControl::class);
    }

    public function ownedAccounts(): HasMany
    {
        return $this->hasMany(AccountCompany::class, 'account_owner_id');
    }

    public function ownedContacts(): HasMany
    {
        return $this->hasMany(AccountContact::class, 'contact_owner_id');
    }

    public function ownedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'lead_owner_id');
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function hierarchyRole(): BelongsTo
    {
        return $this->belongsTo(TaskRole::class, 'task_role_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }
}

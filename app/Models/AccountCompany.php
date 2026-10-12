<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCompany extends Model
{
    protected $appends = ['icon_url'];

    protected $fillable = [
        'account_name',
        'icon',
        'sources_id',
        'types_accounts_companies_id',
        'website',
        'description',
        'segmentation_id',
        'business_entities_id',
        'business_values_id',
        'account_types_id',
        'end_user',
        'parent_account_id',
        'phone',
        'interaction_levels_id',
        'account_owner_id',
        'address_billing_street',
        'address_billing_city',
        'address_billing_province',
        'address_billing_postal_code',
        'address_billing_country',
        'address_shipping_street',
        'address_shipping_city',
        'address_shipping_province',
        'address_shipping_postal_code',
        'address_shipping_country',
        'status',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'sources_id');
    }

    /**
     * URL logo perusahaan. Kolom icon menyimpan path relatif di disk public
     * (hasil upload dari form akun); nilai yang sudah berupa URL dipakai apa adanya.
     */
    protected function iconUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->icon) {
                    return null;
                }

                return preg_match('#^(https?:)?/#i', $this->icon)
                    ? $this->icon
                    : asset('storage/'.$this->icon);
            },
        );
    }

    public function typesAccountsCompany(): BelongsTo
    {
        return $this->belongsTo(TypesAccountsCompany::class, 'types_accounts_companies_id');
    }

    public function segmentation(): BelongsTo
    {
        return $this->belongsTo(Segmentation::class);
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class, 'business_entities_id');
    }

    public function businessValue(): BelongsTo
    {
        return $this->belongsTo(BusinessValue::class, 'business_values_id');
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_types_id');
    }

    public function interactionLevel(): BelongsTo
    {
        return $this->belongsTo(InteractionLevel::class, 'interaction_levels_id');
    }

    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'parent_account_id');
    }

    public function endUserAccount(): BelongsTo
    {
        return $this->belongsTo(AccountCompany::class, 'end_user');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(AccountCompany::class, 'parent_account_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(AccountContact::class, 'account_companies_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'account_companies_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'account_companies_id');
    }
}

<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'full_name',
        'email',
        'password',
        'phone_number',
        'division_id',
        'branch_id',
        'role',
        'task_role_id',
        'icon',
        'salesperson',
        'intern',
    ];

    /**
     * Branch user (kosong = melihat semua branch; hak tetap lewat akses modul).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * User yang terikat branch hanya melihat/memproses dokumen branch-nya.
     */
    public function canAccessBranch(?int $branchId): bool
    {
        return ! $this->branch_id || (int) $this->branch_id === (int) $branchId;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'display_name',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'salesperson' => 'boolean',
            'intern' => 'boolean',
        ];
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->full_name ?: $this->username,
        );
    }

    /**
     * URL foto profil. Kolom icon menyimpan path relatif di disk public
     * (hasil upload dari halaman profil); nilai yang sudah berupa URL dipakai apa adanya.
     */
    protected function avatarUrl(): Attribute
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

    /** Divisi yang melihat SEMUA harga master product serta boleh input/edit harga semua produk. */
    public const PRICE_MANAGER_DIVISIONS = ['Admin'];

    /**
     * Divisi yang hanya mengelola produk milik divisinya sendiri: melihat & mengubah
     * harga beli/currency, mengedit dan menghapus produk divisinya; produk divisi lain
     * hanya bisa dilihat (harga beli tersembunyi). Produk baru/diedit selalu berdivisi user.
     */
    public const PRICE_DIVISION_SCOPED_MANAGERS = ['IMS'];

    /** Divisi yang boleh melihat harga jual master product. */
    public const SELLING_PRICE_VIEWER_DIVISIONS = ['Admin'];

    /**
     * Kolom harga beli master product tampil: Admin (role/divisi) atau divisi IMS.
     * Nilai per produk tetap disaring canViewPurchasePriceOf().
     * Dipakai view (canViewPrice) dan ProductManagementController.
     */
    public function canViewProductPrice(): bool
    {
        return $this->canManageProductPrice() || $this->isDivisionScopedProductManager();
    }

    /**
     * Pengelola harga penuh: role Admin atau divisi Admin — melihat & mengubah harga
     * beli semua produk, urut berdasarkan harga beli.
     */
    public function canManageProductPrice(): bool
    {
        return $this->role === 'Admin' || $this->inDivision(self::PRICE_MANAGER_DIVISIONS);
    }

    /**
     * User berdivisi IMS (bukan Admin): hanya mengelola produk divisinya sendiri.
     */
    public function isDivisionScopedProductManager(): bool
    {
        return ! $this->canManageProductPrice()
            && $this->division_id !== null
            && $this->inDivision(self::PRICE_DIVISION_SCOPED_MANAGERS);
    }

    /**
     * Field harga beli & currency tampil di form produk: Admin atau IMS.
     * Per produk tetap disaring canEditPurchasePriceOf().
     */
    public function canInputProductPrice(): bool
    {
        return $this->canManageProductPrice() || $this->isDivisionScopedProductManager();
    }

    /**
     * Produk milik divisi user (untuk divisi ber-scope seperti IMS).
     */
    public function ownsProductDivision(MasterProduct $product): bool
    {
        return $product->division_id !== null && (int) $product->division_id === (int) $this->division_id;
    }

    /**
     * Edit/hapus produk: IMS hanya produk divisinya; user lain mengikuti hak akses modul.
     */
    public function canManageProduct(MasterProduct $product): bool
    {
        return ! $this->isDivisionScopedProductManager() || $this->ownsProductDivision($product);
    }

    /**
     * Harga beli satu produk terlihat: Admin semua; IMS hanya produk divisinya; lainnya tidak.
     */
    public function canViewPurchasePriceOf(MasterProduct $product): bool
    {
        return $this->canManageProductPrice()
            || ($this->isDivisionScopedProductManager() && $this->ownsProductDivision($product));
    }

    /**
     * Ubah harga beli & currency satu produk: Admin semua; IMS hanya produk divisinya.
     */
    public function canEditPurchasePriceOf(MasterProduct $product): bool
    {
        return $this->canViewPurchasePriceOf($product);
    }

    /**
     * Harga jual master product: hanya role Admin atau user berdivisi Admin.
     * Dipakai view (canViewSellingPrice) dan ProductManagementController.
     */
    public function canViewSellingPrice(): bool
    {
        return $this->role === 'Admin' || $this->inDivision(self::SELLING_PRICE_VIEWER_DIVISIONS);
    }

    /** Cek nama divisi user (tidak peka huruf besar/kecil & spasi tepi). */
    private function inDivision(array $divisions): bool
    {
        $division = strtolower(trim((string) $this->division?->division_name));

        return $division !== '' && in_array($division, array_map('strtolower', $divisions), true);
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

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}

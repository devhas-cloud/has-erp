<?php

namespace App\Support;

use App\Models\Module;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Support\Facades\Auth;

/**
 * Helper perhitungan flag akses modul untuk kebutuhan UI (tombol/kolom pada
 * data endpoint dan halaman show) — BUKAN gerbang akses. Gerbang akses UAC
 * ditangani satu-satunya oleh middleware CheckAccessControl; controller tidak
 * boleh menolak request berdasarkan UAC di sini.
 *
 * Menggantikan metode privat duplikat (hasModuleAccess/isApprover/...) di tiap
 * controller. Perilaku sama dengan implementasi lama:
 *   - Admin selalu dianggap punya semua hak;
 *   - modul tidak ditemukan -> semua flag false;
 *   - canManage() = can_create ATAU can_update (flag tombol Edit di show).
 */
class ModuleAccess
{
    private ?User $user;

    private ?string $moduleCode = null;

    public function __construct(?User $user = null)
    {
        $this->user = $user ?? Auth::user();
    }

    public static function for(?User $user = null): self
    {
        return new self($user);
    }

    public function module(string $moduleCode): self
    {
        $this->moduleCode = $moduleCode;

        return $this;
    }

    public function canRead(): bool
    {
        return $this->has('can_read');
    }

    public function canCreate(): bool
    {
        return $this->has('can_create');
    }

    public function canUpdate(): bool
    {
        return $this->has('can_update');
    }

    public function canDelete(): bool
    {
        return $this->has('can_delete');
    }

    public function canApprove(): bool
    {
        return $this->has('can_approve');
    }

    /**
     * "Bisa mengelola" modul = can_create ATAU can_update (setara
     * hasModuleAccess() lama pada controller GoodsRequest/PurchaseOrder/Quotation).
     */
    public function canManage(): bool
    {
        return $this->canCreate() || $this->canUpdate();
    }

    /**
     * Flag akses modul di luar modul route yang sedang dirender (tombol
     * lintas-modul, mis. "Estimasi PL" pada halaman Quotation). View composer
     * hanya menghitung flag modul route berjalan, jadi kebutuhan lintas-modul
     * lewat helper ini.
     */
    public static function crossModuleCanRead(string $moduleCode): bool
    {
        return (new self)->module($moduleCode)->canRead();
    }

    /**
     * Daftar user_id yang punya can_approve pada modul — target notifikasi
     * approver. Bukan gerbang akses; hanya data untuk notifikasi.
     */
    public static function approverUserIds(string $moduleCode, ?int $exceptUserId = null): array
    {
        $module = Module::where('module_code', $moduleCode)->first();

        if (! $module) {
            return [];
        }

        return UserAccessControl::where('module_id', $module->id)
            ->where('can_approve', true)
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->pluck('user_id')
            ->toArray();
    }

    private function has(string $field): bool
    {
        if (! $this->user) {
            return false;
        }

        if ($this->user->role === 'Admin') {
            return true;
        }

        if (! $this->moduleCode) {
            return false;
        }

        $row = $this->row();

        return (bool) ($row?->{$field} ?? false);
    }

    private function row(): ?UserAccessControl
    {
        if (! $this->moduleCode) {
            return null;
        }

        $module = Module::where('module_code', $this->moduleCode)->first();

        if (! $module) {
            return null;
        }

        return UserAccessControl::where('user_id', $this->user->id)
            ->where('module_id', $module->id)
            ->first();
    }
}

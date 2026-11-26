<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flag "intern" (akun magang) pada user: default false, tersimpan lewat
 * store/update User Management, mengikuti pola boolean salesperson.
 */
class UserManagementInternTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'superadmin',
            'full_name' => 'Super Admin',
            'email' => 'admin@has.com',
            'password' => 'secret',
            'role' => 'Admin',
        ]);
    }

    public function test_intern_defaults_to_false(): void
    {
        $user = User::create([
            'username' => 'staff1', 'full_name' => 'Staff', 'email' => 'staff@has.com',
            'password' => 'secret', 'role' => 'User',
        ]);

        $this->assertFalse($user->fresh()->intern);
    }

    public function test_store_persists_intern_flag(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('user-management.store'), [
            'username' => 'magang1',
            'full_name' => 'Anak Magang',
            'email' => 'magang@has.com',
            'password' => 'secret',
            'role' => 'User',
            'intern' => '1',
        ])->assertRedirect(route('user-management.index'));

        $this->assertTrue((bool) User::where('username', 'magang1')->value('intern'));
    }

    public function test_update_toggles_intern_flag(): void
    {
        $target = User::create([
            'username' => 'staff2', 'full_name' => 'Staff Dua', 'email' => 'staff2@has.com',
            'password' => 'secret', 'role' => 'User',
        ]);

        $this->actingAs($this->admin());

        $this->put(route('user-management.update', $target->id), [
            'username' => 'staff2', 'full_name' => 'Staff Dua', 'email' => 'staff2@has.com',
            'role' => 'User', 'intern' => '1',
        ])->assertRedirect(route('user-management.index'));

        $this->assertTrue($target->fresh()->intern);

        // Kirim tanpa intern (hidden 0 di form) -> kembali false.
        $this->put(route('user-management.update', $target->id), [
            'username' => 'staff2', 'full_name' => 'Staff Dua', 'email' => 'staff2@has.com',
            'role' => 'User', 'intern' => '0',
        ])->assertRedirect(route('user-management.index'));

        $this->assertFalse($target->fresh()->intern);
    }
}

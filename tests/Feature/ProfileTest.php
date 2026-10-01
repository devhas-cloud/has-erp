<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman profil: setiap user yang login (termasuk yang tidak punya akses
 * modul apapun) bisa mengubah data dirinya sendiri — dan hanya data dirinya.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'username' => 'user_'.uniqid(),
            'full_name' => 'Nama Lama',
            'email' => uniqid().'@has.com',
            'password' => 'secret123',
            'role' => 'User',
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_user_without_any_module_access_can_open_profile(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee($user->username);
    }

    public function test_user_can_update_own_profile(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'full_name' => 'Nama Baru',
                'email' => 'baru@has.com',
                'phone_number' => '08123456789',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->full_name);
        $this->assertSame('baru@has.com', $user->email);
        $this->assertSame('08123456789', $user->phone_number);
    }

    public function test_profile_update_cannot_change_role_or_username(): void
    {
        $user = $this->makeUser(['username' => 'tetap']);

        $this->actingAs($user)->put(route('profile.update'), [
            'full_name' => 'Nama Baru',
            'email' => $user->email,
            'role' => 'Admin',
            'username' => 'diubah',
        ]);

        $user->refresh();
        $this->assertSame('User', $user->role);
        $this->assertSame('tetap', $user->username);
    }

    public function test_email_must_be_unique_among_other_users(): void
    {
        $this->makeUser(['email' => 'dipakai@has.com']);
        $user = $this->makeUser();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'full_name' => 'Nama Baru',
                'email' => 'dipakai@has.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_upload_and_replace_photo(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)->put(route('profile.update'), [
            'full_name' => $user->full_name,
            'email' => $user->email,
            'photo' => UploadedFile::fake()->image('foto.jpg', 200, 200),
        ])->assertSessionHasNoErrors();

        $first = $user->refresh()->icon;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);
        $this->assertStringEndsWith('/storage/'.$first, $user->avatar_url);

        $this->actingAs($user)->put(route('profile.update'), [
            'full_name' => $user->full_name,
            'email' => $user->email,
            'photo' => UploadedFile::fake()->image('foto2.png', 200, 200),
        ]);

        $second = $user->refresh()->icon;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_updating_profile_without_photo_keeps_existing_photo(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $path = UploadedFile::fake()->image('foto.jpg')->store('avatars', 'public');
        $user->update(['icon' => $path]);

        $this->actingAs($user)->put(route('profile.update'), [
            'full_name' => 'Nama Baru',
            'email' => $user->email,
        ]);

        $this->assertSame($path, $user->refresh()->icon);
        Storage::disk('public')->assertExists($path);
    }

    public function test_photo_must_be_an_image(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'full_name' => $user->full_name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->refresh()->icon);
    }

    public function test_user_can_remove_photo(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $path = UploadedFile::fake()->image('foto.jpg')->store('avatars', 'public');
        $user->update(['icon' => $path]);

        $this->actingAs($user)
            ->delete(route('profile.photo.destroy'))
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->icon);
        $this->assertNull($user->avatar_url);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'secret123',
                'password' => 'baru12345',
                'password_confirmation' => 'baru12345',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('baru12345', $user->refresh()->password));
    }

    public function test_password_is_not_changed_when_current_password_is_wrong(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password'), [
                'current_password' => 'salah',
                'password' => 'baru12345',
                'password_confirmation' => 'baru12345',
            ])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('secret123', $user->refresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password'), [
                'current_password' => 'secret123',
                'password' => 'baru12345',
                'password_confirmation' => 'berbeda',
            ])
            ->assertSessionHasErrorsIn('password', 'password');

        $this->assertTrue(Hash::check('secret123', $user->refresh()->password));
    }
}

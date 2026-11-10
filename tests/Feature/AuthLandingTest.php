<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Login & halaman awal ('/') harus universal: mengarah ke modul pertama yang
 * boleh dibaca akun ybs (urutan sidebar: group lalu module_name), bukan
 * hardcode ke satu modul (dulu selalu 'user-management.index', sehingga akun
 * tanpa akses modul itu terjebak redirect loop lewat CheckAccessControl).
 */
class AuthLandingTest extends TestCase
{
    use RefreshDatabase;

    private Module $moduleB;

    private Module $moduleA;

    protected function setUp(): void
    {
        parent::setUp();

        // Sengaja dibuat tidak berurutan secara id supaya "modul pertama"
        // hanya bisa benar kalau memang diurutkan per group + module_name,
        // bukan kebetulan cocok dengan urutan pembuatan / id.
        $this->moduleB = Module::create([
            'module_code' => 'MOD_B',
            'module_name' => 'B Module',
            'route_name' => 'user-management',
            'icon' => 'fa fa-b',
            'group' => 'B Group',
        ]);

        $this->moduleA = Module::create([
            'module_code' => 'MOD_A',
            'module_name' => 'A Module',
            'route_name' => 'currency',
            'icon' => 'fa fa-a',
            'group' => 'A Group',
        ]);
    }

    private function makeUser(string $role = 'User'): User
    {
        return User::create([
            'username' => 'user_'.uniqid(),
            'email' => uniqid().'@has.com',
            'password' => bcrypt('secret'),
            'role' => $role,
        ]);
    }

    public function test_login_lands_on_first_accessible_module_not_hardcoded_module(): void
    {
        $user = $this->makeUser();

        // Hanya boleh baca modul A (group "A Group"), TIDAK boleh baca modul B
        // (route 'user-management' — target lama yang di-hardcode).
        UserAccessControl::create([
            'user_id' => $user->id,
            'module_id' => $this->moduleA->id,
            'can_read' => true,
        ]);
        UserAccessControl::create([
            'user_id' => $user->id,
            'module_id' => $this->moduleB->id,
            'can_read' => false,
        ]);

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'secret',
        ]);

        $response->assertRedirect(route('currency.index'));
        $this->assertAuthenticatedAs($user);

        // Halaman modul yang boleh dibaca benar-benar bisa diakses (bukan loop).
        $this->followingRedirects()
            ->get(route('currency.index'))
            ->assertOk();
    }

    public function test_root_route_is_also_universal_per_account(): void
    {
        $user = $this->makeUser();
        UserAccessControl::create([
            'user_id' => $user->id,
            'module_id' => $this->moduleA->id,
            'can_read' => true,
        ]);

        $this->actingAs($user)->get('/')->assertRedirect(route('currency.index'));
    }

    public function test_admin_lands_on_first_module_by_sidebar_order(): void
    {
        $admin = $this->makeUser('Admin');

        // Admin tidak butuh baris UserAccessControl — selalu boleh baca semua modul.
        $response = $this->post(route('login'), [
            'username' => $admin->username,
            'password' => 'secret',
        ]);

        // "A Group" < "B Group" secara alfabet -> modul A (currency) duluan.
        $response->assertRedirect(route('currency.index'));
    }

    public function test_account_without_any_readable_module_lands_on_no_access_page_without_looping(): void
    {
        $user = $this->makeUser();
        // Tidak ada UserAccessControl sama sekali untuk user ini.

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'secret',
        ]);

        $response->assertRedirect(route('no-access'));

        // Halaman itu sendiri harus bisa dibuka langsung (tidak ikut memantul).
        $this->actingAs($user)->get(route('no-access'))
            ->assertOk()
            ->assertSee('Belum Ada Akses Modul');

        $this->actingAs($user)->get('/')->assertRedirect(route('no-access'));
    }

    public function test_remember_me_cookie_lasts_seven_days(): void
    {
        $user = $this->makeUser();

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'secret',
            'remember' => '1',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $rememberCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($rememberCookie);
        $this->assertEqualsWithDelta(
            now()->addDays(7)->timestamp,
            $rememberCookie->getExpiresTime(),
            120
        );
    }

    public function test_logout_redirects_to_login_and_clears_auth(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_expired_logout_token_redirects_to_login_instead_of_419(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $request = Request::create('/logout', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new TokenMismatchException);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('login'), $response->headers->get('Location'));
        $this->assertGuest();
    }
}

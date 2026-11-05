<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSalespersonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['username' => 'superadmin', 'email' => 'sa@has.com', 'password' => bcrypt('x'), 'role' => 'Admin']);
    }

    public function test_salesperson_defaults_to_false(): void
    {
        $user = User::create(['username' => 'staff', 'email' => 'staff@has.com', 'password' => bcrypt('x'), 'role' => 'User']);

        $this->assertFalse($user->fresh()->salesperson);
    }

    public function test_store_and_update_salesperson_flag(): void
    {
        $this->actingAs($this->admin)->post(route('user-management.store'), [
            'username' => 'citra', 'full_name' => 'Citra', 'email' => 'citra@has.com',
            'password' => 'secret123', 'role' => 'User', 'salesperson' => '1',
        ])->assertRedirect(route('user-management.index'));

        $user = User::where('username', 'citra')->firstOrFail();
        $this->assertTrue($user->salesperson);

        // Switch dimatikan: hanya hidden input "0" yang terkirim.
        $this->actingAs($this->admin)->put(route('user-management.update', $user->id), [
            'username' => 'citra', 'full_name' => 'Citra', 'email' => 'citra@has.com',
            'role' => 'User', 'salesperson' => '0',
        ])->assertRedirect(route('user-management.index'));
        $this->assertFalse($user->fresh()->salesperson);

        $this->actingAs($this->admin)->getJson(route('user-management.data'))
            ->assertOk()
            ->assertJsonFragment(['username' => 'citra', 'salesperson' => false]);
    }

    public function test_pages_show_salesperson_field(): void
    {
        $user = User::create(['username' => 'dian', 'email' => 'dian@has.com', 'password' => bcrypt('x'), 'role' => 'User', 'salesperson' => true]);

        $this->actingAs($this->admin)->get(route('user-management.create'))->assertOk()->assertSee('name="salesperson"', false);
        $this->actingAs($this->admin)->get(route('user-management.edit', $user->id))->assertOk()->assertSee('id="salesperson" name="salesperson" value="1" checked', false);
        $this->actingAs($this->admin)->get(route('user-management.show', $user->id))->assertOk()->assertSee('Salesperson');
    }
}

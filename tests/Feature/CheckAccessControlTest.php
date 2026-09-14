<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Activity;
use App\Models\Division;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Middleware CheckAccessControl menentukan modul dari route name dengan
 * memotong di TITIK PERTAMA (route_name di tabel modules selalu 1 segmen
 * tanpa titik). Route bertingkat 3 segmen (mis. "leads-management.activities.destroy")
 * sempat memotong di titik TERAKHIR, menghasilkan baseName "leads-management.activities"
 * yang tidak cocok modul manapun -> permission check di-skip total. Test ini
 * mengunci perbaikannya supaya tidak regresi.
 */
class CheckAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create([
            'division_name' => 'X',
            'description' => 'X',
            'type' => 'Internal',
            'status' => 'Active',
        ]);
    }

    private function makeUser(string $username): User
    {
        return User::create([
            'username' => $username,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);
    }

    private function grant(User $user, string $moduleCode, array $access): void
    {
        $module = Module::where('module_code', $moduleCode)->firstOrFail();

        UserAccessControl::create(array_merge([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'can_create' => false,
            'can_read' => false,
            'can_update' => false,
            'can_delete' => false,
            'can_approve' => false,
        ], $access));
    }

    public function test_normal_single_segment_route_is_blocked_without_permission(): void
    {
        Module::create(['module_code' => 'MOD_LEADS_MANAGEMENT', 'module_name' => 'Leads', 'route_name' => 'leads-management', 'icon' => 'fa', 'group' => 'CRM']);
        $user = $this->makeUser('zeroaccess');

        $this->actingAs($user)->get(route('leads-management.index'))->assertStatus(302);
    }

    /**
     * Sebelum perbaikan: request ini lolos (200) walau user tidak punya izin
     * apa pun, karena middleware gagal menemukan modul "leads-management.activities".
     */
    public function test_nested_three_segment_route_is_blocked_without_permission(): void
    {
        Module::create(['module_code' => 'MOD_LEADS_MANAGEMENT', 'module_name' => 'Leads', 'route_name' => 'leads-management', 'icon' => 'fa', 'group' => 'CRM']);
        $user = $this->makeUser('zeroaccess');

        $activity = Activity::create(['content' => 'test', 'user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('leads-management.activities.destroy', $activity->id))
            ->assertStatus(403);

        $this->assertNotNull($activity->fresh());
    }

    /**
     * Sebaliknya, user yang MEMANG diberi can_delete pada modul Leads
     * Management harus tetap bisa memakai route bertingkat ini (bukti
     * perbaikan tidak salah-blokir user yang berhak).
     */
    public function test_nested_three_segment_route_allowed_with_correct_permission(): void
    {
        Module::create(['module_code' => 'MOD_LEADS_MANAGEMENT', 'module_name' => 'Leads', 'route_name' => 'leads-management', 'icon' => 'fa', 'group' => 'CRM']);
        $user = $this->makeUser('withaccess');
        $this->grant($user, 'MOD_LEADS_MANAGEMENT', ['can_delete' => true]);

        $activity = Activity::create(['content' => 'test', 'user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('leads-management.activities.destroy', $activity->id))
            ->assertOk();

        $this->assertNull($activity->fresh());
    }

    /**
     * Modul lain yang juga punya route bertingkat (Opportunity Management)
     * harus ikut tercakup oleh perbaikan yang sama.
     */
    public function test_opportunity_management_nested_route_is_gated_by_correct_module(): void
    {
        Module::create(['module_code' => 'MOD_OPPORTUNITY_MANAGEMENT', 'module_name' => 'Opportunity', 'route_name' => 'opportunity-management', 'icon' => 'fa', 'group' => 'CRM']);

        $company = AccountCompany::create([
            'account_name' => 'PT Maju Bersama',
            'address_billing_street' => 'Jl. Industri No. 1',
            'address_billing_city' => 'Jakarta',
            'address_billing_province' => 'DKI Jakarta',
            'status' => 'Active',
        ]);
        $admin = User::create(['username' => 'admin', 'email' => 'admin@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'Admin']);
        $opportunity = Opportunity::create(['opportunity_name' => 'Test', 'account_companies_id' => $company->id, 'owner_id' => $admin->id, 'probability' => 50]);

        // Middleware selalu redirect (302) untuk GET yang tidak diizinkan,
        // terlepas dari header Accept (lihat CheckAccessControl::handle()) —
        // sama seperti perilaku route single-segment biasa.
        $noAccessUser = $this->makeUser('zeroaccess');
        $this->actingAs($noAccessUser)
            ->get(route('opportunity-management.activities.fetch', $opportunity->id))
            ->assertStatus(302);

        $readerUser = $this->makeUser('reader');
        $this->grant($readerUser, 'MOD_OPPORTUNITY_MANAGEMENT', ['can_read' => true]);
        $this->actingAs($readerUser)
            ->getJson(route('opportunity-management.activities.fetch', $opportunity->id))
            ->assertOk();
    }
}

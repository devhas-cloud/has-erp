<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\TaskRole;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar opportunity mengikuti aturan visibilitas yang sama dengan Leads:
 * divisi Sales non-Manager hanya melihat opportunity miliknya sendiri.
 */
class OpportunityManagementDataTest extends TestCase
{
    use RefreshDatabase;

    private Module $module;

    private Division $sales;

    private User $salesA;

    private User $salesB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Module::create([
            'module_code' => 'MOD_OPPORTUNITY_MANAGEMENT',
            'module_name' => 'Opportunity Management',
            'route_name' => 'opportunity-management',
            'icon' => 'fa fa-chart-line',
            'group' => 'CRM',
        ]);

        // Sengaja huruf kecil: pencocokan nama divisi tidak boleh case-sensitive.
        $this->sales = Division::create(['division_name' => 'sales', 'description' => 'Sales', 'type' => 'Internal', 'status' => 'Active']);

        $this->salesA = $this->makeUser('sales_a', 'Zaki', $this->sales->id);
        $this->salesB = $this->makeUser('sales_b', 'Andi', $this->sales->id);

        $company = AccountCompany::create(['account_name' => 'PT Maju Bersama', 'status' => 'Active']);
        Opportunity::create(['opportunity_name' => 'Opp A1', 'next_step' => 'b-step', 'account_companies_id' => $company->id, 'owner_id' => $this->salesA->id]);
        Opportunity::create(['opportunity_name' => 'Opp A2', 'next_step' => 'c-step', 'account_companies_id' => $company->id, 'owner_id' => $this->salesA->id]);
        Opportunity::create(['opportunity_name' => 'Opp B1', 'next_step' => 'a-step', 'account_companies_id' => $company->id, 'owner_id' => $this->salesB->id]);
    }

    private function makeUser(string $username, string $fullName, ?int $divisionId = null, ?int $taskRoleId = null): User
    {
        $user = User::create([
            'username' => $username,
            'full_name' => $fullName,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $divisionId,
            'task_role_id' => $taskRoleId,
            'role' => 'User',
        ]);

        UserAccessControl::create([
            'user_id' => $user->id,
            'module_id' => $this->module->id,
            'can_read' => true,
        ]);

        return $user;
    }

    private function names(User $user, array $query = []): array
    {
        return $this->actingAs($user)
            ->getJson(route('opportunity-management.data', $query))
            ->assertOk()
            ->json('data.*.opportunity_name');
    }

    public function test_sales_non_manager_only_sees_own_opportunities(): void
    {
        $response = $this->actingAs($this->salesA)
            ->getJson(route('opportunity-management.data'))
            ->assertOk();

        $this->assertEqualsCanonicalizing(['Opp A1', 'Opp A2'], $response->json('data.*.opportunity_name'));
        // Total tidak boleh membocorkan jumlah opportunity milik user lain.
        $this->assertSame(2, $response->json('recordsTotal'));
        $this->assertSame(2, $response->json('recordsFiltered'));
    }

    public function test_sales_manager_sees_all_opportunities(): void
    {
        $role = TaskRole::create(['role_name' => 'Manager', 'hierarchy_level' => 3]);
        $manager = $this->makeUser('sales_mgr', 'Manajer', $this->sales->id, $role->id);

        $this->assertCount(3, $this->names($manager));
    }

    public function test_non_sales_division_sees_all_opportunities(): void
    {
        $water = Division::create(['division_name' => 'WATER', 'description' => 'Water', 'type' => 'External', 'status' => 'Active']);
        $user = $this->makeUser('water_user', 'Water', $water->id);

        $this->assertCount(3, $this->names($user));
    }

    public function test_next_step_column_sorts_by_next_step(): void
    {
        $user = $this->makeUser('viewer', 'Viewer');

        $this->assertSame(
            ['Opp B1', 'Opp A1', 'Opp A2'],
            $this->names($user, ['order' => [['column' => 4, 'dir' => 'asc']]])
        );
    }

    public function test_owner_column_sorts_by_displayed_name(): void
    {
        $user = $this->makeUser('viewer', 'Viewer');

        // Andi (sales_b) sebelum Zaki (sales_a) — urut nama tampil, bukan username.
        $this->assertSame(
            ['Opp B1', 'Opp A1', 'Opp A2'],
            $this->names($user, ['order' => [['column' => 5, 'dir' => 'asc']]])
        );
    }

    public function test_account_column_sort_does_not_error(): void
    {
        $user = $this->makeUser('viewer', 'Viewer');

        $this->assertCount(3, $this->names($user, ['order' => [['column' => 2, 'dir' => 'desc']]]));
    }
}

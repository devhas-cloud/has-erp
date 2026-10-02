<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\Stage;
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
        Opportunity::create(['opportunity_name' => 'Opp A1', 'next_step' => 'b-step', 'close_date' => '2026-03-15', 'account_companies_id' => $company->id, 'owner_id' => $this->salesA->id]);
        Opportunity::create(['opportunity_name' => 'Opp A2', 'next_step' => 'c-step', 'close_date' => '2026-01-10', 'account_companies_id' => $company->id, 'owner_id' => $this->salesA->id]);
        Opportunity::create(['opportunity_name' => 'Opp B1', 'next_step' => 'a-step', 'close_date' => '2026-02-20', 'account_companies_id' => $company->id, 'owner_id' => $this->salesB->id]);
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
            $this->names($user, ['order' => [['column' => 6, 'dir' => 'asc']]])
        );
    }

    public function test_close_date_range_filter_is_inclusive(): void
    {
        $user = $this->makeUser('viewer', 'Viewer');

        $response = $this->actingAs($user)->getJson(route('opportunity-management.data', [
            'close_date_from' => '2026-02-20',
            'close_date_to' => '2026-03-15',
        ]))->assertOk();

        $this->assertEqualsCanonicalizing(['Opp A1', 'Opp B1'], $response->json('data.*.opportunity_name'));
        $this->assertSame(3, $response->json('recordsTotal'));
        $this->assertSame(2, $response->json('recordsFiltered'));
    }

    public function test_close_date_filter_works_with_only_one_bound(): void
    {
        $user = $this->makeUser('viewer', 'Viewer');

        $this->assertEqualsCanonicalizing(['Opp A1', 'Opp B1'], $this->names($user, ['close_date_from' => '2026-02-01']));
        $this->assertEqualsCanonicalizing(['Opp A2'], $this->names($user, ['close_date_to' => '2026-02-01']));
    }

    public function test_close_date_filter_respects_sales_scope(): void
    {
        // Opp B1 (20 Feb) masuk rentang tapi milik sales lain.
        $this->assertSame(['Opp A1'], $this->names($this->salesA, ['close_date_from' => '2026-02-01']));
    }

    public function test_close_date_column_sorts_by_close_date(): void
    {
        $user = $this->makeUser('viewer', 'Viewer');

        $this->assertSame(
            ['Opp A2', 'Opp B1', 'Opp A1'],
            $this->names($user, ['order' => [['column' => 5, 'dir' => 'asc']]])
        );
        $this->assertSame(
            ['Opp A1', 'Opp B1', 'Opp A2'],
            $this->names($user, ['order' => [['column' => 5, 'dir' => 'desc']]])
        );
    }

    public function test_invalid_close_date_filter_is_rejected(): void
    {
        $user = $this->makeUser('viewer', 'Viewer');

        $this->actingAs($user)
            ->getJson(route('opportunity-management.data', ['close_date_from' => 'bukan-tanggal']))
            ->assertStatus(422);
    }

    public function test_index_offers_years_covering_close_dates_and_current_year(): void
    {
        $company = AccountCompany::first();
        Opportunity::create(['opportunity_name' => 'Opp Lama', 'close_date' => '2024-05-01', 'account_companies_id' => $company->id, 'owner_id' => $this->salesA->id]);
        $user = $this->makeUser('viewer', 'Viewer');

        $this->actingAs($user)
            ->get(route('opportunity-management.index'))
            ->assertOk()
            ->assertViewHas('closeDateYears', range(2024, max(2026, now()->year)));
    }

    public function test_stage_filter_accepts_multiple_stages_and_combines_with_close_date(): void
    {
        $new = Stage::create(['stage_name' => 'New', 'status' => 'Active']);
        $won = Stage::create(['stage_name' => 'Closed Won', 'status' => 'Active']);
        $lost = Stage::create(['stage_name' => 'Closed Lost', 'status' => 'Active']);
        Opportunity::where('opportunity_name', 'Opp A1')->update(['stage_id' => $new->id]);   // 15 Mar
        Opportunity::where('opportunity_name', 'Opp A2')->update(['stage_id' => $won->id]);   // 10 Jan
        Opportunity::where('opportunity_name', 'Opp B1')->update(['stage_id' => $lost->id]);  // 20 Feb
        $user = $this->makeUser('viewer', 'Viewer');

        $this->assertSame(['Opp A1'], $this->names($user, ['stage_ids' => [$new->id]]));
        $this->assertEqualsCanonicalizing(['Opp A1', 'Opp A2'], $this->names($user, ['stage_ids' => [$new->id, $won->id]]));
        $this->assertCount(3, $this->names($user, ['stage_ids' => []]));

        $response = $this->actingAs($user)->getJson(route('opportunity-management.data', [
            'stage_ids' => [$new->id, $won->id],
            'close_date_from' => '2026-02-01',
        ]))->assertOk();
        $this->assertSame(['Opp A1'], $response->json('data.*.opportunity_name'));
        $this->assertSame(3, $response->json('recordsTotal'));
        $this->assertSame(1, $response->json('recordsFiltered'));

        $this->actingAs($user)
            ->getJson(route('opportunity-management.data', ['stage_ids' => ['abc']]))
            ->assertStatus(422);
    }

    public function test_account_column_sort_does_not_error(): void
    {
        $user = $this->makeUser('viewer', 'Viewer');

        $this->assertCount(3, $this->names($user, ['order' => [['column' => 2, 'dir' => 'desc']]]));
    }
}

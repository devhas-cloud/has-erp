<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\QuoteConfiguration;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImsConfigurationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Division $ims;

    private Division $water;

    private User $creator;

    private User $imsColleague;

    private User $waterUser;

    private TaskCategory $quoteCategory;

    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ims = Division::create([
            'division_name' => 'IMS',
            'description' => 'IMS Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->water = Division::create([
            'division_name' => 'WATER',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->creator = User::create([
            'username' => 'imaidin',
            'email' => 'imaidin@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->ims->id,
            'role' => 'User',
        ]);

        $this->imsColleague = User::create([
            'username' => 'iriki',
            'email' => 'iriki@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->ims->id,
            'role' => 'User',
        ]);

        $this->waterUser = User::create([
            'username' => 'zuri',
            'email' => 'zuri@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->water->id,
            'role' => 'User',
        ]);

        $this->quoteCategory = TaskCategory::create([
            'name' => 'Quote',
            'use_division_handler' => true,
        ]);

        $company = AccountCompany::create([
            'account_name' => 'PT Material IMS',
            'status' => 'Active',
        ]);
        $contact = AccountContact::create([
            'account_companies_id' => $company->id,
            'full_name' => 'Risqul',
            'email' => 'risqul@ims.com',
            'mobile' => '081234567890',
        ]);
        $this->opportunity = Opportunity::create([
            'opportunity_name' => 'Material IMS',
            'account_companies_id' => $company->id,
            'account_contacts_id' => $contact->id,
            'owner_id' => $this->creator->id,
            'probability' => 60,
        ]);

        $module = Module::create([
            'module_code' => 'MOD_IMS_CONFIGURATION',
            'module_name' => 'IMS Configuration',
            'route_name' => 'ims-configuration',
            'group' => 'IMS',
        ]);

        foreach ([$this->creator, $this->imsColleague] as $user) {
            UserAccessControl::create([
                'user_id' => $user->id,
                'module_id' => $module->id,
                'can_create' => true,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => true,
                'can_approve' => true,
            ]);
        }

        // User divisi WATER diberi hak approve modul IMS tapi beda divisi,
        // agar middleware lolos dan aturan divisi (controller) yang menguji.
        UserAccessControl::create([
            'user_id' => $this->waterUser->id,
            'module_id' => $module->id,
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => true,
            'can_approve' => true,
        ]);
    }

    private function createQuoteTask(): Task
    {
        return Task::create([
            'creator_id' => $this->creator->id,
            'category_id' => $this->quoteCategory->id,
            'opportunity_id' => $this->opportunity->id,
            'title' => 'Quote IMS Material',
            'due_date' => '2026-08-20',
            'status' => 'in_progress',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ]);
    }

    private function createImsQuotation(): QuoteConfiguration
    {
        $task = $this->createQuoteTask();

        $this->actingAs($this->creator)->postJson(route('ims-configuration.store'), [
            'task_id' => $task->id,
            'parameter_note' => 'Material',
            'items' => [
                ['_key' => 'new-1', 'category' => 'Material', 'part_number' => 'M-001', 'description' => 'Material A', 'qty' => 1, 'price' => 12500000, 'unit' => 'pcs'],
            ],
        ])->assertOk();

        return QuoteConfiguration::latest('id')->firstOrFail();
    }

    public function test_create_sets_ims_division(): void
    {
        $quotation = $this->createImsQuotation();

        $this->assertSame($this->ims->id, $quotation->division_id);
        $this->assertSame('IMS', $quotation->division?->division_name);

        $item = $quotation->items()->first();
        $this->assertSame(12500000.0, (float) $item->price);
        $this->assertSame('pcs', $item->unit);
    }

    public function test_create_sanitizes_item_description(): void
    {
        $task = $this->createQuoteTask();

        $this->actingAs($this->creator)->postJson(route('ims-configuration.store'), [
            'task_id' => $task->id,
            'items' => [['_key' => 'new-1', 'description' => '<b>Sensor</b><script>alert(1)</script>', 'qty' => 1]],
        ])->assertOk();

        $quotation = QuoteConfiguration::latest('id')->firstOrFail();
        $this->assertSame('<b>Sensor</b>', $quotation->items()->first()->description);
    }

    public function test_data_only_returns_ims_configurations(): void
    {
        $this->createImsQuotation();

        // Buat config WATER langsung (division_id = WATER) untuk memastikan tidak bocor ke list IMS.
        $task = $this->createQuoteTask();
        QuoteConfiguration::create([
            'division_id' => $this->water->id,
            'task_id' => $task->id,
            'date' => '2026-08-20',
            'parameter_note' => 'pH',
            'status' => 'draft',
            'created_by' => $this->waterUser->id,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
        ]);

        $response = $this->actingAs($this->creator)
            ->getJson(route('ims-configuration.data'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->ims->id, $response->json('data.0.division_id'));
        $this->assertSame('IMS', $response->json('data.0.division_name'));
    }

    public function test_search_products_only_returns_ims_division(): void
    {
        MasterProduct::create([
            'name' => 'Material IMS',
            'code' => 'MAT-IMS-001',
            'brand' => 'ims',
            'category' => 'Material',
            'division_id' => $this->ims->id,
            'status' => 'Active',
        ]);
        MasterProduct::create([
            'name' => 'pH WATER',
            'code' => 'W-001',
            'brand' => 's::can',
            'category' => 'pH',
            'division_id' => $this->water->id,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->creator)
            ->getJson(route('ims-configuration.search-products', ['draw' => 1, 'length' => 100]))
            ->assertOk();

        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertSame('MAT-IMS-001', $response->json('data.0.code'));
    }

    public function test_approval_same_division_ims(): void
    {
        $quotation = $this->createImsQuotation();
        $this->actingAs($this->creator)
            ->postJson(route('ims-configuration.submit', $quotation->id))
            ->assertOk();

        // User divisi IMS lain (satu divisi) bisa approve
        $this->actingAs($this->imsColleague)
            ->postJson(route('ims-configuration.approve', $quotation->id))
            ->assertOk();
        $this->assertSame('approved', $quotation->fresh()->status);
    }

    public function test_water_user_cannot_approve_ims_configuration(): void
    {
        $quotation = $this->createImsQuotation();
        $this->actingAs($this->creator)
            ->postJson(route('ims-configuration.submit', $quotation->id))
            ->assertOk();

        $this->actingAs($this->waterUser)
            ->postJson(route('ims-configuration.approve', $quotation->id))
            ->assertForbidden();

        $this->assertSame('waiting_approval', $quotation->fresh()->status);
    }

    public function test_duplicate_config_same_task_same_division_blocked(): void
    {
        $quotation = $this->createImsQuotation();
        $task = Task::find($quotation->task_id);

        // Task yang sudah punya config divisi ini tidak muncul di dropdown create
        $this->actingAs($this->creator)
            ->get(route('ims-configuration.create'))
            ->assertOk()
            ->assertDontSee($task->title);

        // Store untuk task yang sama => 422 (tidak dobel)
        $this->actingAs($this->creator)
            ->postJson(route('ims-configuration.store'), [
                'task_id' => $task->id,
                'items' => [['_key' => 'new-1', 'description' => 'Material A', 'qty' => 1]],
            ])
            ->assertStatus(422);

        $this->assertSame(1, QuoteConfiguration::where('task_id', $task->id)->where('division_id', $this->ims->id)->count());
    }

    public function test_different_division_can_create_config_for_same_task(): void
    {
        $quotation = $this->createImsQuotation();
        $task = Task::find($quotation->task_id);

        // Divisi WATER tetap bisa membuat config untuk task yang sama (unik per division_id + task_id)
        $this->actingAs($this->waterUser)
            ->postJson(route('ims-configuration.store'), [
                'task_id' => $task->id,
                'items' => [['_key' => 'new-1', 'description' => 'Material dari WATER', 'qty' => 2]],
            ])
            ->assertOk();

        $second = QuoteConfiguration::latest('id')->firstOrFail();
        $this->assertSame($this->water->id, $second->division_id);
        $this->assertSame($task->id, $second->task_id);
    }
}

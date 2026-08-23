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

class WaterConfigurationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Division $water;

    private Division $ims;

    private User $creator;

    private User $waterColleague;

    private User $imsUser;

    private TaskCategory $quoteCategory;

    private AccountCompany $company;

    private AccountContact $contact;

    private Opportunity $opportunity;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->water = Division::create([
            'division_name' => 'WATER',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->ims = Division::create([
            'division_name' => 'IMS',
            'description' => 'IMS Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->creator = User::create([
            'username' => 'maidin',
            'email' => 'maidin@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->water->id,
            'role' => 'User',
        ]);

        $this->waterColleague = User::create([
            'username' => 'riki',
            'email' => 'riki@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->water->id,
            'role' => 'User',
        ]);

        $this->imsUser = User::create([
            'username' => 'zuri',
            'email' => 'zuri@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->ims->id,
            'role' => 'User',
        ]);

        $this->sales = User::create([
            'username' => 'tania',
            'email' => 'tania@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->ims->id,
            'role' => 'User',
        ]);

        $this->quoteCategory = TaskCategory::create([
            'name' => 'Quote',
            'use_division_handler' => true,
        ]);

        $this->company = AccountCompany::create([
            'account_name' => 'Kawasan Industri Gresik',
            'address_billing_street' => 'Jl. Raya Manyar',
            'address_billing_city' => 'Gresik',
            'address_billing_province' => 'Jawa Timur',
            'status' => 'Active',
        ]);

        $this->contact = AccountContact::create([
            'account_companies_id' => $this->company->id,
            'full_name' => 'Risqul',
            'email' => 'risqul@kig.com',
            'mobile' => '081234567890',
        ]);

        $this->opportunity = Opportunity::create([
            'opportunity_name' => 'Water Treatment Tuban',
            'account_companies_id' => $this->company->id,
            'account_contacts_id' => $this->contact->id,
            'owner_id' => $this->sales->id,
            'probability' => 70,
        ]);

        $module = Module::create([
            'module_code' => 'MOD_WATER_CONFIGURATION',
            'module_name' => 'Water Configuration',
            'route_name' => 'water-configuration',
            'group' => 'Quotation',
        ]);

        foreach ([$this->creator, $this->waterColleague, $this->imsUser] as $user) {
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
    }

    private function createQuoteTask(): Task
    {
        return Task::create([
            'creator_id' => $this->sales->id,
            'category_id' => $this->quoteCategory->id,
            'opportunity_id' => $this->opportunity->id,
            'title' => 'Quote Water Treatment',
            'due_date' => '2026-08-20',
            'status' => 'in_progress',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ]);
    }

    private function createQuotation(User $asUser): QuoteConfiguration
    {
        $task = $this->createQuoteTask();

        $response = $this->actingAs($asUser)->postJson(route('water-configuration.store'), [
            'task_id' => $task->id,
            'date' => '2026-08-20',
            'parameter_note' => 'pH, Ammonia, COD, TSS dan Debit',
            'items' => [
                [
                    '_key' => 'new-1',
                    'category' => 'pH',
                    'part_number' => 'E-514-4-075',
                    'description' => 'pH::lyser pro, incl. 7,5 m fixed cable',
                    'qty' => 1,
                ],
            ],
        ]);
        $response->assertOk();

        return QuoteConfiguration::latest('id')->firstOrFail();
    }

    public function test_create_links_to_task_and_opportunity(): void
    {
        $task = $this->createQuoteTask();
        $this->actingAs($this->creator)->postJson(route('water-configuration.store'), [
            'task_id' => $task->id,
            'parameter_note' => 'pH, Ammonia, COD, TSS dan Debit',
            'items' => [['_key' => 'new-1', 'parent_key' => null, 'item_no' => '', 'description' => 'ammo::lyser pro', 'qty' => 1]],
        ])->assertOk();

        $quotation = QuoteConfiguration::latest('id')->firstOrFail();

        $this->assertSame($task->id, $quotation->task_id);
        $this->assertSame($this->opportunity->id, $quotation->opportunity_id);
        $this->assertSame('draft', $quotation->status);
        $this->assertSame($this->creator->id, $quotation->created_by);
        $this->assertSame($task->due_date->format('Y-m-d'), $quotation->date->format('Y-m-d'));
        $this->assertSame($quotation->id, $quotation->group_id);
        $this->assertSame(1, $quotation->version);
        $this->assertTrue($quotation->is_current);
        $this->assertSame('ammo::lyser pro', $quotation->items()->first()->description);
        $this->assertSame($this->creator->division_id, $quotation->division_id);
        $this->assertSame('WATER', $quotation->division?->division_name);
    }

    public function test_create_sanitizes_item_description(): void
    {
        $task = $this->createQuoteTask();

        $this->actingAs($this->creator)->postJson(route('water-configuration.store'), [
            'task_id' => $task->id,
            'parameter_note' => 'pH',
            'items' => [['_key' => 'new-1', 'description' => '<b>pH::lyser</b><script>alert(1)</script>', 'qty' => 1]],
        ])->assertOk();

        $quotation = QuoteConfiguration::latest('id')->firstOrFail();
        $this->assertSame('<b>pH::lyser</b>', $quotation->items()->first()->description);
    }

    public function test_derived_customer_data_from_task(): void
    {
        $quotation = $this->createQuotation($this->creator);

        // location = company name
        $this->assertSame('Kawasan Industri Gresik', $quotation->location);
        // to_name / pic = account contact
        $this->assertSame('Risqul', $quotation->to_name);
        $this->assertSame('Risqul', $quotation->pic_name);
        $this->assertSame('081234567890', $quotation->pic_phone);
        $this->assertSame('risqul@kig.com', $quotation->pic_email);
        // address = company billing address
        $this->assertSame('Jl. Raya Manyar, Gresik, Jawa Timur', $quotation->address);
        // sales = task creator
        $this->assertSame('tania', $quotation->sales_name);
    }

    public function test_fetch_task_returns_derived_data(): void
    {
        $task = $this->createQuoteTask();

        $response = $this->actingAs($this->creator)
            ->getJson(route('water-configuration.fetch-task').'?task_id='.$task->id)
            ->assertOk();

        $this->assertSame('Kawasan Industri Gresik', $response->json('data.location'));
        $this->assertSame('Risqul', $response->json('data.pic_name'));
        $this->assertSame('tania', $response->json('data.sales_name'));
        $this->assertSame($this->opportunity->id, $response->json('data.opportunity_id'));
    }

    public function test_creator_from_water_division_cannot_approve_own_quotation(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();

        // Aturan: pembuat tidak bisa approve dokumennya sendiri
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.approve', $quotation->id))
            ->assertForbidden();

        $this->assertSame('waiting_approval', $quotation->fresh()->status);
    }

    public function test_other_user_from_same_division_can_approve(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();

        $this->actingAs($this->waterColleague)
            ->postJson(route('water-configuration.approve', $quotation->id))
            ->assertOk();

        $fresh = $quotation->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($this->waterColleague->id, $fresh->final_checked_by);
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_user_from_other_division_cannot_approve(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();

        $this->actingAs($this->imsUser)
            ->postJson(route('water-configuration.approve', $quotation->id))
            ->assertForbidden();

        $this->assertSame('waiting_approval', $quotation->fresh()->status);
    }

    public function test_admin_can_approve_any_quotation(): void
    {
        $admin = User::create([
            'username' => 'admin',
            'email' => 'admin@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->ims->id,
            'role' => 'Admin',
        ]);

        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('water-configuration.approve', $quotation->id))
            ->assertOk();

        $this->assertSame('approved', $quotation->fresh()->status);
    }

    public function test_reject_requires_reason_and_same_division_approver(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();

        // Reject tanpa alasan => error validasi
        $this->actingAs($this->waterColleague)
            ->postJson(route('water-configuration.reject', $quotation->id), [])
            ->assertUnprocessable();

        // Reject oleh user divisi lain => forbidden
        $this->actingAs($this->imsUser)
            ->postJson(route('water-configuration.reject', $quotation->id), ['approval_note' => 'Harga terlalu tinggi'])
            ->assertForbidden();

        // Reject oleh user satu divisi dengan alasan => sukses
        $this->actingAs($this->waterColleague)
            ->postJson(route('water-configuration.reject', $quotation->id), ['approval_note' => 'Harga terlalu tinggi'])
            ->assertOk();

        $fresh = $quotation->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('Harga terlalu tinggi', $fresh->approval_note);
    }

    public function test_rejected_quotation_can_be_revised_and_resubmitted(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();
        $this->actingAs($this->waterColleague)
            ->postJson(route('water-configuration.reject', $quotation->id), ['approval_note' => 'Harga terlalu tinggi'])
            ->assertOk();

        $this->assertSame('rejected', $quotation->fresh()->status);

        // Rejected tidak bisa diedit langsung (harus lewat revisi)
        $this->actingAs($this->creator)
            ->get(route('water-configuration.edit', $quotation->id))
            ->assertRedirect(route('water-configuration.index'));

        // Buat revisi => versi baru (Draft)
        $revisionResponse = $this->actingAs($this->creator)
            ->postJson(route('water-configuration.revise', $quotation->id))
            ->assertOk();
        $revisionId = $revisionResponse->json('id');
        $revision = QuoteConfiguration::findOrFail($revisionId);

        $this->assertSame($quotation->group_id, $revision->group_id);
        $this->assertSame($quotation->id, $revision->parent_id);
        $this->assertSame(2, $revision->version);
        $this->assertSame('draft', $revision->status);
        $this->assertFalse($revision->is_current);
        $this->assertCount(1, $revision->items);
        $this->assertSame($quotation->items()->first()->description, $revision->items()->first()->description);

        // Versi lama (rejected) tetap sebagai riwayat
        $this->assertSame('rejected', $quotation->fresh()->status);

        // Edit versi revisi, update, lalu submit ulang
        $this->actingAs($this->creator)
            ->get(route('water-configuration.edit', $revision->id))
            ->assertOk();

        $task = $this->createQuoteTask();
        $this->actingAs($this->creator)
            ->putJson(route('water-configuration.update', $revision->id), [
                'task_id' => $task->id,
                'parameter_note' => 'pH, Ammonia',
                'items' => [
                    ['_key' => 'new-1', 'category' => 'pH', 'part_number' => 'E-514-4-075', 'description' => 'pH::lyser pro', 'qty' => 2],
                ],
            ])
            ->assertOk();

        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $revision->id))
            ->assertOk();

        $this->assertSame('waiting_approval', $revision->fresh()->status);
    }

    public function test_approved_quotation_cannot_be_edited_or_deleted(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();
        $this->actingAs($this->waterColleague)
            ->postJson(route('water-configuration.approve', $quotation->id))
            ->assertOk();

        $this->actingAs($this->creator)
            ->putJson(route('water-configuration.update', $quotation->id), [
                'task_id' => $this->createQuoteTask()->id,
                'items' => [['_key' => 'new-1', 'description' => 'x', 'qty' => 1]],
            ])
            ->assertStatus(422);

        $this->actingAs($this->creator)
            ->deleteJson(route('water-configuration.destroy', $quotation->id))
            ->assertStatus(422);
    }

    public function test_approved_is_locked_unlocked_and_revised(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();
        $this->actingAs($this->waterColleague)
            ->postJson(route('water-configuration.approve', $quotation->id))
            ->assertOk();

        $fresh = $quotation->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertTrue($fresh->is_current);
        $this->assertTrue($fresh->isLocked());

        // Approved terkunci => belum bisa direvisi
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.revise', $quotation->id))
            ->assertStatus(422);

        // Bukan approver tidak bisa buka kunci
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.unlock', $quotation->id))
            ->assertForbidden();

        // Approver buka kunci
        $this->actingAs($this->waterColleague)
            ->postJson(route('water-configuration.unlock', $quotation->id))
            ->assertOk();

        $unlocked = $quotation->fresh();
        $this->assertNotNull($unlocked->unlocked_at);
        $this->assertFalse($unlocked->isLocked());

        // Pembuat buat revisi => versi 2 Draft, items tersalin
        $revisionResponse = $this->actingAs($this->creator)
            ->postJson(route('water-configuration.revise', $quotation->id))
            ->assertOk();
        $revision = QuoteConfiguration::findOrFail($revisionResponse->json('id'));

        $this->assertSame($quotation->group_id, $revision->group_id);
        $this->assertSame($quotation->id, $revision->parent_id);
        $this->assertSame(2, $revision->version);
        $this->assertSame('draft', $revision->status);
        $this->assertFalse($revision->is_current);
        $this->assertCount(1, $revision->items);
        $this->assertSame($this->creator->division_id, $revision->division_id);

        // Sumber approved menjadi Archived setelah revisi dibuat
        $this->assertSame('archived', $quotation->fresh()->status);
        $this->assertFalse($quotation->fresh()->is_current);
    }

    public function test_revision_approval_makes_new_current_and_old_stays_history(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)->postJson(route('water-configuration.submit', $quotation->id))->assertOk();
        $this->actingAs($this->waterColleague)->postJson(route('water-configuration.approve', $quotation->id))->assertOk();
        $this->actingAs($this->waterColleague)->postJson(route('water-configuration.unlock', $quotation->id))->assertOk();

        $rev = $this->actingAs($this->creator)->postJson(route('water-configuration.revise', $quotation->id))->json('id');
        $revision = QuoteConfiguration::findOrFail($rev);

        $this->actingAs($this->creator)->postJson(route('water-configuration.submit', $revision->id))->assertOk();
        $this->actingAs($this->waterColleague)->postJson(route('water-configuration.approve', $revision->id))->assertOk();

        $revision->refresh();
        $quotation->refresh();

        $this->assertSame('approved', $revision->status);
        $this->assertTrue($revision->is_current);
        $this->assertFalse($quotation->is_current);

        // Versi lama (approved) diarsipkan sebagai riwayat, bukan Approved lagi
        $this->assertSame('archived', $quotation->status);

        // Endpoint versions mengembalikan kedua versi
        $versions = $this->actingAs($this->creator)
            ->getJson(route('water-configuration.versions', $revision->id))
            ->assertOk()
            ->json('versions');

        $this->assertCount(2, $versions);
        $this->assertSame(2, $versions[0]['version']);
        $this->assertSame(1, $versions[1]['version']);
        $this->assertTrue($versions[0]['is_current']);
    }

    public function test_reject_allowed_with_can_approve_without_can_create(): void
    {
        UserAccessControl::where('user_id', $this->waterColleague->id)
            ->first()
            ->update(['can_create' => false]);

        $quotation = $this->createQuotation($this->creator);
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.submit', $quotation->id))
            ->assertOk();

        $this->actingAs($this->waterColleague)
            ->postJson(route('water-configuration.reject', $quotation->id), ['approval_note' => 'Data tidak lengkap'])
            ->assertOk();

        $this->assertSame('rejected', $quotation->fresh()->status);
    }

    public function test_creator_can_view_show_and_print_pages(): void
    {
        $quotation = $this->createQuotation($this->creator);

        $this->actingAs($this->creator)
            ->get(route('water-configuration.show', $quotation->id))
            ->assertOk()
            ->assertSee('Configuration '.$quotation->opportunity->opportunity_name)
            ->assertSee('Quote Water Treatment');

        $this->actingAs($this->creator)
            ->get(route('water-configuration.pdf', $quotation->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=Quote-Configuration-'.$quotation->id.'.pdf');
    }

    public function test_show_honors_back_to_task_parameter(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $taskId = $quotation->task_id;

        // Dengan ?back=task-{id} => tombol "Kembali ke Task" menuju task-planner.show
        $this->actingAs($this->creator)
            ->get(route('water-configuration.show', ['water_configuration' => $quotation->id, 'back' => 'task-'.$taskId]))
            ->assertOk()
            ->assertSee('Kembali ke Task')
            ->assertSee(route('task-planner.show', $taskId));

        // Tanpa back => tombol "Kembali" ke daftar configuration
        $this->actingAs($this->creator)
            ->get(route('water-configuration.show', $quotation->id))
            ->assertOk()
            ->assertSee('Kembali')
            ->assertDontSee('Kembali ke Task');
    }

    public function test_create_page_renders_form(): void
    {
        $this->actingAs($this->creator)
            ->get(route('water-configuration.create'))
            ->assertOk()
            ->assertSee('Buat Quote Configuration')
            ->assertSee('Pilih Task Quote')
            ->assertSee('Produk (Part Number)')
            ->assertSee('Pilih Part Instrument');
    }

    public function test_create_page_only_lists_incomplete_tasks(): void
    {
        Task::create([
            'creator_id' => $this->sales->id,
            'category_id' => $this->quoteCategory->id,
            'opportunity_id' => $this->opportunity->id,
            'title' => 'Task Done',
            'due_date' => '2026-08-20',
            'status' => 'done',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ]);

        $this->createQuoteTask();

        $response = $this->actingAs($this->creator)
            ->get(route('water-configuration.create'))
            ->assertOk();

        $this->assertStringNotContainsString('Task Done', $response->getContent());
        $this->assertStringContainsString('Water Treatment Tuban', $response->getContent());
    }

    public function test_search_products_returns_only_water_division_active_products(): void
    {
        MasterProduct::create([
            'name' => 'pH::lyser pro',
            'code' => 'E-514-4-075',
            'brand' => 's::can',
            'category' => 'pH',
            'description' => 'parameter spectro sensor pH',
            'division_id' => $this->water->id,
            'price' => 12500000,
            'status' => 'Active',
        ]);
        // Produk nama mirip tapi divisi IMS (non-WATER) => tidak boleh tampil
        MasterProduct::create([
            'name' => 'pH::lyser ims',
            'code' => 'E-999-IMS',
            'brand' => 's::can',
            'category' => 'pH',
            'division_id' => $this->ims->id,
            'price' => 1000,
            'status' => 'Active',
        ]);
        // Produk nonaktif walau divisi WATER => tidak boleh tampil
        MasterProduct::create([
            'name' => 'Produk Nonaktif',
            'code' => 'X-001',
            'brand' => 'x',
            'division_id' => $this->water->id,
            'price' => 1000,
            'status' => 'Inactive',
        ]);

        $response = $this->actingAs($this->creator)
            ->getJson(route('water-configuration.search-products', ['draw' => 1, 'search[value]' => 'pH', 'length' => 100]))
            ->assertOk();

        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('E-514-4-075', $response->json('data.0.code'));
        $this->assertSame('pH', $response->json('data.0.category'));
        $this->assertSame('parameter spectro sensor pH', $response->json('data.0.description'));

        // Pencarian berdasarkan deskripsi juga harus cocok
        $this->actingAs($this->creator)
            ->getJson(route('water-configuration.search-products', ['draw' => 2, 'search[value]' => 'spectro', 'length' => 100]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', 'E-514-4-075');
    }

    public function test_store_links_items_to_master_products(): void
    {
        $product = MasterProduct::create([
            'name' => 'ammo::lyser pro',
            'code' => 'E-532-pro-075',
            'brand' => 's::can',
            'category' => 'Ammonia',
            'price' => 15000000,
            'status' => 'Active',
        ]);

        $task = $this->createQuoteTask();

        $this->actingAs($this->creator)->postJson(route('water-configuration.store'), [
            'task_id' => $task->id,
            'parameter_note' => 'Ammonia',
            'items' => [
                [
                    '_key' => 'new-1',
                    'product_id' => $product->id,
                    'category' => 'Ammonia',
                    'part_number' => 'E-532-pro-075',
                    'description' => 'ammo::lyser pro',
                    'qty' => 1,
                ],
            ],
        ])->assertOk();

        $quotation = QuoteConfiguration::latest('id')->firstOrFail();

        $this->assertSame($product->id, $quotation->items()->first()->product_id);
        $this->assertSame('ammo::lyser pro', $quotation->items()->first()->product->name);
        // Price diambil dari database (master_products) via product_id.
        $this->assertEquals(15000000, $quotation->items()->first()->price);
    }

    public function test_store_sets_price_from_product_when_qty_positive_else_zero(): void
    {
        $product = MasterProduct::create([
            'name' => 'pH::lyser pro',
            'code' => 'E-514-pro-075',
            'brand' => 'S::CAN',
            'category' => 'pH',
            'division_id' => $this->water->id,
            'description' => 'pH sensor',
            'price' => 5000000,
            'status' => 'Active',
        ]);

        $task = $this->createQuoteTask();

        // Harga diambil dari DATABASE (master_products) via product_id, bukan dari input user.
        $this->actingAs($this->creator)->postJson(route('water-configuration.store'), [
            'task_id' => $task->id,
            'parameter_note' => 'pH',
            'items' => [
                ['_key' => 'new-1', 'product_id' => $product->id, 'category' => 'pH', 'description' => 'sensor', 'qty' => 1, 'price' => 12345],
                ['_key' => 'new-2', 'product_id' => $product->id, 'category' => 'pH', 'description' => 'sensor 0', 'qty' => 0, 'price' => 999],
                ['_key' => 'new-3', 'product_id' => null, 'category' => 'pH', 'description' => 'manual', 'qty' => 1],
            ],
        ])->assertOk();

        $quotation = QuoteConfiguration::latest('id')->firstOrFail();
        $rows = $quotation->items()->orderBy('sort_order')->get();

        // qty >= 1 dengan product_id → price dari DB (5000000), abaikan input user 12345.
        $this->assertEquals(5000000, $rows[0]->price);
        // qty 0 → price 0 meski ada product_id.
        $this->assertEquals(0, $rows[1]->price);
        // tanpa product_id → price 0.
        $this->assertEquals(0, $rows[2]->price);
    }

    public function test_fetch_template_returns_parent_and_children(): void
    {
        $task = $this->createQuoteTask();

        $this->actingAs($this->creator)->postJson(route('water-configuration.store'), [
            'task_id' => $task->id,
            'parameter_note' => 'pH',
            'items' => [
                ['_key' => 'new-1', 'description' => 'Parent pH', 'qty' => 1],
                ['_key' => 'new-2', 'parent_key' => 'new-1', 'description' => 'Child sensor A', 'qty' => 1],
                ['_key' => 'new-3', 'parent_key' => 'new-1', 'description' => 'Child sensor B', 'qty' => 1],
                ['_key' => 'new-4', 'description' => 'Parent NH3', 'qty' => 1],
            ],
        ])->assertOk();

        $config = QuoteConfiguration::latest('id')->firstOrFail();

        $response = $this->actingAs($this->creator)
            ->getJson(route('water-configuration.fetch-template', $config->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $items = $response->json('items');

        // 4 item (2 parent + 2 children) dikembalikan.
        $this->assertCount(4, $items);

        // Urutan DFS: parent sebelum children-nya.
        $this->assertSame('Parent pH', $items[0]['description']);
        $this->assertSame('Child sensor A', $items[1]['description']);
        $this->assertSame('Child sensor B', $items[2]['description']);
        $this->assertSame('Parent NH3', $items[3]['description']);

        // Root punya parent_key null; children menunjuk parent.
        $this->assertNull($items[0]['parent_key']);
        $this->assertSame($items[0]['_key'], $items[1]['parent_key']);
        $this->assertSame($items[0]['_key'], $items[2]['parent_key']);
        $this->assertNull($items[3]['parent_key']);
    }

    public function test_duplicate_config_same_task_same_division_blocked(): void
    {
        $quotation = $this->createQuotation($this->creator);
        $task = Task::find($quotation->task_id);

        // Task yang sudah punya config divisi ini tidak muncul di dropdown create
        $this->actingAs($this->creator)
            ->get(route('water-configuration.create'))
            ->assertOk()
            ->assertDontSee($task->title);

        // Store untuk task yang sama => 422 (tidak dobel)
        $this->actingAs($this->creator)
            ->postJson(route('water-configuration.store'), [
                'task_id' => $task->id,
                'parameter_note' => 'pH',
                'items' => [['_key' => 'new-1', 'description' => 'pH::lyser pro', 'qty' => 1]],
            ])
            ->assertStatus(422);

        $this->assertSame(1, QuoteConfiguration::where('task_id', $task->id)->count());
    }
}

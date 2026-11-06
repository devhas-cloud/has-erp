<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Currency;
use App\Models\Division;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\ProjectPoSupplierApproval;
use App\Models\Quotation;
use App\Models\QuoteConfiguration;
use App\Models\QuoteConfigurationItem;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsRequestTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $creator;

    private User $approver;

    private User $userWithoutApprove;

    private AccountCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create([
            'division_name' => 'Water',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->admin = User::create([
            'username' => 'superadmin',
            'email' => 'superadmin@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'Admin',
        ]);

        $this->company = AccountCompany::create([
            'account_name' => 'PT Maju Bersama',
            'address_billing_street' => 'Jl. Industri No. 1',
            'address_billing_city' => 'Jakarta',
            'address_billing_province' => 'DKI Jakarta',
            'status' => 'Active',
        ]);

        $module = Module::create([
            'module_code' => 'MOD_GOODS_REQUEST',
            'module_name' => 'Permintaan Barang',
            'route_name' => 'goods-request',
            'icon' => 'fa fa-dolly',
            'group' => 'Purchasing',
        ]);

        $this->creator = $this->makeUser('creator', $module, ['can_create' => true, 'can_update' => true]);
        $this->approver = $this->makeUser('approver', $module, ['can_approve' => true]);
        $this->userWithoutApprove = $this->makeUser('nobody', $module, []);
    }

    private function makeUser(string $username, Module $module, array $access): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        UserAccessControl::create(array_merge([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'can_create' => false,
            'can_read' => true,
            'can_update' => false,
            'can_delete' => false,
            'can_approve' => false,
        ], $access));

        return $user;
    }

    private function createOpportunity(string $name = 'SPARING Kawasan Industri Gresik'): Opportunity
    {
        return Opportunity::create([
            'opportunity_name' => $name,
            'account_companies_id' => $this->company->id,
            'owner_id' => $this->admin->id,
            'probability' => 70,
        ]);
    }

    private function createQuotation(?Opportunity $opportunity = null): Quotation
    {
        $opportunity ??= $this->createOpportunity();

        return Quotation::create([
            'opportunity_id' => $opportunity->id,
            'status' => Quotation::STATUS_FINISH,
            'created_by' => $this->admin->id,
            'quotation_number' => '001/HAS/QT-T/VIII/2026',
            'to_name' => 'PT Maju Bersama',
        ]);
    }

    private function createProject(?Quotation $quotation = null, string $poNumber = 'PO-CUST-001'): Project
    {
        static $seq = 0;
        $seq++;

        return Project::create([
            'project_number' => now()->format('ym').str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'project_name' => 'Project SPARING '.$seq,
            'quotation_id' => $quotation?->id,
            'company_id' => $this->company->id,
            'po_number_company' => $poNumber === 'PO-CUST-001' ? 'PO-CUST-001-'.$seq : $poNumber,
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1000000,
            'net_sale_amount' => 900000,
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }

    private function approveProject(Project $project, int $count = 2): Project
    {
        $users = array_slice([$this->admin->id, $this->creator->id, $this->approver->id], 0, $count);
        foreach ($users as $userId) {
            ProjectPoSupplierApproval::create(['project_id' => $project->id, 'user_id' => $userId, 'approved_at' => now()]);
        }

        return $project->fresh();
    }

    /**
     * Project aktif dengan kuorum PO Supplier Approval (2 approver berbeda)
     * terpenuhi dan terikat quotation ber-opportunity — eligible untuk
     * diajukan Permintaan Barang.
     */
    private function product(string $code = 'PN-1', ?string $name = null): MasterProduct
    {
        return MasterProduct::firstOrCreate(['code' => $code], ['name' => $name ?? 'Produk '.$code, 'division_id' => $this->division->id, 'status' => 'Active']);
    }

    private function createEligibleProject(?Opportunity $opportunity = null): Project
    {
        return $this->approveProject($this->createProject($this->createQuotation($opportunity)));
    }

    public function test_create_page_only_lists_eligible_projects(): void
    {
        $eligible = $this->createEligibleProject();

        $opportunity2 = $this->createOpportunity('Opportunity Lain');
        $notReady = $this->approveProject($this->createProject($this->createQuotation($opportunity2)), 1);

        $inactive = $this->approveProject($this->createProject($this->createQuotation($opportunity2)));
        $inactive->update(['status' => Project::STATUS_COMPLETED]);

        $response = $this->actingAs($this->creator)->get(route('goods-request.create'));
        $response->assertOk();
        $response->assertSee($eligible->project_name);
        $response->assertDontSee($notReady->project_name);
        $response->assertDontSee($inactive->project_name);
    }

    public function test_store_rejects_ineligible_project(): void
    {
        $project = $this->createProject($this->createQuotation());

        $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'project_id' => $project->id,
            'items' => [],
        ])->assertStatus(422);

        $this->assertSame(0, GoodsRequest::count());
    }

    public function test_store_allows_project_without_quotation(): void
    {
        // Project retail: tanpa quotation/opportunity, item dari katalog produk.
        $project = $this->approveProject($this->createProject());
        $product = $this->product('PN-MANUAL', 'Barang manual');

        $response = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_PROJECT,
            'project_id' => $project->id,
            'items' => [
                ['master_product_id' => $product->id, 'part_number' => 'PN-MANUAL', 'description' => 'Barang manual', 'qty' => 1, 'unit' => 'Unit'],
            ],
        ])->assertOk();

        $gr = GoodsRequest::findOrFail($response->json('id'));
        $this->assertSame($project->id, $gr->project_id);
        $this->assertNull($gr->quotation());
        $this->assertSame('PN-MANUAL', $gr->items->first()->part_number);
    }

    public function test_store_internal_request_without_project(): void
    {
        $product = $this->product();

        $response = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_INTERNAL,
            'items' => [
                ['master_product_id' => $product->id, 'description' => 'Sensor', 'qty' => 2, 'unit' => 'Unit'],
            ],
        ])->assertOk();

        $gr = GoodsRequest::findOrFail($response->json('id'));
        $this->assertSame(GoodsRequest::PURPOSE_INTERNAL, $gr->purpose);
        $this->assertNull($gr->project_id);
        $this->assertFalse($gr->isForProject());
    }

    public function test_store_requires_project_for_project_purpose_and_product_per_item(): void
    {
        $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_PROJECT,
            'items' => [['master_product_id' => $this->product()->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('project_id');

        $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_INTERNAL,
            'items' => [['description' => 'Tanpa produk', 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.master_product_id');
    }

    public function test_create_page_lists_eligible_project_without_quotation(): void
    {
        $project = $this->approveProject($this->createProject());

        $this->actingAs($this->creator)->get(route('goods-request.create'))
            ->assertOk()
            ->assertSee($project->project_name);
    }

    public function test_store_persists_request_with_opportunity_items_and_creator_division(): void
    {
        $project = $this->createEligibleProject();

        $response = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_PROJECT,
            'project_id' => $project->id,
            'notes' => 'Mohon segera diproses',
            'items' => [
                ['master_product_id' => $this->product('PN-1')->id, 'part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit'],
                ['master_product_id' => $this->product('PN-2')->id, 'part_number' => 'PN-2', 'description' => 'Sensor B', 'qty' => 1, 'unit' => 'Unit'],
            ],
        ])->assertOk();

        $gr = GoodsRequest::findOrFail($response->json('id'));
        $this->assertSame($project->id, $gr->project_id);
        $this->assertSame(GoodsRequest::PURPOSE_PROJECT, $gr->purpose);
        $this->assertSame($project->quotation_id, $gr->quotation()?->id);
        $this->assertSame($this->division->id, $gr->division_id);
        $this->assertSame(GoodsRequest::STATUS_DRAFT, $gr->status);
        $this->assertSame('Mohon segera diproses', $gr->notes);
        $this->assertCount(2, $gr->items);
        $this->assertSame('PN-1', $gr->items->first()->part_number);
        $this->assertArrayNotHasKey('price', $gr->items->first()->getAttributes());
    }

    public function test_store_rejected_for_user_without_module_access(): void
    {
        $project = $this->createEligibleProject();

        $this->actingAs($this->userWithoutApprove)->postJson(route('goods-request.store'), [
            'project_id' => $project->id,
            'items' => [],
        ])->assertStatus(403);
    }

    /**
     * User yang HANYA diberi can_update (tanpa can_create) harus tetap bisa
     * mengubah permintaan barang Draft — permission gate untuk update() adalah
     * can_update lewat middleware (PUT), bukan gate tambahan di controller.
     */
    public function test_update_allowed_for_user_with_only_can_update_permission(): void
    {
        $module = Module::where('module_code', 'MOD_GOODS_REQUEST')->first();
        $updateOnlyUser = $this->makeUser('updateonly', $module, ['can_update' => true]);

        $project = $this->createEligibleProject();
        $gr = GoodsRequest::create([
            'project_id' => $project->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($updateOnlyUser)->putJson(route('goods-request.update', $gr->id), [
            'project_id' => $project->id,
            'notes' => 'Update oleh user can_update-only',
            'items' => [],
        ])->assertOk();

        $this->assertSame('Update oleh user can_update-only', $gr->fresh()->notes);
    }

    /**
     * User can_update-only TIDAK boleh membuat (POST store butuh can_create) —
     * konsisten dengan mapping middleware: POST -> can_create.
     */
    public function test_store_blocked_for_user_with_only_can_update_permission(): void
    {
        $module = Module::where('module_code', 'MOD_GOODS_REQUEST')->first();
        $updateOnlyUser = $this->makeUser('updateonly', $module, ['can_update' => true]);

        $project = $this->createEligibleProject();

        $this->actingAs($updateOnlyUser)->postJson(route('goods-request.store'), [
            'project_id' => $project->id,
            'items' => [],
        ])->assertStatus(403);

        $this->assertSame(0, GoodsRequest::count());
    }

    /**
     * User can_update-only TIDAK bisa submit (POST submit butuh can_create) —
     * state transition ditangani middleware dengan hak yang sama seperti create,
     * konsisten dengan modul configuration lain ("sebelumnya").
     */
    public function test_submit_blocked_for_user_with_only_can_update_permission(): void
    {
        $module = Module::where('module_code', 'MOD_GOODS_REQUEST')->first();
        $updateOnlyUser = $this->makeUser('updateonly', $module, ['can_update' => true]);

        $project = $this->createEligibleProject();
        $gr = GoodsRequest::create([
            'project_id' => $project->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);
        $gr->items()->create(['description' => 'Item', 'qty' => 1]);

        $this->actingAs($updateOnlyUser)->postJson(route('goods-request.submit', $gr->id))->assertStatus(403);

        $this->assertSame(GoodsRequest::STATUS_DRAFT, $gr->fresh()->status);
    }

    /**
     * User yang HANYA diberi can_delete (tanpa can_create/can_update) harus
     * tetap bisa menghapus permintaan barang Draft — permission gate untuk
     * destroy() adalah can_delete lewat middleware (DELETE).
     */
    public function test_destroy_allowed_for_user_with_only_can_delete_permission(): void
    {
        $module = Module::where('module_code', 'MOD_GOODS_REQUEST')->first();
        $deleteOnlyUser = $this->makeUser('deleteonly', $module, ['can_delete' => true]);

        $project = $this->createEligibleProject();
        $gr = GoodsRequest::create([
            'project_id' => $project->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($deleteOnlyUser)->deleteJson(route('goods-request.destroy', $gr->id))->assertOk();

        $this->assertSame(0, GoodsRequest::count());
    }

    public function test_submit_requires_at_least_one_item(): void
    {
        $project = $this->createEligibleProject();
        $gr = GoodsRequest::create([
            'project_id' => $project->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator)->postJson(route('goods-request.submit', $gr->id))->assertStatus(422);

        $gr->items()->create(['description' => 'Item', 'qty' => 1]);
        $this->actingAs($this->creator)->postJson(route('goods-request.submit', $gr->id))->assertOk();

        $this->assertSame(GoodsRequest::STATUS_WAITING_APPROVAL, $gr->fresh()->status);
    }

    private function waitingRequest(?int $divisionId = null): GoodsRequest
    {
        $project = $this->createEligibleProject();
        $gr = GoodsRequest::create([
            'project_id' => $project->id,
            'division_id' => $divisionId ?? $this->division->id,
            'status' => GoodsRequest::STATUS_WAITING_APPROVAL,
            'created_by' => $this->creator->id,
        ]);
        $gr->items()->create(['description' => 'Item', 'qty' => 1]);

        return $gr;
    }

    private function otherDivision(): Division
    {
        return Division::firstOrCreate(['division_name' => 'IMS'], ['description' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);
    }

    public function test_request_is_approved_by_a_single_approver(): void
    {
        $this->assertSame(1, GoodsRequest::REQUIRED_APPROVALS);
        $gr = $this->waitingRequest();
        $secondApprover = $this->makeUser('approver2', Module::where('module_code', GoodsRequest::MODULE_CODE)->first(), ['can_approve' => true]);

        $this->actingAs($this->userWithoutApprove)->postJson(route('goods-request.approve', $gr->id))->assertStatus(403);

        $this->actingAs($this->approver)->postJson(route('goods-request.approve', $gr->id))
            ->assertOk()
            ->assertJsonPath('approval_count', 1)
            ->assertJsonPath('approved', true)
            ->assertJsonPath('message', 'Permintaan barang Approved dan siap diproses PO.');

        $fresh = $gr->fresh();
        $this->assertSame(GoodsRequest::STATUS_APPROVED, $fresh->status);
        $this->assertSame($this->approver->id, $fresh->final_checked_by);
        $this->assertNotNull($fresh->approved_at);
        $this->assertSame([$this->approver->id], $fresh->approvals->pluck('user_id')->all());

        // Sudah Approved: approver lain tidak bisa menambah approval.
        $this->actingAs($secondApprover)->postJson(route('goods-request.approve', $gr->id))->assertStatus(422);
        $this->assertSame(1, $gr->approvals()->count());

        $this->actingAs($secondApprover)->get(route('goods-request.show', $gr->id))
            ->assertOk()
            ->assertSee('approver')
            ->assertDontSee('1/1');
    }

    public function test_creator_with_approve_access_can_approve_own_request(): void
    {
        $gr = $this->waitingRequest();
        $gr->update(['created_by' => $this->approver->id]);

        $this->actingAs($this->approver)->get(route('goods-request.show', $gr->id))
            ->assertOk()
            ->assertSee('grApprove()', false);

        $this->actingAs($this->approver)->postJson(route('goods-request.approve', $gr->id))
            ->assertOk()
            ->assertJsonPath('approved', true);
        $this->assertSame(GoodsRequest::STATUS_APPROVED, $gr->fresh()->status);
    }

    private function financeDivision(): Division
    {
        return Division::firstOrCreate(['division_name' => 'Finance'], ['description' => 'Finance', 'type' => 'Internal', 'status' => 'Active']);
    }

    public function test_non_finance_approver_cannot_approve_other_division_request(): void
    {
        $gr = $this->waitingRequest($this->otherDivision()->id);
        $gr->update(['created_by' => $this->admin->id]);

        $this->actingAs($this->approver)->postJson(route('goods-request.approve', $gr->id))->assertNotFound();
        $this->assertSame(0, $gr->approvals()->count());

        $this->approver->update(['division_id' => $this->financeDivision()->id]);
        $this->actingAs($this->approver->fresh())->postJson(route('goods-request.approve', $gr->id))->assertOk();
        $this->assertSame(1, $gr->approvals()->count());
    }

    public function test_reject_stops_the_request(): void
    {
        $gr = $this->waitingRequest();

        $this->actingAs($this->approver)->postJson(route('goods-request.reject', $gr->id), [
            'approval_note' => 'Anggaran belum tersedia',
        ])->assertOk();
        $this->assertSame(GoodsRequest::STATUS_REJECTED, $gr->fresh()->status);

        $this->actingAs($this->admin)->postJson(route('goods-request.approve', $gr->id))->assertStatus(422);
        $this->assertSame(0, $gr->approvals()->count());
    }

    public function test_only_finance_approvers_see_all_divisions(): void
    {
        $own = $this->waitingRequest();
        $other = $this->waitingRequest($this->otherDivision()->id);
        $other->update(['created_by' => $this->admin->id]);

        $ids = fn (User $user) => collect($this->actingAs($user->fresh())->getJson(route('goods-request.data'))->assertOk()->json('data'))->pluck('id')->all();

        // Tanpa can_approve -> hanya divisinya.
        $this->assertSame([$own->id], $ids($this->userWithoutApprove));
        $this->actingAs($this->userWithoutApprove)->get(route('goods-request.show', $other->id))->assertNotFound();

        // can_approve tapi bukan Finance -> tetap hanya divisinya.
        $this->assertSame([$own->id], $ids($this->approver));

        // Divisi Finance + can_approve -> semua divisi (nama dicocokkan case-insensitive).
        $finance = Division::create(['division_name' => 'FINANCE', 'description' => 'Finance', 'type' => 'Internal', 'status' => 'Active']);
        $this->approver->update(['division_id' => $finance->id]);
        $this->assertEqualsCanonicalizing([$own->id, $other->id], $ids($this->approver));

        // Divisi Finance tanpa can_approve -> hanya divisinya (Finance), tidak ada permintaan.
        $this->userWithoutApprove->update(['division_id' => $finance->id]);
        $this->assertSame([], $ids($this->userWithoutApprove));

        $this->assertEqualsCanonicalizing([$own->id, $other->id], $ids($this->admin));

        // User tanpa divisi hanya melihat permintaan buatannya sendiri.
        $this->creator->update(['division_id' => null]);
        $this->assertSame([$own->id], $ids($this->creator));
    }

    public function test_reject_requires_approval_note(): void
    {
        $project = $this->createEligibleProject();
        $gr = GoodsRequest::create([
            'project_id' => $project->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_WAITING_APPROVAL,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->approver)->postJson(route('goods-request.reject', $gr->id), [])->assertStatus(422);

        $this->actingAs($this->approver)->postJson(route('goods-request.reject', $gr->id), [
            'approval_note' => 'Barang belum diperlukan',
        ])->assertOk();

        $fresh = $gr->fresh();
        $this->assertSame(GoodsRequest::STATUS_REJECTED, $fresh->status);
        $this->assertSame('Barang belum diperlukan', $fresh->approval_note);
    }

    /**
     * Item configuration yang muncul di picker "Ambil dari Configuration"
     * mengikuti opportunity_id (live master data lintas divisi), bukan
     * snapshot satu quotation saja.
     */
    public function test_fetch_config_items_filters_by_opportunity_and_approved_status(): void
    {
        $project = $this->createEligibleProject();
        $opportunity = $project->quotation->opportunity;

        $config = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'opportunity_id' => $opportunity->id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ]);
        $config->update(['group_id' => $config->id]);

        QuoteConfigurationItem::create([
            'quote_configuration_id' => $config->id,
            'category' => 'SPARING',
            'part_number' => 'A-1',
            'description' => 'Sensor A',
            'qty' => 1,
            'selling_price' => 100000,
            'sort_order' => 1,
        ]);

        // Configuration draft di opportunity yang sama TIDAK boleh muncul.
        $draftConfig = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'opportunity_id' => $opportunity->id,
            'group_id' => 2,
            'version' => 1,
            'is_current' => true,
            'status' => QuoteConfiguration::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);
        $draftConfig->update(['group_id' => $draftConfig->id]);
        QuoteConfigurationItem::create([
            'quote_configuration_id' => $draftConfig->id,
            'part_number' => 'B-1',
            'description' => 'Belum Approved',
            'qty' => 1,
            'sort_order' => 1,
        ]);

        // Configuration approved di opportunity LAIN juga tidak boleh muncul.
        $otherOpportunity = $this->createOpportunity('Opportunity Lain');
        $otherConfig = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'opportunity_id' => $otherOpportunity->id,
            'group_id' => 3,
            'version' => 1,
            'is_current' => true,
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ]);
        $otherConfig->update(['group_id' => $otherConfig->id]);
        QuoteConfigurationItem::create([
            'quote_configuration_id' => $otherConfig->id,
            'part_number' => 'C-1',
            'description' => 'Opportunity Lain',
            'qty' => 1,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->creator)
            ->getJson(route('goods-request.fetch-config-items', ['opportunity_id' => $opportunity->id]))
            ->assertOk();

        $this->assertTrue($response->json('success'));
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('A-1', $response->json('data.0.part_number'));
        $this->assertSame('Water', $response->json('data.0.division_name'));
        $this->assertArrayHasKey('quote_configuration_item_id', $response->json('data.0'));
    }

    /**
     * Urutan item di picker mengikuti hierarki parent -> anak
     * (QuoteConfiguration::flattenTree()), bukan dikelompokkan per kategori.
     */
    public function test_fetch_config_items_orders_parent_before_children_with_depth(): void
    {
        $project = $this->createEligibleProject();
        $opportunity = $project->quotation->opportunity;

        $config = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'opportunity_id' => $opportunity->id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ]);
        $config->update(['group_id' => $config->id]);

        $parent = QuoteConfigurationItem::create([
            'quote_configuration_id' => $config->id,
            'item_no' => '1',
            'category' => 'SPARING',
            'part_number' => 'A-1',
            'description' => 'Parent A',
            'qty' => 1,
            'sort_order' => 1,
        ]);
        QuoteConfigurationItem::create([
            'quote_configuration_id' => $config->id,
            'parent_id' => $parent->id,
            'item_no' => '1.1',
            'category' => 'SPARING',
            'part_number' => 'A-1-1',
            'description' => 'Child of A',
            'qty' => 1,
            'sort_order' => 2,
        ]);
        QuoteConfigurationItem::create([
            'quote_configuration_id' => $config->id,
            'item_no' => '2',
            'category' => 'RO',
            'part_number' => 'B-1',
            'description' => 'Parent B (kategori beda, harus tetap setelah subtree A)',
            'qty' => 1,
            'sort_order' => 3,
        ]);

        $response = $this->actingAs($this->creator)
            ->getJson(route('goods-request.fetch-config-items', ['opportunity_id' => $opportunity->id]))
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);
        // Urutan DFS: parent A, child A, lalu B — bukan dikelompokkan per kategori.
        $this->assertSame(['A-1', 'A-1-1', 'B-1'], array_column($data, 'part_number'));
        $this->assertSame([0, 1, 0], array_column($data, 'depth'));
        $this->assertArrayNotHasKey('category', $data[0]);
    }

    /**
     * Modal "Tambah Baris Manual" mengambil dari katalog Master Product —
     * tanpa menampilkan harga sama sekali (harga baru ditentukan saat PO ke
     * supplier), hanya part number/nama barang dan divisi.
     */
    public function test_search_products_excludes_price_and_filters_by_search_term(): void
    {
        MasterProduct::create(['name' => 'pH Sensor', 'code' => 'PH-100', 'division_id' => $this->division->id, 'selling_price' => 5000000, 'status' => 'Active']);
        MasterProduct::create(['name' => 'Flow Meter', 'code' => 'FM-200', 'division_id' => $this->division->id, 'selling_price' => 2000000, 'status' => 'Active']);
        MasterProduct::create(['name' => 'Nonaktif', 'code' => 'OFF-1', 'division_id' => $this->division->id, 'selling_price' => 1000, 'status' => 'Inactive']);

        // Produk divisi lain tetap muncul — katalog tidak dibatasi divisi user.
        $otherDivision = Division::create(['division_name' => 'IMS', 'description' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);
        MasterProduct::create(['name' => 'Dosing Pump', 'code' => 'DP-300', 'division_id' => $otherDivision->id, 'status' => 'Active']);

        $response = $this->actingAs($this->creator)
            ->getJson(route('goods-request.search-products'))
            ->assertOk();

        $data = collect($response->json('data'));
        $this->assertSame(3, $data->count());
        $this->assertContains('DP-300', $data->pluck('code')->all());
        $this->assertArrayNotHasKey('price', $data->first());

        $filtered = $this->actingAs($this->creator)
            ->getJson(route('goods-request.search-products', ['search' => ['value' => 'pH']]))
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $filtered);
        $this->assertSame('PH-100', $filtered[0]['code']);
    }

    public function test_search_products_supports_paging_and_category_division_filters(): void
    {
        $otherDivision = Division::create(['division_name' => 'IMS', 'description' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);
        foreach (range(1, 12) as $i) {
            MasterProduct::create([
                'name' => sprintf('Sensor %02d', $i),
                'code' => 'S-'.$i,
                'category' => $i <= 4 ? 'Sensor pH' : 'Sensor DO',
                'division_id' => $i <= 4 ? $otherDivision->id : $this->division->id,
                'status' => 'Active',
            ]);
        }

        $page2 = $this->actingAs($this->creator)
            ->getJson(route('goods-request.search-products', ['per_page' => 5, 'page' => 2, 'with_filters' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 6)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.name', 'Sensor 06');
        $this->assertSame(['Sensor DO', 'Sensor pH'], $page2->json('filters.categories'));
        $this->assertCount(2, $page2->json('filters.divisions'));

        $this->actingAs($this->creator)
            ->getJson(route('goods-request.search-products', ['category' => 'Sensor pH']))
            ->assertJsonPath('meta.total', 4)
            ->assertJsonMissingPath('filters');

        $this->actingAs($this->creator)
            ->getJson(route('goods-request.search-products', ['division_id' => $this->division->id, 'q' => 'Sensor 1']))
            ->assertJsonPath('meta.total', 3); // Sensor 10, 11, 12
    }

    public function test_store_persists_master_product_traceability_on_item(): void
    {
        $project = $this->createEligibleProject();
        $product = MasterProduct::create(['name' => 'pH Sensor', 'code' => 'PH-100', 'division_id' => $this->division->id, 'status' => 'Active']);

        $response = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_PROJECT,
            'project_id' => $project->id,
            'items' => [
                ['master_product_id' => $product->id, 'part_number' => $product->code, 'description' => $product->name, 'qty' => 3, 'unit' => 'Unit'],
            ],
        ])->assertOk();

        $gr = GoodsRequest::findOrFail($response->json('id'));
        $this->assertSame($product->id, $gr->items->first()->master_product_id);
    }

    /**
     * Deskripsi item disimpan lewat sanitizeDescription() (sama seperti
     * Quotation/Water Configuration) — tag berbahaya dibuang, tapi <br> dan
     * format ringan (bold/italic/underline) yang datang dari editor
     * contenteditable dipertahankan apa adanya.
     */
    public function test_store_sanitizes_item_description_but_keeps_safe_html(): void
    {
        $project = $this->createEligibleProject();

        $richDescription = 'Range <br>- pH : 0-14<br>- Conductivity : 0-200.000 uS/cm<br>- TDS : 0-130 ppt<br>- Salinity : 0-180 PSU<br>- Temperature : -5 - 70°C';
        $withScript = $richDescription.'<script>alert(1)</script>';

        $response = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_PROJECT,
            'project_id' => $project->id,
            'items' => [
                ['master_product_id' => $this->product()->id, 'description' => $withScript, 'qty' => 1, 'unit' => 'Unit'],
            ],
        ])->assertOk();

        $gr = GoodsRequest::findOrFail($response->json('id'));
        $saved = $gr->items->first()->description;

        $this->assertSame($richDescription, $saved);
        $this->assertStringNotContainsString('<script>', $saved);
    }

    /**
     * Halaman edit, picker "Ambil dari Configuration", dan picker Master
     * Product semuanya mengembalikan deskripsi lewat
     * Quotation::renderDescription() supaya newline mentah (kalau ada) ikut
     * dikonversi ke <br> dan tetap konsisten dengan pola contenteditable di
     * water-configuration/form.blade.php.
     */
    public function test_fetch_config_items_and_search_products_render_description_line_breaks(): void
    {
        $project = $this->createEligibleProject();
        $opportunity = $project->quotation->opportunity;

        $config = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'opportunity_id' => $opportunity->id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ]);
        $config->update(['group_id' => $config->id]);

        QuoteConfigurationItem::create([
            'quote_configuration_id' => $config->id,
            'part_number' => 'A-1',
            'description' => "Range\n- pH : 0-14\n- TDS : 0-130 ppt",
            'qty' => 1,
            'sort_order' => 1,
        ]);

        $configResponse = $this->actingAs($this->creator)
            ->getJson(route('goods-request.fetch-config-items', ['opportunity_id' => $opportunity->id]))
            ->assertOk();
        $this->assertSame('Range<br>- pH : 0-14<br>- TDS : 0-130 ppt', $configResponse->json('data.0.description'));

        MasterProduct::create([
            'name' => 'pH Sensor',
            'code' => 'PH-100',
            'division_id' => $this->division->id,
            'description' => "Baris satu\nBaris dua",
            'status' => 'Active',
        ]);

        $productResponse = $this->actingAs($this->creator)
            ->getJson(route('goods-request.search-products'))
            ->assertOk();
        $this->assertSame('Baris satu<br>Baris dua', $productResponse->json('data.0.description'));
    }

    public function test_index_and_show_pages_render(): void
    {
        $project = $this->createEligibleProject();
        $gr = GoodsRequest::create([
            'project_id' => $project->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator)->get(route('goods-request.index'))->assertOk();
        $this->actingAs($this->creator)->get(route('goods-request.show', $gr->id))->assertOk();
    }

    public function test_data_filters_by_status_and_returns_counts_without_quotation_column(): void
    {
        $project = $this->createEligibleProject();
        $make = fn (string $status) => GoodsRequest::create([
            'project_id' => $project->id,
            'division_id' => $this->division->id,
            'status' => $status,
            'created_by' => $this->creator->id,
        ]);
        $draft = $make(GoodsRequest::STATUS_DRAFT);
        $draft->items()->createMany([
            ['description' => 'A', 'qty' => 2, 'sort_order' => 1],
            ['description' => 'B', 'qty' => 3, 'sort_order' => 2],
        ]);
        $make(GoodsRequest::STATUS_APPROVED);
        $make(GoodsRequest::STATUS_APPROVED);

        $all = $this->actingAs($this->creator)->getJson(route('goods-request.data'))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 3)
            ->assertJsonPath('status_counts.draft', 1)
            ->assertJsonPath('status_counts.approved', 2)
            ->assertJsonPath('status_counts.rejected', 0);
        $this->assertArrayNotHasKey('quotation_number', $all->json('data.0'));

        $this->actingAs($this->creator)->getJson(route('goods-request.data', ['status' => 'draft']))
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.item_count', 2)
            ->assertJsonPath('data.0.total_qty', 5)
            ->assertJsonPath('status_counts.approved', 2);
    }

    public function test_item_currency_is_locked_to_source(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        $usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 20000, 'is_base' => false, 'status' => 'Active']);
        $eur = Currency::create(['name' => 'EUR', 'symbol' => '€', 'rate' => 18000, 'is_base' => false, 'status' => 'Active']);

        $project = $this->createEligibleProject();
        $config = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'opportunity_id' => $project->quotation->opportunity_id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ]);
        $configItem = QuoteConfigurationItem::create(['quote_configuration_id' => $config->id, 'part_number' => 'C-1', 'description' => 'Config', 'qty' => 1, 'currency' => 'usd', 'sort_order' => 1]);
        $blankConfigItem = QuoteConfigurationItem::create(['quote_configuration_id' => $config->id, 'part_number' => 'C-2', 'description' => 'Config tanpa mata uang', 'qty' => 1, 'sort_order' => 2]);
        $product = MasterProduct::create(['name' => 'Valve', 'code' => 'V-1', 'division_id' => $this->division->id, 'currency_id' => $eur->id, 'status' => 'Active']);

        // Picker configuration & katalog membawa mata uang sumbernya.
        $this->actingAs($this->creator)->getJson(route('goods-request.fetch-config-items', ['opportunity_id' => $project->quotation->opportunity_id]))
            ->assertJsonPath('data.0.currency', 'USD')
            ->assertJsonPath('data.1.currency', 'IDR');
        $this->actingAs($this->creator)->getJson(route('goods-request.search-products', ['q' => 'V-1']))
            ->assertJsonPath('data.0.currency', 'EUR');

        $id = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_PROJECT,
            'project_id' => $project->id,
            'items' => [
                // Mata uang dari client diabaikan: configuration diutamakan, lalu master produk.
                ['quote_configuration_item_id' => $configItem->id, 'master_product_id' => $product->id, 'description' => 'Config', 'qty' => 1, 'currency' => 'IDR'],
                ['quote_configuration_item_id' => $blankConfigItem->id, 'master_product_id' => $product->id, 'description' => 'Config tanpa mata uang', 'qty' => 1],
                ['master_product_id' => $product->id, 'description' => 'Valve', 'qty' => 1, 'currency' => 'USD'],
            ],
        ])->assertOk()->json('id');

        $this->assertSame(['USD', 'IDR', 'EUR'], GoodsRequest::findOrFail($id)->items->pluck('currency')->all());
        $this->actingAs($this->creator)->get(route('goods-request.show', $id))->assertOk()->assertSee('EUR');
        $this->actingAs($this->creator)->get(route('goods-request.edit', $id))->assertOk();
    }

    public function test_config_items_already_requested_are_hidden_and_rejected(): void
    {
        $project = $this->createEligibleProject();
        $config = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'opportunity_id' => $project->quotation->opportunity_id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ]);
        $first = QuoteConfigurationItem::create(['quote_configuration_id' => $config->id, 'part_number' => 'C-1', 'description' => 'Sensor', 'qty' => 1, 'sort_order' => 1]);
        $second = QuoteConfigurationItem::create(['quote_configuration_id' => $config->id, 'part_number' => 'C-2', 'description' => 'Logger', 'qty' => 1, 'sort_order' => 2]);
        $product = $this->product('C-1');
        $line = fn (QuoteConfigurationItem $item) => ['quote_configuration_item_id' => $item->id, 'master_product_id' => $product->id, 'description' => 'x', 'qty' => 1];
        $fetch = fn (?int $goodsRequestId = null) => $this->actingAs($this->creator)->getJson(route('goods-request.fetch-config-items', [
            'opportunity_id' => $project->quotation->opportunity_id,
            'goods_request_id' => $goodsRequestId,
        ]));

        $id = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_PROJECT,
            'project_id' => $project->id,
            'items' => [$line($first)],
        ])->assertOk()->json('id');

        // Permintaan lain tidak melihat C-1; saat edit permintaan pemiliknya tetap terlihat.
        $fetch()->assertJsonCount(1, 'data')->assertJsonPath('data.0.part_number', 'C-2')->assertJsonPath('hidden_count', 1);
        $fetch($id)->assertJsonCount(2, 'data')->assertJsonPath('hidden_count', 0);

        $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_PROJECT,
            'project_id' => $project->id,
            'items' => [$line($first)],
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->actingAs($this->creator)->putJson(route('goods-request.update', $id), ['items' => [$line($first), $line($second)]])->assertOk();

        // Permintaan Rejected melepas item configuration-nya.
        GoodsRequest::whereKey($id)->update(['status' => GoodsRequest::STATUS_REJECTED]);
        $fetch()->assertJsonCount(2, 'data')->assertJsonPath('hidden_count', 0);
    }

    public function test_request_number_uses_document_format_and_is_searchable(): void
    {
        $store = fn () => $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'purpose' => GoodsRequest::PURPOSE_INTERNAL,
            'items' => [['master_product_id' => $this->product('PN-9')->id, 'description' => 'Kabel', 'qty' => 1]],
        ])->assertOk()->json('id');

        $first = GoodsRequest::findOrFail($store());
        $second = GoodsRequest::findOrFail($store());

        $prefix = 'PR-'.now()->format('ym');
        $this->assertSame($prefix.'0001', $first->request_number);
        $this->assertSame($prefix.'0002', $second->request_number);

        $this->actingAs($this->creator)->getJson(route('goods-request.data', ['search' => ['value' => $prefix.'0002']]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.request_number', $prefix.'0002');
        $this->actingAs($this->creator)->get(route('goods-request.show', $second->id))->assertOk()->assertSee('Purchase Request '.$prefix.'0002');
    }
}

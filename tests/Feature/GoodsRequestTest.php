<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\QuoteConfiguration;
use App\Models\QuoteConfigurationItem;
use App\Models\Quotation;
use App\Models\QuotationPoSupplierApproval;
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
            'division_name' => 'WATER',
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

    /**
     * Quotation Finish dengan kuorum PO Supplier Approval (2 approver
     * berbeda) terpenuhi dan terikat pada satu opportunity — eligible untuk
     * diajukan Permintaan Barang.
     */
    private function createEligibleQuotation(?Opportunity $opportunity = null): Quotation
    {
        $opportunity ??= $this->createOpportunity();

        $quotation = Quotation::create([
            'opportunity_id' => $opportunity->id,
            'status' => Quotation::STATUS_FINISH,
            'created_by' => $this->admin->id,
            'quotation_number' => '001/HAS/QT-T/VIII/2026',
            'to_name' => 'PT Maju Bersama',
        ]);

        QuotationPoSupplierApproval::create(['quotation_id' => $quotation->id, 'user_id' => $this->admin->id, 'approved_at' => now()]);
        QuotationPoSupplierApproval::create(['quotation_id' => $quotation->id, 'user_id' => $this->creator->id, 'approved_at' => now()]);

        return $quotation->fresh();
    }

    public function test_create_page_only_lists_eligible_quotations(): void
    {
        $eligible = $this->createEligibleQuotation();

        $opportunity2 = $this->createOpportunity('Opportunity Lain');
        $notFinished = Quotation::create(['opportunity_id' => $opportunity2->id, 'status' => Quotation::STATUS_APPROVED, 'created_by' => $this->admin->id, 'quotation_number' => '002', 'to_name' => 'Belum Finish']);

        $notReady = Quotation::create(['opportunity_id' => $opportunity2->id, 'status' => Quotation::STATUS_FINISH, 'created_by' => $this->admin->id, 'quotation_number' => '003', 'to_name' => 'Baru 1 Approver']);
        QuotationPoSupplierApproval::create(['quotation_id' => $notReady->id, 'user_id' => $this->admin->id, 'approved_at' => now()]);

        $response = $this->actingAs($this->creator)->get(route('goods-request.create'));
        $response->assertOk();
        $response->assertSee($eligible->quotation_number);
        $response->assertDontSee('Belum Finish');
        $response->assertDontSee('Baru 1 Approver');
    }

    public function test_store_rejects_ineligible_quotation(): void
    {
        $opportunity = $this->createOpportunity();
        $quotation = Quotation::create(['opportunity_id' => $opportunity->id, 'status' => Quotation::STATUS_APPROVED, 'created_by' => $this->admin->id]);

        $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'quotation_id' => $quotation->id,
            'items' => [],
        ])->assertStatus(422);

        $this->assertSame(0, GoodsRequest::count());
    }

    public function test_store_rejects_quotation_without_opportunity(): void
    {
        // Finish + kuorum 2/2 terpenuhi, tapi tidak terikat opportunity manapun.
        $quotation = Quotation::create(['status' => Quotation::STATUS_FINISH, 'created_by' => $this->admin->id]);
        QuotationPoSupplierApproval::create(['quotation_id' => $quotation->id, 'user_id' => $this->admin->id, 'approved_at' => now()]);
        QuotationPoSupplierApproval::create(['quotation_id' => $quotation->id, 'user_id' => $this->creator->id, 'approved_at' => now()]);

        $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'quotation_id' => $quotation->id,
            'items' => [],
        ])->assertStatus(422);

        $this->assertSame(0, GoodsRequest::count());
    }

    public function test_store_persists_request_with_opportunity_items_and_creator_division(): void
    {
        $quotation = $this->createEligibleQuotation();

        $response = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'quotation_id' => $quotation->id,
            'notes' => 'Mohon segera diproses',
            'items' => [
                ['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit'],
                ['part_number' => 'PN-2', 'description' => 'Sensor B', 'qty' => 1, 'unit' => 'Unit'],
            ],
        ])->assertOk();

        $gr = GoodsRequest::findOrFail($response->json('id'));
        $this->assertSame($quotation->id, $gr->quotation_id);
        $this->assertSame($quotation->opportunity_id, $gr->opportunity_id);
        $this->assertSame($this->division->id, $gr->division_id);
        $this->assertSame(GoodsRequest::STATUS_DRAFT, $gr->status);
        $this->assertSame('Mohon segera diproses', $gr->notes);
        $this->assertCount(2, $gr->items);
        $this->assertSame('PN-1', $gr->items->first()->part_number);
        $this->assertArrayNotHasKey('price', $gr->items->first()->getAttributes());
    }

    public function test_store_rejected_for_user_without_module_access(): void
    {
        $quotation = $this->createEligibleQuotation();

        $this->actingAs($this->userWithoutApprove)->postJson(route('goods-request.store'), [
            'quotation_id' => $quotation->id,
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

        $quotation = $this->createEligibleQuotation();
        $gr = GoodsRequest::create([
            'opportunity_id' => $quotation->opportunity_id,
            'quotation_id' => $quotation->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($updateOnlyUser)->putJson(route('goods-request.update', $gr->id), [
            'quotation_id' => $quotation->id,
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

        $quotation = $this->createEligibleQuotation();

        $this->actingAs($updateOnlyUser)->postJson(route('goods-request.store'), [
            'quotation_id' => $quotation->id,
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

        $quotation = $this->createEligibleQuotation();
        $gr = GoodsRequest::create([
            'opportunity_id' => $quotation->opportunity_id,
            'quotation_id' => $quotation->id,
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

        $quotation = $this->createEligibleQuotation();
        $gr = GoodsRequest::create([
            'opportunity_id' => $quotation->opportunity_id,
            'quotation_id' => $quotation->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($deleteOnlyUser)->deleteJson(route('goods-request.destroy', $gr->id))->assertOk();

        $this->assertSame(0, GoodsRequest::count());
    }

    public function test_submit_requires_at_least_one_item(): void
    {
        $quotation = $this->createEligibleQuotation();
        $gr = GoodsRequest::create([
            'opportunity_id' => $quotation->opportunity_id,
            'quotation_id' => $quotation->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator)->postJson(route('goods-request.submit', $gr->id))->assertStatus(422);

        $gr->items()->create(['description' => 'Item', 'qty' => 1]);
        $this->actingAs($this->creator)->postJson(route('goods-request.submit', $gr->id))->assertOk();

        $this->assertSame(GoodsRequest::STATUS_WAITING_APPROVAL, $gr->fresh()->status);
    }

    public function test_approve_requires_can_approve_permission_regardless_of_division(): void
    {
        $quotation = $this->createEligibleQuotation();
        $gr = GoodsRequest::create([
            'opportunity_id' => $quotation->opportunity_id,
            'quotation_id' => $quotation->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_WAITING_APPROVAL,
            'created_by' => $this->creator->id,
        ]);
        $gr->items()->create(['description' => 'Item', 'qty' => 1]);

        // approver bukan dari divisi yang sama dengan pemohon, tetap boleh approve
        // karena approval modul ini hanya berdasarkan permission can_approve.
        $otherDivision = Division::create(['division_name' => 'IMS', 'description' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);
        $this->approver->update(['division_id' => $otherDivision->id]);

        $this->actingAs($this->userWithoutApprove)->postJson(route('goods-request.approve', $gr->id))->assertStatus(403);

        $this->actingAs($this->approver)->postJson(route('goods-request.approve', $gr->id))->assertOk();

        $fresh = $gr->fresh();
        $this->assertSame(GoodsRequest::STATUS_APPROVED, $fresh->status);
        $this->assertSame($this->approver->id, $fresh->final_checked_by);
    }

    public function test_reject_requires_approval_note(): void
    {
        $quotation = $this->createEligibleQuotation();
        $gr = GoodsRequest::create([
            'opportunity_id' => $quotation->opportunity_id,
            'quotation_id' => $quotation->id,
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
        $quotation = $this->createEligibleQuotation();
        $opportunity = $quotation->opportunity;

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
            'price' => 100000,
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
        $this->assertSame('WATER', $response->json('data.0.division_name'));
        $this->assertArrayHasKey('quote_configuration_item_id', $response->json('data.0'));
    }

    /**
     * Urutan item di picker mengikuti hierarki parent -> anak
     * (QuoteConfiguration::flattenTree()), bukan dikelompokkan per kategori.
     */
    public function test_fetch_config_items_orders_parent_before_children_with_depth(): void
    {
        $quotation = $this->createEligibleQuotation();
        $opportunity = $quotation->opportunity;

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
        MasterProduct::create(['name' => 'pH Sensor', 'code' => 'PH-100', 'division_id' => $this->division->id, 'price' => 5000000, 'status' => 'Active']);
        MasterProduct::create(['name' => 'Flow Meter', 'code' => 'FM-200', 'division_id' => $this->division->id, 'price' => 2000000, 'status' => 'Active']);
        MasterProduct::create(['name' => 'Nonaktif', 'code' => 'OFF-1', 'division_id' => $this->division->id, 'price' => 1000, 'status' => 'Inactive']);

        $response = $this->actingAs($this->creator)
            ->getJson(route('goods-request.search-products'))
            ->assertOk();

        $data = collect($response->json('data'));
        $this->assertSame(2, $data->count());
        $this->assertArrayNotHasKey('price', $data->first());

        $filtered = $this->actingAs($this->creator)
            ->getJson(route('goods-request.search-products', ['search' => ['value' => 'pH']]))
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $filtered);
        $this->assertSame('PH-100', $filtered[0]['code']);
    }

    public function test_store_persists_master_product_traceability_on_item(): void
    {
        $quotation = $this->createEligibleQuotation();
        $product = MasterProduct::create(['name' => 'pH Sensor', 'code' => 'PH-100', 'division_id' => $this->division->id, 'status' => 'Active']);

        $response = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'quotation_id' => $quotation->id,
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
        $quotation = $this->createEligibleQuotation();

        $richDescription = 'Range <br>- pH : 0-14<br>- Conductivity : 0-200.000 uS/cm<br>- TDS : 0-130 ppt<br>- Salinity : 0-180 PSU<br>- Temperature : -5 - 70°C';
        $withScript = $richDescription.'<script>alert(1)</script>';

        $response = $this->actingAs($this->creator)->postJson(route('goods-request.store'), [
            'quotation_id' => $quotation->id,
            'items' => [
                ['description' => $withScript, 'qty' => 1, 'unit' => 'Unit'],
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
        $quotation = $this->createEligibleQuotation();
        $opportunity = $quotation->opportunity;

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
        $this->assertSame("Range<br>- pH : 0-14<br>- TDS : 0-130 ppt", $configResponse->json('data.0.description'));

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
        $quotation = $this->createEligibleQuotation();
        $gr = GoodsRequest::create([
            'opportunity_id' => $quotation->opportunity_id,
            'quotation_id' => $quotation->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $this->actingAs($this->creator)->get(route('goods-request.index'))->assertOk();
        $this->actingAs($this->creator)->get(route('goods-request.show', $gr->id))->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Currency;
use App\Models\Division;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private Division $otherDivision;

    private User $admin;

    private User $creator;

    private User $approver;

    private User $userWithoutAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create([
            'division_name' => 'WATER',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->otherDivision = Division::create([
            'division_name' => 'IMS',
            'description' => 'IMS',
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

        $module = Module::create([
            'module_code' => 'MOD_PURCHASE_ORDER',
            'module_name' => 'Purchase Order',
            'route_name' => 'purchase-order',
            'icon' => 'fa fa-file-invoice-dollar',
            'group' => 'Purchasing',
        ]);

        $this->creator = $this->makeUser('creator', $module, ['can_create' => true, 'can_update' => true, 'can_delete' => true]);
        $this->approver = $this->makeUser('approver', $module, ['can_approve' => true]);
        $this->userWithoutAccess = $this->makeUser('nobody', $module, []);
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

    private function makeQuotationWithOpportunity(): Quotation
    {
        $company = AccountCompany::create([
            'account_name' => 'PT Maju Bersama',
            'address_billing_street' => 'Jl. Industri No. 1',
            'address_billing_city' => 'Jakarta',
            'address_billing_province' => 'DKI Jakarta',
            'status' => 'Active',
        ]);

        $opportunity = Opportunity::create([
            'opportunity_name' => 'SPARING Kawasan Industri Gresik',
            'account_companies_id' => $company->id,
            'owner_id' => $this->admin->id,
            'probability' => 70,
        ]);

        return Quotation::create([
            'opportunity_id' => $opportunity->id,
            'status' => Quotation::STATUS_FINISH,
            'created_by' => $this->admin->id,
            'quotation_number' => '001/HAS/QT-T/VIII/2026',
            'to_name' => 'PT Maju Bersama',
        ]);
    }

    private function makeApprovedGoodsRequest(Division $division, array $itemsData = [['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit']]): GoodsRequest
    {
        $quotation = $this->makeQuotationWithOpportunity();

        $gr = GoodsRequest::create([
            'opportunity_id' => $quotation->opportunity_id,
            'quotation_id' => $quotation->id,
            'division_id' => $division->id,
            'status' => GoodsRequest::STATUS_APPROVED,
            'created_by' => $this->creator->id,
            'final_checked_by' => $this->approver->id,
            'approved_at' => now(),
        ]);

        foreach ($itemsData as $i => $data) {
            $gr->items()->create(array_merge(['sort_order' => $i + 1], $data));
        }

        return $gr->fresh('items');
    }

    public function test_store_rejected_for_user_without_module_access(): void
    {
        $this->actingAs($this->userWithoutAccess)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier Jaya',
            'items' => [],
        ])->assertStatus(403);

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_store_requires_supplier_name(): void
    {
        $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'items' => [],
        ])->assertStatus(422);
    }

    /**
     * Item bisa berasal dari beberapa Permintaan Barang berbeda divisi
     * sekaligus, digabung dalam satu PO ke satu supplier — ini inti dari
     * "group purchasing" yang diminta.
     */
    public function test_store_persists_items_from_multiple_goods_requests_across_divisions(): void
    {
        $grWater = $this->makeApprovedGoodsRequest($this->division, [
            ['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit'],
        ]);
        $grIms = $this->makeApprovedGoodsRequest($this->otherDivision, [
            ['part_number' => 'PN-2', 'description' => 'Valve B', 'qty' => 5, 'unit' => 'Pcs'],
        ]);

        $response = $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier Jaya',
            'po_number' => 'PO-001',
            'items' => [
                [
                    'goods_request_item_id' => $grWater->items->first()->id,
                    'goods_request_id' => $grWater->id,
                    'part_number' => 'PN-1',
                    'description' => 'Sensor A',
                    'qty' => 2,
                    'unit' => 'Unit',
                    'currency' => 'IDR',
                    'price_currency' => 100000,
                    'price' => 100000,
                ],
                [
                    'goods_request_item_id' => $grIms->items->first()->id,
                    'goods_request_id' => $grIms->id,
                    'part_number' => 'PN-2',
                    'description' => 'Valve B',
                    'qty' => 5,
                    'unit' => 'Pcs',
                    'currency' => 'IDR',
                    'price_currency' => 50000,
                    'price' => 50000,
                ],
            ],
        ])->assertOk();

        $po = PurchaseOrder::with('items')->findOrFail($response->json('id'));
        $this->assertSame('PT Supplier Jaya', $po->supplier_name);
        $this->assertCount(2, $po->items);
        $this->assertSame([$this->division->id, $this->otherDivision->id], $po->items->map(fn ($i) => $i->goodsRequest->division_id)->sort()->values()->all());
        $this->assertSame(2 * 100000 + 5 * 50000, (int) $po->grandTotal());
    }

    public function test_store_sanitizes_item_description(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division);

        $rich = 'Range <br>- pH : 0-14';
        $withScript = $rich.'<script>alert(1)</script>';

        $response = $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier Jaya',
            'items' => [
                [
                    'goods_request_item_id' => $gr->items->first()->id,
                    'goods_request_id' => $gr->id,
                    'description' => $withScript,
                    'qty' => 1,
                ],
            ],
        ])->assertOk();

        $po = PurchaseOrder::with('items')->findOrFail($response->json('id'));
        $saved = $po->items->first()->description;
        $this->assertSame($rich, $saved);
        $this->assertStringNotContainsString('<script>', $saved);
    }

    /**
     * Satu item Permintaan Barang tidak boleh dipakai di lebih dari satu PO —
     * dicek di controller (pesan error jelas) DAN dijamin oleh unique
     * constraint di level DB sebagai backstop race-condition.
     */
    public function test_goods_request_item_already_used_in_another_po_is_rejected(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division);
        $itemId = $gr->items->first()->id;

        $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier Pertama',
            'items' => [
                ['goods_request_item_id' => $itemId, 'goods_request_id' => $gr->id, 'part_number' => 'PN-1', 'qty' => 2],
            ],
        ])->assertOk();

        $this->assertSame(1, PurchaseOrder::count());

        $response = $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier Kedua',
            'items' => [
                ['goods_request_item_id' => $itemId, 'goods_request_id' => $gr->id, 'part_number' => 'PN-1', 'qty' => 2],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_db_unique_constraint_blocks_duplicate_goods_request_item_id_directly(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division);
        $itemId = $gr->items->first()->id;

        $po1 = PurchaseOrder::create(['supplier_name' => 'A', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);
        $po2 = PurchaseOrder::create(['supplier_name' => 'B', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);

        PurchaseOrderItem::create(['purchase_order_id' => $po1->id, 'goods_request_item_id' => $itemId, 'goods_request_id' => $gr->id, 'sort_order' => 1]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        PurchaseOrderItem::create(['purchase_order_id' => $po2->id, 'goods_request_item_id' => $itemId, 'goods_request_id' => $gr->id, 'sort_order' => 1]);
    }

    /**
     * fetchAvailableItems: hanya item dari Permintaan Barang berstatus
     * Approved, mengelompokkan per Permintaan Barang, dan mengecualikan item
     * yang sudah dipakai di PO manapun.
     */
    public function test_fetch_available_items_only_returns_approved_and_unused_items_grouped_by_request(): void
    {
        $grApproved = $this->makeApprovedGoodsRequest($this->division, [
            ['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit'],
            ['part_number' => 'PN-2', 'description' => 'Sensor B', 'qty' => 1, 'unit' => 'Unit'],
        ]);

        $draftQuotation = $this->makeQuotationWithOpportunity();
        $grDraft = GoodsRequest::create([
            'opportunity_id' => $draftQuotation->opportunity_id,
            'quotation_id' => $draftQuotation->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);
        $grDraft->items()->create(['part_number' => 'PN-DRAFT', 'description' => 'Belum Approved', 'qty' => 1, 'sort_order' => 1]);

        // Pakai satu item lewat PO lain -> harus dikecualikan dari daftar tersedia.
        $po = PurchaseOrder::create(['supplier_name' => 'PT Lain', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'goods_request_item_id' => $grApproved->items->first()->id,
            'goods_request_id' => $grApproved->id,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->creator)->getJson(route('purchase-order.fetch-available-items'))->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($grApproved->id, $data[0]['goods_request_id']);
        $this->assertCount(1, $data[0]['items']);
        $this->assertSame('PN-2', $data[0]['items'][0]['part_number']);
    }

    public function test_submit_requires_at_least_one_item(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);

        $this->actingAs($this->creator)->postJson(route('purchase-order.submit', $po->id))->assertStatus(422);

        $po->items()->create(['part_number' => 'PN-1', 'qty' => 1, 'sort_order' => 1]);
        $this->actingAs($this->creator)->postJson(route('purchase-order.submit', $po->id))->assertOk();

        $this->assertSame(PurchaseOrder::STATUS_WAITING_APPROVAL, $po->fresh()->status);
    }

    public function test_approve_requires_can_approve_permission(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_WAITING_APPROVAL, 'created_by' => $this->creator->id]);

        $this->actingAs($this->userWithoutAccess)->postJson(route('purchase-order.approve', $po->id))->assertStatus(403);

        $this->actingAs($this->approver)->postJson(route('purchase-order.approve', $po->id))->assertOk();

        $fresh = $po->fresh();
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $fresh->status);
        $this->assertSame($this->approver->id, $fresh->final_checked_by);
    }

    public function test_reject_requires_approval_note(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_WAITING_APPROVAL, 'created_by' => $this->creator->id]);

        $this->actingAs($this->approver)->postJson(route('purchase-order.reject', $po->id), [])->assertStatus(422);

        $this->actingAs($this->approver)->postJson(route('purchase-order.reject', $po->id), [
            'approval_note' => 'Harga tidak sesuai',
        ])->assertOk();

        $fresh = $po->fresh();
        $this->assertSame(PurchaseOrder::STATUS_REJECTED, $fresh->status);
        $this->assertSame('Harga tidak sesuai', $fresh->approval_note);
    }

    public function test_update_and_destroy_require_draft_status(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_WAITING_APPROVAL, 'created_by' => $this->creator->id]);

        $this->actingAs($this->creator)->putJson(route('purchase-order.update', $po->id), [
            'supplier_name' => 'PT Supplier Baru',
            'items' => [],
        ])->assertStatus(422);

        $this->actingAs($this->creator)->deleteJson(route('purchase-order.destroy', $po->id))->assertStatus(422);

        $this->assertSame(1, PurchaseOrder::count());
    }

    /**
     * User yang HANYA diberi can_delete (tanpa can_create/can_update) harus
     * tetap bisa menghapus PO Draft — permission gate untuk destroy() adalah
     * can_delete lewat middleware (DELETE).
     */
    public function test_destroy_allowed_for_user_with_only_can_delete_permission(): void
    {
        $module = Module::where('module_code', 'MOD_PURCHASE_ORDER')->first();
        $deleteOnlyUser = $this->makeUser('deleteonly', $module, ['can_delete' => true]);

        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);

        $this->actingAs($deleteOnlyUser)->deleteJson(route('purchase-order.destroy', $po->id))->assertOk();

        $this->assertSame(0, PurchaseOrder::count());
    }

    /**
     * User yang HANYA diberi can_update (tanpa can_create) harus tetap bisa
     * mengubah PO Draft — permission gate untuk update() adalah can_update
     * lewat middleware (PUT), bukan gate tambahan di controller.
     */
    public function test_update_allowed_for_user_with_only_can_update_permission(): void
    {
        $module = Module::where('module_code', 'MOD_PURCHASE_ORDER')->first();
        $updateOnlyUser = $this->makeUser('updateonly', $module, ['can_update' => true]);

        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);

        $this->actingAs($updateOnlyUser)->putJson(route('purchase-order.update', $po->id), [
            'supplier_name' => 'PT Supplier Baru',
            'items' => [],
        ])->assertOk();

        $this->assertSame('PT Supplier Baru', $po->fresh()->supplier_name);
    }

    /**
     * User can_update-only TIDAK boleh membuat (POST store butuh can_create) —
     * konsisten dengan mapping middleware: POST -> can_create.
     */
    public function test_store_blocked_for_user_with_only_can_update_permission(): void
    {
        $module = Module::where('module_code', 'MOD_PURCHASE_ORDER')->first();
        $updateOnlyUser = $this->makeUser('updateonly', $module, ['can_update' => true]);

        $this->actingAs($updateOnlyUser)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier',
            'items' => [],
        ])->assertStatus(403);

        $this->assertSame(0, PurchaseOrder::count());
    }

    /**
     * User can_update-only TIDAK bisa submit (POST submit butuh can_create) —
     * state transition ditangani middleware dengan hak yang sama seperti create,
     * konsisten dengan modul configuration lain ("sebelumnya").
     */
    public function test_submit_blocked_for_user_with_only_can_update_permission(): void
    {
        $module = Module::where('module_code', 'MOD_PURCHASE_ORDER')->first();
        $updateOnlyUser = $this->makeUser('updateonly', $module, ['can_update' => true]);

        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);
        $po->items()->create(['part_number' => 'PN-1', 'qty' => 1, 'sort_order' => 1]);

        $this->actingAs($updateOnlyUser)->postJson(route('purchase-order.submit', $po->id))->assertStatus(403);

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $po->fresh()->status);
    }

    public function test_index_create_show_and_edit_pages_render(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);

        $this->actingAs($this->creator)->get(route('purchase-order.index'))->assertOk();
        $this->actingAs($this->creator)->get(route('purchase-order.create'))->assertOk();
        $this->actingAs($this->creator)->get(route('purchase-order.show', $po->id))->assertOk();
        $this->actingAs($this->creator)->get(route('purchase-order.edit', $po->id))->assertOk();
    }

    public function test_store_persists_supplier_relation_and_snapshot_fields(): void
    {
        $supplier = Supplier::create([
            'name' => 'CV. Riztech Engineering',
            'address' => "Kp. Ranca Sabir, Desa Malakasari\nKecamatan Baleendah Kab. Bandung Jawa Barat 40375",
            'phone' => '0812-2221-2911',
            'attn_name' => 'CV. Riztech',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => $supplier->name,
            'supplier_id' => $supplier->id,
            'supplier_address' => $supplier->address,
            'supplier_phone' => $supplier->phone,
            'supplier_attn' => $supplier->attn_name,
            'terms' => 'TT',
            'request_by_name' => 'Tania',
            'finance_name' => 'Limaran Bambang Slamet',
            'accounting_name' => 'Sundowo',
            'items' => [],
        ])->assertOk();

        $po = PurchaseOrder::findOrFail($response->json('id'));
        $this->assertSame($supplier->id, $po->supplier_id);
        $this->assertSame($supplier->phone, $po->supplier_phone);
        $this->assertSame('TT', $po->terms);
        $this->assertSame('Tania', $po->request_by_name);
        $this->assertSame('Limaran Bambang Slamet', $po->finance_name);
        $this->assertSame('Sundowo', $po->accounting_name);
    }

    /**
     * "PROJECT" pada dokumen cetak PO diturunkan dari opportunity milik
     * Permintaan Barang asal tiap item (bukan field terpisah) — item tanpa
     * Permintaan Barang (baris manual) masuk grup "Stock".
     */
    public function test_project_groups_derive_from_goods_request_opportunity_with_stock_fallback(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [
            ['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit'],
        ]);
        $opportunityName = $gr->opportunity->opportunity_name;

        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->creator->id]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'goods_request_item_id' => $gr->items->first()->id,
            'goods_request_id' => $gr->id,
            'part_number' => 'PN-1',
            'qty' => 2,
            'sort_order' => 1,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'part_number' => 'PN-MANUAL',
            'qty' => 1,
            'sort_order' => 2,
        ]);

        $groups = $po->fresh(['items.goodsRequest.opportunity'])->projectGroups();

        $this->assertCount(2, $groups);
        $this->assertSame($opportunityName, $groups[0]['label']);
        $this->assertCount(1, $groups[0]['items']);
        $this->assertSame('Stock', $groups[1]['label']);
        $this->assertCount(1, $groups[1]['items']);
    }

    public function test_pdf_renders_successfully_with_grouped_items_and_mixed_currency_fallback(): void
    {
        $supplier = Supplier::create(['name' => 'CV. Riztech Engineering', 'address' => 'Bandung', 'status' => 'Active']);
        $gr = $this->makeApprovedGoodsRequest($this->division, [
            ['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit'],
        ]);

        $po = PurchaseOrder::create([
            'supplier_name' => $supplier->name,
            'supplier_id' => $supplier->id,
            'supplier_address' => $supplier->address,
            'terms' => 'TT',
            'date' => now(),
            'notes' => 'Freight to Jakarta Using FedEx Account Number 365492026',
            'request_by_name' => 'Tania',
            'finance_name' => 'Limaran Bambang Slamet',
            'accounting_name' => 'Sundowo',
            'status' => PurchaseOrder::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'goods_request_item_id' => $gr->items->first()->id,
            'goods_request_id' => $gr->id,
            'part_number' => 'PN-1',
            'description' => 'Sensor A',
            'qty' => 2,
            'unit' => 'Unit',
            'currency' => 'EUR',
            'price_currency' => 941,
            'price' => 941 * 15800,
            'sort_order' => 1,
        ]);
        // Mata uang berbeda pada item kedua -> printCurrency() harus fallback ke IDR.
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'part_number' => 'PN-MANUAL',
            'description' => 'Item stock',
            'qty' => 1,
            'unit' => 'Each',
            'currency' => 'IDR',
            'price_currency' => 100000,
            'price' => 100000,
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($this->creator)->get(route('purchase-order.pdf', $po->id));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertGreaterThan(1000, strlen($response->getContent()));

        $this->assertNull($po->fresh(['items'])->printCurrency());
    }

    /**
     * Hirarki parent-child: baris group (kategori/project) sebagai parent,
     * item sebagai anak. Baris group tidak dihitung ke total.
     */
    public function test_store_persists_hierarchy_with_category_group_and_children(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [
            ['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit'],
        ]);
        $opportunityName = $gr->opportunity->opportunity_name;

        $response = $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier Jaya',
            'items' => [
                ['_key' => 'g1', 'category' => $opportunityName],
                [
                    '_key' => 'i1',
                    'parent_key' => 'g1',
                    'goods_request_item_id' => $gr->items->first()->id,
                    'goods_request_id' => $gr->id,
                    'part_number' => 'PN-1',
                    'description' => 'Sensor A',
                    'qty' => 2,
                    'unit' => 'Unit',
                    'currency' => 'IDR',
                    'price_currency' => 100000,
                    'price' => 100000,
                ],
            ],
        ])->assertOk();

        $po = PurchaseOrder::with('items')->findOrFail($response->json('id'));

        $this->assertCount(2, $po->items);

        $header = $po->items->first();
        $this->assertSame($opportunityName, $header->category);
        $this->assertTrue($header->isHeader());
        $this->assertNull($header->goods_request_item_id);

        $child = $po->items->last();
        $this->assertSame($header->id, $child->parent_id);
        $this->assertNull($child->category);

        // Total & jumlah item hanya menghitung item; baris group tidak ikut.
        $this->assertSame(2 * 100000, (int) $po->grandTotal());
        $this->assertSame(1, $po->itemCount());

        // PDF dengan baris group kategori tetap render (label grup dari hirarki).
        $this->actingAs($this->creator)->get(route('purchase-order.pdf', $po->id))->assertOk();
    }

    /**
     * Picker produk master (search-products) mengembalikan harga + mata uang
     * agar form bisa mengisi currency & price otomatis saat part number dipilih.
     */
    public function test_search_products_returns_price_and_currency(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        $usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 20000, 'is_base' => false, 'status' => 'Active']);
        $product = MasterProduct::create([
            'name' => 'pH::lyser pro',
            'code' => 'E-514-4-075',
            'division_id' => $this->division->id,
            'price' => 100,
            'currency_id' => $usd->id,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->creator)
            ->getJson(route('purchase-order.search-products').'?q=E-514')
            ->assertOk();

        $this->assertSame($product->id, $response->json('data.0.id'));
        $this->assertSame('USD', $response->json('data.0.currency'));
        $this->assertEquals(100.0, $response->json('data.0.price'));
        $this->assertEquals(20000, $response->json('data.0.rate'));
    }

    /**
     * Saat item memakai master_product_id dan user tidak mengirim harga,
     * currency + harga diisi otomatis dari master product (Q2=A: tetap bisa
     * diedit/negosiasi — nilai client dipakai bila dikirim).
     */
    public function test_store_autofills_pricing_from_master_product(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        $usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 20000, 'is_base' => false, 'status' => 'Active']);
        $product = MasterProduct::create([
            'name' => 'Sensor DO',
            'code' => 'DO-001',
            'division_id' => $this->division->id,
            'price' => 100,
            'currency_id' => $usd->id,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier Jaya',
            'items' => [
                [
                    '_key' => 'i1',
                    'master_product_id' => $product->id,
                    'part_number' => 'DO-001',
                    'description' => 'Sensor DO',
                    'qty' => 2,
                    'unit' => 'Unit',
                ],
            ],
        ])->assertOk();

        $po = PurchaseOrder::with('items')->findOrFail($response->json('id'));
        $item = $po->items->first();

        $this->assertSame($product->id, $item->master_product_id);
        $this->assertSame(100.0, (float) $item->price_currency);
        $this->assertSame('USD', $item->currency);
        $this->assertSame(2000000.0, (float) $item->price);
        $this->assertSame(4000000, (int) $po->grandTotal());
    }

    /**
     * fetchAvailableItems membawa master_product_id & project (opportunity)
     * agar form bisa membuat group kategori per project saat item diambil.
     */
    public function test_fetch_available_items_includes_master_product_and_project(): void
    {
        $product = MasterProduct::create([
            'name' => 'Sensor A',
            'code' => 'PN-1',
            'division_id' => $this->division->id,
            'price' => 50000,
            'status' => 'Active',
        ]);
        $gr = $this->makeApprovedGoodsRequest($this->division, [
            ['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit'],
        ]);
        $gr->items->first()->update(['master_product_id' => $product->id]);

        $response = $this->actingAs($this->creator)
            ->getJson(route('purchase-order.fetch-available-items'))
            ->assertOk();

        $this->assertSame($gr->opportunity->opportunity_name, $response->json('data.0.opportunity_name'));
        $this->assertSame($gr->opportunity->opportunity_name, $response->json('data.0.items.0.project_name'));
        $this->assertSame($product->id, $response->json('data.0.items.0.master_product_id'));
    }

}

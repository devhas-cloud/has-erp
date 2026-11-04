<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Currency;
use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
            'division_name' => 'Water',
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

    private function makeProject(string $name): Project
    {
        static $seq = 0;
        $seq++;

        $company = AccountCompany::firstOrCreate(['account_name' => 'PT Maju Bersama'], ['status' => 'Active']);

        return Project::create([
            'project_number' => '2610'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'project_name' => $name,
            'company_id' => $company->id,
            'po_number_company' => 'PO-CUST-'.$seq,
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1000000,
            'net_sale_amount' => 900000,
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Permintaan Barang Approved; tiap item otomatis terhubung master product
     * (kode = part_number). Tanpa project -> tujuan internal.
     */
    private function makeApprovedGoodsRequest(Division $division, array $itemsData = [['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 2, 'unit' => 'Unit']], ?Project $project = null): GoodsRequest
    {
        $gr = GoodsRequest::create([
            'purpose' => $project ? GoodsRequest::PURPOSE_PROJECT : GoodsRequest::PURPOSE_INTERNAL,
            'project_id' => $project?->id,
            'division_id' => $division->id,
            'status' => GoodsRequest::STATUS_APPROVED,
            'created_by' => $this->creator->id,
            'final_checked_by' => $this->approver->id,
            'approved_at' => now(),
        ]);

        foreach ($itemsData as $i => $data) {
            $product = MasterProduct::firstOrCreate(['code' => $data['part_number']], [
                'name' => 'Produk '.$data['part_number'],
                'division_id' => $division->id,
                'currency_id' => $data['currency_id'] ?? null,
                'purchase_price' => $data['purchase_price'] ?? 0,
                'status' => 'Active',
            ]);
            unset($data['currency_id'], $data['purchase_price']);
            $gr->items()->create(array_merge(['sort_order' => $i + 1, 'master_product_id' => $product->id], $data));
        }

        return $gr->fresh('items');
    }

    private function line(GoodsRequestItem $item, ?int $qty = null, ?float $price = 1000): array
    {
        return ['goods_request_item_id' => $item->id, 'qty' => $qty ?? $item->qty, 'price' => $price, 'description' => $item->description];
    }

    private function storePo(array $items, array $extra = []): TestResponse
    {
        return $this->actingAs($this->creator)->postJson(route('purchase-order.store'), array_merge([
            'supplier_name' => 'PT Supplier Jaya',
            'items' => $items,
        ], $extra));
    }

    private function receive(PurchaseOrder $po, int $qty): void
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'G1'], ['name' => 'Gudang 12', 'type' => Warehouse::TYPE_STOCK, 'status' => 'Active']);
        $receipt = GoodsReceipt::create([
            'receipt_number' => 'GRN-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'received_date' => now()->toDateString(),
            'status' => GoodsReceipt::STATUS_POSTED,
            'created_by' => $this->creator->id,
        ]);
        $item = $po->items()->first();
        $receipt->items()->create([
            'purchase_order_item_id' => $item->id,
            'master_product_id' => $item->master_product_id,
            'qty' => $qty,
            'destination' => 'warehouse',
            'sort_order' => 1,
        ]);
        $po->refreshReceiptStatus();
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

    public function test_po_number_is_generated_with_type_suffix_and_shared_sequence(): void
    {
        $this->travelTo('2026-10-15 09:00:00');
        $store = fn (array $extra) => $this->actingAs($this->creator)
            ->postJson(route('purchase-order.store'), array_merge(['supplier_name' => 'PT Supplier'], $extra))
            ->assertOk()
            ->json('po_number');

        // po_number dari client diabaikan; default jenis = manufacture.
        $this->assertSame('PO-26100001', $store(['po_number' => 'MANUAL-1']));
        $this->assertSame('PO-26100002-L', $store(['po_type' => 'local']));
        $this->assertSame('PO-26100003-C', $store(['po_type' => 'calibration']));
        $this->assertSame('PO-26100004', $store(['po_type' => 'manufacture']));

        $this->assertSame(PurchaseOrder::TYPE_MANUFACTURE, PurchaseOrder::where('po_number', 'PO-26100001')->value('po_type'));

        $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier',
            'po_type' => 'import',
        ])->assertStatus(422)->assertJsonValidationErrors('po_type');

        // Acuan tanggal dibuat: urutan reset di tahun berikutnya.
        $this->travelTo('2027-01-03 09:00:00');
        $this->assertSame('PO-27010001-L', $store(['po_type' => 'local', 'date' => '2026-12-30']));
    }

    public function test_changing_po_type_only_changes_suffix(): void
    {
        $this->travelTo('2026-10-15 09:00:00');
        $id = $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier',
            'po_type' => 'local',
        ])->assertOk()->json('id');
        $this->actingAs($this->creator)->postJson(route('purchase-order.store'), ['supplier_name' => 'PT Lain'])->assertOk();

        $update = function (string $type) use ($id) {
            $this->actingAs($this->creator)->putJson(route('purchase-order.update', $id), [
                'supplier_name' => 'PT Supplier',
                'po_type' => $type,
                'po_number' => 'DIABAIKAN',
            ])->assertOk();

            return PurchaseOrder::findOrFail($id);
        };

        $this->assertSame('PO-26100001-C', $update('calibration')->po_number);
        $po = $update('manufacture');
        $this->assertSame('PO-26100001', $po->po_number);
        $this->assertSame(PurchaseOrder::TYPE_MANUFACTURE, $po->po_type);
        $this->assertSame('PO-26100001-L', $update('local')->po_number);

        $this->actingAs($this->creator)->get(route('purchase-order.show', $id))
            ->assertOk()
            ->assertSee('PO-26100001-L')
            ->assertSee('Local');
        $this->actingAs($this->creator)->get(route('purchase-order.pdf', $id))->assertOk();
    }

    /**
     * Item bisa berasal dari beberapa Permintaan Barang lintas divisi,
     * digabung dalam satu PO ke satu supplier. Data produk & satuan diambil
     * dari item permintaan (server), bukan dari client.
     */
    public function test_store_persists_items_from_multiple_goods_requests_across_divisions(): void
    {
        $grA = $this->makeApprovedGoodsRequest($this->division);
        $grB = $this->makeApprovedGoodsRequest($this->otherDivision, [['part_number' => 'PN-2', 'description' => 'Sensor B', 'qty' => 5, 'unit' => 'Pcs']]);

        $id = $this->storePo([
            $this->line($grA->items->first(), 2, 1500),
            $this->line($grB->items->first(), 5, 250),
        ])->assertOk()->json('id');

        $po = PurchaseOrder::with('items')->findOrFail($id);
        $this->assertCount(2, $po->items);
        $this->assertSame($grA->items->first()->master_product_id, $po->items[0]->master_product_id);
        $this->assertSame('PN-1', $po->items[0]->part_number);
        $this->assertSame('Pcs', $po->items[1]->unit);
        $this->assertSame(1500.0, $po->items[0]->price);
        $this->assertSame(3000.0 + 1250.0, $po->grandTotal());
        $this->assertEqualsCanonicalizing(['Water', 'IMS'], $po->divisionNames());
    }

    public function test_store_sanitizes_item_description(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division);
        $line = $this->line($gr->items->first());
        $line['description'] = 'Range<br>- pH 0-14<script>alert(1)</script>';

        $id = $this->storePo([$line])->assertOk()->json('id');

        $this->assertSame('Range<br>- pH 0-14', PurchaseOrder::findOrFail($id)->items->first()->description);
    }

    public function test_item_must_come_from_approved_goods_request(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division);
        $gr->update(['status' => GoodsRequest::STATUS_WAITING_APPROVAL]);

        $this->storePo([$this->line($gr->items->first())])->assertStatus(422);
        $this->storePo([['qty' => 1]])->assertStatus(422)->assertJsonValidationErrors('items.0.goods_request_item_id');
        $this->assertSame(0, PurchaseOrder::count());
    }

    /**
     * Satu item permintaan boleh dipecah ke beberapa PO, tapi total qty tidak
     * boleh melebihi sisa kebutuhan.
     */
    public function test_goods_request_item_can_be_split_across_pos_up_to_remaining_qty(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit']]);
        $item = $gr->items->first();

        $this->storePo([$this->line($item, 6)])->assertOk();
        $this->storePo([$this->line($item, 5)])->assertStatus(422)->assertJsonPath('success', false);
        $second = $this->storePo([$this->line($item, 4)])->assertOk()->json('id');

        $this->assertSame(0, $item->fresh()->remainingQty());
        $this->assertSame('done', $item->fresh()->fulfillmentStatus());

        // Saat edit, qty baris PO itu sendiri tidak dihitung sebagai pemakai sisa.
        $this->actingAs($this->creator)->putJson(route('purchase-order.update', $second), [
            'supplier_name' => 'PT Supplier Jaya',
            'items' => [$this->line($item, 4, 2000)],
        ])->assertOk();
        $this->actingAs($this->creator)->putJson(route('purchase-order.update', $second), [
            'supplier_name' => 'PT Supplier Jaya',
            'items' => [$this->line($item, 5)],
        ])->assertStatus(422);
    }

    public function test_rejected_po_releases_goods_request_qty(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 3, 'unit' => 'Unit']]);
        $item = $gr->items->first();
        $id = $this->storePo([$this->line($item)])->assertOk()->json('id');
        $this->assertSame(0, $item->fresh()->remainingQty());

        PurchaseOrder::whereKey($id)->update(['status' => PurchaseOrder::STATUS_WAITING_APPROVAL]);
        $this->actingAs($this->approver)->postJson(route('purchase-order.reject', $id), ['approval_note' => 'Harga mahal'])->assertOk();

        $this->assertSame(3, $item->fresh()->remainingQty());
    }

    /**
     * Cancel hanya oleh approver PO, wajib alasan; barang yang sudah diterima
     * tetap, sisanya dilepas kembali ke Permintaan Barang.
     */
    public function test_cancel_releases_only_unreceived_qty(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit']]);
        $item = $gr->items->first();
        $po = PurchaseOrder::findOrFail($this->storePo([$this->line($item)])->assertOk()->json('id'));

        // Draft belum bisa dibatalkan.
        $this->actingAs($this->approver)->postJson(route('purchase-order.void', $po->id), ['cancel_reason' => 'x'])->assertStatus(422);

        $po->update(['status' => PurchaseOrder::STATUS_APPROVED]);
        $this->receive($po, 4);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->status);

        $this->actingAs($this->creator)->postJson(route('purchase-order.void', $po->id), ['cancel_reason' => 'Supplier tidak sanggup'])->assertStatus(403);
        $this->actingAs($this->approver)->postJson(route('purchase-order.void', $po->id), [])->assertStatus(422)->assertJsonValidationErrors('cancel_reason');
        $this->actingAs($this->approver)->postJson(route('purchase-order.void', $po->id), ['cancel_reason' => 'Supplier tidak sanggup'])->assertOk();

        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $po->status);
        $this->assertSame($this->approver->id, $po->cancelled_by);
        $this->assertSame(6, $item->fresh()->remainingQty());

        $this->actingAs($this->approver)->get(route('purchase-order.show', $po->id))
            ->assertOk()
            ->assertSee('Supplier tidak sanggup');
    }

    public function test_receipt_status_follows_received_qty(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 5, 'unit' => 'Unit']]);
        $po = PurchaseOrder::findOrFail($this->storePo([$this->line($gr->items->first())])->assertOk()->json('id'));
        $po->update(['status' => PurchaseOrder::STATUS_APPROVED]);

        $this->receive($po, 2);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->status);
        $this->receive($po, 3);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
        $this->assertSame(0, $po->items()->first()->remainingReceiptQty());
    }

    /**
     * Picker hanya menampilkan item Approved yang masih punya sisa; stok bebas
     * ikut sebagai informasi; filter project / internal.
     */
    public function test_fetch_available_items_returns_remaining_with_free_stock_and_filters(): void
    {
        $project = $this->makeProject('SPARING Gresik');
        $grProject = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 4, 'unit' => 'Unit']], $project);
        $grInternal = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-2', 'description' => 'Kabel', 'qty' => 2, 'unit' => 'Roll']]);
        $draft = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-3', 'description' => 'Draft', 'qty' => 1, 'unit' => 'Unit']]);
        $draft->update(['status' => GoodsRequest::STATUS_DRAFT]);

        $warehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang 12', 'type' => Warehouse::TYPE_STOCK, 'status' => 'Active']);
        StockMovement::record(['warehouse_id' => $warehouse->id, 'master_product_id' => $grProject->items->first()->master_product_id, 'type' => StockMovement::TYPE_MISC_IN, 'qty' => 7, 'created_by' => $this->admin->id]);

        $this->storePo([$this->line($grProject->items->first(), 1)])->assertOk();

        $data = $this->actingAs($this->creator)->getJson(route('purchase-order.fetch-available-items'))->assertOk()->json();
        $this->assertCount(2, $data['data']);
        $projectGroup = collect($data['data'])->firstWhere('goods_request_id', $grProject->id);
        $this->assertSame('SPARING Gresik', $projectGroup['project_name']);
        $this->assertSame(3, $projectGroup['items'][0]['remaining_qty']);
        $this->assertSame(7, $projectGroup['items'][0]['free_stock']);
        $this->assertSame($project->id, $data['projects'][0]['id']);

        $internal = $this->actingAs($this->creator)->getJson(route('purchase-order.fetch-available-items', ['project_id' => 'internal']))->json('data');
        $this->assertSame([$grInternal->id], array_column($internal, 'goods_request_id'));

        $byProject = $this->actingAs($this->creator)->getJson(route('purchase-order.fetch-available-items', ['project_id' => $project->id]))->json('data');
        $this->assertSame([$grProject->id], array_column($byProject, 'goods_request_id'));
    }

    public function test_price_defaults_to_master_product_purchase_price_when_currency_matches(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 2, 'unit' => 'Unit', 'purchase_price' => 125000]]);

        $id = $this->storePo([$this->line($gr->items->first(), 2, null)])->assertOk()->json('id');
        $this->assertSame(125000.0, PurchaseOrder::findOrFail($id)->items->first()->price);

        $picker = $this->actingAs($this->creator)->getJson(route('purchase-order.fetch-available-items'))->json('data');
        $this->assertSame([], $picker);
    }

    public function test_po_rejects_mixed_currencies_and_follows_request_currency(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        $usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 16000, 'is_base' => false, 'status' => 'Active']);

        $gr = $this->makeApprovedGoodsRequest($this->division, [
            ['part_number' => 'PN-1', 'description' => 'Sensor IDR', 'qty' => 1, 'unit' => 'Unit', 'currency' => 'IDR'],
            ['part_number' => 'PN-2', 'description' => 'Sensor USD', 'qty' => 1, 'unit' => 'Unit', 'currency' => 'USD', 'currency_id' => $usd->id, 'purchase_price' => 120],
        ]);
        [$idr, $dollar] = [$gr->items[0], $gr->items[1]];

        $this->storePo([$this->line($idr), $this->line($dollar)])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Mata uang dari client diabaikan — mengikuti item permintaan; harga tanpa kurs.
        $id = $this->storePo([$this->line($dollar, 1, null) + ['currency' => 'IDR']])->assertOk()->json('id');
        $po = PurchaseOrder::with('items')->findOrFail($id);
        $this->assertSame('USD', $po->items->first()->currency);
        $this->assertSame(120.0, $po->items->first()->price);
        $this->assertSame('USD', $po->currencyCode());
    }

    public function test_project_groups_use_project_name_or_internal_label(): void
    {
        $project = $this->makeProject('SPARING Gresik');
        $grProject = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 1, 'unit' => 'Unit']], $project);
        $grInternal = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'PN-2', 'description' => 'Kabel', 'qty' => 1, 'unit' => 'Roll']]);

        $id = $this->storePo([$this->line($grProject->items->first()), $this->line($grInternal->items->first())])->assertOk()->json('id');
        $po = PurchaseOrder::with('items.goodsRequestItem.goodsRequest.project')->findOrFail($id);

        $this->assertSame(['SPARING Gresik', 'Stok / Internal'], $po->projectLabels());
        $this->actingAs($this->creator)->get(route('purchase-order.show', $id))->assertOk()->assertSee('SPARING Gresik');
        $this->actingAs($this->creator)->get(route('purchase-order.edit', $id))->assertOk();
        $this->actingAs($this->creator)->get(route('purchase-order.pdf', $id))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_totals_apply_discount_then_ppn_and_render_on_pdf(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [['part_number' => 'HSM-1', 'description' => 'Heat Stress Monitor', 'qty' => 1, 'unit' => 'Unit']]);

        $id = $this->storePo([$this->line($gr->items->first(), 1, 1125000)], [
            'supplier_name' => 'PT Teknora Sukses Cemerlang',
            'po_type' => 'calibration',
            'delivery' => 'Franco Jakarta',
            'your_ref' => '1055/TSC/10/2026',
            'discount_percent' => 10,
            'ppn_percent' => 11,
        ])->assertOk()->json('id');

        $po = PurchaseOrder::with('items')->findOrFail($id);
        $this->assertSame('Franco Jakarta', $po->delivery);
        $this->assertSame('1055/TSC/10/2026', $po->your_ref);
        // 1.125.000 - 10% = 1.012.500, PPN 11% = 111.375, Total 1.123.875.
        $this->assertSame([
            'subtotal' => 1125000.0,
            'discount' => 112500.0,
            'after_discount' => 1012500.0,
            'ppn' => 111375.0,
            'total' => 1123875.0,
        ], $po->printTotals());

        $this->actingAs($this->creator)->postJson(route('purchase-order.store'), [
            'supplier_name' => 'PT Supplier',
            'ppn_percent' => 120,
        ])->assertStatus(422)->assertJsonValidationErrors('ppn_percent');

        $this->actingAs($this->creator)->get(route('purchase-order.show', $id))
            ->assertOk()
            ->assertSee('Sub Total 1')
            ->assertSee('1,123,875');
        $this->actingAs($this->creator)->get(route('purchase-order.pdf', $id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_rejected_po_can_be_revised_with_rev_number(): void
    {
        $gr = $this->makeApprovedGoodsRequest($this->division, [
            ['part_number' => 'PN-1', 'description' => 'Sensor A', 'qty' => 5, 'unit' => 'Unit'],
            ['part_number' => 'PN-2', 'description' => 'Logger', 'qty' => 2, 'unit' => 'Unit'],
        ]);
        [$first, $second] = $gr->items->all();

        $id = $this->storePo([$this->line($first, 4), $this->line($second)], ['po_type' => PurchaseOrder::TYPE_LOCAL, 'terms' => '30 hari'])->assertOk()->json('id');
        $source = PurchaseOrder::findOrFail($id);
        $baseNumber = substr($source->po_number, 0, 11); // PO- + 8 digit

        // Hanya PO Rejected yang bisa direvisi.
        $this->actingAs($this->creator)->postJson(route('purchase-order.revise', $id))->assertStatus(422);

        $source->update(['status' => PurchaseOrder::STATUS_REJECTED, 'approval_note' => 'Harga terlalu tinggi', 'rejected_at' => now()]);

        // Item kedua sudah di-PO di tempat lain -> dilewati saat revisi.
        $this->storePo([$this->line($second)])->assertOk();

        $this->actingAs($this->approver)->postJson(route('purchase-order.revise', $id))->assertForbidden();
        $revisionId = $this->actingAs($this->creator)->postJson(route('purchase-order.revise', $id))
            ->assertOk()
            ->assertJsonPath('redirect', route('purchase-order.edit', PurchaseOrder::max('id')))
            ->json('id');

        $revision = PurchaseOrder::with('items')->findOrFail($revisionId);
        $this->assertSame($baseNumber.'REV-L', $revision->po_number);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $revision->status);
        $this->assertSame($id, $revision->revision_of_id);
        $this->assertSame('30 hari', $revision->terms);
        $this->assertNull($revision->approval_note);
        $this->assertCount(1, $revision->items);
        $this->assertSame(4, $revision->items->first()->qty);

        // Satu PO Rejected hanya satu revisi.
        $this->actingAs($this->creator)->postJson(route('purchase-order.revise', $id))->assertStatus(422);
        $this->actingAs($this->creator)->get(route('purchase-order.show', $id))->assertOk()->assertSee($baseNumber.'REV-L');
        $this->actingAs($this->creator)->get(route('purchase-order.show', $revisionId))->assertOk()->assertSee('Revisi dari');

        // Ganti jenis saat edit: REV tetap, suffix ikut jenis baru.
        $this->actingAs($this->creator)->putJson(route('purchase-order.update', $revisionId), [
            'supplier_name' => 'PT Supplier Jaya',
            'po_type' => PurchaseOrder::TYPE_CALIBRATION,
            'items' => [['goods_request_item_id' => $first->id, 'qty' => 4, 'price' => 900]],
        ])->assertOk();
        $this->assertSame($baseNumber.'REV-C', $revision->fresh()->po_number);

        // Revisi yang ditolak lagi -> REV2, tetap nomor dasar yang sama.
        $revision->update(['status' => PurchaseOrder::STATUS_REJECTED]);
        $secondRevisionId = $this->actingAs($this->creator)->postJson(route('purchase-order.revise', $revisionId))->assertOk()->json('id');
        $this->assertSame($baseNumber.'REV2-C', PurchaseOrder::findOrFail($secondRevisionId)->po_number);

        // Nomor revisi tidak memajukan urutan PO baru.
        $nextId = $this->storePo([$this->line($first, 1)])->assertOk()->json('id');
        $this->assertSame(str_pad((string) ((int) substr($baseNumber, 7) + 2), 4, '0', STR_PAD_LEFT), substr(PurchaseOrder::findOrFail($nextId)->po_number, 7, 4));
    }
}

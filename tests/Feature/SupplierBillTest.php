<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Finance Tahap 1: Tagihan Supplier dengan 3-way match (PO x Penerimaan x Tagihan),
 * verifikasi Finance (bukan pembuat), void beralasan, status penagihan PO.
 */
class SupplierBillTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $staff;

    private User $approver;

    private Supplier $supplier;

    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'Admin']);
        $this->staff = User::create(['username' => 'staff', 'email' => 'staff@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'User']);
        $this->approver = User::create(['username' => 'finance', 'email' => 'finance@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'User']);

        $module = Module::create(['module_code' => 'MOD_SUPPLIER_BILL', 'module_name' => 'Tagihan Supplier', 'route_name' => 'supplier-bill', 'icon' => 'fa fa-file-invoice', 'group' => 'Finance']);
        UserAccessControl::create(['user_id' => $this->staff->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
        UserAccessControl::create(['user_id' => $this->approver->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => true]);

        $this->supplier = Supplier::create(['name' => 'PT Sensor Jaya', 'status' => 'Active', 'payment_term_days' => 30, 'is_pkp' => true]);

        $project = Project::create([
            'project_number' => '26100001', 'project_name' => 'SPARING Gresik',
            'company_id' => AccountCompany::create(['account_name' => 'PT Maju', 'status' => 'Active'])->id,
            'po_number_company' => 'PO-1', 'po_date' => '2026-10-01', 'po_date_received' => '2026-10-02', 'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1, 'net_sale_amount' => 1, 'status' => Project::STATUS_ACTIVE,
        ]);
        $request = GoodsRequest::create(['purpose' => GoodsRequest::PURPOSE_PROJECT, 'project_id' => $project->id, 'division_id' => $this->division->id, 'status' => GoodsRequest::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        $product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor pH', 'division_id' => $this->division->id, 'status' => 'Active']);
        $requestItem = $request->items()->create(['master_product_id' => $product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit', 'sort_order' => 1]);

        $this->po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id, 'supplier_name' => $this->supplier->name, 'po_number' => '26110001',
            'status' => PurchaseOrder::STATUS_APPROVED, 'discount_percent' => 10, 'ppn_percent' => 11, 'payment_term_days' => 30,
            'created_by' => $this->admin->id,
        ]);
        $this->po->items()->create(['goods_request_item_id' => $requestItem->id, 'master_product_id' => $product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit', 'price' => 1000, 'currency' => 'IDR', 'sort_order' => 1]);

        $this->receive(6);
    }

    private function receive(int $qty): void
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'G1'], ['name' => 'Gudang', 'type' => Warehouse::TYPE_STOCK, 'status' => 'Active']);
        $receipt = GoodsReceipt::create(['receipt_number' => 'GRN-'.uniqid(), 'purchase_order_id' => $this->po->id, 'warehouse_id' => $warehouse->id, 'received_date' => now()->toDateString(), 'status' => GoodsReceipt::STATUS_POSTED, 'created_by' => $this->admin->id]);
        $item = $this->po->items()->first();
        $receipt->items()->create(['purchase_order_item_id' => $item->id, 'master_product_id' => $item->master_product_id, 'qty' => $qty, 'destination' => 'warehouse', 'sort_order' => 1]);
        $this->po->refreshReceiptStatus();
    }

    private function storeBill(int $qty, float $price = 1000, string $invoice = 'INV-001', ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->staff)->postJson(route('supplier-bill.store'), [
            'purchase_order_id' => $this->po->id,
            'supplier_invoice_number' => $invoice,
            'tax_invoice_number' => '010.000-26.00000001',
            'bill_date' => '2026-11-02',
            'items' => [['purchase_order_item_id' => $this->po->items()->first()->id, 'qty' => $qty, 'price' => $price]],
        ]);
    }

    public function test_three_way_match_rules_and_totals(): void
    {
        $this->actingAs($this->staff)->getJson(route('supplier-bill.billable-lines', ['purchase_order_id' => $this->po->id]))
            ->assertOk()
            ->assertJsonPath('lines.0.received', 6)
            ->assertJsonPath('lines.0.billable', 6)
            ->assertJsonPath('lines.0.project_label', '26100001 — SPARING Gresik')
            ->assertJsonPath('header.payment_term_days', 30);

        // Qty melebihi yang diterima -> ditolak; harga di luar toleransi 1% -> ditolak.
        $this->storeBill(7)->assertStatus(422)->assertJsonPath('success', false);
        $this->storeBill(6, 1020)->assertStatus(422);

        $id = $this->storeBill(6, 1005)->assertOk()->json('id');
        $bill = SupplierBill::with('items')->findOrFail($id);
        $this->assertSame(SupplierBill::STATUS_DRAFT, $bill->status);
        $this->assertSame('2026-12-02', $bill->due_date->toDateString());
        $this->assertEquals(6030, $bill->subtotal);
        $this->assertEquals(603, $bill->discount_amount);
        $this->assertEquals(596.97, $bill->ppn_amount);
        $this->assertEquals(6023.97, $bill->total);
        $this->assertNotNull($bill->items->first()->project_id);

        // Barang yang sudah ditagih (draft ikut dihitung) tidak bisa ditagih dua kali; invoice dobel ditolak.
        $this->storeBill(1, 1000, 'INV-002')->assertStatus(422);
        $this->receive(2);
        $this->storeBill(1, 1000, 'INV-001')->assertStatus(422)->assertJsonPath('success', false);
        $this->storeBill(2, 1000, 'INV-002')->assertOk();

        $this->assertSame('partial', $this->po->fresh(['items.billItems.bill'])->billingStatus());
    }

    public function test_verification_and_void_releases_qty(): void
    {
        $id = $this->storeBill(6)->assertOk()->json('id');

        // Staf tanpa can_approve -> 403; approver yang juga pembuat -> ditolak (pemisahan tugas).
        $this->actingAs($this->staff)->postJson(route('supplier-bill.approve', $id))->assertForbidden();
        // Semua qty yang diterima sudah ada di draft lain -> tagihan baru ditolak.
        $this->storeBill(1, 1000, 'INV-OWN', $this->approver)->assertStatus(422);

        $this->actingAs($this->approver)->postJson(route('supplier-bill.approve', $id))->assertOk();
        $bill = SupplierBill::findOrFail($id);
        $this->assertSame(SupplierBill::STATUS_VERIFIED, $bill->status);
        $this->assertSame($this->approver->id, $bill->verified_by);
        $this->assertEquals($bill->total, $bill->outstanding());

        // Terverifikasi tidak bisa diedit / dihapus.
        $this->actingAs($this->staff)->deleteJson(route('supplier-bill.destroy', $id))->assertStatus(422);
        $this->actingAs($this->staff)->get(route('supplier-bill.edit', $id))->assertForbidden();

        $this->actingAs($this->approver)->postJson(route('supplier-bill.void', $id), [])->assertStatus(422)->assertJsonValidationErrors('void_reason');
        $this->actingAs($this->approver)->postJson(route('supplier-bill.void', $id), ['void_reason' => 'Invoice diterbitkan ulang'])->assertOk();
        $this->assertSame(SupplierBill::STATUS_VOID, $bill->fresh()->status);

        // Setelah void: qty kembali bisa ditagih, no. invoice yang sama boleh dipakai lagi.
        $this->actingAs($this->staff)->getJson(route('supplier-bill.billable-lines', ['purchase_order_id' => $this->po->id]))->assertJsonPath('lines.0.billable', 6);
        $ownBill = $this->storeBill(6, 1000, 'INV-001', $this->approver)->assertOk()->json('id');
        // Pembuat yang punya can_approve boleh memverifikasi tagihannya sendiri.
        $this->actingAs($this->approver)->get(route('supplier-bill.show', $ownBill))->assertOk()->assertSee('sb-verify-btn', false);
        $this->actingAs($this->approver)->postJson(route('supplier-bill.approve', $ownBill))->assertOk();
        $this->assertSame(SupplierBill::STATUS_VERIFIED, SupplierBill::find($ownBill)->status);
    }

    public function test_pages_edit_and_delete_draft(): void
    {
        $id = $this->storeBill(4)->assertOk()->json('id');

        foreach (['index', 'create'] as $page) {
            $this->actingAs($this->staff)->get(route('supplier-bill.'.$page))->assertOk();
        }
        $this->actingAs($this->staff)->get(route('supplier-bill.show', $id))->assertOk()->assertSee('INV-001')->assertSee('SPARING Gresik');
        $this->actingAs($this->staff)->get(route('supplier-bill.edit', $id))->assertOk();

        // Edit draft: qty milik draft ini sendiri tetap tersedia.
        $this->actingAs($this->staff)->getJson(route('supplier-bill.billable-lines', ['purchase_order_id' => $this->po->id, 'bill_id' => $id]))->assertJsonPath('lines.0.billable', 6);
        $this->actingAs($this->staff)->putJson(route('supplier-bill.update', $id), [
            'purchase_order_id' => $this->po->id,
            'supplier_invoice_number' => 'INV-001',
            'bill_date' => '2026-11-02',
            'due_date' => '2026-11-20',
            'items' => [['purchase_order_item_id' => $this->po->items()->first()->id, 'qty' => 6, 'price' => 1000]],
        ])->assertOk();
        $bill = SupplierBill::findOrFail($id);
        $this->assertSame('2026-11-20', $bill->due_date->toDateString());
        $this->assertEquals(6000 - 600 + 594, $bill->total);

        $this->actingAs($this->staff)->getJson(route('supplier-bill.data', ['status' => 'draft']))->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($this->admin)->get(route('purchase-order.show', $this->po->id))->assertOk()->assertSee($bill->bill_number)->assertSee('Ditagih Sebagian');

        $this->actingAs($this->staff)->deleteJson(route('supplier-bill.destroy', $id))->assertOk();
        $this->assertDatabaseMissing('supplier_bills', ['id' => $id]);
    }
}

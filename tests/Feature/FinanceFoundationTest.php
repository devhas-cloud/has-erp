<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance Tahap 0: data keuangan supplier, batas uang muka (khusus approver),
 * dan termin bayar terstruktur di PO / Direct Purchase.
 */
class FinanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'Admin']);
        $this->staff = User::create(['username' => 'staff', 'email' => 'staff@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'User']);

        $module = Module::create(['module_code' => 'MOD_SUPPLIER', 'module_name' => 'Supplier', 'route_name' => 'supplier', 'icon' => 'fa fa-truck', 'group' => 'Master Data']);
        UserAccessControl::create(['user_id' => $this->staff->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => false, 'can_approve' => false]);
    }

    public function test_supplier_finance_fields_and_advance_limit_requires_approver(): void
    {
        $id = $this->actingAs($this->staff)->postJson(route('supplier.store'), [
            'name' => 'PT Sensor Jaya',
            'status' => 'Active',
            'payment_term_days' => 45,
            'npwp' => '01.234.567.8-901.000',
            'is_pkp' => 1,
            'bank_name' => 'BCA',
            'bank_account_no' => '1234567890',
            'bank_account_name' => 'PT Sensor Jaya',
            'max_advance_percent' => 100,
        ])->assertOk()->json('id');

        $supplier = Supplier::findOrFail($id);
        $this->assertSame(45, $supplier->payment_term_days);
        $this->assertTrue($supplier->is_pkp);
        $this->assertSame('BCA', $supplier->bank_name);
        // Batas uang muka tidak ikut lewat form biasa.
        $this->assertEquals(0, $supplier->max_advance_percent);

        // Termin kosong saat update tidak menimpa nilai lama; default supplier baru 30 hari.
        $this->actingAs($this->staff)->putJson(route('supplier.update', $id), ['name' => 'PT Sensor Jaya', 'status' => 'Active'])->assertOk();
        $this->assertSame(45, $supplier->fresh()->payment_term_days);
        $other = $this->actingAs($this->staff)->postJson(route('supplier.store'), ['name' => 'CV Lain', 'status' => 'Active'])->json('id');
        $this->assertSame(30, Supplier::find($other)->payment_term_days);

        $this->actingAs($this->staff)->postJson(route('supplier.advance.approve', $id), ['max_advance_percent' => 100])->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('supplier.advance.approve', $id), ['max_advance_percent' => 101])->assertStatus(422);
        $this->actingAs($this->admin)->postJson(route('supplier.advance.approve', $id), ['max_advance_percent' => 100])->assertOk();
        $this->assertEquals(100, $supplier->fresh()->max_advance_percent);

        $this->actingAs($this->staff)->getJson(route('supplier.search', ['q' => 'Sensor']))
            ->assertOk()->assertJsonPath('results.0.payment_term_days', 45);
        $this->actingAs($this->staff)->getJson(route('supplier.data'))
            ->assertOk()->assertJsonPath('data.0.payment_term_label', 'Net 30 Days');
        $this->actingAs($this->staff)->get(route('supplier.index'))->assertOk()->assertSee('Keuangan &amp; Pajak', false);
    }

    public function test_purchase_order_and_direct_purchase_take_supplier_payment_term(): void
    {
        $supplier = Supplier::create(['name' => 'PT Sensor Jaya', 'status' => 'Active', 'payment_term_days' => 45]);
        $product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor pH', 'division_id' => $this->division->id, 'status' => 'Active']);
        $request = GoodsRequest::create(['purpose' => GoodsRequest::PURPOSE_INTERNAL, 'division_id' => $this->division->id, 'status' => GoodsRequest::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        $item = $request->items()->create(['master_product_id' => $product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 5, 'unit' => 'Unit', 'sort_order' => 1]);

        $po = fn (array $extra) => $this->actingAs($this->admin)->postJson(route('purchase-order.store'), array_merge([
            'supplier_name' => $supplier->name,
            'supplier_id' => $supplier->id,
            'items' => [['goods_request_item_id' => $item->id, 'qty' => 1, 'price' => 1000]],
        ], $extra))->assertOk()->json('id');

        $default = PurchaseOrder::findOrFail($po([]));
        $this->assertSame(45, $default->payment_term_days);
        $custom = PurchaseOrder::findOrFail($po(['payment_term_days' => 0]));
        $this->assertSame(0, $custom->payment_term_days);

        $this->actingAs($this->admin)->get(route('purchase-order.show', $default->id))->assertOk()->assertSee('Net 45 Days');
        $this->actingAs($this->admin)->get(route('purchase-order.pdf', $custom->id))->assertOk();

        $dpId = $this->actingAs($this->admin)->postJson(route('direct-purchase.store'), [
            'supplier_name' => $supplier->name,
            'supplier_id' => $supplier->id,
            'purpose' => 'stock',
            'items' => [['master_product_id' => $product->id, 'description' => 'Sensor', 'qty' => 1, 'unit' => 'Unit', 'price' => 1000]],
        ])->assertOk()->json('id');
        $this->actingAs($this->admin)->get(route('direct-purchase.show', $dpId))->assertOk()->assertSee('Net 45 Days');
    }
}

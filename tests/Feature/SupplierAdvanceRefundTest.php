<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierRefund;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Finance Tahap 3: Uang Muka (batas % supplier, wajib dipotongkan otomatis di
 * tagihan reguler) dan Refund (PO batal / barang kurang -> Menunggu Refund).
 */
class SupplierAdvanceRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $approver;

    private Supplier $supplier;

    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'Admin']);
        $this->staff = User::create(['username' => 'kasir', 'email' => 'kasir@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);
        $this->approver = User::create(['username' => 'finance', 'email' => 'finance@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);

        foreach (['supplier-bill' => 'MOD_SUPPLIER_BILL', 'supplier-payment' => 'MOD_SUPPLIER_PAYMENT', 'supplier-refund' => 'MOD_SUPPLIER_REFUND'] as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Finance']);
            UserAccessControl::create(['user_id' => $this->staff->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
            UserAccessControl::create(['user_id' => $this->approver->id, 'module_id' => $module->id, 'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => true]);
        }

        $this->supplier = Supplier::create(['name' => 'PT Sensor Jaya', 'status' => 'Active', 'payment_term_days' => 30, 'max_advance_percent' => 0]);
        $product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor pH', 'division_id' => $division->id, 'status' => 'Active']);
        $request = GoodsRequest::create(['purpose' => GoodsRequest::PURPOSE_INTERNAL, 'division_id' => $division->id, 'status' => GoodsRequest::STATUS_APPROVED, 'created_by' => $admin->id]);
        $requestItem = $request->items()->create(['master_product_id' => $product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit', 'sort_order' => 1]);

        $this->po = PurchaseOrder::create(['supplier_id' => $this->supplier->id, 'supplier_name' => $this->supplier->name, 'po_number' => '26110001', 'status' => PurchaseOrder::STATUS_APPROVED, 'payment_term_days' => 30, 'created_by' => $admin->id]);
        $this->po->items()->create(['goods_request_item_id' => $requestItem->id, 'master_product_id' => $product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit', 'price' => 1000, 'currency' => 'IDR', 'sort_order' => 1]);
    }

    private function receive(int $qty): void
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'G1'], ['name' => 'Gudang', 'type' => Warehouse::TYPE_STOCK, 'status' => 'Active']);
        $receipt = GoodsReceipt::create(['receipt_number' => 'GRN-'.uniqid(), 'purchase_order_id' => $this->po->id, 'warehouse_id' => $warehouse->id, 'received_date' => now()->toDateString(), 'status' => GoodsReceipt::STATUS_POSTED, 'created_by' => $this->staff->id]);
        $item = $this->po->items()->first();
        $receipt->items()->create(['purchase_order_item_id' => $item->id, 'master_product_id' => $item->master_product_id, 'qty' => $qty, 'destination' => 'warehouse', 'sort_order' => 1]);
        $this->po->refreshReceiptStatus();
    }

    private function advance(float $amount, string $invoice = 'PRO-1'): TestResponse
    {
        return $this->actingAs($this->staff)->postJson(route('supplier-bill.store'), [
            'bill_type' => 'advance', 'purchase_order_id' => $this->po->id, 'supplier_invoice_number' => $invoice,
            'bill_date' => now()->toDateString(), 'advance_amount' => $amount,
        ]);
    }

    private function regular(int $qty, string $invoice): int
    {
        return $this->actingAs($this->staff)->postJson(route('supplier-bill.store'), [
            'purchase_order_id' => $this->po->id, 'supplier_invoice_number' => $invoice, 'bill_date' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $this->po->items()->first()->id, 'qty' => $qty, 'price' => 1000]],
        ])->assertOk()->json('id');
    }

    private function verify(int $billId): void
    {
        $this->actingAs($this->approver)->postJson(route('supplier-bill.approve', $billId))->assertOk();
    }

    private function payInFull(int $billId, string $ref): void
    {
        $bill = SupplierBill::findOrFail($billId);
        $paymentId = $this->actingAs($this->staff)->postJson(route('supplier-payment.store'), [
            'supplier_id' => $this->supplier->id, 'payment_date' => now()->toDateString(), 'method' => 'transfer', 'reference_number' => $ref,
            'allocations' => [['supplier_bill_id' => $bill->id, 'amount' => $bill->outstanding()]],
        ])->assertOk()->json('id');
        $this->actingAs($this->approver)->postJson(route('supplier-payment.approve', $paymentId))->assertOk();
    }

    private function freshPo(): PurchaseOrder
    {
        return PurchaseOrder::with(['supplier', 'items.goodsReceiptItems.goodsReceipt', 'items.billItems.bill', 'supplierBills.allocations.payment', 'supplierRefunds'])->findOrFail($this->po->id);
    }

    public function test_form_lists_approved_po_for_advance_with_reason_when_limit_is_zero(): void
    {
        $options = fn () => collect($this->actingAs($this->staff)->get(route('supplier-bill.create', ['type' => 'advance']))
            ->assertOk()->viewData('purchaseOrders'))->keyBy('id');

        // PO Approved tanpa barang diterima: tidak layak tagihan reguler, layak uang muka hanya bila batas supplier > 0%.
        $option = $options()[$this->po->id];
        $this->assertFalse($option['regular']);
        $this->assertSame('no_limit', $option['advance']);
        $this->actingAs($this->staff)->get(route('supplier-bill.create', ['type' => 'advance']))
            ->assertSee('batas uang muka supplier 0%')->assertSee('Maks. Uang Muka per PO');

        $this->supplier->update(['max_advance_percent' => 50]);
        $this->assertSame('ok', $options()[$this->po->id]['advance']);

        // Batas habis diminta -> nonaktif dengan alasan.
        $this->advance(5000)->assertOk();
        $this->assertSame('full', $options()[$this->po->id]['advance']);

        // Setelah barang diterima, PO juga muncul untuk tagihan reguler.
        $this->receive(2);
        $this->assertTrue($options()[$this->po->id]['regular']);
    }

    public function test_advance_limit_and_automatic_deduction(): void
    {
        // Supplier belum diizinkan uang muka.
        $this->advance(1000)->assertStatus(422);

        $this->supplier->update(['max_advance_percent' => 50]);
        $this->actingAs($this->staff)->getJson(route('supplier-bill.billable-lines', ['purchase_order_id' => $this->po->id]))
            ->assertOk()->assertJsonPath('header.advance_limit', 5000);

        $this->advance(6000)->assertStatus(422);
        $advanceId = $this->advance(5000)->assertOk()->json('id');
        $this->advance(1, 'PRO-2')->assertStatus(422);

        $advance = SupplierBill::findOrFail($advanceId);
        $this->assertSame(SupplierBill::TYPE_ADVANCE, $advance->bill_type);
        $this->assertEquals(5000, $advance->total);
        $this->assertSame(now()->toDateString(), $advance->due_date->toDateString());

        // Draft reguler dibuat sebelum uang muka dibayar -> belum dipotong; saat verifikasi dihitung ulang.
        $this->verify($advanceId);
        $this->receive(6);
        $regularId = $this->regular(6, 'INV-1');
        $this->assertEquals(0, SupplierBill::find($regularId)->advance_deduction);

        $this->payInFull($advanceId, 'TRF-DP');
        $this->assertSame('advance', $this->freshPo()->paymentStatus());

        $this->verify($regularId);
        $regular = SupplierBill::findOrFail($regularId);
        $this->assertEquals(5000, $regular->advance_deduction);
        $this->assertEquals(1000, $regular->amountDue());
        $this->assertEquals(1000, $regular->outstanding());
        $this->assertSame(SupplierBill::STATUS_VERIFIED, $regular->status);
        $this->assertEquals(0, $this->freshPo()->advanceBalance());

        // Saldo uang muka habis -> tagihan berikutnya tanpa potongan.
        $this->receive(4);
        $lastId = $this->regular(4, 'INV-2');
        $this->assertEquals(0, SupplierBill::find($lastId)->advance_deduction);

        $this->actingAs($this->staff)->get(route('supplier-bill.show', $regularId))->assertOk()->assertSee('Dipotong Uang Muka');
        $this->actingAs($this->staff)->get(route('supplier-bill.show', $advanceId))->assertOk()->assertSee('Saldo Uang Muka');
        $this->actingAs($this->staff)->get(route('supplier-bill.create', ['type' => 'advance']))->assertOk();
        $this->actingAs($this->staff)->getJson(route('supplier-bill.data', ['bill_type' => 'advance']))->assertOk()->assertJsonPath('recordsFiltered', 1);
    }

    public function test_full_advance_cancelled_po_waits_for_refund(): void
    {
        $this->supplier->update(['max_advance_percent' => 100]);
        $advanceId = $this->advance(10000)->assertOk()->json('id');
        $this->verify($advanceId);
        $this->payInFull($advanceId, 'TRF-100');

        // 8 datang, ditagih 8.000 -> tertutup uang muka -> langsung lunas.
        $this->receive(8);
        $billId = $this->regular(8, 'INV-8');
        $this->verify($billId);
        $bill = SupplierBill::findOrFail($billId);
        $this->assertEquals(8000, $bill->advance_deduction);
        $this->assertSame(SupplierBill::STATUS_PAID, $bill->status);
        $this->assertEquals(0, $this->freshPo()->refundDue(), 'masih menunggu sisa barang');

        // PO dibatalkan -> sisa uang muka 2.000 harus dikembalikan.
        $this->po->update(['status' => PurchaseOrder::STATUS_CANCELLED]);
        $po = $this->freshPo();
        $this->assertEquals(2000, $po->refundDue());
        $this->assertSame('refund', $po->paymentStatus());
        $this->actingAs($this->staff)->get(route('supplier-refund.index'))->assertOk()->assertSee('26110001')->assertSee('2,000');

        $payload = ['purchase_order_id' => $this->po->id, 'refund_date' => now()->toDateString(), 'method' => 'transfer', 'reference_number' => 'RF-1'];
        $this->actingAs($this->staff)->postJson(route('supplier-refund.store'), $payload + ['amount' => 2500])->assertStatus(422);
        $refundId = $this->actingAs($this->staff)->postJson(route('supplier-refund.store'), $payload + ['amount' => 2000])->assertOk()->json('id');
        // Draft lain tidak boleh melebihi sisa.
        $this->actingAs($this->staff)->postJson(route('supplier-refund.store'), $payload + ['amount' => 1])->assertStatus(422);

        $this->actingAs($this->staff)->postJson(route('supplier-refund.approve', $refundId))->assertForbidden();
        $this->actingAs($this->approver)->postJson(route('supplier-refund.approve', $refundId))->assertOk();
        $this->assertSame(SupplierRefund::STATUS_POSTED, SupplierRefund::find($refundId)->status);
        $this->assertEquals(0, $this->freshPo()->refundDue());
        $this->assertSame('paid', $this->freshPo()->paymentStatus());

        $this->actingAs($this->staff)->get(route('supplier-refund.show', $refundId))->assertOk()->assertSee('RF-1');
        $this->actingAs($this->approver)->postJson(route('supplier-refund.void', $refundId), ['void_reason' => 'Dana tidak masuk'])->assertOk();
        $this->assertEquals(2000, $this->freshPo()->refundDue());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\DirectPurchase;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Finance Tahap 2: Pembayaran Supplier — sebagian / banyak tagihan, satu mata
 * uang, kurs valas, posting oleh approver lain, void membuka sisa hutang,
 * status bayar PO, dan tagihan otomatis dari Direct Purchase.
 */
class SupplierPaymentTest extends TestCase
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
        $this->staff = User::create(['username' => 'kasir', 'email' => 'kasir@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'User']);
        $this->approver = User::create(['username' => 'finance', 'email' => 'finance@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'User']);

        $module = Module::create(['module_code' => 'MOD_SUPPLIER_PAYMENT', 'module_name' => 'Pembayaran Supplier', 'route_name' => 'supplier-payment', 'icon' => 'fa fa-money-bill', 'group' => 'Finance']);
        UserAccessControl::create(['user_id' => $this->staff->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
        UserAccessControl::create(['user_id' => $this->approver->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => true]);

        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 15000, 'is_base' => false, 'status' => 'Active']);

        $this->supplier = Supplier::create(['name' => 'PT Sensor Jaya', 'status' => 'Active', 'payment_term_days' => 30, 'bank_name' => 'BCA', 'bank_account_no' => '123', 'bank_account_name' => 'PT Sensor Jaya']);
        $this->po = PurchaseOrder::create(['supplier_id' => $this->supplier->id, 'supplier_name' => $this->supplier->name, 'po_number' => '26110001', 'status' => PurchaseOrder::STATUS_RECEIVED, 'created_by' => $this->admin->id]);
    }

    private function bill(float $total, string $currency = 'IDR', string $invoice = 'INV-1', ?Supplier $supplier = null): SupplierBill
    {
        return SupplierBill::create([
            'bill_number' => SupplierBill::generateNumber(), 'supplier_id' => ($supplier ?? $this->supplier)->id, 'purchase_order_id' => $this->po->id,
            'supplier_invoice_number' => $invoice, 'bill_date' => '2026-10-01', 'due_date' => '2026-10-31', 'currency' => $currency,
            'subtotal' => $total, 'total' => $total, 'status' => SupplierBill::STATUS_VERIFIED, 'created_by' => $this->admin->id,
        ]);
    }

    private function pay(array $allocations, array $extra = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->staff)->postJson(route('supplier-payment.store'), array_merge([
            'supplier_id' => $this->supplier->id,
            'payment_date' => '2026-11-02',
            'method' => 'transfer',
            'reference_number' => 'TRF-001',
            'allocations' => $allocations,
        ], $extra));
    }

    public function test_partial_then_full_payment_updates_bill_and_po_status(): void
    {
        $bill = $this->bill(6000);

        $this->actingAs($this->staff)->getJson(route('supplier-payment.open-bills', ['supplier_id' => $this->supplier->id]))
            ->assertOk()->assertJsonPath('bills.0.outstanding', 6000)->assertJsonPath('supplier.bank_name', 'BCA');

        $id = $this->pay([['supplier_bill_id' => $bill->id, 'amount' => 3000]])->assertOk()->json('id');
        $payment = SupplierPayment::findOrFail($id);
        $this->assertSame(SupplierPayment::STATUS_DRAFT, $payment->status);
        $this->assertSame('123', $payment->bank_account_no);
        $this->assertEquals(6000, $bill->fresh()->outstanding(), 'draft belum mengurangi hutang');

        // Pemisahan tugas: staf tanpa can_approve -> 403.
        $this->actingAs($this->staff)->postJson(route('supplier-payment.approve', $id))->assertForbidden();
        $this->actingAs($this->approver)->postJson(route('supplier-payment.approve', $id))->assertOk();

        $bill->refresh();
        $this->assertSame(SupplierBill::STATUS_PARTIALLY_PAID, $bill->status);
        $this->assertEquals(3000, $bill->outstanding());
        $this->assertSame('partial', $this->po->fresh('supplierBills.allocations.payment')->paymentStatus());

        // Melebihi sisa hutang -> ditolak.
        $this->pay([['supplier_bill_id' => $bill->id, 'amount' => 3000.01]])->assertStatus(422);

        $second = $this->pay([['supplier_bill_id' => $bill->id, 'amount' => 3000]], ['reference_number' => 'TRF-002'])->assertOk()->json('id');
        $this->actingAs($this->approver)->postJson(route('supplier-payment.approve', $second))->assertOk();
        $this->assertSame(SupplierBill::STATUS_PAID, $bill->fresh()->status);
        $this->assertSame('paid', $this->po->fresh('supplierBills.allocations.payment')->paymentStatus());

        $this->actingAs($this->admin)->getJson(route('purchase-order.data', ['pay_status' => 'paid']))->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($this->admin)->getJson(route('purchase-order.data', ['pay_status' => 'unpaid']))->assertOk()->assertJsonPath('recordsFiltered', 0);

        // Void pembayaran kedua membuka kembali sisa hutang.
        $this->actingAs($this->approver)->postJson(route('supplier-payment.void', $second), ['void_reason' => 'Transfer gagal'])->assertOk();
        $bill->refresh();
        $this->assertSame(SupplierBill::STATUS_PARTIALLY_PAID, $bill->status);
        $this->assertEquals(3000, $bill->outstanding());
    }

    public function test_multi_bill_rules_currency_and_creator_may_post(): void
    {
        $a = $this->bill(1000, 'IDR', 'INV-A');
        $b = $this->bill(2000, 'IDR', 'INV-B');
        $usd = $this->bill(100, 'USD', 'INV-USD');
        $other = $this->bill(500, 'IDR', 'INV-X', Supplier::create(['name' => 'CV Lain', 'status' => 'Active']));
        $draftBill = $this->bill(700, 'IDR', 'INV-D');
        $draftBill->update(['status' => SupplierBill::STATUS_DRAFT]);

        $this->pay([['supplier_bill_id' => $a->id, 'amount' => 1000], ['supplier_bill_id' => $usd->id, 'amount' => 100]])->assertStatus(422);
        $this->pay([['supplier_bill_id' => $other->id, 'amount' => 500]])->assertStatus(422);
        $this->pay([['supplier_bill_id' => $draftBill->id, 'amount' => 700]])->assertStatus(422);
        $this->pay([['supplier_bill_id' => $a->id, 'amount' => 1000]], ['reference_number' => ''])->assertStatus(422)->assertJsonValidationErrors('reference_number');
        $this->pay([['supplier_bill_id' => $usd->id, 'amount' => 100]])->assertStatus(422);

        $usdPayment = $this->pay([['supplier_bill_id' => $usd->id, 'amount' => 100]], ['exchange_rate' => 15500])->assertOk()->json('id');
        $this->assertEquals(1550000, SupplierPayment::find($usdPayment)->amount_base);

        // Satu transfer untuk dua tagihan; tunai tanpa referensi boleh.
        $id = $this->pay([['supplier_bill_id' => $a->id, 'amount' => 1000], ['supplier_bill_id' => $b->id, 'amount' => 500]], ['method' => 'cash', 'reference_number' => null], $this->approver)->assertOk()->json('id');
        $this->assertEquals(1500, SupplierPayment::find($id)->amount);

        // Pembuat yang punya can_approve boleh memposting pembayarannya sendiri.
        $this->actingAs($this->approver)->get(route('supplier-payment.show', $id))->assertOk()->assertSee('sp-post-btn', false);
        $this->actingAs($this->approver)->postJson(route('supplier-payment.approve', $id))->assertOk();
        $this->assertSame(SupplierBill::STATUS_PAID, $a->fresh()->status);
        $this->assertEquals(1500, $b->fresh()->outstanding());

        // Posted tidak bisa diedit / dihapus; void wajib alasan.
        $this->actingAs($this->staff)->deleteJson(route('supplier-payment.destroy', $id))->assertStatus(422);
        $this->actingAs($this->approver)->postJson(route('supplier-payment.void', $id), [])->assertStatus(422)->assertJsonValidationErrors('void_reason');

        foreach (['index', 'create'] as $page) {
            $this->actingAs($this->staff)->get(route('supplier-payment.'.$page))->assertOk();
        }
        $this->actingAs($this->staff)->get(route('supplier-payment.show', $id))->assertOk()->assertSee('INV-A');
        $this->actingAs($this->staff)->get(route('supplier-payment.edit', $usdPayment))->assertOk();
        $this->actingAs($this->staff)->getJson(route('supplier-payment.data', ['status' => 'posted']))->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($this->admin)->get(route('supplier-bill.show', $a->id))->assertOk()->assertSee('Riwayat Pembayaran');
    }

    public function test_direct_purchase_approval_creates_verified_bill(): void
    {
        $product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Kabel', 'division_id' => $this->division->id, 'status' => 'Active']);
        $dp = DirectPurchase::create([
            'dp_number' => '26110001', 'supplier_id' => $this->supplier->id, 'supplier_name' => $this->supplier->name,
            'purpose' => DirectPurchase::PURPOSE_STOCK, 'status' => DirectPurchase::STATUS_WAITING_APPROVAL,
            'date' => '2026-11-02', 'payment_term_days' => 0, 'ppn_percent' => 11, 'created_by' => $this->staff->id,
        ]);
        $dp->items()->create(['master_product_id' => $product->id, 'part_number' => 'PN-1', 'description' => 'Kabel', 'qty' => 2, 'unit' => 'Roll', 'currency' => 'IDR', 'price' => 50000, 'sort_order' => 1]);

        $this->actingAs($this->admin)->postJson(route('direct-purchase.approve', $dp->id))->assertOk();

        $bill = SupplierBill::where('direct_purchase_id', $dp->id)->firstOrFail();
        $this->assertSame(SupplierBill::STATUS_VERIFIED, $bill->status);
        $this->assertSame('2026-11-02', $bill->due_date->toDateString());
        $this->assertEquals(111000, $bill->total);
        $this->actingAs($this->admin)->get(route('direct-purchase.show', $dp->id))->assertOk()->assertSee($bill->bill_number);
    }
}

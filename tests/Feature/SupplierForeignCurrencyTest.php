<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\SupplierRefund;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Support\PayableReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Valas hutang supplier (V1 + V2): kurs tanggal invoice supplier = nilai buku,
 * kurs bank saat bayar (Rupiah didebit / kurs) -> selisih kurs terealisasi,
 * uang muka & refund dengan kurs historis, selisih belum terealisasi di laporan.
 */
class SupplierForeignCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private User $approver;

    private Supplier $supplier;

    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'Admin']);
        $this->staff = User::create(['username' => 'kasir', 'email' => 'kasir@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);
        $this->approver = User::create(['username' => 'finance', 'email' => 'finance@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);

        foreach (['supplier-bill' => 'MOD_SUPPLIER_BILL', 'supplier-payment' => 'MOD_SUPPLIER_PAYMENT', 'supplier-refund' => 'MOD_SUPPLIER_REFUND'] as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Finance']);
            UserAccessControl::create(['user_id' => $this->staff->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
            UserAccessControl::create(['user_id' => $this->approver->id, 'module_id' => $module->id, 'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => true]);
        }

        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 16000, 'is_base' => false, 'status' => 'Active']);

        $this->supplier = Supplier::create(['name' => 'Badger Meter', 'status' => 'Active', 'payment_term_days' => 30, 'max_advance_percent' => 50]);
        $product = MasterProduct::create(['code' => 'FM-1', 'name' => 'Flow Meter', 'division_id' => $division->id, 'status' => 'Active']);
        $request = GoodsRequest::create(['purpose' => GoodsRequest::PURPOSE_INTERNAL, 'division_id' => $division->id, 'status' => GoodsRequest::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        $requestItem = $request->items()->create(['master_product_id' => $product->id, 'part_number' => 'FM-1', 'description' => 'Flow Meter', 'qty' => 10, 'unit' => 'Unit', 'sort_order' => 1]);

        $this->po = PurchaseOrder::create(['supplier_id' => $this->supplier->id, 'supplier_name' => $this->supplier->name, 'po_number' => '26110007', 'status' => PurchaseOrder::STATUS_APPROVED, 'payment_term_days' => 30, 'created_by' => $this->admin->id]);
        $this->po->items()->create(['goods_request_item_id' => $requestItem->id, 'master_product_id' => $product->id, 'part_number' => 'FM-1', 'description' => 'Flow Meter', 'qty' => 10, 'unit' => 'Unit', 'price' => 100, 'currency' => 'USD', 'sort_order' => 1]);
    }

    private function receive(int $qty): void
    {
        $warehouse = Warehouse::firstOrCreate(['code' => 'G1'], ['name' => 'Gudang', 'type' => Warehouse::TYPE_STOCK, 'status' => 'Active']);
        $receipt = GoodsReceipt::create(['receipt_number' => 'GRN-'.uniqid(), 'purchase_order_id' => $this->po->id, 'warehouse_id' => $warehouse->id, 'received_date' => now()->toDateString(), 'status' => GoodsReceipt::STATUS_POSTED, 'created_by' => $this->staff->id]);
        $item = $this->po->items()->first();
        $receipt->items()->create(['purchase_order_item_id' => $item->id, 'master_product_id' => $item->master_product_id, 'qty' => $qty, 'destination' => 'warehouse', 'sort_order' => 1]);
        $this->po->refreshReceiptStatus();
    }

    private function regularBill(int $qty, ?float $rate, string $invoice = 'INV-US-1')
    {
        return $this->actingAs($this->staff)->postJson(route('supplier-bill.store'), [
            'purchase_order_id' => $this->po->id, 'supplier_invoice_number' => $invoice, 'bill_date' => now()->toDateString(),
            'exchange_rate' => $rate,
            'items' => [['purchase_order_item_id' => $this->po->items()->first()->id, 'qty' => $qty, 'price' => 100]],
        ]);
    }

    private function pay(SupplierBill $bill, float $amount, array $extra = []): SupplierPayment
    {
        $id = $this->actingAs($this->staff)->postJson(route('supplier-payment.store'), array_merge([
            'supplier_id' => $this->supplier->id, 'payment_date' => now()->toDateString(), 'method' => 'transfer', 'reference_number' => 'TT-'.uniqid(),
            'allocations' => [['supplier_bill_id' => $bill->id, 'amount' => $amount]],
        ], $extra))->assertOk()->json('id');
        $this->actingAs($this->approver)->postJson(route('supplier-payment.approve', $id))->assertOk();

        return SupplierPayment::with('allocations')->findOrFail($id);
    }

    public function test_foreign_bill_requires_invoice_date_rate_as_book_value(): void
    {
        $this->receive(10);

        $this->actingAs($this->staff)->getJson(route('supplier-bill.billable-lines', ['purchase_order_id' => $this->po->id]))
            ->assertOk()->assertJsonPath('header.is_foreign', true)->assertJsonPath('header.default_rate', 16000);

        $this->regularBill(10, null)->assertStatus(422)
            ->assertJsonPath('message', 'Kurs USD ke IDR pada tanggal invoice supplier wajib diisi.');

        $id = $this->regularBill(10, 16200)->assertOk()->json('id');
        $bill = SupplierBill::findOrFail($id);
        $this->assertSame(16200.0, $bill->exchange_rate);
        $this->assertFalse($bill->rate_estimated);
        $this->assertEquals(16200000, $bill->toBook($bill->total));

        $this->actingAs($this->approver)->postJson(route('supplier-bill.approve', $id))->assertOk();
        $this->actingAs($this->staff)->get(route('supplier-bill.show', $id))->assertOk()
            ->assertSee('1 USD = IDR 16.200')->assertSee('Kurs tanggal invoice supplier');
    }

    public function test_partial_payments_realize_fx_difference_from_bank_rate(): void
    {
        $this->receive(10);
        $bill = SupplierBill::findOrFail($this->regularBill(10, 16200)->assertOk()->json('id'));
        $this->actingAs($this->approver)->postJson(route('supplier-bill.approve', $bill->id))->assertOk();

        // Bayar USD 400 dari rekening IDR: bank mendebit Rp 6.560.000 -> kurs 16.400, rugi kurs 80.000.
        $first = $this->pay($bill, 400, ['debit_amount_base' => 6560000, 'bank_charge' => 25000]);
        $this->assertSame(16400.0, $first->exchange_rate);
        $this->assertEquals(6560000, $first->amount_base);
        $this->assertEquals(25000, $first->bank_charge);
        $allocation = $first->allocations->first();
        $this->assertEquals(6480000, $allocation->amount_base_book);
        $this->assertEquals(6560000, $allocation->amount_base_paid);
        $this->assertEquals(80000, $allocation->fx_difference);

        // Pelunasan USD 600 dengan kurs 16.100 -> laba kurs 60.000.
        $second = $this->pay($bill, 600, ['exchange_rate' => 16100]);
        $this->assertEquals(-60000, $second->allocations->first()->fx_difference);

        $bill->refresh();
        $this->assertSame(SupplierBill::STATUS_PAID, $bill->status);
        $this->assertEquals(20000, $bill->realizedFx());

        $fx = (new PayableReport)->fx();
        $this->assertCount(2, $fx['realized']);
        $this->assertEquals(20000, $fx['realized_total']);

        $this->actingAs($this->approver)->get(route('supplier-payment.show', $first->id))->assertOk()
            ->assertSee('Rugi selisih kurs IDR 80.000')->assertSee('Biaya bank IDR 25.000');
        $this->actingAs($this->admin)->get(route('payable-report.index', ['tab' => 'fx']))->assertOk()
            ->assertSee($first->payment_number)->assertSee('Total selisih kurs terealisasi (rugi)');
        $this->actingAs($this->admin)->get(route('payable-report.export', ['tab' => 'fx']))->assertOk();
    }

    public function test_advance_and_refund_use_historical_rates(): void
    {
        // Uang muka USD 500 dengan kurs 16.000, dibayar dengan kurs 16.500 -> rugi kurs 250.000.
        $advanceId = $this->actingAs($this->staff)->postJson(route('supplier-bill.store'), [
            'bill_type' => 'advance', 'purchase_order_id' => $this->po->id, 'supplier_invoice_number' => 'PI-1',
            'bill_date' => now()->toDateString(), 'advance_amount' => 500, 'exchange_rate' => 16000,
        ])->assertOk()->json('id');
        $advance = SupplierBill::findOrFail($advanceId);
        $this->actingAs($this->approver)->postJson(route('supplier-bill.approve', $advanceId))->assertOk();
        $payment = $this->pay($advance, 500, ['exchange_rate' => 16500]);
        $this->assertEquals(250000, $payment->allocations->first()->fx_difference);

        // PO batal -> refund USD 500 diterima dengan kurs 16.800: nilai buku uang muka 8.000.000, diterima 8.400.000 -> laba 400.000.
        $this->po->update(['status' => PurchaseOrder::STATUS_CANCELLED]);
        $refundId = $this->actingAs($this->staff)->postJson(route('supplier-refund.store'), [
            'purchase_order_id' => $this->po->id, 'refund_date' => now()->toDateString(), 'method' => 'transfer',
            'reference_number' => 'RF-1', 'amount' => 500, 'exchange_rate' => 16800,
        ])->assertOk()->json('id');
        $this->actingAs($this->approver)->postJson(route('supplier-refund.approve', $refundId))->assertOk();

        $refund = SupplierRefund::findOrFail($refundId);
        $this->assertEquals(8000000, $refund->amount_base_book);
        $this->assertEquals(8400000, $refund->amount_base);
        $this->assertEquals(-400000, $refund->fx_difference);
        $this->actingAs($this->staff)->get(route('supplier-refund.show', $refundId))->assertOk()->assertSee('Laba IDR 400.000');

        $fx = (new PayableReport)->fx();
        $this->assertEquals(-150000, $fx['realized_total']);
    }

    public function test_open_foreign_bill_shows_unrealized_difference_and_estimated_rate_can_be_corrected(): void
    {
        $this->receive(10);
        $bill = SupplierBill::findOrFail($this->regularBill(10, 16200)->assertOk()->json('id'));
        $this->actingAs($this->approver)->postJson(route('supplier-bill.approve', $bill->id))->assertOk();

        // Kurs terkunci setelah Verified (bukan kurs perkiraan).
        $this->actingAs($this->staff)->patchJson(route('supplier-bill.exchange-rate', $bill->id), ['exchange_rate' => 15000])->assertStatus(422);

        // Kurs master naik ke 17.000: hutang tetap dinilai nilai buku, selisih 800.000 belum terealisasi.
        Currency::where('name', 'USD')->update(['rate' => 17000]);
        $row = (new PayableReport)->supplierSummary()->first();
        $this->assertEquals(16200000, $row['outstanding']);
        $this->assertEquals(800000, $row['unrealized_fx']);
        $this->assertEquals(800000, (new PayableReport)->fx()['unrealized_total']);

        // Tagihan lama berkurs perkiraan (hasil migrasi) boleh dikoreksi sekali, walau sudah ada pembayaran Posted.
        $bill->update(['rate_estimated' => true, 'exchange_rate' => 20000]);
        $posted = $this->pay($bill, 200, ['exchange_rate' => 16400]);
        $this->assertEquals(-720000, $posted->allocations->first()->fx_difference, 'kurs perkiraan 20.000 menghasilkan laba semu');
        $draftId = $this->actingAs($this->staff)->postJson(route('supplier-payment.store'), [
            'supplier_id' => $this->supplier->id, 'payment_date' => now()->toDateString(), 'method' => 'transfer', 'reference_number' => 'TT-9',
            'exchange_rate' => 16500, 'allocations' => [['supplier_bill_id' => $bill->id, 'amount' => 800]],
        ])->assertOk()->json('id');
        $this->actingAs($this->staff)->get(route('supplier-bill.show', $bill->id))->assertOk()->assertSee('kurs perkiraan');

        $this->actingAs($this->staff)->patchJson(route('supplier-bill.exchange-rate', $bill->id), ['exchange_rate' => 16250])->assertOk();
        $bill->refresh();
        $this->assertSame(16250.0, $bill->exchange_rate);
        $this->assertFalse($bill->rate_estimated);
        // Pembayaran Posted & draft ikut dihitung ulang dengan kurs yang benar.
        $postedAllocation = $posted->allocations->first()->fresh();
        $this->assertEquals(3250000, $postedAllocation->amount_base_book);
        $this->assertEquals(3280000, $postedAllocation->amount_base_paid, 'Rupiah yang sudah dibayar tidak berubah');
        $this->assertEquals(30000, $postedAllocation->fx_difference);
        $draft = SupplierPayment::with('allocations')->findOrFail($draftId)->allocations->first();
        $this->assertEquals(13000000, $draft->amount_base_book);
        $this->assertEquals(200000, $draft->fx_difference);

        $this->actingAs($this->staff)->patchJson(route('supplier-bill.exchange-rate', $bill->id), ['exchange_rate' => 16300])->assertStatus(422);
    }

    public function test_base_currency_bill_ignores_rate(): void
    {
        $this->po->items()->update(['currency' => 'IDR', 'price' => 100000]);
        $this->receive(2);
        $id = $this->actingAs($this->staff)->postJson(route('supplier-bill.store'), [
            'purchase_order_id' => $this->po->id, 'supplier_invoice_number' => 'INV-IDR', 'bill_date' => now()->toDateString(),
            'exchange_rate' => 16000,
            'items' => [['purchase_order_item_id' => $this->po->items()->first()->id, 'qty' => 2, 'price' => 100000]],
        ])->assertOk()->json('id');

        $bill = SupplierBill::findOrFail($id);
        $this->assertSame(1.0, $bill->exchange_rate);
        $this->assertFalse($bill->isForeign());
        $this->assertEquals(200000, $bill->toBook($bill->total));
    }
}

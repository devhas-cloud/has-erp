<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Finance Tahap 4: PPh dipotong (tarif bebas), biaya tambahan, faktur pajak
 * wajib untuk supplier PKP, bukti potong, dan Retur ke Supplier.
 */
class SupplierTaxReturnTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private PurchaseOrder $po;

    private Warehouse $warehouse;

    private MasterProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'Admin']);
        $this->supplier = Supplier::create(['name' => 'PT Kalibrasi Jaya', 'status' => 'Active', 'payment_term_days' => 30, 'is_pkp' => true, 'default_pph_percent' => 2]);
        $this->warehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang', 'status' => 'Active']);
        $this->product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor pH', 'division_id' => $division->id, 'status' => 'Active', 'is_material' => true]);

        $request = GoodsRequest::create(['purpose' => GoodsRequest::PURPOSE_INTERNAL, 'division_id' => $division->id, 'status' => GoodsRequest::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        $requestItem = $request->items()->create(['master_product_id' => $this->product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit', 'sort_order' => 1]);

        $this->po = PurchaseOrder::create(['supplier_id' => $this->supplier->id, 'supplier_name' => $this->supplier->name, 'po_number' => '26110001', 'status' => PurchaseOrder::STATUS_APPROVED, 'ppn_percent' => 11, 'payment_term_days' => 30, 'created_by' => $this->admin->id]);
        $this->po->items()->create(['goods_request_item_id' => $requestItem->id, 'master_product_id' => $this->product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit', 'price' => 1000, 'currency' => 'IDR', 'sort_order' => 1]);
    }

    private function receive(int $qty): void
    {
        $receipt = GoodsReceipt::create(['receipt_number' => 'GRN-'.uniqid(), 'purchase_order_id' => $this->po->id, 'warehouse_id' => $this->warehouse->id, 'received_date' => now()->toDateString(), 'status' => GoodsReceipt::STATUS_POSTED, 'created_by' => $this->admin->id]);
        $item = $this->po->items()->first();
        $line = $receipt->items()->create(['purchase_order_item_id' => $item->id, 'master_product_id' => $item->master_product_id, 'qty' => $qty, 'destination' => 'warehouse', 'sort_order' => 1]);
        $this->seedStock($this->warehouse->id, $this->product, $qty, ['unit_cost' => 1000, 'source_type' => Stock::SOURCE_GOODS_RECEIPT_ITEM, 'source_id' => $line->id]);
        $this->po->refreshReceiptStatus();
    }

    private function storeBill(int $qty, array $extra = [])
    {
        return $this->actingAs($this->admin)->postJson(route('supplier-bill.store'), array_merge([
            'purchase_order_id' => $this->po->id,
            'supplier_invoice_number' => 'INV-'.uniqid(),
            'bill_date' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $this->po->items()->first()->id, 'qty' => $qty, 'price' => 1000]],
        ], $extra));
    }

    public function test_pph_charges_tax_invoice_and_slip(): void
    {
        $this->receive(10);

        $this->actingAs($this->admin)->getJson(route('supplier-bill.billable-lines', ['purchase_order_id' => $this->po->id]))
            ->assertOk()->assertJsonPath('header.default_pph_percent', 2);

        $this->storeBill(10, ['charges' => [['description' => '', 'amount' => 500]]])->assertStatus(422)->assertJsonValidationErrors('charges.0.description');

        $id = $this->storeBill(10, [
            'charges' => [['description' => 'Ongkos kirim', 'amount' => 500]],
            'pph_label' => 'PPh 23',
            'pph_percent' => 2.5,
        ])->assertOk()->json('id');

        $bill = SupplierBill::findOrFail($id);
        $this->assertEquals(500, $bill->other_charges);
        $this->assertEquals(10500, $bill->taxBase());
        $this->assertEquals(1155, $bill->ppn_amount);
        $this->assertEquals(11655, $bill->total);
        $this->assertEquals(10500, $bill->pph_base);
        $this->assertEquals(262.5, $bill->pph_amount);
        $this->assertEquals(11392.5, $bill->amountDue());

        // Supplier PKP ber-PPN: faktur pajak wajib sebelum verifikasi.
        $this->actingAs($this->admin)->postJson(route('supplier-bill.approve', $id))->assertStatus(422);

        // Dasar PPh boleh diisi lain (mis. porsi jasa saja).
        $this->actingAs($this->admin)->putJson(route('supplier-bill.update', $id), [
            'purchase_order_id' => $this->po->id,
            'supplier_invoice_number' => $bill->supplier_invoice_number,
            'tax_invoice_number' => '010.000-26.00000009',
            'bill_date' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $this->po->items()->first()->id, 'qty' => 10, 'price' => 1000]],
            'charges' => [['description' => 'Ongkos kirim', 'amount' => 500]],
            'pph_label' => 'PPh 23', 'pph_percent' => 2, 'pph_base' => 5000,
        ])->assertOk();
        $bill->refresh();
        $this->assertEquals(100, $bill->pph_amount);
        $this->assertEquals(11555, $bill->amountDue());

        $this->actingAs($this->admin)->postJson(route('supplier-bill.approve', $id))->assertOk();
        $this->assertEquals(11555, $bill->fresh()->outstanding());

        $this->actingAs($this->admin)->getJson(route('supplier-bill.data', ['pph_pending' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($this->admin)->patchJson(route('supplier-bill.pph-slip', $id), [])->assertStatus(422)->assertJsonValidationErrors('pph_slip_number');
        $this->actingAs($this->admin)->patchJson(route('supplier-bill.pph-slip', $id), ['pph_slip_number' => 'BP-0001', 'pph_slip_date' => now()->toDateString()])->assertOk();
        $this->actingAs($this->admin)->getJson(route('supplier-bill.data', ['pph_pending' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 0);

        $this->actingAs($this->admin)->get(route('supplier-bill.show', $id))->assertOk()->assertSee('Ongkos kirim')->assertSee('BP-0001')->assertSee('Yang Ditransfer');
        $this->actingAs($this->admin)->get(route('supplier-bill.edit', $id))->assertForbidden();
    }

    public function test_return_reduces_received_billable_and_stock(): void
    {
        $this->receive(10);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $this->po->fresh()->status);
        $this->storeBill(6, ['tax_invoice_number' => 'FP-1'])->assertOk();

        $this->actingAs($this->admin)->getJson(route('supplier-return.returnable-lines', ['purchase_order_id' => $this->po->id]))
            ->assertOk()->assertJsonPath('lines.0.returnable', 4);

        $payload = ['purchase_order_id' => $this->po->id, 'warehouse_id' => $this->warehouse->id, 'return_date' => now()->toDateString(), 'reason' => '3 unit pecah'];
        $item = $this->po->items()->first();
        $row = Stock::firstOrFail();
        $this->actingAs($this->admin)->getJson(route('supplier-return.stock-options', ['purchase_order_item_id' => $item->id]))
            ->assertOk()->assertJsonPath('data.0.id', $row->id);
        $pick = fn (float $qty) => ['items' => [['purchase_order_item_id' => $item->id, 'picks' => [['stock_id' => $row->id, 'qty' => $qty]]]]];

        $this->actingAs($this->admin)->postJson(route('supplier-return.store'), $payload + $pick(5))->assertStatus(422);
        $this->actingAs($this->admin)->postJson(route('supplier-return.store'), array_merge($payload, ['reason' => '']) + $pick(3))->assertStatus(422);

        // Baris stok yang bukan dari penerimaan PO ini ditolak.
        $other = $this->seedStock($this->warehouse->id, $this->product, 5)->first();
        $this->actingAs($this->admin)->postJson(route('supplier-return.store'), $payload + ['items' => [['purchase_order_item_id' => $item->id, 'picks' => [['stock_id' => $other->id, 'qty' => 3]]]]])
            ->assertStatus(422);

        $id = $this->actingAs($this->admin)->postJson(route('supplier-return.store'), $payload + $pick(3))->assertOk()->json('id');

        $item = $item->fresh();
        $this->assertSame(7, $item->receivedQty());
        $this->assertSame(1, $item->billableQty());
        $this->assertEquals(7, $row->fresh()->qty);
        $this->assertDatabaseHas('stock_logs', ['type' => StockLog::TYPE_RETURN_SUPPLIER, 'qty' => -3, 'stock_id' => $row->id]);
        $this->assertDatabaseHas('supplier_return_items', ['supplier_return_id' => $id, 'stock_id' => $row->id, 'destination' => 'warehouse']);
        // PO kembali menunggu barang pengganti.
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $this->po->fresh()->status);

        // Barang retur tidak bisa ditagih: sisa yang bisa ditagih hanya 1.
        $this->storeBill(2)->assertStatus(422);

        $this->actingAs($this->admin)->get(route('supplier-return.index'))->assertOk();
        $this->actingAs($this->admin)->getJson(route('supplier-return.data', ['draw' => 1, 'supplier' => $this->po->supplier_id]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertSee('3 unit pecah');
        $this->actingAs($this->admin)->getJson(route('supplier-return.data', ['draw' => 1, 'warehouse' => 'direct']))
            ->assertOk()->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($this->admin)->get(route('supplier-return.show', $id))->assertOk();
        $this->actingAs($this->admin)->get(route('supplier-return.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('purchase-order.show', $this->po->id))->assertOk()->assertSee('Retur ke Supplier');
    }
}

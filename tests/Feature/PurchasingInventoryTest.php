<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Currency;
use App\Models\DirectPurchase;
use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur procure-to-stock: Penerimaan Barang (PO / Direct Purchase) dengan
 * tujuan per baris, alokasi project, Pemenuhan dari Stok, Tutup Sisa, dan
 * pemetaan master product pada item Permintaan Barang lama.
 */
class PurchasingInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $warehouseUser;

    private Warehouse $stockWarehouse;

    private Warehouse $transitWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create([
            'username' => 'superadmin',
            'email' => 'superadmin@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'Admin',
        ]);

        $this->warehouseUser = User::create([
            'username' => 'gudang',
            'email' => 'gudang@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);
        foreach (['goods-receipt' => 'MOD_GOODS_RECEIPT', 'stock-out' => 'MOD_STOCK_OUT', 'goods-request' => 'MOD_GOODS_REQUEST'] as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Inventory']);
            UserAccessControl::create([
                'user_id' => $this->warehouseUser->id,
                'module_id' => $module->id,
                'can_create' => true,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => false,
                'can_approve' => false,
            ]);
        }

        $this->stockWarehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang 12', 'type' => Warehouse::TYPE_STOCK, 'status' => 'Active']);
        $this->transitWarehouse = Warehouse::create(['code' => 'T1', 'name' => 'Transit', 'type' => Warehouse::TYPE_TRANSIT, 'status' => 'Active']);
    }

    private function product(string $code): MasterProduct
    {
        return MasterProduct::firstOrCreate(['code' => $code], ['name' => 'Produk '.$code, 'division_id' => $this->division->id, 'status' => 'Active']);
    }

    private function project(): Project
    {
        $company = AccountCompany::create(['account_name' => 'PT Maju Bersama', 'status' => 'Active']);

        return Project::create([
            'project_number' => '26100001',
            'project_name' => 'SPARING Gresik',
            'company_id' => $company->id,
            'po_number_company' => 'PO-CUST-1',
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1000000,
            'net_sale_amount' => 900000,
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }

    private function approvedRequest(int $qty, ?Project $project = null, ?MasterProduct $product = null): GoodsRequestItem
    {
        $gr = GoodsRequest::create([
            'purpose' => $project ? GoodsRequest::PURPOSE_PROJECT : GoodsRequest::PURPOSE_INTERNAL,
            'project_id' => $project?->id,
            'division_id' => $this->division->id,
            'status' => GoodsRequest::STATUS_APPROVED,
            'created_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        return $gr->items()->create([
            'master_product_id' => ($product ?? $this->product('PN-1'))->id,
            'part_number' => 'PN-1',
            'description' => 'Sensor',
            'qty' => $qty,
            'unit' => 'Unit',
            'sort_order' => 1,
        ]);
    }

    private function approvedPo(GoodsRequestItem $requestItem, ?int $qty = null): PurchaseOrder
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        $po->items()->create([
            'goods_request_item_id' => $requestItem->id,
            'master_product_id' => $requestItem->master_product_id,
            'part_number' => $requestItem->part_number,
            'description' => $requestItem->description,
            'qty' => $qty ?? $requestItem->qty,
            'unit' => 'Unit',
            'price' => 1000,
            'sort_order' => 1,
        ]);

        return $po->load('items');
    }

    private function receive(string $type, int $sourceId, array $items, ?int $warehouseId = null)
    {
        return $this->actingAs($this->warehouseUser)->postJson(route('goods-receipt.store'), [
            'source_type' => $type,
            'source_id' => $sourceId,
            'warehouse_id' => $warehouseId,
            'received_date' => '2026-11-02',
            'items' => $items,
        ]);
    }

    public function test_po_receipt_into_transit_allocates_to_project_and_updates_status(): void
    {
        $project = $this->project();
        $requestItem = $this->approvedRequest(5, $project);
        $po = $this->approvedPo($requestItem);
        $line = $po->items->first();

        $this->actingAs($this->warehouseUser)
            ->getJson(route('goods-receipt.fetch-source-items', ['source_type' => 'po', 'source_id' => $po->id]))
            ->assertOk()
            ->assertJsonPath('items.0.remaining', 5)
            ->assertJsonPath('items.0.project_id', $project->id);

        $id = $this->receive('po', $po->id, [['source_line_id' => $line->id, 'qty' => 2, 'destination' => 'warehouse']], $this->transitWarehouse->id)
            ->assertOk()->json('id');

        $receipt = GoodsReceipt::findOrFail($id);
        $this->assertSame(GoodsReceipt::STATUS_POSTED, $receipt->status);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->status);
        // Stok project di gudang transit, bukan stok bebas.
        $this->assertSame(2, StockMovement::balance($this->transitWarehouse->id, $requestItem->master_product_id, $project->id));
        $this->assertSame(0, StockMovement::balance($this->transitWarehouse->id, $requestItem->master_product_id));

        $this->receive('po', $po->id, [['source_line_id' => $line->id, 'qty' => 3, 'destination' => 'warehouse']], $this->transitWarehouse->id)->assertOk();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);

        $this->actingAs($this->warehouseUser)->get(route('goods-receipt.show', $id))->assertOk()->assertSee('SPARING Gresik');
        $this->actingAs($this->warehouseUser)->get(route('goods-receipt.create', ['source_type' => 'po', 'source_id' => $po->id]))->assertOk();
    }

    public function test_receipt_values_stock_in_base_currency_after_po_discount(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 15000, 'is_base' => false, 'status' => 'Active']);

        $po = $this->approvedPo($this->approvedRequest(2));
        $po->update(['discount_percent' => 10]);
        $po->items()->update(['price' => 100, 'currency' => 'USD']);

        $this->receive('po', $po->id, [['source_line_id' => $po->items->first()->id, 'qty' => 2, 'destination' => 'warehouse']], $this->stockWarehouse->id)->assertOk();

        $this->assertEquals(1350000, StockMovement::where('type', StockMovement::TYPE_RECEIPT)->value('unit_cost'));
    }

    public function test_receipt_rejects_over_receipt_and_requires_warehouse_for_stock_lines(): void
    {
        $po = $this->approvedPo($this->approvedRequest(3));
        $line = $po->items->first();

        $this->receive('po', $po->id, [['source_line_id' => $line->id, 'qty' => 4, 'destination' => 'warehouse']], $this->stockWarehouse->id)
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->receive('po', $po->id, [['source_line_id' => $line->id, 'qty' => 1, 'destination' => 'warehouse']])
            ->assertStatus(422);

        // Draft PO belum bisa diterima.
        $po->update(['status' => PurchaseOrder::STATUS_DRAFT]);
        $this->receive('po', $po->id, [['source_line_id' => $line->id, 'qty' => 1, 'destination' => 'warehouse']], $this->stockWarehouse->id)
            ->assertStatus(422);

        $this->assertSame(0, GoodsReceipt::count());
    }

    public function test_direct_use_line_confirms_receipt_without_adding_stock(): void
    {
        $requestItem = $this->approvedRequest(2);
        $po = $this->approvedPo($requestItem);

        $this->receive('po', $po->id, [['source_line_id' => $po->items->first()->id, 'qty' => 2, 'destination' => 'direct']])->assertOk();

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_direct_purchase_flow_with_purpose_and_receipt(): void
    {
        $product = $this->product('DP-1');

        $this->actingAs($this->admin)->postJson(route('direct-purchase.store'), [
            'supplier_name' => 'Toko Listrik',
            'purpose' => 'project',
            'items' => [['master_product_id' => $product->id, 'description' => 'Kabel', 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('project_id');

        $this->actingAs($this->admin)->postJson(route('direct-purchase.store'), [
            'supplier_name' => 'Toko Listrik',
            'purpose' => 'stock',
            'items' => [['description' => 'Tanpa produk', 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.master_product_id');

        $id = $this->actingAs($this->admin)->postJson(route('direct-purchase.store'), [
            'supplier_name' => 'Toko Listrik',
            'supplier_phone' => '0812',
            'purpose' => 'stock',
            'ppn_percent' => 11,
            'items' => [['master_product_id' => $product->id, 'part_number' => 'DP-1', 'description' => 'Kabel', 'qty' => 10, 'unit' => 'Roll', 'price' => 1000]],
        ])->assertOk()->json('id');

        $dp = DirectPurchase::with('items')->findOrFail($id);
        $this->assertSame(['subtotal' => 10000.0, 'ppn' => 1100.0, 'total' => 11100.0], $dp->totalsFrom($dp->grandTotal()));

        $this->actingAs($this->admin)->get(route('direct-purchase.show', $id))->assertOk()->assertSee('Untuk Stok');
        $this->actingAs($this->admin)->get(route('direct-purchase.edit', $id))->assertOk();
        $this->actingAs($this->admin)->get(route('direct-purchase.pdf', $id))->assertOk();

        $dp->update(['status' => DirectPurchase::STATUS_APPROVED]);
        $line = $dp->items->first();

        $this->receive('dp', $dp->id, [['source_line_id' => $line->id, 'qty' => 11, 'destination' => 'warehouse']], $this->stockWarehouse->id)->assertStatus(422);
        $this->receive('dp', $dp->id, [['source_line_id' => $line->id, 'qty' => 10, 'destination' => 'warehouse']], $this->stockWarehouse->id)->assertOk();

        $this->assertSame(10, StockMovement::balance($this->stockWarehouse->id, $product->id));
        $this->assertFalse($dp->fresh('items.goodsReceiptItems.goodsReceipt')->isReceivable());
    }

    public function test_internal_request_is_issued_from_free_stock(): void
    {
        $product = $this->product('PN-1');
        StockMovement::record(['warehouse_id' => $this->stockWarehouse->id, 'master_product_id' => $product->id, 'type' => StockMovement::TYPE_MISC_IN, 'qty' => 3, 'created_by' => $this->admin->id]);
        $requestItem = $this->approvedRequest(5, null, $product);

        $this->actingAs($this->warehouseUser)->get(route('stock-out.index'))->assertOk()->assertSee('Keluarkan');

        $store = fn (int $qty) => $this->actingAs($this->warehouseUser)->postJson(route('stock-out.store'), [
            'goods_request_item_id' => $requestItem->id,
            'warehouse_id' => $this->stockWarehouse->id,
            'qty' => $qty,
        ]);

        $store(4)->assertStatus(422); // stok hanya 3
        $store(3)->assertOk();

        $this->assertSame(0, StockMovement::balance($this->stockWarehouse->id, $product->id));
        $fresh = GoodsRequestItem::findOrFail($requestItem->id);
        $this->assertSame(3, $fresh->stockQty());
        $this->assertSame(2, $fresh->remainingQty());
    }

    public function test_project_request_is_allocated_to_transit_warehouse(): void
    {
        $project = $this->project();
        $product = $this->product('PN-1');
        StockMovement::record(['warehouse_id' => $this->stockWarehouse->id, 'master_product_id' => $product->id, 'type' => StockMovement::TYPE_MISC_IN, 'qty' => 5, 'created_by' => $this->admin->id]);
        $requestItem = $this->approvedRequest(2, $project, $product);

        $payload = ['goods_request_item_id' => $requestItem->id, 'warehouse_id' => $this->stockWarehouse->id, 'qty' => 2];
        $this->actingAs($this->warehouseUser)->postJson(route('stock-out.store'), $payload)->assertStatus(422);
        $this->actingAs($this->warehouseUser)->postJson(route('stock-out.store'), $payload + ['destination_warehouse_id' => $this->transitWarehouse->id])->assertOk();

        $this->assertSame(3, StockMovement::balance($this->stockWarehouse->id, $product->id));
        $this->assertSame(2, StockMovement::balance($this->transitWarehouse->id, $product->id, $project->id));
        $this->assertSame(0, GoodsRequestItem::findOrFail($requestItem->id)->remainingQty());
    }

    public function test_close_remaining_by_creator_with_reason(): void
    {
        $requestItem = $this->approvedRequest(4);
        $requestItem->goodsRequest->update(['created_by' => $this->warehouseUser->id]);
        $this->approvedPo($requestItem, 1);

        $url = route('goods-request.close-remaining', $requestItem->goods_request_id);
        $this->actingAs($this->warehouseUser)->patchJson($url, [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($this->warehouseUser)->patchJson($url, ['reason' => 'Tidak jadi dipakai'])->assertOk();

        $fresh = GoodsRequestItem::findOrFail($requestItem->id);
        $this->assertSame(3, $fresh->closed_qty);
        $this->assertSame(0, $fresh->remainingQty());
        $this->assertSame('done', $fresh->fulfillmentStatus());

        $this->actingAs($this->warehouseUser)->patchJson($url, ['reason' => 'lagi'])->assertStatus(422);
    }

    public function test_map_product_on_legacy_item_until_processed(): void
    {
        $requestItem = $this->approvedRequest(2);
        $requestItem->update(['master_product_id' => null]);
        $product = $this->product('PN-NEW');
        $url = route('goods-request.items.product', [$requestItem->goods_request_id, $requestItem->id]);

        $this->actingAs($this->warehouseUser)->patchJson($url, ['master_product_id' => $product->id])->assertOk();
        $this->assertSame($product->id, $requestItem->fresh()->master_product_id);

        $this->approvedPo($requestItem->fresh());
        $this->actingAs($this->warehouseUser)->patchJson($url, ['master_product_id' => $this->product('PN-1')->id])->assertStatus(422);
    }
}

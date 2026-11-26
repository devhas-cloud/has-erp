<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Currency;
use App\Models\DirectPurchase;
use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Alur procure-to-stock: Penerimaan Barang (PO / Direct Purchase) dengan
 * tujuan per baris, alokasi project, Pemenuhan dari Stok, Tutup Sisa, dan
 * pemetaan master product pada item Permintaan Barang lama.
 */
class PurchasingInventoryTest extends TestCase
{
    use InteractsWithStock;
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

        $this->stockWarehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang 12', 'status' => 'Active']);
        $this->transitWarehouse = Warehouse::create(['code' => 'T1', 'name' => 'Transit', 'status' => 'Active']);
    }

    private function product(string $code): MasterProduct
    {
        // Default material (dihitung per jumlah); test barang ber-SN membuat produknya sendiri.
        return MasterProduct::firstOrCreate(['code' => $code], ['name' => 'Produk '.$code, 'division_id' => $this->division->id, 'status' => 'Active', 'is_material' => true]);
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
        $this->assertEquals(2, $this->stockBalance($this->transitWarehouse->id, $requestItem->master_product_id, $project->id));
        $this->assertEquals(0, $this->stockBalance($this->transitWarehouse->id, $requestItem->master_product_id));

        $this->receive('po', $po->id, [['source_line_id' => $line->id, 'qty' => 3, 'destination' => 'warehouse']], $this->transitWarehouse->id)->assertOk();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);

        $this->actingAs($this->warehouseUser)->get(route('goods-receipt.show', $id))->assertOk()->assertSee('SPARING Gresik');
        $this->actingAs($this->warehouseUser)->get(route('goods-receipt.create', ['source_type' => 'po', 'source_id' => $po->id]))->assertOk();
    }

    public function test_serial_items_accept_optional_photos_on_receipt_and_later(): void
    {
        Storage::fake('local');
        $serial = MasterProduct::create(['code' => 'SN-CAM', 'name' => 'Kamera', 'division_id' => $this->division->id, 'status' => 'Active', 'is_material' => false]);
        $serialPo = $this->approvedPo($this->approvedRequest(2, null, $serial));
        $materialPo = $this->approvedPo($this->approvedRequest(3));

        // Foto pada material ditolak; tidak ada yang terposting.
        $this->actingAs($this->warehouseUser)->post(route('goods-receipt.store'), [
            'source_type' => 'po', 'source_id' => $materialPo->id, 'warehouse_id' => $this->stockWarehouse->id,
            'items' => [['source_line_id' => $materialPo->items->first()->id, 'qty' => 3, 'destination' => 'warehouse', 'photos' => [UploadedFile::fake()->image('m.jpg')]]],
        ], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, GoodsReceipt::count());

        // Barang ber-SN: 2 foto ikut tersimpan bersama posting.
        $id = $this->actingAs($this->warehouseUser)->post(route('goods-receipt.store'), [
            'source_type' => 'po', 'source_id' => $serialPo->id, 'warehouse_id' => $this->stockWarehouse->id,
            'items' => [['source_line_id' => $serialPo->items->first()->id, 'qty' => 2, 'destination' => 'warehouse', 'serial_numbers' => ['A1'],
                'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')]]],
        ], ['Accept' => 'application/json'])->assertOk()->json('id');

        $item = GoodsReceipt::findOrFail($id)->items->first();
        $this->assertCount(2, $item->photos);
        $item->photos->each(fn ($doc) => Storage::disk('local')->assertExists($doc->file_path));

        // Foto susulan dari halaman detail, lalu ditampilkan di galeri.
        $this->actingAs($this->warehouseUser)->post(route('goods-receipt.photos.store', [$id, $item->id]), [
            'files' => [UploadedFile::fake()->image('c.webp')],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(3, 'photos');
        $photo = $item->photos()->first();
        $this->actingAs($this->warehouseUser)->get(route('goods-receipt.photos.show', [$id, $item->id, $photo->id]))->assertOk();
        $this->actingAs($this->warehouseUser)->get(route('goods-receipt.show', $id))->assertOk()
            ->assertSee('data-upload-url="'.route('goods-receipt.photos.store', [$id, $item->id]).'"', false)
            ->assertSee(str_replace('/', '\\/', route('goods-receipt.photos.show', [$id, $item->id, $photo->id])), false);

        // Maksimal 10 foto per item.
        $this->actingAs($this->warehouseUser)->post(route('goods-receipt.photos.store', [$id, $item->id]), [
            'files' => array_map(fn ($i) => UploadedFile::fake()->image("x{$i}.jpg"), range(1, 8)),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        // Hapus foto (butuh hak hapus modul; Admin boleh).
        $this->actingAs($this->admin)->deleteJson(route('goods-receipt.photos.destroy', [$id, $item->id, $photo->id]))->assertOk();
        $this->assertCount(2, $item->photos()->get());
        Storage::disk('local')->assertMissing($photo->file_path);
    }

    public function test_receipt_values_stock_in_base_currency_after_po_discount(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 15000, 'is_base' => false, 'status' => 'Active']);

        $po = $this->approvedPo($this->approvedRequest(2));
        $po->update(['discount_percent' => 10]);
        $po->items()->update(['price' => 100, 'currency' => 'USD']);

        $this->receive('po', $po->id, [['source_line_id' => $po->items->first()->id, 'qty' => 2, 'destination' => 'warehouse']], $this->stockWarehouse->id)->assertOk();

        $this->assertEquals(1350000, Stock::value('unit_cost'));
        $this->assertEquals(1350000, StockLog::where('type', StockLog::TYPE_IN)->value('unit_cost'));
    }

    public function test_receipt_uses_entered_exchange_rate_and_records_it(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        $usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 15000, 'is_base' => false, 'status' => 'Active']);
        Currency::whereKey($usd->id)->update(['updated_at' => now()->subDays(10)]);

        $po = $this->approvedPo($this->approvedRequest(2));
        $po->items()->update(['price' => 100, 'currency' => 'USD']);
        $line = $po->items->first();

        // Form menerima kurs master sebagai default, ditandai usang.
        $this->actingAs($this->warehouseUser)
            ->getJson(route('goods-receipt.fetch-source-items', ['source_type' => 'po', 'source_id' => $po->id]))
            ->assertOk()
            ->assertJsonPath('currencies.0.code', 'USD')
            ->assertJsonPath('currencies.0.known', true)
            ->assertJsonPath('currencies.0.stale', true);

        $this->actingAs($this->warehouseUser)->postJson(route('goods-receipt.store'), [
            'source_type' => 'po',
            'source_id' => $po->id,
            'warehouse_id' => $this->stockWarehouse->id,
            'received_date' => '2026-11-02',
            'items' => [['source_line_id' => $line->id, 'qty' => 2, 'destination' => 'warehouse']],
            'rates' => ['USD' => 16250],
        ])->assertOk();

        $this->assertEquals(16250, GoodsReceiptItem::value('exchange_rate'));
        $this->assertEquals(1625000, Stock::value('unit_cost'));
        // Kurs master tidak berubah oleh penerimaan.
        $this->assertEquals(15000, (float) $usd->fresh()->rate);
    }

    public function test_receipt_uses_rate_set_on_po(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 15000, 'is_base' => false, 'status' => 'Active']);

        $po = $this->approvedPo($this->approvedRequest(2));
        $po->items()->update(['price' => 100, 'currency' => 'USD']);
        $po->update(['exchange_rate' => 17000]);

        $this->actingAs($this->warehouseUser)
            ->getJson(route('goods-receipt.fetch-source-items', ['source_type' => 'po', 'source_id' => $po->id]))
            ->assertJsonPath('currencies.0.locked', true)
            ->assertJsonPath('currencies.0.rate', 17000);

        // Kurs yang dikirim form diabaikan: kurs PO yang berlaku.
        $this->actingAs($this->warehouseUser)->postJson(route('goods-receipt.store'), [
            'source_type' => 'po', 'source_id' => $po->id, 'warehouse_id' => $this->stockWarehouse->id, 'received_date' => '2026-11-02',
            'items' => [['source_line_id' => $po->items->first()->id, 'qty' => 2, 'destination' => 'warehouse']],
            'rates' => ['USD' => 16250],
        ])->assertOk();

        $this->assertEquals(17000, GoodsReceiptItem::value('exchange_rate'));
        $this->assertEquals(1700000, Stock::value('unit_cost'));
    }

    public function test_receipt_rejects_currency_missing_from_master(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);

        $po = $this->approvedPo($this->approvedRequest(2));
        $po->items()->update(['price' => 100, 'currency' => 'SGD']);

        $this->actingAs($this->warehouseUser)
            ->getJson(route('goods-receipt.fetch-source-items', ['source_type' => 'po', 'source_id' => $po->id]))
            ->assertJsonPath('currencies.0.known', false);

        $this->receive('po', $po->id, [['source_line_id' => $po->items->first()->id, 'qty' => 2, 'destination' => 'warehouse']], $this->stockWarehouse->id)
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
        $this->assertSame(0, Stock::count());
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
        $this->assertSame(0, Stock::count());
        $this->assertSame(0, StockLog::count());
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

        $this->assertEquals(10, $this->stockBalance($this->stockWarehouse->id, $product->id));
        $this->assertFalse($dp->fresh('items.goodsReceiptItems.goodsReceipt')->isReceivable());
    }

    public function test_direct_purchase_sets_exchange_rate_used_by_bill_and_receipt(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 15000, 'is_base' => false, 'status' => 'Active']);
        $product = $this->product('DP-FX');
        $supplier = \App\Models\Supplier::create(['name' => 'Supplier Luar']);
        $payload = fn (string $currency, ?float $rate) => [
            'supplier_name' => 'Supplier Luar', 'supplier_id' => $supplier->id, 'purpose' => 'stock', 'exchange_rate' => $rate,
            'items' => [['master_product_id' => $product->id, 'description' => 'Sensor', 'qty' => 2, 'unit' => 'Unit', 'price' => 100, 'currency' => $currency]],
        ];

        // Valas: kurs isian disimpan; kosong -> kurs master. Mata uang dasar -> tanpa kurs.
        $id = $this->actingAs($this->admin)->postJson(route('direct-purchase.store'), $payload('USD', 16500))->assertOk()->json('id');
        $this->assertEquals(16500, DirectPurchase::find($id)->exchange_rate);
        $other = $this->actingAs($this->admin)->postJson(route('direct-purchase.store'), $payload('USD', null))->json('id');
        $this->assertEquals(15000, DirectPurchase::find($other)->exchange_rate);
        $local = $this->actingAs($this->admin)->postJson(route('direct-purchase.store'), $payload('IDR', 16500))->json('id');
        $this->assertNull(DirectPurchase::find($local)->exchange_rate);
        $this->actingAs($this->admin)->postJson(route('direct-purchase.store'), $payload('USD', -1))->assertStatus(422);

        // Diubah saat Draft.
        $this->actingAs($this->admin)->putJson(route('direct-purchase.update', $id), $payload('USD', 16800))->assertOk();
        $dp = DirectPurchase::with('items')->findOrFail($id);
        $this->assertEquals(16800, $dp->exchange_rate);
        $this->actingAs($this->admin)->get(route('direct-purchase.show', $id))->assertOk()->assertSee('Set on this direct purchase');
        $this->actingAs($this->admin)->get(route('direct-purchase.edit', $id))->assertOk()->assertSee('dp-exchange-rate', false);

        // Tagihan otomatis memakai kurs DP (bukan perkiraan).
        $dp->update(['status' => DirectPurchase::STATUS_WAITING_APPROVAL]);
        $this->actingAs($this->admin)->postJson(route('direct-purchase.approve', $id))->assertOk();
        $bill = \App\Models\SupplierBill::where('direct_purchase_id', $id)->firstOrFail();
        $this->assertEquals(16800, $bill->exchange_rate);
        $this->assertFalse($bill->rate_estimated);

        // Penerimaan: kurs DP terkunci, isian form diabaikan.
        $this->actingAs($this->warehouseUser)
            ->getJson(route('goods-receipt.fetch-source-items', ['source_type' => 'dp', 'source_id' => $id]))
            ->assertJsonPath('currencies.0.locked', true)
            ->assertJsonPath('currencies.0.rate', 16800);
        $this->actingAs($this->warehouseUser)->postJson(route('goods-receipt.store'), [
            'source_type' => 'dp', 'source_id' => $id, 'warehouse_id' => $this->stockWarehouse->id, 'received_date' => '2026-11-02',
            'items' => [['source_line_id' => $dp->items->first()->id, 'qty' => 2, 'destination' => 'warehouse']],
            'rates' => ['USD' => 15000],
        ])->assertOk();
        $this->assertEquals(16800, GoodsReceiptItem::value('exchange_rate'));
        $this->assertEquals(1680000, Stock::value('unit_cost'));

        // Detail DP: total setara IDR, kurs penerimaan & kurs tagihan.
        $this->actingAs($this->admin)->get(route('direct-purchase.show', $id))->assertOk()
            ->assertSee('≈ IDR')->assertSee('3,360,000')->assertSee('@ 16,800');
    }

    public function test_internal_request_is_issued_from_free_stock(): void
    {
        $product = $this->product('PN-1');
        $row = $this->seedStock($this->stockWarehouse->id, $product, 3)->first();
        $requestItem = $this->approvedRequest(5, null, $product);

        $this->actingAs($this->warehouseUser)->get(route('stock-out.index'))->assertOk()->assertSee('Keluarkan');
        $this->actingAs($this->warehouseUser)
            ->getJson(route('stock-out.stock-options', ['goods_request_item_id' => $requestItem->id, 'warehouse_id' => $this->stockWarehouse->id]))
            ->assertOk()->assertJsonPath('data.0.id', $row->id);

        $store = fn (float $qty) => $this->actingAs($this->warehouseUser)->postJson(route('stock-out.store'), [
            'goods_request_item_id' => $requestItem->id,
            'warehouse_id' => $this->stockWarehouse->id,
            'picks' => [['stock_id' => $row->id, 'qty' => $qty]],
        ]);

        $store(4)->assertStatus(422); // stok hanya 3
        $store(2.5)->assertStatus(422); // total harus bulat sesuai satuan permintaan
        $store(3)->assertOk();

        $this->assertEquals(0, $this->stockBalance($this->stockWarehouse->id, $product->id));
        $this->assertSame(-3.0, StockLog::where('type', StockLog::TYPE_OUT)->value('qty'));
        $fresh = GoodsRequestItem::findOrFail($requestItem->id);
        $this->assertSame(3, $fresh->stockQty());
        $this->assertSame(2, $fresh->remainingQty());
    }

    public function test_project_request_can_be_allocated_in_the_same_warehouse(): void
    {
        $project = $this->project();
        $product = $this->product('PN-2');
        $row = $this->seedStock($this->stockWarehouse->id, $product, 4)->first();
        $requestItem = $this->approvedRequest(4, $project, $product);

        // Tanpa gudang tujuan = tetap di gudang asal, hanya pindah kepemilikan: saldo bebas -> saldo project.
        $this->actingAs($this->warehouseUser)->postJson(route('stock-out.store'), [
            'goods_request_item_id' => $requestItem->id,
            'warehouse_id' => $this->stockWarehouse->id,
            'picks' => [['stock_id' => $row->id, 'qty' => 4]],
        ])->assertOk();

        $this->assertEquals(4, $this->stockBalance($this->stockWarehouse->id, $product->id, $project->id));
        $this->assertEquals(0, $this->stockBalance($this->stockWarehouse->id, $product->id));
        $this->assertSame(2, Stock::count());
        $this->assertSame([-4.0, 4.0], StockLog::where('type', StockLog::TYPE_ALLOCATE)->orderBy('id')->pluck('qty')->all());
        $this->assertSame(4, GoodsRequestItem::findOrFail($requestItem->id)->stockQty());
    }

    public function test_project_request_is_allocated_to_transit_warehouse(): void
    {
        $project = $this->project();
        $product = $this->product('PN-1');
        $row = $this->seedStock($this->stockWarehouse->id, $product, 5)->first();
        $requestItem = $this->approvedRequest(2, $project, $product);

        $payload = ['goods_request_item_id' => $requestItem->id, 'warehouse_id' => $this->stockWarehouse->id, 'picks' => [['stock_id' => $row->id, 'qty' => 2]]];
        $this->actingAs($this->warehouseUser)->postJson(route('stock-out.store'), $payload + ['destination_warehouse_id' => $this->transitWarehouse->id])->assertOk();

        $this->assertEquals(3, $this->stockBalance($this->stockWarehouse->id, $product->id));
        $this->assertEquals(2, $this->stockBalance($this->transitWarehouse->id, $product->id, $project->id));
        // Material: saldo project di gudang transit (satu baris, tanpa pecahan).
        $this->assertSame(1, Stock::where('project_id', $project->id)->count());
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

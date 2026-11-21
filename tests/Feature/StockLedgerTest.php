<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockEntry;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Skema stok baru (stocks + stock_logs): barang ber-SN per unit dengan SN
 * menyusul, unit tanpa SN diblokir keluar, transfer tanpa pecah, retur SN,
 * kunci is_material, dan riwayat per SN.
 */
class StockLedgerTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private User $user;

    private Division $division;

    private Warehouse $warehouse;

    private Warehouse $site;

    private MasterProduct $inverter;

    private MasterProduct $cable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'Admin', 'description' => 'Admin', 'type' => 'Internal', 'status' => 'Active']);
        $this->user = User::create(['username' => 'gudang', 'email' => 'gudang@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'Admin']);
        $modules = [
            'goods-receipt' => 'MOD_GOODS_RECEIPT', 'stock-entry' => 'MOD_STOCK_ENTRY', 'stock-transfer' => 'MOD_STOCK_TRANSFER',
            'stock-detail' => 'MOD_STOCK_DETAIL', 'stock-report' => 'MOD_STOCK_REPORT', 'product-management' => 'MOD_PRODUCT',
        ];
        foreach ($modules as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Inventory']);
            UserAccessControl::create(['user_id' => $this->user->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => false, 'can_approve' => false]);
        }

        $this->warehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang Pusat', 'status' => 'Active']);
        $this->site = Warehouse::create(['code' => 'G2', 'name' => 'Gudang Site', 'status' => 'Active']);
        $this->inverter = MasterProduct::create(['code' => 'INV-5', 'name' => 'Inverter 5kW', 'division_id' => $this->division->id, 'status' => 'Active']);
        $this->cable = MasterProduct::create(['code' => 'CBL-1', 'name' => 'Kabel NYY', 'division_id' => $this->division->id, 'status' => 'Active', 'is_material' => true]);
    }

    private function approvedPo(MasterProduct $product, int $qty): PurchaseOrder
    {
        $supplier = Supplier::create(['name' => 'PT Supplier', 'status' => 'Active']);
        $po = PurchaseOrder::create(['supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'po_number' => 'PO-'.uniqid(), 'status' => PurchaseOrder::STATUS_APPROVED, 'created_by' => $this->user->id]);
        $po->items()->create(['master_product_id' => $product->id, 'part_number' => $product->code, 'description' => $product->name, 'qty' => $qty, 'unit' => 'Unit', 'price' => 1000, 'currency' => 'IDR', 'sort_order' => 1]);

        return $po->load('items');
    }

    public function test_receipt_of_serialized_item_creates_one_row_per_unit_with_serials_optional(): void
    {
        $po = $this->approvedPo($this->inverter, 3);
        $line = $po->items->first();
        $receive = fn (array $item) => $this->actingAs($this->user)->postJson(route('goods-receipt.store'), [
            'source_type' => 'po', 'source_id' => $po->id, 'warehouse_id' => $this->warehouse->id, 'received_date' => '2026-11-16',
            'items' => [array_merge(['source_line_id' => $line->id, 'qty' => 3, 'destination' => 'warehouse'], $item)],
        ]);

        $receive(['serial_numbers' => ['A1', 'A1']])->assertStatus(422);                 // SN ganda
        $receive(['serial_numbers' => ['A1', 'A2', 'A3', 'A4']])->assertStatus(422);     // SN melebihi qty

        $id = $receive(['serial_numbers' => ['A1', 'A2'], 'batch_number' => 'MFG-77', 'warranty_until' => '2027-11-16'])->assertOk()->json('id');

        $rows = Stock::orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame([1.0, 1.0, 1.0], $rows->pluck('qty_in')->all());
        $this->assertSame(['A1', 'A2', null], $rows->pluck('serial_number')->all());
        $this->assertSame(['MFG-77'], $rows->pluck('batch_number')->unique()->values()->all());
        $this->assertSame($po->supplier_id, $rows->first()->supplier_id);
        $this->assertSame(1, Stock::missingSerial()->count());

        $this->actingAs($this->user)->get(route('goods-receipt.show', $id))->assertOk()->assertSee('1 SN unit not filled in');

        // Daftar: kolom SN kosong + filter toolbar.
        $data = fn (array $filters) => $this->actingAs($this->user)->getJson(route('goods-receipt.data', $filters))->assertOk();
        $data([])->assertJsonPath('data.0.missing_serial', 1)->assertJsonPath('data.0.source_kind', 'PO');
        $data(['missing_sn' => 1])->assertJsonPath('recordsFiltered', 1);
        $data(['source_type' => 'dp'])->assertJsonPath('recordsFiltered', 0);
        $data(['warehouse' => 'direct'])->assertJsonPath('recordsFiltered', 0);
        $data(['warehouse' => $this->warehouse->id, 'date_from' => '2026-11-16', 'date_to' => '2026-11-16', 'supplier' => 'PT Supplier'])->assertJsonPath('recordsFiltered', 1);
        $data(['date_from' => '2026-11-17'])->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($this->user)->get(route('goods-receipt.index'))->assertOk()->assertSee('SN incomplete');

        // SN yang sudah terdaftar ditolak di penerimaan berikutnya.
        $po2 = $this->approvedPo($this->inverter, 1);
        $this->actingAs($this->user)->postJson(route('goods-receipt.store'), [
            'source_type' => 'po', 'source_id' => $po2->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['source_line_id' => $po2->items->first()->id, 'qty' => 1, 'destination' => 'warehouse', 'serial_numbers' => ['A2']]],
        ])->assertStatus(422)->assertJsonPath('message', 'SN INV-5 sudah terdaftar: A2.');
        $this->assertSame(1, GoodsReceipt::count());
    }

    public function test_complete_serial_later_and_lock_after_leaving(): void
    {
        [$a, $b] = $this->seedStock($this->warehouse->id, $this->inverter, 2, [], ['SN-1'])->all();

        $this->actingAs($this->user)->get(route('stock-detail.index'))->assertOk()->assertSee('Lengkapi Data Stok');
        $this->actingAs($this->user)->getJson(route('stock-detail.data', ['show' => 'missing_serial']))
            ->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.id', $b->id);

        // SN ganda ditolak.
        $this->actingAs($this->user)->postJson(route('stock-detail.save'), ['rows' => [['id' => $b->id, 'serial_number' => 'SN-1']]])->assertStatus(422);

        $this->actingAs($this->user)->postJson(route('stock-detail.save'), ['rows' => [['id' => $b->id, 'serial_number' => 'SN-2', 'expiry_date' => '2030-01-01', 'note' => 'Unit demo']]])
            ->assertOk()->assertJsonPath('missing_total', 0);
        $this->assertSame('SN-2', $b->fresh()->serial_number);
        $this->assertSame('Unit demo', $b->fresh()->note);
        $this->assertDatabaseHas('stock_logs', ['stock_id' => $b->id, 'type' => StockLog::TYPE_DETAIL_UPDATE, 'qty' => 0]);
        $this->assertStringContainsString('SN: — → SN-2', StockLog::where('type', StockLog::TYPE_DETAIL_UPDATE)->value('note'));

        // Material tidak memakai SN.
        $roll = $this->seedStock($this->warehouse->id, $this->cable, 100)->first();
        $this->actingAs($this->user)->postJson(route('stock-detail.save'), ['rows' => [['id' => $roll->id, 'serial_number' => 'X']]])->assertStatus(422);

        // Setelah keluar, data tidak bisa diubah.
        $this->actingAs($this->user);
        app(StockService::class)->take($a->fresh('masterProduct'), 1, StockLog::TYPE_OUT, []);
        $this->actingAs($this->user)->postJson(route('stock-detail.save'), ['rows' => [['id' => $a->id, 'batch_number' => 'LATE']]])->assertStatus(422);

        $this->actingAs($this->user)->get(route('stock-detail.show', $a->id))->assertOk()->assertSee('SN-1')->assertSee('Keluar');
    }

    public function test_unit_without_serial_cannot_leave_but_can_be_transferred_whole(): void
    {
        $unit = $this->seedStock($this->warehouse->id, $this->inverter, 1)->first();
        $service = app(StockService::class);

        try {
            $service->take($unit->fresh('masterProduct'), 1, StockLog::TYPE_OUT, []);
            $this->fail('Unit tanpa SN seharusnya tidak bisa keluar.');
        } catch (StockException $e) {
            $this->assertStringContainsString('belum punya SN', $e->getMessage());
        }

        // Transfer boleh tanpa SN dan tidak memecah baris.
        $this->actingAs($this->user)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $this->site->id,
            'items' => [['stock_id' => $unit->id, 'qty' => 1]],
        ])->assertOk();

        $this->assertSame(1, Stock::count());
        $this->assertSame($this->site->id, $unit->fresh()->warehouse_id);
        $this->assertSame([-1.0, 1.0], StockLog::whereIn('type', [StockLog::TYPE_TRANSFER_OUT, StockLog::TYPE_TRANSFER_IN])->orderBy('id')->pluck('qty')->all());
        $this->assertSame([$this->warehouse->id, $this->site->id], StockLog::whereIn('type', [StockLog::TYPE_TRANSFER_OUT, StockLog::TYPE_TRANSFER_IN])->orderBy('id')->pluck('warehouse_id')->all());

        // Barang ber-SN tidak bisa diambil sebagian.
        $this->expectException(StockException::class);
        $service->move($unit->fresh('masterProduct'), 0.5, $this->warehouse->id, null, StockLog::TYPE_TRANSFER_OUT, []);
    }

    public function test_material_is_one_balance_per_position_with_weighted_cost(): void
    {
        // Dua penerimaan material di gudang yang sama -> satu saldo, harga rata-rata tertimbang.
        $roll = $this->seedStock($this->warehouse->id, $this->cable, 100, ['unit_cost' => 5000, 'batch_number' => 'LOT-A'])->first();
        $again = $this->seedStock($this->warehouse->id, $this->cable, 50, ['unit_cost' => 8000, 'batch_number' => 'LOT-B'])->first();
        $this->assertSame($roll->id, $again->id);
        $roll->refresh();
        $this->assertEquals(150, $roll->qty);
        $this->assertEquals(150, $roll->qty_in);
        $this->assertEquals(6000, $roll->unit_cost); // (100×5000 + 50×8000) / 150
        $this->assertNull($roll->batch_number, 'batch berbeda tidak memisah saldo');
        $this->assertEquals(8000, StockLog::where('stock_id', $roll->id)->orderByDesc('id')->value('unit_cost'), 'log masuk mencatat harga barang yang masuk');

        // Transfer sebagian: saldo asal berkurang, saldo tujuan dibuat / ditambah (tanpa pecahan baris).
        $transfer = fn (float $qty) => $this->actingAs($this->user)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $this->site->id,
            'items' => [['stock_id' => $roll->id, 'qty' => $qty]],
        ])->assertOk();
        $transfer(15.5);
        $transfer(4.5);

        $site = Stock::pool($this->cable->id, $this->site->id, null)->get();
        $this->assertCount(1, $site);
        $this->assertEquals(20, $site->first()->qty);
        $this->assertEquals(6000, $site->first()->unit_cost);
        $this->assertNull($site->first()->parent_id);
        $this->assertEquals(130, $roll->fresh()->qty);
        $this->assertSame(2, Stock::count());

        // Saldo habis lalu terisi lagi: tetap baris yang sama.
        $this->actingAs($this->user);
        app(StockService::class)->take($site->first()->fresh('masterProduct'), 20, StockLog::TYPE_OUT, ['note' => 'pakai']);
        $refill = $this->seedStock($this->site->id, $this->cable, 5, ['unit_cost' => 7000])->first();
        $this->assertSame($site->first()->id, $refill->id);
        $this->assertEquals(7000, $refill->fresh()->unit_cost);

        // Setiap keluar tetap tercatat per transaksi.
        $this->assertSame(1, StockLog::where('stock_id', $site->first()->id)->where('type', StockLog::TYPE_OUT)->count());
        $this->actingAs($this->user)->get(route('stock-detail.show', $roll->id))->assertOk();
    }

    public function test_migration_merges_old_material_rows_into_one_balance(): void
    {
        // Baris material lama (sebelum aturan saldo): 3 baris di posisi yang sama + 1 baris habis.
        $make = fn (float $qty, float $cost, float $qtyIn = null) => Stock::create([
            'master_product_id' => $this->cable->id, 'warehouse_id' => $this->warehouse->id, 'project_id' => null,
            'is_material' => true, 'qty_in' => $qtyIn ?? max($qty, 1), 'qty' => $qty, 'unit_cost' => $cost, 'received_date' => now()->toDateString(),
        ]);
        $first = $make(10, 1000);
        $closed = $make(0, 9999, 4);
        $second = $make(20, 2500);
        $third = $make(5, 4000, 8);
        $unit = $this->seedStock($this->warehouse->id, $this->inverter, 1, [], ['SN-X'])->first();

        (require database_path('migrations/2026_11_19_000002_merge_material_stock_rows.php'))->up();

        $first->refresh();
        $this->assertEquals(35, $first->qty);
        $this->assertEquals(10 + 20 + 5, $first->qty_in);
        $this->assertEquals(round((10 * 1000 + 20 * 2500 + 5 * 4000) / 35, 4), $first->unit_cost);
        $this->assertEquals(0, $second->fresh()->qty);
        $this->assertEquals(0, $third->fresh()->qty);
        $this->assertEquals(0, $closed->fresh()->qty);
        $this->assertSame(4, StockLog::where('type', StockLog::TYPE_MERGE)->count());
        $this->assertEquals(35, $this->stockBalance($this->warehouse->id, $this->cable->id));
        // Barang ber-SN tidak disentuh.
        $this->assertEquals(1, $unit->fresh()->qty);

        // Masuk berikutnya menambah saldo yang sama.
        $this->assertSame($first->id, $this->seedStock($this->warehouse->id, $this->cable, 1, ['unit_cost' => 1000])->first()->id);
    }

    public function test_stock_entry_returns_serial_to_its_history_and_rejects_serial_in_stock(): void
    {
        $unit = $this->seedStock($this->warehouse->id, $this->inverter, 1, [], ['SN-9'])->first();
        $entry = fn (array $serials, int $qty = 1) => $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->site->id,
            'reason' => StockEntry::REASON_RETURN,
            'items' => [['master_product_id' => $this->inverter->id, 'qty' => $qty, 'serial_numbers' => $serials]],
        ]);

        $entry(['SN-9'])->assertStatus(422); // masih tercatat di stok

        $this->actingAs($this->user);
        app(StockService::class)->take($unit->fresh('masterProduct'), 1, StockLog::TYPE_OUT, []);

        // Kembali dari customer: baris lama aktif lagi (riwayat SN satu jalur), tidak membuat baris baru.
        $entry(['SN-9'])->assertOk();
        $this->assertSame(1, Stock::count());
        $this->assertEquals(1, $unit->fresh()->qty);
        $this->assertSame($this->site->id, $unit->fresh()->warehouse_id);

        // Produk sama boleh lebih dari satu baris; material boleh desimal; barang ber-SN harus bulat.
        $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->warehouse->id,
            'reason' => StockEntry::REASON_OPENING,
            'items' => [
                ['master_product_id' => $this->cable->id, 'qty' => 10.25, 'batch_number' => 'LOT-1'],
                ['master_product_id' => $this->cable->id, 'qty' => 4, 'batch_number' => 'LOT-2'],
            ],
        ])->assertOk();
        $this->assertEquals(14.25, $this->stockBalance($this->warehouse->id, $this->cable->id));
        $entry([], 2)->assertOk();
        $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->warehouse->id,
            'reason' => StockEntry::REASON_OPENING,
            'items' => [['master_product_id' => $this->inverter->id, 'qty' => 1.5]],
        ])->assertStatus(422);
    }

    public function test_serialized_quantity_guard_and_is_material_lock(): void
    {
        try {
            $this->seedStock($this->warehouse->id, $this->inverter, StockService::MAX_SERIAL_UNITS + 1);
            $this->fail('Qty besar barang ber-SN seharusnya ditolak.');
        } catch (StockException $e) {
            $this->assertStringContainsString('ubah ke Material', $e->getMessage());
        }

        $this->seedStock($this->warehouse->id, $this->inverter, 1, [], ['SN-1']);
        $this->assertTrue($this->inverter->hasStock());

        $this->actingAs($this->user)->putJson(route('product-management.update', $this->inverter->id), [
            'code' => 'INV-5', 'name' => 'Inverter 5kW', 'status' => 'Active', 'is_material' => 1,
        ])->assertStatus(422)->assertJsonPath('success', false);
        $this->assertFalse($this->inverter->fresh()->isMaterial());

        // Tanpa stok, jenis stok boleh diubah.
        $this->actingAs($this->user)->putJson(route('product-management.update', $this->cable->id), [
            'code' => 'CBL-1', 'name' => 'Kabel NYY', 'status' => 'Active', 'is_material' => 0,
        ])->assertOk();
        $this->assertFalse($this->cable->fresh()->isMaterial());
    }

    public function test_stock_card_opening_balance_is_calculated_backwards(): void
    {
        $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->warehouse->id, 'reason' => StockEntry::REASON_OPENING, 'entry_date' => '2026-10-05',
            'items' => [['master_product_id' => $this->cable->id, 'qty' => 50, 'unit_cost' => 100]],
        ])->assertOk();
        $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->warehouse->id, 'reason' => StockEntry::REASON_OPENING, 'entry_date' => '2026-11-05',
            'items' => [['master_product_id' => $this->cable->id, 'qty' => 20, 'unit_cost' => 100]],
        ])->assertOk();

        // Periode November: saldo awal = 70 sekarang - 20 masuk sejak 1 Nov = 50.
        $response = $this->actingAs($this->user)->get(route('stock-report.card', ['productId' => $this->cable->id, 'from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk();
        $this->assertEquals(50, $response->viewData('openingQty'));
        $this->assertEquals(5000, $response->viewData('openingValue'));
        $this->assertCount(1, $response->viewData('rows'));
        $response->assertSee('Saldo akhir: 70');
    }
}

<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\DeliveryOrder;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\PackingList;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\StockLog;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Packing List per koli -> Siap Kirim (stok dipesan) -> dimuat Delivery Order
 * (baris dari koli) -> posting DO mengeluarkan stok dari isi koli.
 */
class PackingListTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Warehouse $other;

    private Project $project;

    private QuotationItem $telemetry;

    private QuotationItem $sensors;

    private MasterProduct $logger;

    private MasterProduct $cable;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->user = User::create(['username' => 'gudang', 'email' => 'gudang@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);
        foreach (['packing-list' => 'MOD_PACKING_LIST', 'delivery-order' => 'MOD_DELIVERY_ORDER'] as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Inventory']);
            UserAccessControl::create(['user_id' => $this->user->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
        }

        $this->warehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang Utama', 'status' => 'Active']);
        $this->other = Warehouse::create(['code' => 'G2', 'name' => 'Gudang Lain', 'status' => 'Active']);
        $company = AccountCompany::create(['account_name' => 'PT Weda Bay Nickel', 'status' => 'Active', 'phone' => '0813', 'address_shipping_street' => 'Gudang IMIP', 'address_shipping_city' => 'Jakarta']);
        $quotation = Quotation::create(['status' => Quotation::STATUS_FINISH, 'created_by' => $this->user->id, 'quotation_number' => '001/HAS/QT/XI/2026', 'to_name' => 'PT Weda Bay Nickel', 'subtotal' => 1, 'grand_total' => 1]);
        $this->telemetry = QuotationItem::create(['quotation_id' => $quotation->id, 'item_no' => '1', 'part_number' => '2026041491', 'description' => '<p>Vulink <b>Telemetry</b> System</p>', 'qty' => 3, 'unit' => 'Set', 'sort_order' => 1]);
        $this->sensors = QuotationItem::create(['quotation_id' => $quotation->id, 'item_no' => '2', 'part_number' => null, 'description' => 'Sensor AT600', 'qty' => 4, 'unit' => 'Set', 'sort_order' => 2]);
        $this->project = Project::create([
            'project_number' => '26110001', 'project_name' => 'Telemetry WBN', 'company_id' => $company->id, 'quotation_id' => $quotation->id,
            'po_number_company' => 'WBN-N-26078', 'po_date' => '2026-05-05', 'po_date_received' => '2026-05-06', 'po_due_date' => '2026-12-01',
            'total_sale_amount' => 1, 'net_sale_amount' => 1, 'status' => Project::STATUS_ACTIVE,
        ]);

        $this->logger = MasterProduct::create(['code' => 'VUL-1', 'name' => 'Vulink Logger', 'status' => 'Active', 'is_material' => false]);
        $this->cable = MasterProduct::create(['code' => 'CBL-1', 'name' => 'Kabel', 'status' => 'Active', 'is_material' => true]);
    }

    private function box(string $description, float $qty, array $stocks, ?int $quotationItemId = null, array $extra = []): array
    {
        return array_merge([
            'quotation_item_id' => $quotationItemId,
            'description' => $description,
            'qty' => $qty,
            'unit' => 'Set',
            'stocks' => $stocks,
        ], $extra);
    }

    private function packing(array $boxes, array $extra = [])
    {
        return $this->actingAs($this->user)->postJson(route('packing-list.store'), array_merge([
            'project_id' => $this->project->id,
            'warehouse_id' => $this->warehouse->id,
            'packing_date' => '2026-08-31',
            'recipient_name' => 'PT Weda Bay Nickel',
            'customer_ref' => 'WBN-N-26078',
            'customer_ref_date' => '2026-05-05',
            'marking' => 'PT. Weda Bay Nickel',
            'signer_name' => 'Kurniawan',
            'signer_position' => 'Logistics & Warehouse Manager',
            'boxes' => $boxes,
        ], $extra));
    }

    private function deliveryPayload(array $extra): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'warehouse_id' => $this->warehouse->id,
            'delivery_date' => '2026-08-31',
            'recipient_name' => 'PT Weda Bay Nickel',
            'shipping_address' => 'Gudang IMIP',
        ], $extra);
    }

    public function test_packing_list_ready_then_delivery_order_posts_box_contents(): void
    {
        $units = $this->seedStock($this->warehouse->id, $this->logger, 3, ['project_id' => $this->project->id], ['SN-1', 'SN-2', 'SN-3']);
        $roll = $this->seedStock($this->warehouse->id, $this->cable, 100, ['project_id' => $this->project->id, 'unit' => 'Meter'])->first();
        $noSn = $this->seedStock($this->warehouse->id, $this->logger, 1, ['project_id' => $this->project->id])->first();

        // Data project: default penerima, PO customer, item Quotation (deskripsi jadi teks).
        $data = $this->actingAs($this->user)->getJson(route('packing-list.project-data', ['project_id' => $this->project->id]))->assertOk();
        $data->assertJsonPath('defaults.customer_ref', 'WBN-N-26078')->assertJsonPath('defaults.customer_ref_date', '2026-05-05');
        $this->assertSame('Vulink Telemetry System', $data->json('quotation_items.0.description'));

        // Koli tanpa stok, unit tanpa SN, stok gudang lain ditolak.
        $this->packing([$this->box('Kosong', 1, [])])->assertStatus(422);
        $this->packing([$this->box('Logger', 1, [['stock_id' => $noSn->id, 'qty' => 1]])])->assertStatus(422);
        $this->packing([$this->box('Logger', 1, [['stock_id' => $units[0]->id, 'qty' => 1]])], ['warehouse_id' => $this->other->id])->assertStatus(422);

        $id = $this->packing([
            $this->box("2026041491\nVulink Telemetry System", 3, [
                ['stock_id' => $units[0]->id, 'qty' => 1], ['stock_id' => $units[1]->id, 'qty' => 1], ['stock_id' => $units[2]->id, 'qty' => 1],
            ], $this->telemetry->id, ['qty_order' => 3, 'length_cm' => 66, 'width_cm' => 30, 'height_cm' => 41, 'weight_kg' => 12]),
            $this->box('Sensor-sensor AT600, Kabel', 4, [['stock_id' => $roll->id, 'qty' => 40]], $this->sensors->id, ['qty_order' => 4, 'weight_kg' => 36]),
        ])->assertOk()->json('id');
        $pl = PackingList::with('boxes.items')->findOrFail($id);
        $this->assertSame('PKL-26080001', $pl->packing_number);
        $this->assertSame(2, $pl->boxes->count());
        $this->assertEquals(48, $pl->totalWeight());
        $this->assertSame('Meter', $pl->boxes[1]->items[0]->unit);

        // Draft belum memesan stok.
        $this->assertEquals(0, StockService::available($units[0]->fresh()) - 1);

        // Halaman & PDF.
        foreach (['index', 'create'] as $page) {
            $this->actingAs($this->user)->get(route('packing-list.'.$page))->assertOk();
        }
        $this->actingAs($this->user)->get(route('packing-list.edit', $id))->assertOk();
        $this->actingAs($this->user)->get(route('packing-list.show', $id))->assertOk()->assertSee('1 of 2')->assertSee('SN-2');
        $this->actingAs($this->user)->get(route('packing-list.pdf', [$id, 'detail' => 1]))->assertOk();
        $html = view('packing-list.pdf', ['pl' => $pl->fresh(['project', 'warehouse', 'boxes.items.stock.masterProduct']), 'withDetail' => true])->render();
        $this->assertStringContainsString('2 Package box = 48Kg', $html);
        $this->assertStringContainsString('05 Mei 2026', $html);
        $this->assertStringContainsString('31 Agustus 2026', $html);
        $this->assertStringContainsString('SN-3', $html);

        // Siap Kirim: stok dipesan — modul lain tidak bisa mengambil.
        $this->actingAs($this->user)->postJson(route('packing-list.ready', $id))->assertOk();
        $this->assertEquals(60, StockService::available($roll->fresh()));
        try {
            app(StockService::class)->move($units[0]->fresh(), 1, $this->other->id, $this->project->id, StockLog::TYPE_TRANSFER_OUT, ['note' => 'pindah']);
            $this->fail('Unit yang dipesan PKL tidak boleh dipindah.');
        } catch (StockException $e) {
            $this->assertStringContainsString('dipesan Packing List', $e->getMessage());
        }
        app(StockService::class)->take($roll->fresh(), 60, StockLog::TYPE_OUT, ['note' => 'sisa bebas']);
        $this->assertEquals(40, $roll->fresh()->qty);
        // Pemilih stok hanya menampilkan sisa belum dipesan.
        $options = $this->actingAs($this->user)->getJson(route('delivery-order.stock-options', ['project_id' => $this->project->id, 'warehouse_id' => $this->warehouse->id]))->json('data');
        $this->assertSame([$noSn->id], array_column($options, 'id'));
        // Terkunci: tidak bisa diubah.
        $this->actingAs($this->user)->putJson(route('packing-list.update', $id), [])->assertStatus(422);

        // DO dengan Packing List: tidak boleh campur isi stok langsung.
        $lines = [['quotation_item_id' => $this->telemetry->id, 'description' => 'Vulink Telemetry System', 'qty' => 3, 'unit' => 'Set', 'reference' => 'Box 1 · PKL-26080001'],
            ['quotation_item_id' => $this->sensors->id, 'description' => 'Sensor AT600', 'qty' => 4, 'unit' => 'Set', 'reference' => 'Box 2 · PKL-26080001']];
        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->deliveryPayload([
            'uses_packing_list' => true, 'packing_list_ids' => [$id],
            'lines' => [array_merge($lines[0], ['stocks' => [['stock_id' => $units[0]->id, 'qty' => 1]]])],
        ]))->assertStatus(422);
        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->deliveryPayload(['uses_packing_list' => true, 'packing_list_ids' => [], 'lines' => $lines]))->assertStatus(422);

        $listed = $this->actingAs($this->user)->getJson(route('delivery-order.packing-lists', ['project_id' => $this->project->id, 'warehouse_id' => $this->warehouse->id]))->json('data');
        $this->assertSame([$id], array_column($listed, 'id'));

        $doId = $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->deliveryPayload([
            'uses_packing_list' => true, 'packing_list_ids' => [$id], 'lines' => $lines,
            'customer_ref' => 'WBN-N-26078', 'checked_by_name' => 'Kurniawan',
        ]))->assertOk()->json('id');
        $this->assertSame($doId, $pl->fresh()->delivery_order_id);
        // Sudah dimuat DO: tidak bisa dibuka kembali, tidak tampil untuk DO lain.
        $this->actingAs($this->user)->postJson(route('packing-list.reopen', $id))->assertStatus(422);
        $this->assertSame([], $this->actingAs($this->user)->getJson(route('delivery-order.packing-lists', ['project_id' => $this->project->id, 'warehouse_id' => $this->warehouse->id]))->json('data'));

        $this->actingAs($this->user)->get(route('delivery-order.show', $doId))->assertOk()->assertSee('Box 2 · PKL-26080001');
        $doHtml = view('delivery-order.pdf', ['order' => DeliveryOrder::with(['items.stocks.stock.masterProduct', 'packingLists', 'project.company', 'warehouse', 'creator'])->find($doId)])->render();
        $this->assertStringContainsString('Box 1 · PKL-26080001', $doHtml);
        $this->assertStringContainsString('Kurniawan', $doHtml);

        // Posting: stok keluar dari isi koli, PKL Terkirim.
        $this->actingAs($this->user)->postJson(route('delivery-order.post', $doId))->assertOk();
        $this->assertSame(PackingList::STATUS_SHIPPED, $pl->fresh()->status);
        $this->assertFalse($units[0]->fresh()->is_open);
        $this->assertEquals(0, $roll->fresh()->qty);
        $this->assertSame(4, StockLog::where('ref_type', StockLog::REF_PACKING_LIST_ITEM)->where('type', StockLog::TYPE_OUT)->count());

        // Info terkirim per item Quotation dari baris DO terposting.
        $items = $this->actingAs($this->user)->getJson(route('delivery-order.project-stock', ['project_id' => $this->project->id]))->json('quotation_items');
        $this->assertEquals(3, $items[0]['delivered']);
        $this->assertEquals(4, $items[1]['delivered']);
    }

    public function test_reopen_and_delete_draft_delivery_order_releases_packing_list(): void
    {
        $roll = $this->seedStock($this->warehouse->id, $this->cable, 10, ['project_id' => $this->project->id])->first();
        $first = $this->packing([$this->box('Kabel', 1, [['stock_id' => $roll->id, 'qty' => 6]])])->json('id');
        $second = $this->packing([$this->box('Kabel', 1, [['stock_id' => $roll->id, 'qty' => 6]])])->json('id');

        $this->actingAs($this->user)->postJson(route('packing-list.ready', $first))->assertOk();
        // Sisa belum dipesan tinggal 4 -> PKL kedua tidak bisa Siap Kirim.
        $this->actingAs($this->user)->postJson(route('packing-list.ready', $second))->assertStatus(422);

        // Buka kembali melepas pesanan.
        $this->actingAs($this->user)->postJson(route('packing-list.reopen', $first))->assertOk();
        $this->actingAs($this->user)->postJson(route('packing-list.ready', $second))->assertOk();
        $this->actingAs($this->user)->deleteJson(route('packing-list.destroy', $second))->assertStatus(422);
        $this->actingAs($this->user)->deleteJson(route('packing-list.destroy', $first))->assertOk();

        $doId = $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->deliveryPayload([
            'uses_packing_list' => true, 'packing_list_ids' => [$second],
            'lines' => [['description' => 'Kabel', 'qty' => 1, 'unit' => 'Lot']],
        ]))->assertOk()->json('id');
        $this->actingAs($this->user)->deleteJson(route('delivery-order.destroy', $doId))->assertOk();
        $pl = PackingList::findOrFail($second);
        $this->assertNull($pl->delivery_order_id);
        $this->assertTrue($pl->isReady());

        // DO Langsung tidak bisa memakai stok yang dipesan PKL.
        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->deliveryPayload([
            'uses_packing_list' => false,
            'lines' => [['description' => 'Kabel', 'qty' => 1, 'unit' => 'Lot', 'stocks' => [['stock_id' => $roll->id, 'qty' => 5]]]],
        ]))->assertStatus(422);
        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->deliveryPayload([
            'uses_packing_list' => false,
            'lines' => [['description' => 'Kabel', 'qty' => 1, 'unit' => 'Lot', 'stocks' => [['stock_id' => $roll->id, 'qty' => 4]]]],
        ]))->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockEntry;
use App\Models\StockLog;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Fase 2 inventory: Masuk Stok Lain-lain, Transfer Stok (pindah gudang &
 * alokasi/lepas alokasi project), Laporan Stok dan Kartu Stok.
 */
class StockInventoryTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private Division $division;

    private User $user;

    private Warehouse $stockWarehouse;

    private Warehouse $transitWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->user = User::create([
            'username' => 'gudang',
            'email' => 'gudang@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);
        foreach (['stock-transfer' => 'MOD_STOCK_TRANSFER', 'stock-entry' => 'MOD_STOCK_ENTRY', 'stock-report' => 'MOD_STOCK_REPORT'] as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Purchasing']);
            UserAccessControl::create([
                'user_id' => $this->user->id,
                'module_id' => $module->id,
                'can_create' => true,
                'can_read' => true,
                'can_update' => false,
                'can_delete' => false,
                'can_approve' => false,
            ]);
        }

        $this->stockWarehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang Pusat', 'status' => 'Active']);
        $this->transitWarehouse = Warehouse::create(['code' => 'T1', 'name' => 'Transit', 'status' => 'Active']);
    }

    private function product(string $code = 'PN-1'): MasterProduct
    {
        return MasterProduct::firstOrCreate(['code' => $code], ['name' => 'Produk '.$code, 'unit' => 'Pcs', 'division_id' => $this->division->id, 'status' => 'Active', 'is_material' => true]);
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
        ]);
    }

    private function entry(int $qty, ?Project $project = null, string $code = 'PN-1')
    {
        return $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->stockWarehouse->id,
            'project_id' => $project?->id,
            'reason' => StockEntry::REASON_OPENING,
            'entry_date' => '2026-11-02',
            'items' => [['master_product_id' => $this->product($code)->id, 'qty' => $qty, 'unit' => 'Pcs']],
        ]);
    }

    public function test_stock_entry_adds_stock_with_numbering_and_validation(): void
    {
        $id = $this->entry(10)->assertOk()->json('id');

        $entry = StockEntry::findOrFail($id);
        $this->assertSame('SE-26110001', $entry->entry_number);
        $this->assertEquals(10, $this->stockBalance($this->stockWarehouse->id, $this->product()->id));
        $item = $entry->items()->first();
        $this->assertDatabaseHas('stocks', ['source_type' => Stock::SOURCE_STOCK_ENTRY_ITEM, 'source_id' => $item->id, 'unit' => 'Pcs', 'is_material' => true]);
        $this->assertDatabaseHas('stock_logs', ['type' => StockLog::TYPE_IN, 'ref_type' => StockLog::REF_STOCK_ENTRY_ITEM, 'ref_id' => $item->id]);

        // Satuan ketikan diabaikan (selalu dari master); batch / kedaluwarsa material tidak dipakai.
        $id2 = $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->stockWarehouse->id, 'reason' => StockEntry::REASON_OPENING,
            'items' => [['master_product_id' => $this->product()->id, 'qty' => 5, 'unit' => 'Box', 'unit_cost' => 2000, 'batch_number' => 'LOT-X', 'expiry_date' => '2027-01-01']],
        ])->assertOk()->json('id');
        $item2 = StockEntry::findOrFail($id2)->items()->first();
        $this->assertSame(['Pcs', null, null], [$item2->unit, $item2->batch_number, $item2->expiry_date]);
        $this->assertEquals(15, $this->stockBalance($this->stockWarehouse->id, $this->product()->id));

        // Katalog popup: per halaman, filter jenis, kategori, satuan & harga standar.
        MasterProduct::create(['code' => 'SN-9', 'name' => 'Logger', 'unit' => 'Unit', 'category' => 'Logger', 'division_id' => $this->division->id, 'status' => 'Active', 'is_material' => false]);
        $this->actingAs($this->user)->getJson(route('stock-entry.search-products', ['with_categories' => 1, 'per_page' => 5]))->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('categories', ['Logger'])->assertJsonPath('data.0.code', 'PN-1')->assertJsonPath('data.0.unit', 'Pcs');
        $this->actingAs($this->user)->getJson(route('stock-entry.search-products', ['kind' => 'serial']))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.serialized', true);
        $this->actingAs($this->user)->getJson(route('stock-entry.search-products', ['q' => 'logg', 'category' => 'Logger']))->assertOk()->assertJsonPath('meta.total', 1);

        // Saldo material untuk form + filter daftar.
        $this->actingAs($this->user)->getJson(route('stock-entry.balances', ['warehouse_id' => $this->stockWarehouse->id, 'product_ids' => [$this->product()->id]]))
            ->assertOk()->assertJsonPath('data.'.$this->product()->id.'.qty', 15);
        $this->actingAs($this->user)->getJson(route('stock-entry.data', ['draw' => 1, 'ownership' => 'free', 'reason' => StockEntry::REASON_OPENING]))
            ->assertOk()->assertJsonPath('recordsFiltered', 2)->assertJsonPath('data.0.material_lines', 1)->assertJsonPath('data.0.value', 10000);
        $this->actingAs($this->user)->getJson(route('stock-entry.data', ['draw' => 1, 'reason' => StockEntry::REASON_RETURN]))->assertOk()->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($this->user)->getJson(route('stock-entry.data', ['draw' => 1, 'search' => ['value' => 'PN-1']]))->assertOk()->assertJsonPath('recordsFiltered', 2);

        $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->stockWarehouse->id,
            'reason' => StockEntry::REASON_OTHER,
            'items' => [['master_product_id' => $this->product()->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('note');

        $this->actingAs($this->user)->get(route('stock-entry.index'))->assertOk();
        $this->actingAs($this->user)->get(route('stock-entry.create'))->assertOk();
        $this->actingAs($this->user)->get(route('stock-entry.show', $id))->assertOk()->assertSee('Opening Balance');
        $this->actingAs($this->user)->getJson(route('stock-entry.data', ['search' => ['value' => 'SE-26110001']]))->assertOk()->assertJsonPath('data.0.entry_number', 'SE-26110001');
    }

    public function test_transfer_allocates_free_stock_to_project_in_transit(): void
    {
        $this->entry(10)->assertOk();
        $project = $this->project();
        $product = $this->product();

        $row = Stock::firstOrFail();
        $this->actingAs($this->user)->getJson(route('stock-transfer.available-stock', ['warehouse_id' => $this->stockWarehouse->id]))
            ->assertOk()->assertJsonPath('data.0.id', $row->id)->assertJsonPath('data.0.qty', 10)->assertJsonPath('data.0.unit', 'Pcs');

        $payload = [
            'from_warehouse_id' => $this->stockWarehouse->id,
            'to_warehouse_id' => $this->transitWarehouse->id,
            'to_project_id' => $project->id,
            'transfer_date' => '2026-11-02',
        ];

        $this->actingAs($this->user)->postJson(route('stock-transfer.store'), $payload + ['items' => [['stock_id' => $row->id, 'qty' => 11]]])->assertStatus(422);

        $id = $this->actingAs($this->user)->postJson(route('stock-transfer.store'), $payload + ['items' => [['stock_id' => $row->id, 'qty' => 4]]])
            ->assertOk()->json('id');

        $transfer = StockTransfer::findOrFail($id);
        $this->assertSame('ST-26110001', $transfer->transfer_number);
        $this->assertSame('Allocate to Project', $transfer->kindLabel());
        $this->assertEquals(6, $this->stockBalance($this->stockWarehouse->id, $product->id));
        $this->assertEquals(4, $this->stockBalance($this->transitWarehouse->id, $product->id, $project->id));
        $this->assertEquals(0, $this->stockBalance($this->transitWarehouse->id, $product->id));
        // Material -> saldo project di gudang tujuan; item transfer menunjuk saldo tujuan; pindah kepemilikan = alokasi.
        $child = Stock::where('project_id', $project->id)->firstOrFail();
        $this->assertNull($child->parent_id);
        $this->assertSame($child->id, $transfer->items()->value('stock_id'));
        $this->assertSame(2, StockLog::where('type', StockLog::TYPE_ALLOCATE)->count());

        $this->actingAs($this->user)->get(route('stock-transfer.show', $id))->assertOk()->assertSee('SPARING Gresik');
        $this->actingAs($this->user)->get(route('stock-transfer.index'))->assertOk();
        $this->actingAs($this->user)->get(route('stock-transfer.create'))->assertOk();
        $this->actingAs($this->user)->getJson(route('stock-transfer.data'))->assertOk()->assertJsonPath('data.0.kind', 'Allocate to Project');
        // Filter daftar, isi & nilai (harga saat dipindah), detail.
        $this->actingAs($this->user)->getJson(route('stock-transfer.data', ['draw' => 1, 'kind' => 'allocate', 'ownership' => $project->id]))->assertOk()
            ->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.material_lines', 1);
        $this->actingAs($this->user)->getJson(route('stock-transfer.data', ['draw' => 1, 'kind' => 'move']))->assertOk()->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($this->user)->getJson(route('stock-transfer.data', ['draw' => 1, 'kind' => 'release']))->assertOk()->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($this->user)->getJson(route('stock-transfer.data', ['draw' => 1, 'search' => ['value' => $product->code]]))->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->actingAs($this->user)->get(route('stock-transfer.show', $id))->assertOk()->assertSee('into destination balance')->assertSee('Value allocated');
    }

    public function test_transfer_release_and_same_source_destination_rejected(): void
    {
        $project = $this->project();
        $product = $this->product();
        $this->entry(5, $project)->assertOk();

        $row = Stock::firstOrFail();

        $this->actingAs($this->user)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->stockWarehouse->id,
            'from_project_id' => $project->id,
            'to_warehouse_id' => $this->stockWarehouse->id,
            'to_project_id' => $project->id,
            'items' => [['stock_id' => $row->id, 'qty' => 1]],
        ])->assertStatus(422);

        // Baris milik project tidak bisa dipakai sebagai asal stok bebas.
        $this->actingAs($this->user)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->stockWarehouse->id,
            'to_warehouse_id' => $this->transitWarehouse->id,
            'items' => [['stock_id' => $row->id, 'qty' => 1]],
        ])->assertStatus(422);

        $id = $this->actingAs($this->user)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->stockWarehouse->id,
            'from_project_id' => $project->id,
            'to_warehouse_id' => $this->stockWarehouse->id,
            'items' => [['stock_id' => $row->id, 'qty' => 2]],
        ])->assertOk()->json('id');

        $this->assertSame('Release Allocation', StockTransfer::findOrFail($id)->kindLabel());
        $this->assertEquals(3, $this->stockBalance($this->stockWarehouse->id, $product->id, $project->id));
        $this->assertEquals(2, $this->stockBalance($this->stockWarehouse->id, $product->id));
    }

    public function test_stock_report_balances_filters_and_card(): void
    {
        $project = $this->project();
        $this->entry(7)->assertOk();
        $this->entry(3, $project)->assertOk();
        $this->entry(2, null, 'PN-2')->assertOk();

        $this->actingAs($this->user)->get(route('stock-report.index'))->assertOk()->assertSee('SPARING Gresik');

        $this->actingAs($this->user)->getJson(route('stock-report.data'))
            ->assertOk()->assertJsonPath('recordsTotal', 3);

        $this->actingAs($this->user)->getJson(route('stock-report.data', ['ownership' => 'free']))
            ->assertOk()->assertJsonPath('recordsTotal', 2)->assertJsonPath('data.0.code', 'PN-1')->assertJsonPath('data.0.balance', 7);
        $this->actingAs($this->user)->getJson(route('stock-report.data', ['ownership' => 'free']))
            ->assertJsonPath('data.0.missing_serial', 0)->assertJsonPath('data.0.serialized', false);

        $this->actingAs($this->user)->getJson(route('stock-report.data', ['ownership' => $project->id]))
            ->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.balance', 3);

        $this->actingAs($this->user)->getJson(route('stock-report.data', ['search' => ['value' => 'PN-2']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1);

        $this->actingAs($this->user)->get(route('stock-report.card', ['productId' => $this->product()->id, 'ownership' => 'free']))
            ->assertOk()->assertSee('Saldo akhir: 7')->assertSee('Masuk');

        // Saldo awal dihitung mundur: periode setelah semua pergerakan -> saldo awal = 7, tanpa baris.
        $this->actingAs($this->user)->get(route('stock-report.card', ['productId' => $this->product()->id, 'ownership' => 'free', 'from' => now()->addDay()->toDateString(), 'to' => now()->addDays(2)->toDateString()]))
            ->assertOk()->assertSee('Saldo akhir: 7')->assertSee('Tidak ada pergerakan stok pada periode ini');
    }
}

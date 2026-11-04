<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\StockEntry;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2 inventory: Masuk Stok Lain-lain, Transfer Stok (pindah gudang &
 * alokasi/lepas alokasi project), Laporan Stok dan Kartu Stok.
 */
class StockInventoryTest extends TestCase
{
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

        $this->stockWarehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang Pusat', 'type' => Warehouse::TYPE_STOCK, 'status' => 'Active']);
        $this->transitWarehouse = Warehouse::create(['code' => 'T1', 'name' => 'Transit', 'type' => Warehouse::TYPE_TRANSIT, 'status' => 'Active']);
    }

    private function product(string $code = 'PN-1'): MasterProduct
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
        $this->assertSame('26110001', $entry->entry_number);
        $this->assertSame(10, StockMovement::balance($this->stockWarehouse->id, $this->product()->id));
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovement::TYPE_MISC_IN, 'source_type' => StockMovement::SOURCE_STOCK_ENTRY, 'source_id' => $id, 'unit' => 'Pcs']);

        $this->actingAs($this->user)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->stockWarehouse->id,
            'reason' => StockEntry::REASON_OTHER,
            'items' => [['master_product_id' => $this->product()->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('note');

        $this->actingAs($this->user)->get(route('stock-entry.index'))->assertOk();
        $this->actingAs($this->user)->get(route('stock-entry.create'))->assertOk();
        $this->actingAs($this->user)->get(route('stock-entry.show', $id))->assertOk()->assertSee('Saldo Awal');
        $this->actingAs($this->user)->getJson(route('stock-entry.data'))->assertOk()->assertJsonPath('data.0.entry_number', '26110001');
    }

    public function test_transfer_allocates_free_stock_to_project_in_transit(): void
    {
        $this->entry(10)->assertOk();
        $project = $this->project();
        $product = $this->product();

        $this->actingAs($this->user)->getJson(route('stock-transfer.available-stock', ['warehouse_id' => $this->stockWarehouse->id]))
            ->assertOk()->assertJsonPath('items.0.balance', 10)->assertJsonPath('items.0.unit', 'Pcs');

        $payload = [
            'from_warehouse_id' => $this->stockWarehouse->id,
            'to_warehouse_id' => $this->transitWarehouse->id,
            'to_project_id' => $project->id,
            'transfer_date' => '2026-11-02',
        ];

        $this->actingAs($this->user)->postJson(route('stock-transfer.store'), $payload + ['items' => [['master_product_id' => $product->id, 'qty' => 11]]])->assertStatus(422);

        $id = $this->actingAs($this->user)->postJson(route('stock-transfer.store'), $payload + ['items' => [['master_product_id' => $product->id, 'qty' => 4]]])
            ->assertOk()->json('id');

        $transfer = StockTransfer::findOrFail($id);
        $this->assertSame('26110001', $transfer->transfer_number);
        $this->assertSame('Alokasi ke Project', $transfer->kindLabel());
        $this->assertSame(6, StockMovement::balance($this->stockWarehouse->id, $product->id));
        $this->assertSame(4, StockMovement::balance($this->transitWarehouse->id, $product->id, $project->id));
        $this->assertSame(0, StockMovement::balance($this->transitWarehouse->id, $product->id));

        $this->actingAs($this->user)->get(route('stock-transfer.show', $id))->assertOk()->assertSee('SPARING Gresik');
        $this->actingAs($this->user)->get(route('stock-transfer.index'))->assertOk();
        $this->actingAs($this->user)->get(route('stock-transfer.create'))->assertOk();
        $this->actingAs($this->user)->getJson(route('stock-transfer.data'))->assertOk()->assertJsonPath('data.0.kind', 'Alokasi ke Project');
    }

    public function test_transfer_release_and_same_source_destination_rejected(): void
    {
        $project = $this->project();
        $product = $this->product();
        $this->entry(5, $project)->assertOk();

        $this->actingAs($this->user)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->stockWarehouse->id,
            'from_project_id' => $project->id,
            'to_warehouse_id' => $this->stockWarehouse->id,
            'to_project_id' => $project->id,
            'items' => [['master_product_id' => $product->id, 'qty' => 1]],
        ])->assertStatus(422);

        // Stok bebas kosong -> produk tidak tersedia di asal bebas.
        $this->actingAs($this->user)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->stockWarehouse->id,
            'to_warehouse_id' => $this->transitWarehouse->id,
            'items' => [['master_product_id' => $product->id, 'qty' => 1]],
        ])->assertStatus(422);

        $id = $this->actingAs($this->user)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->stockWarehouse->id,
            'from_project_id' => $project->id,
            'to_warehouse_id' => $this->stockWarehouse->id,
            'items' => [['master_product_id' => $product->id, 'qty' => 2]],
        ])->assertOk()->json('id');

        $this->assertSame('Lepas Alokasi', StockTransfer::findOrFail($id)->kindLabel());
        $this->assertSame(3, StockMovement::balance($this->stockWarehouse->id, $product->id, $project->id));
        $this->assertSame(2, StockMovement::balance($this->stockWarehouse->id, $product->id));
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

        $this->actingAs($this->user)->getJson(route('stock-report.data', ['ownership' => $project->id]))
            ->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.balance', 3);

        $this->actingAs($this->user)->getJson(route('stock-report.data', ['search' => ['value' => 'PN-2']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1);

        $this->actingAs($this->user)->get(route('stock-report.card', ['productId' => $this->product()->id, 'ownership' => 'free']))
            ->assertOk()->assertSee('Saldo akhir: 7')->assertSee('Masuk Lain-lain');
    }
}

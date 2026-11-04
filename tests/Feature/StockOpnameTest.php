<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\StockEntry;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Support\StockValuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4: valuasi rata-rata bergerak dan Stock Opname (draft oleh gudang,
 * posting penyesuaian oleh approver).
 */
class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $counter;

    private User $approver;

    private Warehouse $warehouse;

    private MasterProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->counter = User::create(['username' => 'gudang', 'email' => 'gudang@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'User']);
        $this->approver = User::create(['username' => 'kepala', 'email' => 'kepala@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'User']);

        $modules = [];
        foreach (['stock-opname' => 'MOD_STOCK_OPNAME', 'stock-entry' => 'MOD_STOCK_ENTRY', 'stock-transfer' => 'MOD_STOCK_TRANSFER', 'stock-report' => 'MOD_STOCK_REPORT'] as $route => $code) {
            $modules[$route] = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Purchasing']);
        }
        foreach ($modules as $module) {
            UserAccessControl::create(['user_id' => $this->counter->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
        }
        UserAccessControl::create(['user_id' => $this->approver->id, 'module_id' => $modules['stock-opname']->id, 'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => true]);

        $this->warehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang Pusat', 'type' => Warehouse::TYPE_STOCK, 'status' => 'Active']);
        $this->product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor pH', 'division_id' => $this->division->id, 'status' => 'Active', 'purchase_price' => 900]);
    }

    private function entry(int $qty, ?float $cost, ?Project $project = null): void
    {
        $this->actingAs($this->counter)->postJson(route('stock-entry.store'), [
            'warehouse_id' => $this->warehouse->id,
            'project_id' => $project?->id,
            'reason' => StockEntry::REASON_OPENING,
            'entry_date' => '2026-11-02',
            'items' => [['master_product_id' => $this->product->id, 'qty' => $qty, 'unit' => 'Unit', 'unit_cost' => $cost]],
        ])->assertOk();
    }

    private function project(): Project
    {
        return Project::create([
            'project_number' => '26100001',
            'project_name' => 'SPARING Gresik',
            'company_id' => AccountCompany::create(['account_name' => 'PT Maju', 'status' => 'Active'])->id,
            'po_number_company' => 'PO-1',
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1,
            'net_sale_amount' => 1,
            'status' => Project::STATUS_ACTIVE,
        ]);
    }

    public function test_moving_average_valuation_and_report_value(): void
    {
        $this->entry(10, 1000);
        $this->entry(10, 2000);
        $this->assertEquals(1500, StockValuation::averageCost($this->product->id));

        // Tanpa harga -> harga beli master.
        $this->entry(5, null);
        $this->assertEquals(900, StockMovement::latest('id')->value('unit_cost'));
        $this->assertEquals((10000 + 20000 + 4500) / 25, StockValuation::averageCost($this->product->id));

        // Transfer/alokasi dinilai rata-rata: nilai total tidak berubah.
        $project = $this->project();
        $this->actingAs($this->counter)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $this->warehouse->id,
            'to_project_id' => $project->id,
            'items' => [['master_product_id' => $this->product->id, 'qty' => 5]],
        ])->assertOk();
        $this->assertEquals(1380, StockValuation::averageCost($this->product->id));

        $this->actingAs($this->counter)->getJson(route('stock-report.data'))
            ->assertOk()
            ->assertJsonPath('total_value', 34500)
            ->assertJsonPath('data.0.average_cost', 1380);

        $this->actingAs($this->counter)->get(route('stock-report.card', $this->product->id))->assertOk()->assertSee('34.500');
    }

    public function test_opname_draft_then_approver_posts_adjustments(): void
    {
        $project = $this->project();
        $this->entry(10, 1000);
        $this->entry(4, 1000, $project);

        $this->actingAs($this->counter)->getJson(route('stock-opname.system-stock', ['warehouse_id' => $this->warehouse->id]))
            ->assertOk()->assertJsonCount(2, 'items');

        $other = MasterProduct::create(['code' => 'PN-2', 'name' => 'Logger', 'division_id' => $this->division->id, 'status' => 'Active', 'purchase_price' => 500]);
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => '2026-11-02',
            'items' => [
                ['master_product_id' => $this->product->id, 'project_id' => null, 'counted_qty' => 8, 'remark' => 'Rusak 2'],
                ['master_product_id' => $this->product->id, 'project_id' => $project->id, 'counted_qty' => 4],
                ['master_product_id' => $other->id, 'project_id' => null, 'counted_qty' => 3],
            ],
        ];

        $dup = $payload;
        $dup['items'][] = $payload['items'][0];
        $this->actingAs($this->counter)->postJson(route('stock-opname.store'), $dup)->assertStatus(422);

        $id = $this->actingAs($this->counter)->postJson(route('stock-opname.store'), $payload)->assertOk()->json('id');
        $opname = StockOpname::with('items')->findOrFail($id);
        $this->assertSame('26110001', $opname->opname_number);
        $this->assertSame([10, 4, 0], $opname->items->pluck('system_qty')->all());

        foreach (['index', 'create'] as $page) {
            $this->actingAs($this->counter)->get(route('stock-opname.'.$page))->assertOk();
        }
        $this->actingAs($this->counter)->get(route('stock-opname.edit', $id))->assertOk();
        $this->actingAs($this->counter)->get(route('stock-opname.show', $id))->assertOk()->assertDontSee('id="so-post-btn"', false);
        $this->actingAs($this->approver)->get(route('stock-opname.show', $id))->assertOk()->assertSee('id="so-post-btn"', false);

        // Pembuat (tanpa can_approve) tidak bisa posting.
        $this->actingAs($this->counter)->postJson(route('stock-opname.approve', $id))->assertForbidden();

        // Pergerakan setelah hitung: qty sistem dihitung ulang saat posting.
        StockMovement::record(['warehouse_id' => $this->warehouse->id, 'master_product_id' => $this->product->id, 'type' => StockMovement::TYPE_ISSUE, 'qty' => 1, 'created_by' => $this->counter->id]);

        $this->actingAs($this->approver)->postJson(route('stock-opname.approve', $id))->assertOk();

        $opname->refresh()->load('items');
        $this->assertSame(StockOpname::STATUS_POSTED, $opname->status);
        $this->assertSame([9, 4, 0], $opname->items->pluck('system_qty')->all());
        $this->assertSame(8, StockMovement::balance($this->warehouse->id, $this->product->id));
        $this->assertSame(4, StockMovement::balance($this->warehouse->id, $this->product->id, $project->id));
        $this->assertSame(3, StockMovement::balance($this->warehouse->id, $other->id));
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovement::TYPE_ADJUSTMENT_OUT, 'qty' => 1, 'source_type' => StockMovement::SOURCE_STOCK_OPNAME, 'unit_cost' => 1000]);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovement::TYPE_ADJUSTMENT_IN, 'qty' => 3, 'master_product_id' => $other->id, 'unit_cost' => 500]);
        $this->assertSame(2, StockMovement::where('source_type', StockMovement::SOURCE_STOCK_OPNAME)->count());

        $this->actingAs($this->approver)->postJson(route('stock-opname.approve', $id))->assertStatus(422);
        $this->actingAs($this->counter)->putJson(route('stock-opname.update', $id), $payload)->assertStatus(422);
        $this->actingAs($this->counter)->deleteJson(route('stock-opname.destroy', $id))->assertStatus(422);
        $this->actingAs($this->counter)->get(route('stock-opname.show', $id))->assertOk()->assertSee('Rusak 2');
    }
}

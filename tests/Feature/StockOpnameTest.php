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
use App\Models\StockOpname;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Harga modal per baris stok dan Stock Opname per baris (draft oleh gudang,
 * posting penyesuaian oleh approver; selisih terhadap angka saat dihitung).
 */
class StockOpnameTest extends TestCase
{
    use InteractsWithStock;
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

        $this->warehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang Pusat', 'status' => 'Active']);
        $this->product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor pH', 'division_id' => $this->division->id, 'status' => 'Active', 'purchase_price' => 900, 'is_material' => true]);
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

    public function test_material_balance_uses_weighted_cost_and_report_value(): void
    {
        $this->entry(10, 1000);
        $this->entry(10, 2000);
        // Tanpa harga -> harga beli master.
        $this->entry(5, null);

        // Material = satu saldo; harga rata-rata tertimbang (10×1000 + 10×2000 + 5×900) / 25.
        $this->assertSame(1, Stock::count());
        $this->assertEquals(1380, Stock::firstOrFail()->unit_cost);
        $this->assertSame([1000.0, 2000.0, 900.0], StockLog::where('type', StockLog::TYPE_IN)->orderBy('id')->pluck('unit_cost')->all());

        // Alokasi memindah qty beserta harganya: nilai total tidak berubah.
        $project = $this->project();
        $first = Stock::orderBy('id')->firstOrFail();
        $this->actingAs($this->counter)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $this->warehouse->id,
            'to_project_id' => $project->id,
            'items' => [['stock_id' => $first->id, 'qty' => 5]],
        ])->assertOk();
        $this->assertEquals(1380, Stock::where('project_id', $project->id)->value('unit_cost'));

        $this->actingAs($this->counter)->getJson(route('stock-report.data', ['ownership' => 'free']))
            ->assertOk()
            ->assertJsonPath('total_value', 27600)
            ->assertJsonPath('data.0.average_cost', 1380);
        $this->actingAs($this->counter)->getJson(route('stock-report.data'))->assertOk()->assertJsonPath('total_value', 34500)
            ->assertJsonPath('summary.material_value', 34500)->assertJsonPath('summary.material_positions', 2)
            ->assertJsonPath('summary.serial_units', 0)->assertJsonPath('data.0.units', 1);
        $this->actingAs($this->counter)->getJson(route('stock-report.data', ['kind' => 'serial']))->assertOk()->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($this->counter)->getJson(route('stock-report.data', ['kind' => 'material', 'missing_sn' => 1]))->assertOk()->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($this->counter)->get(route('stock-report.index'))->assertOk()->assertSee('SN belum diisi');

        $this->actingAs($this->counter)->get(route('stock-report.card', $this->product->id))->assertOk()->assertSee('34,500');
    }

    public function test_opname_draft_then_approver_posts_adjustments(): void
    {
        $project = $this->project();
        $this->entry(10, 1000);
        $this->entry(4, 1000, $project);
        [$free, $owned] = Stock::orderBy('id')->get()->all();

        $this->actingAs($this->counter)->getJson(route('stock-opname.system-stock', ['warehouse_id' => $this->warehouse->id]))
            ->assertOk()->assertJsonCount(2, 'data');

        $other = MasterProduct::create(['code' => 'PN-2', 'name' => 'Logger', 'division_id' => $this->division->id, 'status' => 'Active', 'purchase_price' => 500, 'is_material' => true]);
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => '2026-11-02',
            'items' => [
                ['stock_id' => $free->id, 'counted_qty' => 8, 'remark' => 'Rusak 2'],
                ['stock_id' => $owned->id, 'counted_qty' => 4],
                ['master_product_id' => $other->id, 'counted_qty' => 3, 'batch_number' => 'LOT-9', 'unit' => 'Unit'],
            ],
        ];

        $dup = $payload;
        $dup['items'][] = $payload['items'][0];
        $this->actingAs($this->counter)->postJson(route('stock-opname.store'), $dup)->assertStatus(422);

        $id = $this->actingAs($this->counter)->postJson(route('stock-opname.store'), $payload)->assertOk()->json('id');
        $opname = StockOpname::with('items')->findOrFail($id);
        $this->assertSame('SC-26110001', $opname->opname_number);
        $this->assertSame([10.0, 4.0, 0.0], $opname->items->pluck('system_qty')->all());

        foreach (['index', 'create'] as $page) {
            $this->actingAs($this->counter)->get(route('stock-opname.'.$page))->assertOk();
        }
        $this->actingAs($this->counter)->get(route('stock-opname.edit', $id))->assertOk();
        $this->actingAs($this->counter)->get(route('stock-opname.show', $id))->assertOk()->assertDontSee('id="so-post-btn"', false);
        $this->actingAs($this->approver)->get(route('stock-opname.show', $id))->assertOk()->assertSee('id="so-post-btn"', false);

        // Pembuat (tanpa can_approve) tidak bisa posting.
        $this->actingAs($this->counter)->postJson(route('stock-opname.approve', $id))->assertForbidden();

        // Barang keluar SETELAH dihitung: 10 -> 9. Hitung fisik 8 berarti kurang 2 saat dihitung,
        // jadi stok benar sekarang 9 - 2 = 7 (bukan 8).
        $this->actingAs($this->counter);
        app(StockService::class)->take($free->fresh('masterProduct'), 1, StockLog::TYPE_OUT, ['note' => 'keluar setelah dihitung']);
        $this->actingAs($this->approver)->get(route('stock-opname.show', $id))->assertOk()->assertSee('berubah sejak dihitung');

        $this->actingAs($this->approver)->postJson(route('stock-opname.approve', $id))->assertOk();

        $opname->refresh()->load('items');
        $this->assertSame(StockOpname::STATUS_POSTED, $opname->status);
        $this->assertEquals(7, $this->stockBalance($this->warehouse->id, $this->product->id));
        $this->assertEquals(4, $this->stockBalance($this->warehouse->id, $this->product->id, $project->id));
        $this->assertEquals(3, $this->stockBalance($this->warehouse->id, $other->id));
        $this->assertDatabaseHas('stock_logs', ['stock_id' => $free->id, 'type' => StockLog::TYPE_ADJUST, 'qty' => -2, 'unit_cost' => 1000]);
        $this->assertDatabaseHas('stock_logs', ['master_product_id' => $other->id, 'type' => StockLog::TYPE_ADJUST, 'qty' => 3, 'unit_cost' => 500]);
        $this->assertSame('LOT-9', Stock::where('master_product_id', $other->id)->value('batch_number'));
        $this->assertSame(2, StockLog::where('type', StockLog::TYPE_ADJUST)->count());

        $this->actingAs($this->approver)->postJson(route('stock-opname.approve', $id))->assertStatus(422);
        $this->actingAs($this->counter)->putJson(route('stock-opname.update', $id), $payload)->assertStatus(422);
        $this->actingAs($this->counter)->deleteJson(route('stock-opname.destroy', $id))->assertStatus(422);
        $this->actingAs($this->counter)->get(route('stock-opname.show', $id))->assertOk()->assertSee('Rusak 2');
    }

    public function test_opname_rejects_minus_result_and_extra_qty_raises_balance(): void
    {
        $this->entry(5, 1000);
        $row = Stock::firstOrFail();

        // Hitung 1 (kurang 4) lalu 3 keluar sebelum posting -> 5-3-4 = -2: ditolak.
        $id = $this->actingAs($this->counter)->postJson(route('stock-opname.store'), [
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => '2026-11-02',
            'items' => [['stock_id' => $row->id, 'counted_qty' => 1]],
        ])->assertOk()->json('id');
        app(StockService::class)->take($row->fresh('masterProduct'), 3, StockLog::TYPE_OUT, []);
        $this->actingAs($this->approver)->postJson(route('stock-opname.approve', $id))->assertStatus(422)->assertJsonPath('success', false);
        $this->assertEquals(2, $this->stockBalance($this->warehouse->id, $this->product->id));

        // Lebih dari qty awal: saldo material naik langsung (tanpa baris baru).
        $id = $this->actingAs($this->counter)->postJson(route('stock-opname.store'), [
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => '2026-11-03',
            'items' => [['stock_id' => $row->id, 'counted_qty' => 7]],
        ])->assertOk()->json('id');
        $this->actingAs($this->approver)->postJson(route('stock-opname.approve', $id))->assertOk();

        $this->assertEquals(7, $this->stockBalance($this->warehouse->id, $this->product->id));
        $this->assertEquals(7, $row->fresh()->qty);
        $this->assertSame(1, Stock::count());
    }
}

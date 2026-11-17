<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Branch;
use App\Models\DirectPurchase;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\MaterialHandover;
use App\Models\MaterialRequest;
use App\Models\Module;
use App\Models\Production;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\PayableReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Produksi project: Material Request (1 approver, tanpa draft) -> Serah Terima
 * (stok langsung keluar, biaya project hanya dari stok bebas) -> Material di
 * Produksi per project + branch -> Produksi (material sebagian, SN per SN,
 * barang jadi tidak ber-SN di gudang branch yang sama) -> batal produksi.
 */
class ProductionFlowTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private Branch $jakarta;

    private Branch $bandung;

    private Warehouse $jktMain;

    private Warehouse $jktFg;

    private Warehouse $bdgWarehouse;

    private User $requester;

    private User $approver;

    private User $bandungUser;

    private Project $project;

    private Project $otherProject;

    private MasterProduct $panel;

    private MasterProduct $cable;

    private MasterProduct $lamp;

    private MasterProduct $cap;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Ops', 'description' => 'Ops', 'type' => 'Internal', 'status' => 'Active']);
        $this->jakarta = Branch::create(['code' => 'JKT', 'name' => 'HQ Jakarta', 'status' => 'Active']);
        $this->bandung = Branch::create(['code' => 'BDG', 'name' => 'Cabang Bandung', 'status' => 'Active']);
        $this->jktMain = Warehouse::create(['branch_id' => $this->jakarta->id, 'code' => 'J1', 'name' => 'Gudang Jakarta', 'status' => 'Active']);
        $this->jktFg = Warehouse::create(['branch_id' => $this->jakarta->id, 'code' => 'J2', 'name' => 'Gudang Barang Jadi', 'status' => 'Active']);
        $this->bdgWarehouse = Warehouse::create(['branch_id' => $this->bandung->id, 'code' => 'B1', 'name' => 'Gudang Bandung', 'status' => 'Active']);

        $make = fn (string $name, ?Branch $branch) => User::create(['username' => $name, 'email' => $name.'@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'branch_id' => $branch?->id, 'role' => 'User']);
        $this->requester = $make('produksi', $this->jakarta);
        $this->approver = $make('manager', null);
        $this->bandungUser = $make('bdg', $this->bandung);

        $modules = [
            'material-request' => 'MOD_MATERIAL_REQUEST',
            'material-handover' => 'MOD_MATERIAL_HANDOVER',
            'production' => 'MOD_PRODUCTION',
            'direct-purchase' => 'MOD_DIRECT_PURCHASE',
        ];
        foreach ($modules as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Produksi']);
            foreach ([$this->requester, $this->approver, $this->bandungUser] as $user) {
                UserAccessControl::create([
                    'user_id' => $user->id, 'module_id' => $module->id,
                    'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true,
                    'can_approve' => $user->is($this->approver),
                ]);
            }
        }

        $company = AccountCompany::create(['account_name' => 'PT Maju', 'status' => 'Active']);
        $makeProject = fn (string $number, string $status = Project::STATUS_ACTIVE) => Project::create([
            'project_number' => $number, 'project_name' => 'PLTS '.$number, 'company_id' => $company->id,
            'po_number_company' => 'PO-'.$number, 'po_date' => '2026-10-01', 'po_date_received' => '2026-10-02', 'po_due_date' => '2026-12-01',
            'total_sale_amount' => 1, 'net_sale_amount' => 1, 'status' => $status,
        ]);
        $this->project = $makeProject('26110001');
        $this->otherProject = $makeProject('26110002');

        $product = fn (string $code, string $name, bool $material, string $unit) => MasterProduct::create(['code' => $code, 'name' => $name, 'unit' => $unit, 'division_id' => $division->id, 'status' => 'Active', 'is_material' => $material]);
        $this->panel = $product('PNL-1', 'Panel Box', true, 'Unit');
        $this->cable = $product('CBL-1', 'Kabel NYY', true, 'Meter');
        $this->lamp = $product('LMP-1', 'Lampu LED', false, 'Unit');
        $this->cap = $product('CAP-1', 'Cap Lampu', true, 'Pcs');
    }

    private function createMr(array $items, array $extra = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->requester)->postJson(route('material-request.store'), array_merge([
            'project_id' => $this->project->id,
            'branch_id' => $this->jakarta->id,
            'request_date' => '2026-11-17',
            'items' => $items,
        ], $extra));
    }

    private function approvedMr(array $items): MaterialRequest
    {
        $id = $this->createMr($items)->assertOk()->json('id');
        $this->actingAs($this->approver)->postJson(route('material-request.approve', $id))->assertOk();

        return MaterialRequest::with('items')->findOrFail($id);
    }

    private function itemId(MaterialRequest $mr, MasterProduct $product): int
    {
        return $mr->items->firstWhere('master_product_id', $product->id)->id;
    }

    private function handover(MaterialRequest $mr, array $items, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->requester)->postJson(route('material-handover.store'), [
            'material_request_id' => $mr->id,
            'handover_date' => '2026-11-17',
            'items' => $items,
        ]);
    }

    /** id baris Material di Produksi untuk satu baris stok asal / SN. */
    private function poolId(MasterProduct $product, ?string $serial = null): int
    {
        return \App\Models\MaterialHandoverItem::whereHas('stock', fn ($q) => $q->where('master_product_id', $product->id)
            ->when($serial, fn ($s) => $s->where('serial_number', $serial)))->value('id');
    }

    private function produce(array $items, array $extra = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->requester)->postJson(route('production.store'), array_merge([
            'project_id' => $this->project->id,
            'branch_id' => $this->jakarta->id,
            'production_date' => '2026-11-18',
            'output_qty' => 1,
            'output_warehouse_id' => $this->jktFg->id,
            'items' => $items,
        ], $extra));
    }

    private function projectStockCost(): float
    {
        return (float) ((new PayableReport)->projectCosts($this->project->id)->first()['stock_allocated'] ?? 0);
    }

    public function test_full_flow_request_handover_two_productions_and_cancel(): void
    {
        $cableRow = $this->seedStock($this->jktMain->id, $this->cable, 100, ['unit_cost' => 1000, 'unit' => 'Meter'])->first();
        $panelRow = $this->seedStock($this->jktMain->id, $this->panel, 1, ['unit_cost' => 2000000])->first();
        $lamps = $this->seedStock($this->jktMain->id, $this->lamp, 2, ['unit_cost' => 50000], ['SN-L1', 'SN-L2']);
        // Cap lampu sudah milik project (mis. dari PO project) -> tidak dihitung lagi.
        $capRow = $this->seedStock($this->jktMain->id, $this->cap, 1, ['unit_cost' => 10000, 'project_id' => $this->project->id, 'unit' => 'Pcs'])->first();

        // MR tanpa draft: langsung menunggu approval; satuan dari master.
        $response = $this->createMr([
            ['master_product_id' => $this->panel->id, 'qty' => 1],
            ['master_product_id' => $this->cable->id, 'qty' => 50],
            ['master_product_id' => $this->lamp->id, 'qty' => 2],
            ['master_product_id' => $this->cap->id, 'qty' => 1],
        ])->assertOk();
        $mr = MaterialRequest::with('items')->findOrFail($response->json('id'));
        $this->assertSame('MR-26110001', $mr->request_number);
        $this->assertSame(MaterialRequest::STATUS_WAITING_APPROVAL, $mr->status);
        $this->assertSame('Meter', $mr->items->firstWhere('master_product_id', $this->cable->id)->unit);

        // Belum disetujui -> belum bisa diserahkan; pembuat tanpa can_approve tidak bisa menyetujui.
        $this->handover($mr, [['material_request_item_id' => $this->itemId($mr, $this->cable), 'stock_id' => $cableRow->id, 'qty' => 50]])->assertStatus(422);
        $this->actingAs($this->requester)->postJson(route('material-request.approve', $mr->id))->assertForbidden();
        $this->actingAs($this->approver)->postJson(route('material-request.approve', $mr->id))->assertOk();
        $mr->refresh();
        $this->assertTrue($mr->isApproved());

        // Serah terima: sebagian dulu.
        $this->handover($mr, [
            ['material_request_item_id' => $this->itemId($mr, $this->cable), 'stock_id' => $cableRow->id, 'qty' => 50],
            ['material_request_item_id' => $this->itemId($mr, $this->panel), 'stock_id' => $panelRow->id, 'qty' => 1],
        ])->assertOk();
        $this->assertSame(MaterialRequest::STATUS_APPROVED, $mr->fresh()->status);
        $this->assertEquals(50, $this->stockBalance($this->jktMain->id, $this->cable->id));
        $this->assertEquals(0, $this->stockBalance($this->jktMain->id, $this->cable->id, $this->project->id));
        $this->assertEquals(0, $this->stockBalance($this->jktMain->id, $this->panel->id));

        $id = $this->handover($mr, [
            ['material_request_item_id' => $this->itemId($mr, $this->lamp), 'stock_id' => $lamps[0]->id, 'qty' => 1],
            ['material_request_item_id' => $this->itemId($mr, $this->lamp), 'stock_id' => $lamps[1]->id, 'qty' => 1],
            ['material_request_item_id' => $this->itemId($mr, $this->cap), 'stock_id' => $capRow->id, 'qty' => 1],
        ])->assertOk()->json('id');
        $this->assertSame('MH-26110002', MaterialHandover::findOrFail($id)->handover_number);
        // Semua diserahkan -> Selesai otomatis.
        $this->assertSame(MaterialRequest::STATUS_COMPLETED, $mr->fresh()->status);

        // Biaya project: stok bebas yang diserahkan saja (cap sudah milik project).
        $this->assertEqualsWithDelta(50 * 1000 + 2000000 + 2 * 50000, $this->projectStockCost(), 0.01);
        $this->assertSame(0, StockLog::where('type', StockLog::TYPE_ALLOCATE)->where('stock_id', $capRow->id)->count());
        $this->assertSame(StockLog::REF_MATERIAL_HANDOVER_ITEM, StockLog::where('stock_id', $capRow->id)->where('type', StockLog::TYPE_OUT)->value('ref_type'));

        // Produksi 1: Panel + Lampu SN-L1 + Kabel 30 -> produk baru.
        $this->produce([
            ['material_handover_item_id' => $this->poolId($this->panel), 'qty' => 1],
            ['material_handover_item_id' => $this->poolId($this->lamp, 'SN-L1'), 'qty' => 1],
            ['material_handover_item_id' => $this->poolId($this->cable), 'qty' => 30],
        ], ['new_product' => ['code' => 'FG-PL', 'name' => 'Panel Lampu', 'unit' => 'Set']])->assertOk();

        $fg = MasterProduct::where('code', 'FG-PL')->firstOrFail();
        $this->assertTrue($fg->isMaterial());
        $first = Production::where('output_product_id', $fg->id)->firstOrFail();
        $this->assertSame('MO-26110001', $first->production_number);
        $fgStock = $first->outputStock;
        $this->assertSame($this->project->id, $fgStock->project_id);
        $this->assertSame($this->jktFg->id, $fgStock->warehouse_id);
        $this->assertSame('Set', $fgStock->unit);
        $this->assertEqualsWithDelta(2000000 + 50000 + 30000, $fgStock->unit_cost, 0.01);
        $this->assertSame(StockLog::REF_PRODUCTION, StockLog::where('stock_id', $fgStock->id)->value('ref_type'));

        // Produksi 2: Lampu SN-L2 + Cap + Kabel 20 -> produk master yang sudah ada.
        $complete = MasterProduct::create(['code' => 'FG-LL', 'name' => 'Lampu Lengkap', 'unit' => 'Unit', 'status' => 'Active', 'is_material' => true]);
        $this->produce([
            ['material_handover_item_id' => $this->poolId($this->lamp, 'SN-L2'), 'qty' => 1],
            ['material_handover_item_id' => $this->poolId($this->cap), 'qty' => 1],
            ['material_handover_item_id' => $this->poolId($this->cable), 'qty' => 20],
        ], ['output_product_id' => $complete->id, 'output_qty' => 2])->assertOk();
        $second = Production::where('output_product_id', $complete->id)->firstOrFail();
        $this->assertEqualsWithDelta((50000 + 10000 + 20000) / 2, $second->outputStock->unit_cost, 0.01);

        // Produksi tidak menambah biaya project.
        $this->assertEqualsWithDelta(50 * 1000 + 2000000 + 2 * 50000, $this->projectStockCost(), 0.01);

        // Material sudah habis.
        $this->produce([['material_handover_item_id' => $this->poolId($this->cable), 'qty' => 1]], ['output_product_id' => $complete->id])
            ->assertStatus(422)->assertJsonFragment(['success' => false]);

        // Batal produksi: butuh can_approve; material kembali menjadi sisa.
        $this->actingAs($this->requester)->postJson(route('production.void', $second->id), ['reason' => 'salah rakit'])->assertForbidden();
        $this->actingAs($this->approver)->postJson(route('production.void', $second->id), ['reason' => 'salah rakit'])->assertOk();
        $second->refresh();
        $this->assertSame(Production::STATUS_CANCELLED, $second->status);
        $this->assertEquals(0, $this->stockBalance($this->jktFg->id, $complete->id, $this->project->id));
        $cablePool = \App\Models\MaterialHandoverItem::findOrFail($this->poolId($this->cable));
        $this->assertEqualsWithDelta(20, $cablePool->remainingQty(), 0.0001);

        // Barang jadi yang sudah dipakai tidak bisa dibatalkan.
        app(StockService::class)->take($fgStock->fresh(), 1, StockLog::TYPE_OUT, ['note' => 'DO test']);
        $this->actingAs($this->approver)->postJson(route('production.void', $first->id), ['reason' => 'x'])->assertStatus(422);

        // Halaman.
        foreach (['material-request', 'material-handover', 'production'] as $module) {
            $this->actingAs($this->requester)->get(route($module.'.index'))->assertOk();
            $this->actingAs($this->requester)->getJson(route($module.'.data'))->assertOk();
            $this->actingAs($this->requester)->get(route($module.'.create'))->assertOk();
        }
        $this->actingAs($this->requester)->get(route('material-request.show', $mr->id))->assertOk()->assertSee('MH-26110002');
        $this->actingAs($this->requester)->get(route('material-handover.show', $id))->assertOk()->assertSee('SN-L2');
        $this->actingAs($this->requester)->get(route('production.show', $first->id))->assertOk()->assertSee('SN-L1');
        $this->actingAs($this->requester)->get(route('production.show', $second->id))->assertOk()->assertSee('salah rakit');
    }

    public function test_material_request_rules_reject_resubmit_close_and_branch_visibility(): void
    {
        // Project tidak aktif & qty pecahan untuk barang ber-SN ditolak.
        $inactive = Project::create(array_merge($this->project->only(['company_id', 'po_date', 'po_date_received', 'po_due_date', 'total_sale_amount', 'net_sale_amount']), [
            'project_number' => '26110009', 'project_name' => 'Selesai', 'po_number_company' => 'PO-9', 'status' => Project::STATUS_COMPLETED,
        ]));
        $this->createMr([['master_product_id' => $this->cable->id, 'qty' => 5]], ['project_id' => $inactive->id])->assertStatus(422);
        $this->createMr([['master_product_id' => $this->lamp->id, 'qty' => 1.5]])->assertStatus(422);
        // User ber-branch tidak bisa membuat MR untuk branch lain.
        $this->createMr([['master_product_id' => $this->cable->id, 'qty' => 5]], ['branch_id' => $this->bandung->id])->assertStatus(422);

        $id = $this->createMr([['master_product_id' => $this->cable->id, 'qty' => 5]])->assertOk()->json('id');

        // Branch lain tidak melihat MR ini.
        $this->actingAs($this->bandungUser)->get(route('material-request.show', $id))->assertNotFound();

        // Ditolak -> diperbaiki -> otomatis diajukan ulang.
        $this->actingAs($this->approver)->postJson(route('material-request.reject', $id), [])->assertStatus(422);
        $this->actingAs($this->approver)->postJson(route('material-request.reject', $id), ['reason' => 'qty kurang'])->assertOk();
        $this->assertSame(MaterialRequest::STATUS_REJECTED, MaterialRequest::find($id)->status);
        $this->actingAs($this->requester)->putJson(route('material-request.update', $id), [
            'project_id' => $this->project->id, 'branch_id' => $this->jakarta->id, 'request_date' => '2026-11-17',
            'items' => [['master_product_id' => $this->cable->id, 'qty' => 10]],
        ])->assertOk();
        $mr = MaterialRequest::with('items')->find($id);
        $this->assertSame(MaterialRequest::STATUS_WAITING_APPROVAL, $mr->status);
        $this->assertNull($mr->reject_reason);
        $this->assertEquals(10, $mr->items->first()->qty);

        // Disetujui -> tidak bisa diubah / dihapus; bisa ditutup.
        $this->actingAs($this->approver)->postJson(route('material-request.approve', $id))->assertOk();
        $this->actingAs($this->requester)->deleteJson(route('material-request.destroy', $id))->assertStatus(422);
        $this->actingAs($this->requester)->patchJson(route('material-request.close', $id))->assertOk();
        $this->assertSame(MaterialRequest::STATUS_COMPLETED, MaterialRequest::find($id)->status);

        // Menunggu approval boleh dihapus pembuat.
        $other = $this->createMr([['master_product_id' => $this->cable->id, 'qty' => 1]])->json('id');
        $this->actingAs($this->requester)->deleteJson(route('material-request.destroy', $other))->assertOk();
        $this->assertNull(MaterialRequest::find($other));
    }

    public function test_handover_rules(): void
    {
        $mr = $this->approvedMr([
            ['master_product_id' => $this->cable->id, 'qty' => 10],
            ['master_product_id' => $this->lamp->id, 'qty' => 1],
        ]);
        $cableItem = $this->itemId($mr, $this->cable);
        $lampItem = $this->itemId($mr, $this->lamp);

        $bdgCable = $this->seedStock($this->bdgWarehouse->id, $this->cable, 50)->first();
        $otherProjectCable = $this->seedStock($this->jktMain->id, $this->cable, 50, ['project_id' => $this->otherProject->id])->first();
        $freeCable = $this->seedStock($this->jktMain->id, $this->cable, 50)->first();
        $noSnLamp = $this->seedStock($this->jktMain->id, $this->lamp, 1)->first();

        // Gudang branch lain, stok project lain, unit tanpa SN, qty melebihi sisa, produk lain.
        $this->handover($mr, [['material_request_item_id' => $cableItem, 'stock_id' => $bdgCable->id, 'qty' => 5]])->assertStatus(422);
        $this->handover($mr, [['material_request_item_id' => $cableItem, 'stock_id' => $otherProjectCable->id, 'qty' => 5]])->assertStatus(422);
        $this->handover($mr, [['material_request_item_id' => $lampItem, 'stock_id' => $noSnLamp->id, 'qty' => 1]])->assertStatus(422);
        $this->handover($mr, [['material_request_item_id' => $cableItem, 'stock_id' => $freeCable->id, 'qty' => 11]])->assertStatus(422);
        $this->handover($mr, [['material_request_item_id' => $lampItem, 'stock_id' => $freeCable->id, 'qty' => 1]])->assertStatus(422);
        // User branch lain tidak bisa menyerahkan.
        $this->handover($mr, [['material_request_item_id' => $cableItem, 'stock_id' => $freeCable->id, 'qty' => 5]], $this->bandungUser)->assertNotFound();

        $this->assertSame(0, MaterialHandover::count());
        $this->assertEquals(50, $this->stockBalance($this->jktMain->id, $this->cable->id));

        // Pemilih stok: hanya stok bebas / milik project MR di gudang branch MR.
        $options = $this->actingAs($this->requester)->getJson(route('material-handover.stock-options', ['material_request_item_id' => $cableItem, 'warehouse_id' => $this->jktMain->id]))
            ->assertOk()->json('data');
        $this->assertSame([$freeCable->id], array_column($options, 'id'));
        $this->actingAs($this->requester)->getJson(route('material-handover.stock-options', ['material_request_item_id' => $cableItem, 'warehouse_id' => $this->bdgWarehouse->id]))
            ->assertStatus(422);
    }

    public function test_production_rules(): void
    {
        $cable = $this->seedStock($this->jktMain->id, $this->cable, 10, ['unit_cost' => 1000])->first();
        $mr = $this->approvedMr([['master_product_id' => $this->cable->id, 'qty' => 10]]);
        $this->handover($mr, [['material_request_item_id' => $this->itemId($mr, $this->cable), 'stock_id' => $cable->id, 'qty' => 10]])->assertOk();
        $pool = $this->poolId($this->cable);

        $snProduct = MasterProduct::create(['code' => 'FG-SN', 'name' => 'Inverter Rakitan', 'status' => 'Active', 'is_material' => false]);
        $fg = MasterProduct::create(['code' => 'FG-1', 'name' => 'Rakitan', 'status' => 'Active', 'is_material' => true]);

        // Produk barang jadi ber-SN ditolak.
        $this->produce([['material_handover_item_id' => $pool, 'qty' => 5]], ['output_product_id' => $snProduct->id])
            ->assertStatus(422)->assertJsonFragment(['success' => false]);
        // Gudang hasil di branch lain ditolak.
        $this->produce([['material_handover_item_id' => $pool, 'qty' => 5]], ['output_product_id' => $fg->id, 'output_warehouse_id' => $this->bdgWarehouse->id])->assertStatus(422);
        // Material project ini tidak bisa dipakai project lain / branch lain.
        $this->produce([['material_handover_item_id' => $pool, 'qty' => 5]], ['output_product_id' => $fg->id, 'project_id' => $this->otherProject->id])->assertStatus(422);
        $this->produce([['material_handover_item_id' => $pool, 'qty' => 5]], ['output_product_id' => $fg->id, 'branch_id' => $this->bandung->id, 'output_warehouse_id' => $this->bdgWarehouse->id], $this->approver)->assertStatus(422);
        // Kode produk baru yang sudah ada ditolak.
        $this->produce([['material_handover_item_id' => $pool, 'qty' => 5]], ['new_product' => ['code' => 'FG-1', 'name' => 'Lain']])->assertStatus(422);
        // Melebihi sisa.
        $this->produce([['material_handover_item_id' => $pool, 'qty' => 11]], ['output_product_id' => $fg->id])->assertStatus(422);
        $this->assertSame(0, Production::count());

        // Sebagian boleh; sisanya tetap di pool.
        $this->produce([['material_handover_item_id' => $pool, 'qty' => 4]], ['output_product_id' => $fg->id])->assertOk();
        $this->assertEqualsWithDelta(6, \App\Models\MaterialHandoverItem::find($pool)->remainingQty(), 0.0001);
        $data = $this->actingAs($this->requester)->getJson(route('production.pool', ['project_id' => $this->project->id, 'branch_id' => $this->jakarta->id]))->assertOk();
        $this->assertEquals(6, $data->json('data.0.remaining'));
        $this->assertSame([$this->jktMain->id, $this->jktFg->id], collect($data->json('warehouses'))->pluck('id')->sort()->values()->all());
        $this->actingAs($this->bandungUser)->getJson(route('production.pool', ['project_id' => $this->project->id, 'branch_id' => $this->jakarta->id]))->assertForbidden();

        // Barang jadi yang dipindah tidak bisa dibatalkan.
        $production = Production::firstOrFail();
        app(StockService::class)->move($production->outputStock, 1, $this->jktMain->id, $this->project->id, StockLog::TYPE_TRANSFER_OUT, ['note' => 'pindah']);
        $this->actingAs($this->approver)->postJson(route('production.void', $production->id), ['reason' => 'x'])->assertStatus(422);
        $this->assertTrue(Production::first()->isPosted());
    }

    public function test_direct_purchase_reference_merges_material_requests_per_branch(): void
    {
        // JKT: 2 MR (project berbeda) butuh kabel; BDG: 1 MR.
        $this->approvedMr([
            ['master_product_id' => $this->cable->id, 'qty' => 50],
            ['master_product_id' => $this->lamp->id, 'qty' => 2],
        ]);
        $id = $this->createMr([['master_product_id' => $this->cable->id, 'qty' => 30.5]], ['project_id' => $this->otherProject->id])->json('id');
        $this->actingAs($this->approver)->postJson(route('material-request.approve', $id))->assertOk();
        $bdg = $this->createMr([['master_product_id' => $this->cable->id, 'qty' => 10]], ['branch_id' => $this->bandung->id], $this->approver)->json('id');
        $this->actingAs($this->approver)->postJson(route('material-request.approve', $bdg))->assertOk();
        // MR yang belum disetujui tidak ikut.
        $this->createMr([['master_product_id' => $this->cap->id, 'qty' => 5]])->assertOk();

        // Stok: bebas 20 + milik project MR 5 dihitung; milik project lain & branch lain tidak.
        $this->seedStock($this->jktMain->id, $this->cable, 20);
        $this->seedStock($this->jktFg->id, $this->cable, 5, ['project_id' => $this->otherProject->id]);
        $third = Project::create(array_merge($this->project->only(['company_id', 'po_date', 'po_date_received', 'po_due_date', 'total_sale_amount', 'net_sale_amount']), [
            'project_number' => '26110003', 'project_name' => 'Lain', 'po_number_company' => 'PO-3', 'status' => Project::STATUS_ACTIVE,
        ]));
        $this->seedStock($this->jktMain->id, $this->cable, 100, ['project_id' => $third->id]);
        $this->seedStock($this->bdgWarehouse->id, $this->cable, 20);

        // Dalam pembelian: DP Stok belum diterima; DP ditolak / bertujuan project tidak dihitung.
        $dp = fn (string $status, string $purpose, int $qty) => DirectPurchase::create([
            'supplier_name' => 'PT Kabel', 'purpose' => $purpose, 'status' => $status, 'date' => '2026-11-17',
            'project_id' => $purpose === DirectPurchase::PURPOSE_PROJECT ? $this->project->id : null,
        ])->items()->create(['master_product_id' => $this->cable->id, 'qty' => $qty, 'unit' => 'Meter', 'price' => 1000, 'sort_order' => 1]);
        $dp(DirectPurchase::STATUS_APPROVED, DirectPurchase::PURPOSE_STOCK, 40);
        $dp(DirectPurchase::STATUS_REJECTED, DirectPurchase::PURPOSE_STOCK, 100);
        $dp(DirectPurchase::STATUS_APPROVED, DirectPurchase::PURPOSE_PROJECT, 100);

        // Default branch user (JKT); produk sama digabung.
        $res = $this->actingAs($this->requester)->getJson(route('direct-purchase.material-requests'))->assertOk();
        $this->assertSame($this->jakarta->id, $res->json('branch_id'));
        $rows = collect($res->json('data'))->keyBy('code');
        $this->assertSame(['CBL-1', 'LMP-1'], $rows->keys()->all());
        $cable = $rows['CBL-1'];
        $this->assertEquals(80.5, $cable['need']);
        $this->assertEquals(25, $cable['available']);
        $this->assertEquals(40, $cable['on_order']);
        $this->assertSame(16, $cable['suggested']);
        $this->assertCount(2, $cable['requests']);
        $this->assertSame(2, $rows['LMP-1']['suggested']);

        // Branch lain: stok cukup -> saran 0. User ber-branch hanya melihat branch-nya.
        $bdgRows = $this->actingAs($this->approver)->getJson(route('direct-purchase.material-requests', ['branch_id' => $this->bandung->id]))->json('data');
        $this->assertSame(0, $bdgRows[0]['suggested']);
        $this->assertCount(2, $this->actingAs($this->approver)->getJson(route('direct-purchase.material-requests'))->json('branches'));
        $this->assertCount(1, $this->actingAs($this->requester)->getJson(route('direct-purchase.material-requests'))->json('branches'));

        // Form DP menampilkan tombol acuan Material Request.
        $this->actingAs($this->requester)->get(route('direct-purchase.create'))->assertOk()
            ->assertSee('Add from Material Request')->assertSee(route('direct-purchase.material-requests'), false);
    }
}

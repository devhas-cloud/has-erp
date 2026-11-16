<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\Division;
use App\Models\Document;
use App\Models\MasterProduct;
use App\Models\Module;
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
 * Pengiriman antar branch: draft -> kirim (stok dalam pengiriman, surat jalan)
 * -> branch tujuan memeriksa (foto per barang) dan memilih gudang -> diterima
 * hanya bila semua barang sesuai. Plus master Branch, branch user & gudang.
 */
class BranchTransferTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private Branch $jakarta;

    private Branch $bandung;

    private Warehouse $jktWarehouse;

    private Warehouse $bdgWarehouse;

    private User $sender;

    private User $receiver;

    private User $headOffice;

    private MasterProduct $cable;

    private MasterProduct $inverter;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Ops', 'description' => 'Ops', 'type' => 'Internal', 'status' => 'Active']);
        $this->jakarta = Branch::create(['code' => 'JKT', 'name' => 'HQ Jakarta', 'status' => 'Active']);
        $this->bandung = Branch::create(['code' => 'BDG', 'name' => 'Cabang Bandung', 'status' => 'Active']);
        $this->jktWarehouse = Warehouse::create(['branch_id' => $this->jakarta->id, 'code' => 'J1', 'name' => 'Gudang Jakarta', 'status' => 'Active']);
        $this->bdgWarehouse = Warehouse::create(['branch_id' => $this->bandung->id, 'code' => 'B1', 'name' => 'Gudang Bandung', 'status' => 'Active']);

        $make = fn (string $name, ?Branch $branch) => User::create(['username' => $name, 'email' => $name.'@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'branch_id' => $branch?->id, 'role' => 'User']);
        $this->sender = $make('jkt', $this->jakarta);
        $this->receiver = $make('bdg', $this->bandung);
        $this->headOffice = $make('pusat', null);

        foreach (['branch-transfer' => 'MOD_BRANCH_TRANSFER', 'stock-transfer' => 'MOD_STOCK_TRANSFER', 'stock-report' => 'MOD_STOCK_REPORT'] as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Inventory']);
            foreach ([$this->sender, $this->receiver, $this->headOffice] as $user) {
                UserAccessControl::create(['user_id' => $user->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
            }
        }

        $this->cable = MasterProduct::create(['code' => 'CBL-1', 'name' => 'Kabel NYY', 'division_id' => $division->id, 'status' => 'Active', 'is_material' => true]);
        $this->inverter = MasterProduct::create(['code' => 'INV-5', 'name' => 'Inverter 5kW', 'division_id' => $division->id, 'status' => 'Active']);
    }

    private function draft(array $items, array $extra = [])
    {
        return $this->actingAs($this->sender)->postJson(route('branch-transfer.store'), array_merge([
            'from_warehouse_id' => $this->jktWarehouse->id,
            'to_branch_id' => $this->bandung->id,
            'transfer_date' => '2026-11-16',
            'courier' => 'Truk sendiri',
            'vehicle_no' => 'B 1234 XYZ',
            'driver_name' => 'Pak Udin',
            'items' => $items,
        ], $extra));
    }

    public function test_full_flow_ship_in_transit_inspect_with_photos_and_receive(): void
    {
        Storage::fake('local');
        $roll = $this->seedStock($this->jktWarehouse->id, $this->cable, 100, ['unit_cost' => 5000, 'batch_number' => 'LOT-A'])->first();
        $unit = $this->seedStock($this->jktWarehouse->id, $this->inverter, 1, ['unit_cost' => 9000000], ['SN-77'])->first();

        // Branch tujuan = asal ditolak; draft tidak mengurangi stok.
        $this->draft([['stock_id' => $roll->id, 'qty' => 40]], ['to_branch_id' => $this->jakarta->id])->assertStatus(422);
        $id = $this->draft([['stock_id' => $roll->id, 'qty' => 40], ['stock_id' => $unit->id, 'qty' => 1]])->assertOk()->json('id');
        $transfer = BranchTransfer::findOrFail($id);
        $this->assertSame('BT-26110001', $transfer->transfer_number);
        $this->assertSame($this->jakarta->id, $transfer->from_branch_id);
        $this->assertEquals(100, $this->stockBalance($this->jktWarehouse->id, $this->cable->id));

        // Penerima belum bisa memeriksa draft; surat jalan baru ada setelah dikirim.
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.receive', $id))->assertStatus(422);
        $this->actingAs($this->sender)->get(route('branch-transfer.pdf', $id))->assertForbidden();
        // Hanya branch asal yang bisa mengirim.
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.ship', $id))->assertStatus(403);

        $this->actingAs($this->sender)->postJson(route('branch-transfer.ship', $id))->assertOk();
        $transfer->refresh();
        $this->assertTrue($transfer->isInTransit());
        // Dalam pengiriman: tidak terhitung di gudang mana pun.
        $this->assertEquals(60, $this->stockBalance($this->jktWarehouse->id, $this->cable->id));
        $this->assertEquals(0, $this->stockBalance($this->bdgWarehouse->id, $this->cable->id));
        $this->assertFalse($unit->fresh()->is_open);
        $this->assertSame(2, StockLog::where('type', StockLog::TYPE_SHIP_OUT)->count());

        $this->actingAs($this->sender)->get(route('branch-transfer.pdf', $id))->assertOk();
        $this->actingAs($this->headOffice)->get(route('stock-report.index'))->assertOk()->assertSee('Dalam Pengiriman Antar Branch')->assertSee('BT-26110001');

        // Pengirim tidak bisa menerima; penerima memeriksa, satu barang kurang -> tidak bisa diselesaikan.
        $items = $transfer->items()->orderBy('sort_order')->get();
        $this->actingAs($this->sender)->postJson(route('branch-transfer.inspect', $id), ['items' => [['id' => $items[0]->id, 'received_qty' => 40, 'condition_ok' => 1]]])->assertStatus(403);

        $this->actingAs($this->receiver)->postJson(route('branch-transfer.photos.store', [$id, $items[1]->id]), [
            'files' => [UploadedFile::fake()->image('inverter.jpg')],
        ])->assertOk()->assertJsonCount(1, 'photos');
        $this->assertSame(1, Document::where('type', Document::TYPE_RECEIPT_PHOTO)->count());

        $this->actingAs($this->receiver)->postJson(route('branch-transfer.inspect', $id), [
            'to_warehouse_id' => $this->bdgWarehouse->id,
            'items' => [
                ['id' => $items[0]->id, 'received_qty' => 38, 'condition_ok' => 1, 'receive_note' => 'kurang 2 m'],
                ['id' => $items[1]->id, 'received_qty' => 1, 'condition_ok' => 1],
            ],
        ])->assertOk()->assertJsonPath('ready', false);
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.receive', $id))
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertTrue($transfer->fresh()->isInTransit());

        // Gudang tujuan harus milik branch tujuan.
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.inspect', $id), [
            'to_warehouse_id' => $this->jktWarehouse->id,
            'items' => [['id' => $items[0]->id, 'received_qty' => 40, 'condition_ok' => 1]],
        ])->assertStatus(422);

        // Setelah semua sesuai -> diterima ke gudang tujuan.
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.inspect', $id), [
            'to_warehouse_id' => $this->bdgWarehouse->id,
            'items' => [['id' => $items[0]->id, 'received_qty' => 40, 'condition_ok' => 1, 'receive_note' => null]],
        ])->assertOk()->assertJsonPath('ready', true);
        $this->actingAs($this->receiver)->get(route('branch-transfer.show', $id))->assertOk()->assertSee('Selesaikan Penerimaan');
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.receive', $id))->assertOk();

        $transfer->refresh();
        $this->assertTrue($transfer->isReceived());
        $this->assertEquals(40, $this->stockBalance($this->bdgWarehouse->id, $this->cable->id));
        $arrived = Stock::where('warehouse_id', $this->bdgWarehouse->id)->where('master_product_id', $this->cable->id)->firstOrFail();
        $this->assertSame([$roll->id, 'LOT-A', 5000.0], [$arrived->parent_id, $arrived->batch_number, $arrived->unit_cost]);
        // Barang ber-SN kembali ke barisnya sendiri di gudang tujuan.
        $this->assertSame([$this->bdgWarehouse->id, 1.0], [$unit->fresh()->warehouse_id, $unit->fresh()->qty]);
        $this->assertSame(2, StockLog::where('type', StockLog::TYPE_SHIP_IN)->count());

        // Dokumen terkunci.
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.receive', $id))->assertStatus(422);
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.photos.store', [$id, $items[1]->id]), ['files' => [UploadedFile::fake()->image('x.jpg')]])->assertStatus(422);
        $this->actingAs($this->sender)->deleteJson(route('branch-transfer.destroy', $id))->assertStatus(422);
        $this->actingAs($this->sender)->get(route('branch-transfer.show', $id))->assertOk()->assertSee('Diterima');
    }

    public function test_branch_scoping_and_draft_editing(): void
    {
        $roll = $this->seedStock($this->jktWarehouse->id, $this->cable, 10)->first();
        $bdgRoll = $this->seedStock($this->bdgWarehouse->id, $this->cable, 10)->first();

        // User Bandung tidak bisa mengirim dari gudang Jakarta.
        $this->actingAs($this->receiver)->postJson(route('branch-transfer.store'), [
            'from_warehouse_id' => $this->jktWarehouse->id, 'to_branch_id' => $this->jakarta->id, 'transfer_date' => '2026-11-16',
            'items' => [['stock_id' => $roll->id, 'qty' => 1]],
        ])->assertStatus(422);
        // Baris stok harus dari gudang asal.
        $this->draft([['stock_id' => $bdgRoll->id, 'qty' => 1]])->assertStatus(422);

        $id = $this->draft([['stock_id' => $roll->id, 'qty' => 2]])->assertOk()->json('id');
        $this->actingAs($this->sender)->putJson(route('branch-transfer.update', $id), [
            'from_warehouse_id' => $this->jktWarehouse->id, 'to_branch_id' => $this->bandung->id, 'transfer_date' => '2026-11-17',
            'items' => [['stock_id' => $roll->id, 'qty' => 3.5]],
        ])->assertOk();
        $this->assertSame(3.5, BranchTransfer::findOrFail($id)->items()->value('qty'));

        // Penerima (Bandung) melihat dokumen; user branch lain tidak.
        $other = Branch::create(['code' => 'SBY', 'name' => 'Surabaya', 'status' => 'Active']);
        $stranger = User::create(['username' => 'sby', 'email' => 'sby@has.com', 'password' => bcrypt('x'), 'branch_id' => $other->id, 'role' => 'User']);
        UserAccessControl::create(['user_id' => $stranger->id, 'module_id' => Module::where('module_code', 'MOD_BRANCH_TRANSFER')->value('id'), 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);

        $this->actingAs($this->receiver)->getJson(route('branch-transfer.data'))->assertOk()->assertJsonPath('recordsTotal', 1);
        $this->actingAs($stranger)->getJson(route('branch-transfer.data'))->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->actingAs($stranger)->get(route('branch-transfer.show', $id))->assertNotFound();
        $this->actingAs($this->headOffice)->getJson(route('branch-transfer.data'))->assertOk()->assertJsonPath('recordsTotal', 1);

        foreach (['index', 'create'] as $page) {
            $this->actingAs($this->sender)->get(route('branch-transfer.'.$page))->assertOk();
        }
        $this->actingAs($this->sender)->get(route('branch-transfer.edit', $id))->assertOk();
        $this->actingAs($this->sender)->getJson(route('branch-transfer.stock-options', ['warehouse_id' => $this->jktWarehouse->id]))->assertOk()->assertJsonPath('data.0.id', $roll->id);
        $this->actingAs($this->receiver)->getJson(route('branch-transfer.stock-options', ['warehouse_id' => $this->jktWarehouse->id]))->assertForbidden();

        $this->actingAs($this->sender)->deleteJson(route('branch-transfer.destroy', $id))->assertOk();
        $this->assertSame(0, BranchTransfer::count());
    }

    public function test_stock_transfer_is_limited_to_one_branch(): void
    {
        $roll = $this->seedStock($this->jktWarehouse->id, $this->cable, 10)->first();
        $jkt2 = Warehouse::create(['branch_id' => $this->jakarta->id, 'code' => 'J2', 'name' => 'Gudang Jakarta 2', 'status' => 'Active']);

        $this->actingAs($this->headOffice)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->jktWarehouse->id, 'to_warehouse_id' => $this->bdgWarehouse->id,
            'items' => [['stock_id' => $roll->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->actingAs($this->headOffice)->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $this->jktWarehouse->id, 'to_warehouse_id' => $jkt2->id,
            'items' => [['stock_id' => $roll->id, 'qty' => 1]],
        ])->assertOk();
        $this->assertEquals(1, $this->stockBalance($jkt2->id, $this->cable->id));
    }

    public function test_branch_master_in_configuration_user_branch_and_warehouse_branch(): void
    {
        $admin = User::create(['username' => 'admin', 'email' => 'admin@has.com', 'password' => bcrypt('x'), 'role' => 'Admin']);
        foreach (['configuration' => 'MOD_CONFIGURATION', 'user-management' => 'MOD_USER_MANAGEMENT', 'warehouse' => 'MOD_WAREHOUSE'] as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Master Data']);
            UserAccessControl::create(['user_id' => $admin->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
        }

        // Branch di Configuration: kode unik, bisa diedit tanpa bentrok dengan dirinya sendiri.
        $this->actingAs($admin)->postJson(route('configuration.store', 'branches'), ['name' => 'Medan', 'code' => 'MDN', 'status' => 'Active'])->assertOk();
        $this->actingAs($admin)->postJson(route('configuration.store', 'branches'), ['name' => 'Medan 2', 'code' => 'MDN', 'status' => 'Active'])->assertStatus(422);
        $medan = Branch::where('code', 'MDN')->firstOrFail();
        $this->actingAs($admin)->putJson(route('configuration.update', ['branches', $medan->id]), ['name' => 'Cabang Medan', 'code' => 'MDN', 'status' => 'Active'])->assertOk();
        $this->assertSame('Cabang Medan', $medan->fresh()->name);
        $this->actingAs($admin)->getJson(route('configuration.list', 'branches'))->assertOk()->assertJsonPath('pagination.total', 3);

        // Branch yang masih punya gudang tidak bisa dihapus.
        $this->actingAs($admin)->deleteJson(route('configuration.destroy', ['branches', $this->jakarta->id]))->assertStatus(422);
        $this->actingAs($admin)->deleteJson(route('configuration.destroy', ['branches', $medan->id]))->assertOk();

        // Gudang wajib branch.
        $this->actingAs($admin)->postJson(route('warehouse.store'), ['code' => 'X1', 'name' => 'Tanpa Branch', 'status' => 'Active'])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('warehouse.store'), ['code' => 'X1', 'name' => 'Gudang Baru', 'branch_id' => $this->bandung->id, 'status' => 'Active'])->assertOk();
        $this->actingAs($admin)->getJson(route('warehouse.data'))->assertOk()->assertJsonFragment(['branch_name' => 'Cabang Bandung']);

        // User: branch opsional.
        $this->actingAs($admin)->get(route('user-management.create'))->assertOk()->assertSee('Semua branch');
        $this->actingAs($admin)->put(route('user-management.update', $this->sender->id), [
            'username' => 'jkt', 'full_name' => 'User Jakarta', 'email' => 'jkt@has.com', 'role' => 'User', 'branch_id' => $this->bandung->id,
        ])->assertRedirect();
        $this->assertSame($this->bandung->id, $this->sender->fresh()->branch_id);
        $this->actingAs($admin)->put(route('user-management.update', $this->sender->id), [
            'username' => 'jkt', 'full_name' => 'User Jakarta', 'email' => 'jkt@has.com', 'role' => 'User', 'branch_id' => '',
        ])->assertRedirect();
        $this->assertNull($this->sender->fresh()->branch_id);
        $this->actingAs($admin)->get(route('user-management.show', $this->sender->id))->assertOk()->assertSee('Semua branch');
    }
}

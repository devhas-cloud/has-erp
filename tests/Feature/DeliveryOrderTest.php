<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\DeliveryOrder;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Delivery Order jalur Langsung — baris dokumen berisi baris stok milik project,
 * posting oleh gudang (tanpa approval) mengurangi stok, dokumen terkunci,
 * Surat Jalan & integrasi project. Jalur Packing List: PackingListTest.
 */
class DeliveryOrderTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $user;

    private Warehouse $transit;

    private Project $project;

    private MasterProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'Admin']);
        $this->user = User::create(['username' => 'gudang', 'email' => 'gudang@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'User']);

        $module = Module::create(['module_code' => 'MOD_DELIVERY_ORDER', 'module_name' => 'Delivery Order', 'route_name' => 'delivery-order', 'icon' => 'fa fa-truck', 'group' => 'Purchasing']);
        UserAccessControl::create([
            'user_id' => $this->user->id,
            'module_id' => $module->id,
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => true,
            'can_approve' => false,
        ]);

        $this->transit = Warehouse::create(['code' => 'T1', 'name' => 'Transit', 'status' => 'Active']);
        $company = AccountCompany::create([
            'account_name' => 'PT Maju Bersama',
            'status' => 'Active',
            'phone' => '021-555',
            'address_shipping_street' => 'Jl. Industri 5',
            'address_shipping_city' => 'Gresik',
        ]);
        $this->project = Project::create([
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
        $this->product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor pH', 'division_id' => $this->division->id, 'status' => 'Active', 'is_material' => true]);

        $this->row = $this->seedStock($this->transit->id, $this->product, 5, ['project_id' => $this->project->id])->first();
    }

    private Stock $row;

    private function payload(float $qty, ?int $stockId = null): array
    {
        return [
            'project_id' => $this->project->id,
            'warehouse_id' => $this->transit->id,
            'delivery_date' => '2026-11-02',
            'recipient_name' => 'PT Maju Bersama',
            'shipping_address' => 'Jl. Industri 5, Gresik',
            'uses_packing_list' => false,
            'lines' => [[
                'description' => "PN-1\nSensor pH",
                'qty' => 1,
                'unit' => 'Set',
                'stocks' => [['stock_id' => $stockId ?? $this->row->id, 'qty' => $qty]],
            ]],
        ];
    }

    public function test_draft_then_post_reduces_project_stock_and_locks(): void
    {
        $this->actingAs($this->user)->getJson(route('delivery-order.project-stock', ['project_id' => $this->project->id]))
            ->assertOk()
            ->assertJsonPath('warehouses.0.id', $this->transit->id)
            ->assertJsonPath('recipient.address', 'Jl. Industri 5, Gresik');
        $this->actingAs($this->user)->getJson(route('delivery-order.stock-options', ['project_id' => $this->project->id, 'warehouse_id' => $this->transit->id]))
            ->assertOk()->assertJsonPath('data.0.id', $this->row->id)->assertJsonPath('data.0.qty', 5);

        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(6))->assertStatus(422);
        $this->actingAs($this->user)->postJson(route('delivery-order.store'), array_merge($this->payload(1), ['recipient_name' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('recipient_name');

        $id = $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(3))->assertOk()->json('id');
        $order = DeliveryOrder::with('items')->findOrFail($id);
        $this->assertSame('DO-26110001', $order->do_number);
        $this->assertSame('Set', $order->items->first()->unit);
        $this->assertSame('Unit', $order->items->first()->stocks()->value('unit'), 'satuan isi stok dari baris stok');
        $this->assertEquals(5, $this->stockBalance($this->transit->id, $this->product->id, $this->project->id), 'draft tidak mengurangi stok');

        // Material boleh dikirim sebagian (desimal) dari satu baris stok.
        $this->actingAs($this->user)->putJson(route('delivery-order.update', $id), $this->payload(3.5))->assertOk();
        $this->assertSame(3.5, $order->items()->first()->stocks()->first()->qty);

        foreach (['index', 'create'] as $page) {
            $this->actingAs($this->user)->get(route('delivery-order.'.$page))->assertOk();
        }
        $this->actingAs($this->user)->get(route('delivery-order.show', $id))->assertOk()->assertSee('Posting (Barang Dikirim)');
        $this->actingAs($this->user)->get(route('delivery-order.edit', $id))->assertOk();
        $this->actingAs($this->user)->get(route('delivery-order.pdf', $id))->assertOk();

        $this->actingAs($this->user)->postJson(route('delivery-order.post', $id))->assertOk();

        $order->refresh();
        $this->assertSame(DeliveryOrder::STATUS_POSTED, $order->status);
        $this->assertSame($this->user->id, $order->posted_by);
        $this->assertEquals(1.5, $this->stockBalance($this->transit->id, $this->product->id, $this->project->id));
        $this->assertDatabaseHas('stock_logs', ['type' => StockLog::TYPE_OUT, 'ref_type' => StockLog::REF_DELIVERY_ORDER_ITEM_STOCK, 'ref_id' => $order->items()->first()->stocks()->value('id'), 'qty' => -3.5]);

        $this->actingAs($this->user)->postJson(route('delivery-order.post', $id))->assertStatus(422);
        $this->actingAs($this->user)->putJson(route('delivery-order.update', $id), $this->payload(1))->assertStatus(422);
        $this->actingAs($this->user)->deleteJson(route('delivery-order.destroy', $id))->assertStatus(422);
        $this->actingAs($this->user)->get(route('delivery-order.edit', $id))->assertForbidden();

        $this->actingAs($this->admin)->get(route('project.show', $this->project->id))
            ->assertOk()->assertSee('Barang &amp; Pengiriman', false)->assertSee($order->do_number);
    }

    public function test_post_rechecks_stock_and_draft_can_be_deleted(): void
    {
        $first = $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(4))->assertOk()->json('id');
        $second = $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(3))->assertOk()->json('id');

        $this->actingAs($this->user)->postJson(route('delivery-order.post', $first))->assertOk();
        $this->actingAs($this->user)->postJson(route('delivery-order.post', $second))
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame(DeliveryOrder::STATUS_DRAFT, DeliveryOrder::find($second)->status);
        $this->assertEquals(1, $this->stockBalance($this->transit->id, $this->product->id, $this->project->id));

        $this->actingAs($this->user)->deleteJson(route('delivery-order.destroy', $second))->assertOk();
        $this->assertDatabaseMissing('delivery_orders', ['id' => $second]);
    }

    public function test_free_stock_cannot_be_delivered_directly(): void
    {
        $logger = MasterProduct::create(['code' => 'PN-2', 'name' => 'Logger', 'division_id' => $this->division->id, 'status' => 'Active', 'is_material' => true]);
        $free = $this->seedStock($this->transit->id, $logger, 10)->first();

        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(1, $free->id))->assertStatus(422);
    }

    public function test_serialized_unit_without_serial_is_blocked_until_completed(): void
    {
        $inverter = MasterProduct::create(['code' => 'INV-5', 'name' => 'Inverter', 'division_id' => $this->division->id, 'status' => 'Active']);
        [$withSn, $withoutSn] = $this->seedStock($this->transit->id, $inverter, 2, ['project_id' => $this->project->id], ['SN-001'])->all();

        $this->assertSame('SN-001', $withSn->serial_number);
        $this->assertNull($withoutSn->serial_number);

        // Unit tanpa SN tidak bisa dikirim; barang ber-SN selalu utuh.
        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(1, $withoutSn->id))
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(0.5, $withSn->id))->assertStatus(422);

        $id = $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(1, $withSn->id))->assertOk()->json('id');
        $this->actingAs($this->user)->postJson(route('delivery-order.post', $id))->assertOk();
        $this->assertFalse($withSn->fresh()->is_open);

        // Surat jalan mencantumkan SN.
        $this->actingAs($this->user)->get(route('delivery-order.pdf', $id))->assertOk();
        $this->assertStringContainsString('SN-001', view('delivery-order.pdf', ['order' => DeliveryOrder::with(['items.stocks.stock.masterProduct', 'packingLists', 'project.company', 'warehouse', 'creator'])->find($id)])->render());
    }
}

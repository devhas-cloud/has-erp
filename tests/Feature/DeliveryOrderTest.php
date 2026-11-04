<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\DeliveryOrder;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3: Delivery Order — draft dari stok milik project, posting oleh gudang
 * (tanpa approval) mengurangi stok, dokumen terkunci, Surat Jalan & integrasi project.
 */
class DeliveryOrderTest extends TestCase
{
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

        $this->transit = Warehouse::create(['code' => 'T1', 'name' => 'Transit', 'type' => Warehouse::TYPE_TRANSIT, 'status' => 'Active']);
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
        $this->product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor pH', 'division_id' => $this->division->id, 'status' => 'Active']);

        StockMovement::record([
            'warehouse_id' => $this->transit->id,
            'master_product_id' => $this->product->id,
            'project_id' => $this->project->id,
            'type' => StockMovement::TYPE_ALLOCATE_IN,
            'qty' => 5,
            'unit' => 'Unit',
            'created_by' => $this->admin->id,
        ]);
    }

    private function payload(int $qty): array
    {
        return [
            'project_id' => $this->project->id,
            'warehouse_id' => $this->transit->id,
            'delivery_date' => '2026-11-02',
            'recipient_name' => 'PT Maju Bersama',
            'shipping_address' => 'Jl. Industri 5, Gresik',
            'items' => [['master_product_id' => $this->product->id, 'qty' => $qty, 'remark' => 'S/N 001']],
        ];
    }

    public function test_draft_then_post_reduces_project_stock_and_locks(): void
    {
        $this->actingAs($this->user)->getJson(route('delivery-order.project-stock', ['project_id' => $this->project->id]))
            ->assertOk()
            ->assertJsonPath('items.0.balance', 5)
            ->assertJsonPath('recipient.address', 'Jl. Industri 5, Gresik');

        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(6))->assertStatus(422);
        $this->actingAs($this->user)->postJson(route('delivery-order.store'), array_merge($this->payload(1), ['recipient_name' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('recipient_name');

        $id = $this->actingAs($this->user)->postJson(route('delivery-order.store'), $this->payload(3))->assertOk()->json('id');
        $order = DeliveryOrder::with('items')->findOrFail($id);
        $this->assertSame('26110001', $order->do_number);
        $this->assertSame('Unit', $order->items->first()->unit);
        $this->assertSame(5, StockMovement::balance($this->transit->id, $this->product->id, $this->project->id), 'draft tidak mengurangi stok');

        $this->actingAs($this->user)->putJson(route('delivery-order.update', $id), $this->payload(4))->assertOk();
        $this->assertSame(4, $order->items()->first()->qty);

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
        $this->assertSame(1, StockMovement::balance($this->transit->id, $this->product->id, $this->project->id));
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovement::TYPE_DELIVERY, 'source_type' => StockMovement::SOURCE_DELIVERY_ORDER, 'source_id' => $id, 'qty' => 4]);

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
        $this->assertSame(1, StockMovement::balance($this->transit->id, $this->product->id, $this->project->id));

        $this->actingAs($this->user)->deleteJson(route('delivery-order.destroy', $second))->assertOk();
        $this->assertDatabaseMissing('delivery_orders', ['id' => $second]);
    }

    public function test_free_stock_cannot_be_delivered_directly(): void
    {
        StockMovement::record([
            'warehouse_id' => $this->transit->id,
            'master_product_id' => MasterProduct::create(['code' => 'PN-2', 'name' => 'Logger', 'division_id' => $this->division->id, 'status' => 'Active'])->id,
            'type' => StockMovement::TYPE_MISC_IN,
            'qty' => 10,
            'created_by' => $this->admin->id,
        ]);

        $payload = $this->payload(1);
        $payload['items'] = [['master_product_id' => MasterProduct::where('code', 'PN-2')->value('id'), 'qty' => 1]];

        $this->actingAs($this->user)->postJson(route('delivery-order.store'), $payload)->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\DirectPurchase;
use App\Models\Division;
use App\Models\GoodsRequest;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filter bar di daftar Permintaan Barang, Purchase Order, dan Direct Purchase.
 */
class PurchasingListFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $other;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'Admin']);
        $this->other = User::create(['username' => 'other', 'email' => 'other@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->division->id, 'role' => 'Admin']);
    }

    public function test_purchase_order_filters_and_status_counts(): void
    {
        PurchaseOrder::create(['supplier_name' => 'PT Alpha', 'po_number' => '26110001-L', 'po_type' => PurchaseOrder::TYPE_LOCAL, 'status' => PurchaseOrder::STATUS_DRAFT, 'date' => '2026-11-01', 'created_by' => $this->admin->id]);
        PurchaseOrder::create(['supplier_name' => 'PT Beta', 'po_number' => '26110002', 'po_type' => PurchaseOrder::TYPE_MANUFACTURE, 'status' => PurchaseOrder::STATUS_APPROVED, 'date' => '2026-11-10', 'created_by' => $this->other->id]);
        PurchaseOrder::create(['supplier_name' => 'PT Alpha', 'po_number' => '26110003', 'po_type' => PurchaseOrder::TYPE_MANUFACTURE, 'status' => PurchaseOrder::STATUS_APPROVED, 'date' => '2026-11-20', 'created_by' => $this->admin->id]);

        $data = fn (array $params) => $this->actingAs($this->admin)->getJson(route('purchase-order.data', $params))->assertOk();

        $data(['po_type' => 'local'])->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.po_number', '26110001-L');
        $data(['supplier' => 'PT Alpha'])->assertJsonPath('recordsFiltered', 2)->assertJsonPath('status_counts.approved', 1);
        $data(['date_from' => '2026-11-05', 'date_to' => '2026-11-15'])->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.po_number', '26110002');
        $data(['mine' => 1, 'status' => 'approved'])->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.po_number', '26110003');

        $this->actingAs($this->admin)->get(route('purchase-order.index'))->assertOk()->assertSee('Semua Supplier')->assertSee('PT Beta');

        // Kotak cari mencakup part number item.
        PurchaseOrder::where('po_number', '26110002')->first()->items()->create(['part_number' => 'SENSOR-XY', 'qty' => 1, 'sort_order' => 1]);
        $data(['search' => ['value' => 'SENSOR-XY']])->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.po_number', '26110002');
    }

    public function test_direct_purchase_filters(): void
    {
        DirectPurchase::create(['supplier_name' => 'Toko A', 'dp_number' => '26110001', 'purpose' => DirectPurchase::PURPOSE_STOCK, 'status' => DirectPurchase::STATUS_DRAFT, 'created_by' => $this->admin->id]);
        DirectPurchase::create(['supplier_name' => 'Toko B', 'dp_number' => '26110002', 'purpose' => DirectPurchase::PURPOSE_DIRECT, 'status' => DirectPurchase::STATUS_APPROVED, 'created_by' => $this->other->id]);

        $this->actingAs($this->admin)->getJson(route('direct-purchase.data', ['purpose' => 'direct']))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.dp_number', '26110002');
        $this->actingAs($this->admin)->getJson(route('direct-purchase.data', ['status' => 'draft']))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('status_counts.approved', 1);
        $this->actingAs($this->admin)->get(route('direct-purchase.index'))->assertOk()->assertSee('Semua Tujuan');
    }

    public function test_goods_request_filters(): void
    {
        GoodsRequest::create(['request_number' => '26110001', 'purpose' => GoodsRequest::PURPOSE_INTERNAL, 'division_id' => $this->division->id, 'status' => GoodsRequest::STATUS_DRAFT, 'created_by' => $this->admin->id]);
        GoodsRequest::create(['request_number' => '26110002', 'purpose' => GoodsRequest::PURPOSE_INTERNAL, 'division_id' => $this->division->id, 'status' => GoodsRequest::STATUS_APPROVED, 'created_by' => $this->other->id]);

        $this->actingAs($this->admin)->getJson(route('goods-request.data', ['mine' => 1]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.request_number', '26110001');
        $this->actingAs($this->admin)->getJson(route('goods-request.data', ['purpose' => 'project']))
            ->assertOk()->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($this->admin)->getJson(route('goods-request.data', ['division_id' => $this->division->id, 'status' => 'approved']))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('status_counts.draft', 1);
        $this->actingAs($this->admin)->getJson(route('goods-request.data', ['date_from' => now()->addDay()->toDateString()]))
            ->assertOk()->assertJsonPath('recordsFiltered', 0);
        // Filter hanya tampil bila relevan: satu divisi saja -> select Divisi disembunyikan.
        $this->actingAs($this->admin)->get(route('goods-request.index'))->assertOk()
            ->assertSee('Semua Tujuan')->assertSee('data-search', false)->assertDontSee('Semua Divisi');
    }
}

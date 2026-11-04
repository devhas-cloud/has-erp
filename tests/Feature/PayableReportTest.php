<?php

namespace Tests\Feature;

use App\Http\Controllers\PayableReportController;
use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\GoodsReceipt;
use App\Models\GoodsRequest;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\PayableReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance Tahap 5: Laporan Hutang — per supplier, umur hutang, jadwal
 * pembayaran, biaya pembelian per project, export Excel.
 */
class PayableReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $alpha;

    private Supplier $beta;

    private Project $project;

    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 18)->startOfDay());

        $division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'Admin']);
        $this->alpha = Supplier::create(['name' => 'PT Alpha', 'status' => 'Active']);
        $this->beta = Supplier::create(['name' => 'PT Beta', 'status' => 'Active']);

        $this->project = Project::create([
            'project_number' => '26100001', 'project_name' => 'SPARING Gresik',
            'company_id' => AccountCompany::create(['account_name' => 'PT Maju', 'status' => 'Active'])->id,
            'po_number_company' => 'PO-1', 'po_date' => '2026-10-01', 'po_date_received' => '2026-10-02', 'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1, 'net_sale_amount' => 1, 'status' => Project::STATUS_ACTIVE,
        ]);
        $product = MasterProduct::create(['code' => 'PN-1', 'name' => 'Sensor', 'division_id' => $division->id, 'status' => 'Active']);
        $request = GoodsRequest::create(['purpose' => GoodsRequest::PURPOSE_PROJECT, 'project_id' => $this->project->id, 'division_id' => $division->id, 'status' => GoodsRequest::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        $requestItem = $request->items()->create(['master_product_id' => $product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit', 'sort_order' => 1]);

        $this->po = PurchaseOrder::create(['supplier_id' => $this->alpha->id, 'supplier_name' => 'PT Alpha', 'po_number' => '26110001', 'status' => PurchaseOrder::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        $poItem = $this->po->items()->create(['goods_request_item_id' => $requestItem->id, 'master_product_id' => $product->id, 'part_number' => 'PN-1', 'description' => 'Sensor', 'qty' => 10, 'unit' => 'Unit', 'price' => 1000, 'currency' => 'IDR', 'sort_order' => 1]);

        $warehouse = Warehouse::create(['code' => 'T1', 'name' => 'Transit', 'type' => Warehouse::TYPE_TRANSIT, 'status' => 'Active']);
        $receipt = GoodsReceipt::create(['receipt_number' => 'GRN-1', 'purchase_order_id' => $this->po->id, 'warehouse_id' => $warehouse->id, 'received_date' => '2026-10-01', 'status' => GoodsReceipt::STATUS_POSTED, 'created_by' => $this->admin->id]);
        $receipt->items()->create(['purchase_order_item_id' => $poItem->id, 'master_product_id' => $product->id, 'qty' => 6, 'destination' => 'warehouse', 'sort_order' => 1]);

        // Tagihan Alpha: terlambat 40 hari (6.000, dibayar 2.000) & jatuh tempo 3 hari lagi.
        $overdue = $this->bill($this->alpha, 6000, '2026-10-09', 'INV-A1');
        $overdue->items()->create(['line_type' => 'item', 'purchase_order_item_id' => $poItem->id, 'project_id' => $this->project->id, 'qty' => 6, 'price' => 1000, 'amount' => 6000]);
        $this->bill($this->alpha, 1500, '2026-11-21', 'INV-A2');
        // Tagihan Beta: jatuh tempo 10 minggu lagi.
        $this->bill($this->beta, 800, '2027-01-30', 'INV-B1');

        $payment = SupplierPayment::create(['payment_number' => 'PAY-1', 'supplier_id' => $this->alpha->id, 'payment_date' => '2026-11-01', 'method' => 'transfer', 'reference_number' => 'TRF', 'currency' => 'IDR', 'amount' => 2000, 'exchange_rate' => 1, 'amount_base' => 2000, 'status' => SupplierPayment::STATUS_POSTED, 'created_by' => $this->admin->id]);
        $payment->allocations()->create(['supplier_bill_id' => $overdue->id, 'amount' => 2000]);
        $overdue->refreshPaymentStatus();

        StockMovement::record(['warehouse_id' => $warehouse->id, 'master_product_id' => $product->id, 'project_id' => $this->project->id, 'type' => StockMovement::TYPE_ALLOCATE_IN, 'qty' => 2, 'unit_cost' => 750, 'created_by' => $this->admin->id]);
    }

    private function bill(Supplier $supplier, float $total, string $due, string $invoice): SupplierBill
    {
        return SupplierBill::create([
            'bill_number' => SupplierBill::generateNumber(), 'supplier_id' => $supplier->id, 'purchase_order_id' => $this->po->id,
            'supplier_invoice_number' => $invoice, 'bill_date' => '2026-10-01', 'due_date' => $due, 'currency' => 'IDR',
            'subtotal' => $total, 'total' => $total, 'status' => SupplierBill::STATUS_VERIFIED, 'created_by' => $this->admin->id,
        ]);
    }

    public function test_supplier_summary_aging_and_schedule(): void
    {
        $report = new PayableReport;

        $summary = $report->supplierSummary()->keyBy(fn ($r) => $r['supplier']->name);
        $this->assertEquals(5500, $summary['PT Alpha']['outstanding']);
        $this->assertEquals(4000, $summary['PT Alpha']['overdue']);
        $this->assertSame(1, $summary['PT Alpha']['overdue_count']);
        $this->assertEquals(2000, $summary['PT Alpha']['paid']);
        $this->assertEquals(10000, $summary['PT Alpha']['po_value']);
        $this->assertEquals(800, $summary['PT Beta']['outstanding']);

        $aging = $report->aging()->keyBy(fn ($r) => $r['supplier']->name);
        $this->assertEquals(4000, $aging['PT Alpha']['buckets']['d31_60']);
        $this->assertEquals(1500, $aging['PT Alpha']['buckets']['current']);

        $schedule = $report->schedule()->keyBy('key');
        $this->assertEquals(4000, $schedule['0_overdue']['total']);
        $this->assertEquals(1500, $schedule['1_w0']['total']);
        $this->assertEquals(800, $schedule['9_later']['total']);
    }

    public function test_project_costs_and_pages(): void
    {
        $cost = (new PayableReport)->projectCosts($this->project->id)->first();
        $this->assertEquals(10000, $cost['commitment']);
        $this->assertEquals(6000, $cost['received']);
        $this->assertEquals(6000, $cost['billed']);
        $this->assertEquals(2000, $cost['paid']);
        $this->assertEquals(1500, $cost['stock_allocated']);
        $this->assertEquals(11500, $cost['actual']);
        $this->assertNull($cost['estimate']);

        foreach (array_keys(PayableReportController::TABS) as $tab) {
            $this->actingAs($this->admin)->get(route('payable-report.index', ['tab' => $tab]))->assertOk()->assertSee('Laporan Hutang');
        }
        $this->actingAs($this->admin)->get(route('payable-report.index', ['tab' => 'aging']))->assertSee('PT Alpha')->assertSee('4.000');
        $this->actingAs($this->admin)->get(route('payable-report.index', ['tab' => 'project']))->assertSee('SPARING Gresik')->assertSee('11.500');

        $export = $this->actingAs($this->admin)->get(route('payable-report.export', ['tab' => 'schedule']));
        $export->assertOk();
        $this->assertStringContainsString('laporan-hutang-schedule', (string) $export->headers->get('content-disposition'));

        $this->actingAs($this->admin)->get(route('project.show', $this->project->id))->assertOk()->assertSee('Biaya Pembelian');
        $this->actingAs($this->admin)->get(route('supplier-bill.index', ['supplier_id' => $this->alpha->id, 'overdue' => 1]))->assertOk();
    }
}

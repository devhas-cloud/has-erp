<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\DeliveryOrder;
use App\Models\Division;
use App\Models\Invoice;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\PackingList;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Support\ProjectTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Project Tracking: 7 tahap dihitung dari dokumen yang ada, peringatan, daftar
 * & kartu di detail project.
 */
class ProjectTrackingTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private AccountCompany $company;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 09:00:00');

        $division = Division::create(['division_name' => 'Ops', 'description' => 'Ops', 'type' => 'Internal', 'status' => 'Active']);
        $this->user = User::create(['username' => 'pm', 'email' => 'pm@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);
        $this->admin = User::create(['username' => 'admin', 'email' => 'admin@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'Admin']);
        $module = Module::create(['module_code' => 'MOD_PROJECT_TRACKING', 'module_name' => 'Project Tracking', 'route_name' => 'project-tracking', 'icon' => 'fa fa-route', 'group' => 'Admin']);
        UserAccessControl::create(['user_id' => $this->user->id, 'module_id' => $module->id, 'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => false]);

        $this->company = AccountCompany::create(['account_name' => 'PT Maju', 'status' => 'Active']);
        $this->warehouse = Warehouse::create(['code' => 'G1', 'name' => 'Gudang', 'status' => 'Active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function project(string $number, array $extra = []): Project
    {
        return Project::create(array_merge([
            'project_number' => $number, 'project_name' => 'Project '.$number, 'company_id' => $this->company->id,
            'po_number_company' => 'PO-'.$number, 'po_date' => '2026-10-01', 'po_date_received' => '2026-10-02', 'po_due_date' => '2026-12-31',
            'total_sale_amount' => 1000000, 'net_sale_amount' => 1000000, 'status' => Project::STATUS_ACTIVE,
        ], $extra));
    }

    private function postedDo(Project $project, array $lines, string $status = DeliveryOrder::STATUS_POSTED): DeliveryOrder
    {
        static $seq = 0;
        $do = DeliveryOrder::create([
            'do_number' => 'DO-2611'.str_pad((string) ++$seq, 4, '0', STR_PAD_LEFT), 'project_id' => $project->id, 'warehouse_id' => $this->warehouse->id,
            'uses_packing_list' => false, 'delivery_date' => '2026-11-10', 'recipient_name' => 'PT Maju', 'shipping_address' => 'Jakarta', 'status' => $status,
        ]);
        foreach ($lines as $i => [$itemId, $qty]) {
            $do->items()->create(['quotation_item_id' => $itemId, 'description' => 'Barang', 'qty' => $qty, 'unit' => 'Set', 'sort_order' => $i + 1]);
        }

        return $do;
    }

    public function test_new_project_without_documents(): void
    {
        $project = $this->project('26100001');
        $project->forceFill(['created_at' => '2026-10-20'])->save();

        $t = (new ProjectTracker)->forProject($project->fresh());
        $status = collect($t['stages'])->map(fn ($s) => $s['status'])->all();
        $this->assertSame([
            1 => ProjectTracker::DONE, 2 => ProjectTracker::LATE, 3 => ProjectTracker::IDLE, 4 => ProjectTracker::IDLE,
            5 => ProjectTracker::IDLE, 6 => ProjectTracker::NA, 7 => ProjectTracker::IDLE, 8 => ProjectTracker::NA,
            9 => ProjectTracker::IDLE,
        ], $status);
        $this->assertSame(2, $t['current']);
        $this->assertStringContainsString('Supplier PO approval 0/2', $t['alerts'][0][1]);

        // Project baru (< batas hari) belum dianggap tertahan.
        $fresh = $this->project('26110002');
        $this->assertSame(ProjectTracker::IDLE, (new ProjectTracker)->forProject($fresh)['stages'][2]['status']);
    }

    public function test_incoming_quality_and_installation_follow_project_tasks(): void
    {
        $project = $this->project('26110009');
        $iq = TaskCategory::create(['name' => 'Incoming Quality']);
        $install = TaskCategory::create(['name' => 'Installasi']);

        // Tanpa task Installasi -> tahap 8 tidak dipakai; tanpa task IQ -> belum mulai.
        $t = (new ProjectTracker)->forProject($project);
        $this->assertSame(ProjectTracker::IDLE, $t['stages'][5]['status']);
        $this->assertSame(ProjectTracker::NA, $t['stages'][8]['status']);

        $task = fn (TaskCategory $cat, string $status, string $due) => Task::create([
            'creator_id' => $this->user->id, 'project_id' => $project->id, 'category_id' => $cat->id,
            'title' => $cat->name.' '.$status, 'status' => $status, 'due_date' => $due,
        ]);
        $task($iq, 'done', '2026-11-10');
        $task($iq, 'in_progress', '2026-12-31');
        $late = $task($install, 'todo', '2026-11-01');

        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::RUN, $t['stages'][5]['status']);
        $this->assertSame(50, $t['stages'][5]['progress']);
        $this->assertStringContainsString('1 of 2 task(s) done', $t['stages'][5]['summary']);
        $this->assertSame(ProjectTracker::LATE, $t['stages'][8]['status'], 'task Installasi lewat due date');
        $this->assertNotNull($t['stages'][5]['start']);

        Task::where('project_id', $project->id)->update(['status' => 'done']);
        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::DONE, $t['stages'][5]['status']);
        $this->assertSame(ProjectTracker::DONE, $t['stages'][8]['status']);
        $this->assertNotNull($t['stages'][8]['end']);

        // Task batal diabaikan: tanpa task Installasi aktif -> tahap tidak dipakai lagi.
        $late->update(['status' => Task::STATUS_CANCELLED]);
        $this->assertSame(ProjectTracker::NA, (new ProjectTracker)->forProject($project->fresh())['stages'][8]['status']);
    }

    public function test_delivery_and_invoice_stages(): void
    {
        $quotation = Quotation::create(['status' => Quotation::STATUS_FINISH, 'created_by' => $this->admin->id, 'quotation_number' => 'Q-1', 'to_name' => 'PT Maju', 'subtotal' => 1000000, 'grand_total' => 1110000]);
        $package = QuotationItem::create(['quotation_id' => $quotation->id, 'part_number' => 'PKG', 'qty' => 1, 'sort_order' => 1]);
        $childA = QuotationItem::create(['quotation_id' => $quotation->id, 'parent_id' => $package->id, 'part_number' => 'A', 'qty' => 2, 'sort_order' => 2]);
        $childB = QuotationItem::create(['quotation_id' => $quotation->id, 'parent_id' => $package->id, 'part_number' => 'B', 'qty' => 1, 'sort_order' => 3]);
        $single = QuotationItem::create(['quotation_id' => $quotation->id, 'part_number' => 'S', 'qty' => 4, 'sort_order' => 4]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'part_number' => null, 'qty' => null, 'sort_order' => 5]);
        $project = $this->project('26110003', ['quotation_id' => $quotation->id, 'po_due_date' => '2026-11-10']);

        // Paket lewat anak (A lengkap, B belum) + item tunggal sebagian; draft DO tidak dihitung.
        $this->postedDo($project, [[$childA->id, 2], [$single->id, 2]]);
        $this->postedDo($project, [[$childB->id, 1]], DeliveryOrder::STATUS_DRAFT);
        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::LATE, $t['stages'][7]['status'], 'lewat jatuh tempo & belum terkirim semua');
        $this->assertSame(25, $t['stages'][7]['progress']);
        $this->assertSame(ProjectTracker::NA, $t['stages'][2]['status'], 'tanpa pembelian tapi sudah ada DO');
        $this->assertSame(ProjectTracker::NA, $t['stages'][3]['status'], 'tanpa pembelian -> pembayaran tidak dipakai');
        $this->assertSame(ProjectTracker::NA, $t['stages'][4]['status']);
        $this->assertSame(ProjectTracker::NA, $t['stages'][5]['status'], 'tanpa barang diterima -> pengecekan tidak dipakai');
        $this->assertStringContainsString('Past due date by 8 days', collect($t['alerts'])->pluck(1)->implode('|'));

        // Semua item Quotation terkirim -> selesai (aturan a).
        $this->postedDo($project, [[$childB->id, 1], [$single->id, 2]]);
        DeliveryOrder::where('status', DeliveryOrder::STATUS_DRAFT)->delete();
        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::DONE, $t['stages'][7]['status']);
        $this->assertSame(100, $t['stages'][7]['progress']);

        // Invoice: DPP 1.000.000; 30% ditagih & lunas, 70% belum lunas lewat jatuh tempo.
        $invoice = fn (string $no, float $amount, string $status, string $due) => Invoice::create([
            'project_id' => $project->id, 'invoice_number' => $no, 'invoice_date' => '2026-10-15', 'due_date' => $due,
            'subtotal' => $amount, 'discount_amount' => 0, 'percent_payment' => 0, 'amount_payment' => $amount, 'ppn' => 11,
            'total_payment' => $amount * 1.11, 'status' => $status, 'approval_status' => Invoice::APPROVAL_APPROVED,
        ]);
        $invoice('INV-1', 300000, Invoice::STATUS_PAID, '2026-10-30');
        $invoice('INV-2', 700000, Invoice::STATUS_UNPAID, '2026-11-01');
        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::LATE, $t['stages'][9]['status']);
        $this->assertSame(100, $t['finance']['billed_pct']);
        $this->assertSame(30, $t['finance']['paid_pct']);
        $this->assertSame(9, $t['current']);
        $this->assertSame('2026-10-15', $t['stages'][9]['start']->toDateString(), 'tanggal tahap invoice = invoice pertama');

        Invoice::where('invoice_number', 'INV-2')->update(['status' => Invoice::STATUS_PAID]);
        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::DONE, $t['stages'][9]['status']);
    }

    public function test_stock_rule_packing_list_and_pages(): void
    {
        $project = $this->project('26110004');
        $product = MasterProduct::create(['code' => 'P-1', 'name' => 'Panel', 'status' => 'Active', 'is_material' => false]);
        $unit = $this->seedStock($this->warehouse->id, $product, 1, ['project_id' => $project->id])->first();
        $unit->forceFill(['created_at' => '2026-10-01'])->save();

        // Stok project lama tanpa PKL / DO + unit tanpa SN -> peringatan.
        $alerts = collect((new ProjectTracker)->forProject($project)['alerts'])->pluck(1)->implode('|');
        $this->assertStringContainsString('without SN', $alerts);
        $this->assertStringContainsString('ready but not delivered', $alerts);

        // PKL Siap Kirim -> tahap 7 (Delivery) berjalan.
        PackingList::create(['packing_number' => 'PKL-26110001', 'project_id' => $project->id, 'warehouse_id' => $this->warehouse->id, 'packing_date' => '2026-11-18', 'status' => PackingList::STATUS_READY, 'recipient_name' => 'PT Maju']);
        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::RUN, $t['stages'][7]['status']);
        $this->assertStringNotContainsString('ready but not delivered', collect($t['alerts'])->pluck(1)->implode('|'));

        // Aturan b: DO terposting, stok habis, PKL terkirim -> tahap 7 selesai (tanpa Quotation).
        $this->postedDo($project, [[null, 1]]);
        PackingList::query()->update(['status' => PackingList::STATUS_SHIPPED]);
        app(\App\Services\StockService::class)->updateDetails($unit->fresh(), ['serial_number' => 'SN-1'], ['note' => 'sn']);
        app(\App\Services\StockService::class)->take($unit->fresh(), 1, \App\Models\StockLog::TYPE_OUT, ['note' => 'kirim']);
        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::DONE, $t['stages'][7]['status']);
        $this->assertNull($t['stages'][7]['progress']);

        // Halaman: daftar (default Active), filter, dan kartu di detail project.
        $this->project('26110005', ['status' => Project::STATUS_COMPLETED]);
        $this->actingAs($this->user)->get(route('project-tracking.index'))->assertOk()->assertSee('Active projects');
        $rows = $this->actingAs($this->user)->getJson(route('project-tracking.data'))->assertOk()->json('data');
        $this->assertSame(['26110004'], array_column($rows, 'project_number'));
        $this->assertCount(2, $this->actingAs($this->user)->getJson(route('project-tracking.data', ['status' => '']))->json('data'));
        $this->assertSame([], $this->actingAs($this->user)->getJson(route('project-tracking.data', ['stage' => 6]))->json('data'), 'tidak ada project di tahap Production');
        $this->assertSame(route('project-tracking.show', $project->id), $rows[0]['url']);
        $this->actingAs($this->user)->get(route('project-tracking.show', $project->id))->assertOk()
            ->assertSee('Stage Timeline')->assertSee('Project Journey')->assertSee('DO-2611')->assertSee('PKL-26110001')
            ->assertSee('Project Summary')->assertSee('PO-26110004')->assertSee('No billing terms yet.')->assertSee(route('project.show', $project->id), false);
        // Detail project tidak lagi memuat tracking.
        $this->actingAs($this->admin)->get(route('project.show', $project->id))->assertOk()->assertDontSee('Linimasa Tahap');
    }

    public function test_purchase_and_receipt_stages_from_direct_purchase(): void
    {
        $project = $this->project('26110006');
        foreach ([$this->user, $this->admin] as $approver) {
            \App\Models\ProjectPoSupplierApproval::create(['project_id' => $project->id, 'user_id' => $approver->id, 'approved_at' => '2026-10-02']);
        }
        $product = MasterProduct::create(['code' => 'C-1', 'name' => 'Kabel', 'status' => 'Active', 'is_material' => true]);
        $dp = \App\Models\DirectPurchase::create([
            'supplier_name' => 'PT Kabel', 'purpose' => \App\Models\DirectPurchase::PURPOSE_PROJECT, 'project_id' => $project->id,
            'status' => \App\Models\DirectPurchase::STATUS_APPROVED, 'date' => '2026-10-20', 'approved_at' => '2026-10-20 10:00:00',
        ]);
        $dp->items()->create(['master_product_id' => $product->id, 'qty' => 10, 'unit' => 'Meter', 'price' => 1000, 'sort_order' => 1]);

        $t = (new ProjectTracker)->forProject($project->fresh());
        $this->assertSame(ProjectTracker::DONE, $t['stages'][2]['status']);
        $this->assertSame(ProjectTracker::LATE, $t['stages'][4]['status'], 'DP disetujui > 14 hari, barang belum datang');
        $this->assertSame(0, $t['stages'][4]['progress']);
        $this->assertSame(3, $t['current']);
        $this->assertStringContainsString('not arrived', collect($t['alerts'])->pluck(1)->implode('|'));
        $this->assertSame([route('direct-purchase.show', $dp->id)], collect($t['stages'][2]['docs'])->pluck(1)->all());
    }
}

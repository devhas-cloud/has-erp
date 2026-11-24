<?php

namespace Tests\Feature;

use App\Http\Controllers\ReceivableReportController;
use App\Models\AccountCompany;
use App\Models\AppSetting;
use App\Models\DeliveryOrder;
use App\Models\Division;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Models\WhatsAppGroup;
use App\Services\ReceivableDueNotifier;
use App\Support\ReceivableReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Piutang Tahap C: Laporan Piutang & pengingat WhatsApp. Umur invoice dari
 * jatuh tempo invoice; termin belum ditagih diukur terhadap target / PO due date.
 */
class ReceivableReportTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $admin;

    private Project $alpha;

    private Project $beta;

    private Invoice $dp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(8, 10));

        config(['services.wabaileys.url' => 'https://wa.test', 'services.wabaileys.api_key' => 'key', 'services.wabaileys.sender_phone' => '628111']);
        Http::fake(['wa.test/*' => Http::response(['id' => 'MSG1'], 200)]);

        $division = Division::create(['division_name' => 'Finance', 'description' => 'Finance', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'Admin']);
        $this->viewer = User::create(['username' => 'viewer', 'email' => 'viewer@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);
        $collector = User::create(['username' => 'kasir', 'email' => 'kasir@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User', 'phone_number' => '08123456789']);

        $report = Module::create(['module_code' => 'MOD_RECEIVABLE_REPORT', 'module_name' => 'Laporan Piutang', 'route_name' => 'receivable-report', 'icon' => 'fa fa-chart-pie', 'group' => 'Finance']);
        $receipt = Module::create(['module_code' => 'MOD_CUSTOMER_RECEIPT', 'module_name' => 'Penerimaan Customer', 'route_name' => 'customer-receipt', 'icon' => 'fa fa-hand-holding-dollar', 'group' => 'Finance']);
        $none = ['can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_approve' => false];
        UserAccessControl::create(['user_id' => $this->viewer->id, 'module_id' => $report->id, 'can_read' => true] + $none);
        UserAccessControl::create(['user_id' => $collector->id, 'module_id' => $receipt->id, 'can_read' => true] + array_merge($none, ['can_create' => true]));

        WhatsAppGroup::create(['division_id' => $division->id, 'group_name' => 'Finance HAS', 'group_id' => '1203-99@g.us', 'status' => 'Active']);

        // Alpha: nilai kontrak 1.000.000; DP 30% piutang lewat jatuh tempo, T1 50% siap ditagih, T2 20% terkunci.
        $this->alpha = $this->project('26110001', 'PT Alpha', 1000000, '2026-12-08');
        $dp = $this->alpha->billingTerms()->create(['sort_order' => 1, 'name' => 'DP', 'percent' => 30, 'unlocked_at' => now()]);
        $this->alpha->billingTerms()->create(['sort_order' => 2, 'name' => 'Termin 1', 'percent' => 50, 'target_date' => '2026-11-21', 'unlocked_at' => now()]);
        $this->alpha->billingTerms()->create(['sort_order' => 3, 'name' => 'Termin 2', 'percent' => 20]);
        $this->dp = $this->invoice($this->alpha, ['billing_term_id' => $dp->id, 'invoice_number' => 'INV-DP', 'due_date' => '2026-11-08', 'percent_payment' => 30, 'amount_payment' => 300000, 'total_payment' => 333000]);

        // Beta: PO due date sudah lewat, satu termin 100% belum ditagih + tagihan di luar timeline yang sudah lunas.
        $this->beta = $this->project('26110002', 'PT Beta', 2000000, '2026-11-13');
        $this->beta->billingTerms()->create(['sort_order' => 1, 'name' => 'Pembayaran Penuh', 'percent' => 100]);
        $this->invoice($this->beta, ['invoice_number' => 'INV-EXTRA', 'non_term_reason' => 'Pekerjaan tambahan', 'status' => Invoice::STATUS_PAID, 'due_date' => '2026-11-01', 'percent_payment' => 100, 'amount_payment' => 100000, 'total_payment' => 111000]);
    }

    private function project(string $number, string $company, float $value, string $poDue): Project
    {
        return Project::create([
            'project_number' => $number,
            'project_name' => 'Project '.$company,
            'company_id' => AccountCompany::create(['account_name' => $company, 'status' => 'Active'])->id,
            'po_number_company' => 'PO-'.$number,
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => $poDue,
            'total_sale_amount' => $value,
            'net_sale_amount' => $value,
            'type_of_achievement' => Project::TYPE_PROJECT,
            'status' => Project::STATUS_ACTIVE,
        ]);
    }

    private function invoice(Project $project, array $attributes): Invoice
    {
        return Invoice::create(array_merge([
            'project_id' => $project->id,
            'invoice_date' => '2026-10-25',
            'terms' => 14,
            'subtotal' => $project->total_sale_amount,
            'ppn' => 11,
            'status' => Invoice::STATUS_UNPAID,
            'approval_status' => Invoice::APPROVAL_APPROVED,
            'approved_at' => now(),
        ], $attributes));
    }

    public function test_customer_summary(): void
    {
        $report = new ReceivableReport;

        $rows = $report->customerSummary()->keyBy(fn ($r) => $r['company']->account_name);
        $this->assertSame(333000.0, $rows['PT Alpha']['outstanding']);
        $this->assertSame(333000.0, $rows['PT Alpha']['overdue']);
        $this->assertSame(700000.0, $rows['PT Alpha']['unbilled']);
        $this->assertSame(111000.0, $rows['PT Beta']['billed']);
        $this->assertSame(111000.0, $rows['PT Beta']['received']);
        $this->assertSame(2000000.0, $rows['PT Beta']['unbilled']);
        $this->assertSame(1, $rows['PT Alpha']['project_count']);
        $this->assertSame(1000000.0, $rows['PT Alpha']['contract']);
        $this->assertSame(0.0, $rows['PT Alpha']['paid']);
        $this->assertSame(1000000.0, $rows['PT Alpha']['remaining']);
        $this->assertSame(100000.0, $rows['PT Beta']['paid']);
        $this->assertSame(1900000.0, $rows['PT Beta']['remaining']);
    }

    public function test_filters_and_drill_down_links(): void
    {
        // Tab customer & project dimuat lewat ajax (server-side DataTables).
        $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => 'customer', 'overdue' => 1]))
            ->assertOk()->assertSee('PT Alpha')->assertDontSee('PT Beta');

        // Filter status baru: masih memiliki piutang / sudah lunas.
        $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => 'customer', 'status' => 'receivable']))
            ->assertOk()->assertJsonPath('recordsTotal', 2);
        $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => 'customer', 'status' => 'paid']))
            ->assertOk()->assertJsonPath('recordsTotal', 0);
        $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => 'project', 'status' => 'receivable']))
            ->assertOk()->assertJsonPath('recordsTotal', 2);
        $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => 'project', 'status' => 'paid']))
            ->assertOk()->assertJsonPath('recordsTotal', 0);

        $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => 'project', 'late_billing' => 1]))
            ->assertOk()->assertSee('26110002')->assertDontSee('26110001');

        // Drill-down link ikut terender di sel customer (& jadi &amp; setelah escape Blade).
        $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => 'customer']))
            ->assertOk()
            ->assertSee('company_id='.$this->alpha->company_id.'&amp;overdue=1', false);

        // Tab forecast: HTML kelompok dimuat lewat ajax.
        $this->actingAs($this->viewer)->get(route('receivable-report.forecast', ['kind' => 'invoice']))
            ->assertOk()->assertSee('INV-DP')->assertDontSee('Billed late (past target / PO due date)');

        $this->actingAs($this->viewer)->get(route('receivable-report.forecast', ['group_by' => 'customer']))
            ->assertOk()->assertSee('PT Alpha');

        // Halaman utama merender toolbar + header kolom.
        $this->actingAs($this->viewer)->get(route('receivable-report.index', ['tab' => 'customer']))
            ->assertOk()->assertSee('Contract Value')->assertSee('Outstanding')->assertSee('Projects');
        $this->actingAs($this->viewer)->get(route('receivable-report.index', ['tab' => 'project']))
            ->assertOk()->assertSee('Contract Value')->assertSee('Paid')
            ->assertSee('PO Due Date')->assertSee('PO Received')->assertSee('Delivery Date');

        // Footer total harus sejajar dengan header: jumlah sel = jumlah kolom tabel.
        foreach (['customer' => 6, 'project' => 10] as $tab => $columns) {
            $footer = $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => $tab]))->assertOk()->json('footer');
            $this->assertCount($columns, $footer, "Footer tab {$tab} harus {$columns} sel");
        }
    }

    public function test_project_summary_splits_contract_by_term_position(): void
    {
        // Tanggal kirim = DO posted paling awal; DO draft diabaikan.
        $warehouse = Warehouse::create(['code' => 'WH-RR', 'name' => 'Gudang RR', 'status' => 'Active']);
        foreach ([['DO-RR-1', '2026-11-05', DeliveryOrder::STATUS_POSTED], ['DO-RR-2', '2026-11-01', DeliveryOrder::STATUS_POSTED], ['DO-RR-3', '2026-10-01', DeliveryOrder::STATUS_DRAFT]] as [$number, $date, $status]) {
            DeliveryOrder::create([
                'do_number' => $number, 'project_id' => $this->alpha->id, 'warehouse_id' => $warehouse->id,
                'delivery_date' => $date, 'recipient_name' => 'Customer', 'shipping_address' => 'Alamat',
                'status' => $status, 'created_by' => $this->admin->id,
            ]);
        }

        // Invoice non-approved di luar termin tidak boleh masuk outside_timeline.
        $this->invoice($this->beta, [
            'invoice_number' => 'INV-DRAFT-EXTRA',
            'non_term_reason' => 'Draft tambahan',
            'approval_status' => Invoice::APPROVAL_DRAFT,
            'approved_at' => null,
            'due_date' => '2026-11-20',
            'percent_payment' => 100,
            'amount_payment' => 50000,
            'total_payment' => 55500,
        ]);

        $rows = (new ReceivableReport)->projectSummary()->keyBy(fn ($r) => $r['project']->project_number);

        $alpha = $rows['26110001'];
        $this->assertSame(1000000.0, $alpha['contract']);
        $this->assertSame(['unscheduled' => 0.0, 'locked' => 200000.0, 'ready' => 500000.0, 'in_process' => 0.0, 'receivable' => 300000.0, 'paid' => 0.0], $alpha['positions']);
        $this->assertSame(0, $alpha['late_billing']);
        $this->assertSame(20, $alpha['due_days']);
        $this->assertSame('2026-11-01', $alpha['delivery_date']?->format('Y-m-d'));

        // PO due date lewat & termin belum ditagih -> terlambat ditagih.
        $beta = $rows['26110002'];
        $this->assertSame(2000000.0, $beta['positions']['locked']);
        $this->assertSame(1, $beta['late_billing']);
        $this->assertSame(-5, $beta['due_days']);
        $this->assertSame(100000.0, $beta['outside_timeline']);
        // Project dengan termin terlambat ditagih tampil paling atas.
        $this->assertSame('26110002', (new ReceivableReport)->projectSummary()->first()['project']->project_number);
    }

    public function test_forecast_groups_certain_invoices_and_term_estimates(): void
    {
        $groups = (new ReceivableReport)->forecast()->keyBy('key');

        $this->assertSame(333000.0, $groups['0_overdue']['certain']);
        $this->assertSame(2000000.0, $groups['0_late_billing']['estimate']);
        // Termin 1: target 21/11 -> minggu ini (16–22/11).
        $this->assertSame(500000.0, $groups['1_w0']['estimate']);
        // Termin 2 tanpa target -> PO due date 08/12 (minggu +3).
        $this->assertSame(200000.0, $groups['1_w3']['estimate']);
        $this->assertSame('Termin 2', $groups['1_w3']['rows']->first()['term']->name);
    }

    public function test_pages_and_exports_render_for_reader(): void
    {
        foreach (array_keys(ReceivableReportController::TABS) as $tab) {
            $this->actingAs($this->viewer)->get(route('receivable-report.index', ['tab' => $tab]))
                ->assertOk()->assertSee('Receivables Report');

            $this->actingAs($this->viewer)->get(route('receivable-report.export', ['tab' => $tab]))
                ->assertOk()->assertDownload('laporan-piutang-'.$tab.'-2026-11-18.xlsx');
        }

        // Data per tab dimuat lewat ajax.
        $this->actingAs($this->viewer)->getJson(route('receivable-report.data', ['tab' => 'project']))
            ->assertOk()->assertSee('26110002')->assertSee('1 term(s) billed late');
        $this->actingAs($this->viewer)->get(route('receivable-report.forecast'))
            ->assertOk()->assertSee('Billed late (past target / PO due date)')->assertSee('INV-DP');

        // Laporan murni laporan: tanpa form pengaturan, tanpa tautan Atur bagi yang tak punya akses Notifikasi Finance.
        $this->actingAs($this->viewer)->get(route('receivable-report.index'))
            ->assertSee('Receivable reminder (WhatsApp)')->assertDontSee('Simpan Pengaturan')->assertDontSee(route('finance-notification.index'), false);
    }

    public function test_reminder_message_and_scheduled_send(): void
    {
        $message = app(ReceivableDueNotifier::class)->buildMessage(3);
        $this->assertStringContainsString('Invoice lewat jatuh tempo', $message);
        $this->assertStringContainsString('PT Alpha · INV-DP · Rp 333,000 (lewat 10 hari)', $message);
        $this->assertStringContainsString('Terlambat ditagih', $message);
        $this->assertStringContainsString('26110002 · Pembayaran Penuh 100%', $message);

        // Nonaktif secara default: jadwal tidak mengirim.
        $this->assertSame('disabled', app(ReceivableDueNotifier::class)->send()['status']);
        Http::assertNothingSent();

        $group = WhatsAppGroup::first();
        $this->actingAs($this->admin)->patchJson(route('finance-notification.update', 'receivable'), [
            'enabled' => 1, 'days_ahead' => 3, 'send_time' => '08:10', 'whatsapp_group_id' => $group->id, 'notify_receipt_users' => 1,
        ])->assertOk();

        $this->assertSame('sent', app(ReceivableDueNotifier::class)->send()['status']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group') && $request['groupJid'] === '1203-99@g.us');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send') && $request['to'] === '628123456789');
        $this->assertSame('2026-11-18', AppSetting::get(ReceivableDueNotifier::SETTING_KEY)['last_sent_date']);

        // Tidak dobel di hari yang sama.
        $this->assertSame('already_sent', app(ReceivableDueNotifier::class)->send()['status']);
    }

    public function test_nothing_to_remind_when_all_settled(): void
    {
        $this->dp->update(['status' => Invoice::STATUS_PAID]);
        $this->alpha->billingTerms()->delete();
        $this->beta->billingTerms()->delete();

        $this->assertNull(app(ReceivableDueNotifier::class)->buildMessage(3));
        $this->actingAs($this->admin)->postJson(route('finance-notification.send', 'receivable'))
            ->assertOk()->assertJsonPath('success', false);
    }
}

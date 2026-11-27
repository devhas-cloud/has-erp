<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\Division;
use App\Models\ItemLoan;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\Warehouse;
use App\Services\ItemLoanDueNotifier;
use App\Services\StockException;
use App\Services\StockService;
use App\Services\WhatsAppService;
use App\Support\StockPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithStock;
use Tests\TestCase;

/**
 * Item Loan: dicatat petugas inventory, unit ber-SN ditahan (stok tetap),
 * pengembalian per unit (good / damaged / lost), perpanjangan, batal, pengingat WA.
 */
class ItemLoanTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private User $staff;

    private User $borrower;

    private Warehouse $warehouse;

    private MasterProduct $logger;

    private MasterProduct $cable;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 09:00:00');

        $division = Division::create(['division_name' => 'Ops', 'description' => 'Ops', 'type' => 'Internal', 'status' => 'Active']);
        $branch = Branch::create(['code' => 'JKT', 'name' => 'HQ Jakarta', 'status' => 'Active']);
        $this->warehouse = Warehouse::create(['branch_id' => $branch->id, 'code' => 'J1', 'name' => 'Gudang Jakarta', 'status' => 'Active']);
        $this->staff = User::create(['username' => 'gudang', 'email' => 'g@has.com', 'password' => bcrypt('x'), 'division_id' => $division->id, 'role' => 'User']);
        $this->borrower = User::create(['username' => 'teknisi', 'full_name' => 'Budi Teknisi', 'email' => 't@has.com', 'password' => bcrypt('x'), 'division_id' => $division->id, 'role' => 'User', 'phone_number' => '081234567890']);

        $module = Module::create(['module_code' => 'MOD_ITEM_LOAN', 'module_name' => 'Item Loan', 'route_name' => 'item-loan', 'icon' => 'fa fa-box', 'group' => 'Inventory']);
        UserAccessControl::create(['user_id' => $this->staff->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);

        $this->logger = MasterProduct::create(['code' => 'LOG-1', 'name' => 'Data Logger', 'division_id' => $division->id, 'status' => 'Active']);
        $this->cable = MasterProduct::create(['code' => 'CBL-1', 'name' => 'Kabel', 'division_id' => $division->id, 'status' => 'Active', 'is_material' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function units(): array
    {
        return $this->seedStock($this->warehouse->id, $this->logger, 2, ['unit_cost' => 1000000], ['SN-A', 'SN-B'])->all();
    }

    private function lend(array $stockIds, array $extra = [])
    {
        return $this->actingAs($this->staff)->postJson(route('item-loan.store'), array_merge([
            'borrower_type' => 'internal',
            'borrower_user_id' => $this->borrower->id,
            'warehouse_id' => $this->warehouse->id,
            'purpose' => 'Demo di customer',
            'loan_date' => '2026-11-27',
            'due_date' => '2026-12-04',
            'items' => array_map(fn ($id) => ['stock_id' => $id, 'condition_out' => 'Baik'], $stockIds),
        ], $extra));
    }

    public function test_lending_holds_units_without_reducing_stock(): void
    {
        [$a, $b] = $this->units();

        $id = $this->lend([$a->id])->assertOk()->json('id');
        $loan = ItemLoan::findOrFail($id);
        $this->assertSame(ItemLoan::STATUS_ON_LOAN, $loan->status);
        $this->assertStringStartsWith('LN-', $loan->loan_number);

        // Stok & nilai tetap; unit ditahan.
        $this->assertEquals(2, $this->stockBalance($this->warehouse->id, $this->logger->id));
        $this->assertEquals(0, StockService::available($a->fresh()));
        $this->assertSame(1, StockLog::where('type', StockLog::TYPE_LOAN_OUT)->where('stock_id', $a->id)->where('qty', 0)->count());

        // Tidak muncul di pemilih stok dokumen lain; tidak bisa diambil / dipindah / dipinjam lagi.
        $picker = StockPicker::options(['warehouse_id' => $this->warehouse->id])->pluck('id')->all();
        $this->assertSame([$b->id], $picker);
        try {
            app(StockService::class)->take($a->fresh(), 1, StockLog::TYPE_OUT, []);
            $this->fail('Unit dipinjam tidak boleh diambil.');
        } catch (StockException $e) {
            $this->assertStringContainsString('sedang dipinjam', $e->getMessage());
        }
        $this->lend([$a->id])->assertStatus(422);

        $this->actingAs($this->staff)->get(route('item-loan.index'))->assertOk();
        $this->actingAs($this->staff)->get(route('item-loan.create'))->assertOk();
        $this->actingAs($this->staff)->get(route('item-loan.show', $id))->assertOk()->assertSee('SN-A')->assertSee('Budi Teknisi');
        $this->actingAs($this->staff)->getJson(route('item-loan.data', ['draw' => 1]))->assertOk()->assertJsonPath('recordsTotal', 1);
        // Laporan & detail stok menandai unit yang sedang dipinjam.
        $admin = User::create(['username' => 'admin', 'email' => 'adm@has.com', 'password' => bcrypt('x'), 'role' => 'Admin']);
        $this->actingAs($admin)->getJson(route('stock-report.data', ['draw' => 1]))->assertOk()->assertJsonPath('data.0.on_loan', 1);
        $this->actingAs($admin)->get(route('stock-detail.show', $a->id))->assertOk()->assertSee('Sedang dipinjam')->assertSee($loan->loan_number);
    }

    public function test_only_serial_units_with_sn_can_be_lent(): void
    {
        $material = $this->seedStock($this->warehouse->id, $this->cable, 50)->first();
        $this->lend([$material->id])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'serial-numbered'));

        $noSn = $this->seedStock($this->warehouse->id, $this->logger, 1)->first();
        $this->lend([$noSn->id])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'no serial number'));

        // Stock options only list serial units.
        $rows = $this->actingAs($this->staff)->getJson(route('item-loan.stock-options', ['warehouse_id' => $this->warehouse->id]))->assertOk()->json('data');
        $this->assertSame([$noSn->id], array_column($rows, 'id'));
    }

    public function test_external_borrower_and_partial_returns_good_damaged_lost(): void
    {
        Storage::fake('local');
        [$a, $b] = $this->units();
        $c = $this->seedStock($this->warehouse->id, $this->logger, 1, [], ['SN-C'])->first();

        $id = $this->lend([$a->id, $b->id, $c->id], [
            'borrower_type' => 'external', 'borrower_user_id' => null,
            'borrower_name' => 'Pak Andi', 'borrower_company' => 'PT Customer', 'borrower_phone' => '08111',
        ])->assertOk()->json('id');
        $loan = ItemLoan::with('items')->findOrFail($id);
        $this->assertSame('Pak Andi', $loan->borrowerLabel());
        [$ia, $ib, $ic] = $loan->items->all();

        // Foto kondisi per unit.
        $this->actingAs($this->staff)->post(route('item-loan.photos.store', [$id, $ia->id]), ['files' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(1, 'photos');

        // Rusak / hilang wajib catatan.
        $this->actingAs($this->staff)->postJson(route('item-loan.return', $id), ['returned_at' => '2026-11-28', 'items' => [['id' => $ib->id, 'status' => 'damaged']]])->assertStatus(422);

        // Pengembalian pertama: A baik, B rusak -> masih On Loan (C belum).
        $this->actingAs($this->staff)->postJson(route('item-loan.return', $id), ['returned_at' => '2026-11-28', 'items' => [
            ['id' => $ia->id, 'status' => 'good'],
            ['id' => $ib->id, 'status' => 'damaged', 'note' => 'Layar retak'],
        ]])->assertOk();
        $this->assertSame(ItemLoan::STATUS_ON_LOAN, $loan->fresh()->status);
        $this->assertEquals(1, StockService::available($a->fresh()));
        $this->assertEquals(1, StockService::available($b->fresh()));

        // C hilang -> stok berkurang, loan selesai.
        $this->actingAs($this->staff)->postJson(route('item-loan.return', $id), ['returned_at' => '2026-11-29', 'items' => [
            ['id' => $ic->id, 'status' => 'lost', 'note' => 'Tertinggal di lokasi'],
        ]])->assertOk();
        $this->assertSame(ItemLoan::STATUS_RETURNED, $loan->fresh()->status);
        $this->assertEquals(0, (float) $c->fresh()->qty);
        $this->assertEquals(2, $this->stockBalance($this->warehouse->id, $this->logger->id));
        $this->assertSame(1, StockLog::where('type', StockLog::TYPE_LOAN_LOST)->where('qty', -1)->count());

        // Sudah dikembalikan tidak bisa dicatat ulang.
        $this->actingAs($this->staff)->postJson(route('item-loan.return', $id), ['returned_at' => '2026-11-29', 'items' => [['id' => $ia->id, 'status' => 'good']]])->assertStatus(422);
        $this->actingAs($this->staff)->get(route('item-loan.show', $id))->assertOk()->assertSee('Layar retak');
        $pdf = $this->actingAs($this->staff)->get(route('item-loan.pdf', $id))->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringContainsString('inline', $pdf->headers->get('content-disposition'), 'PDF dibuka di browser, bukan diunduh.');
    }

    public function test_extend_and_cancel(): void
    {
        [$a] = $this->units();
        $id = $this->lend([$a->id])->assertOk()->json('id');

        $this->actingAs($this->staff)->postJson(route('item-loan.extend', $id), ['new_due_date' => '2026-12-01', 'reason' => 'x'])->assertStatus(422);
        $this->actingAs($this->staff)->postJson(route('item-loan.extend', $id), ['new_due_date' => '2026-12-11', 'reason' => 'Demo diperpanjang'])->assertOk();
        $loan = ItemLoan::with('extensions')->findOrFail($id);
        $this->assertSame('2026-12-11', $loan->due_date->toDateString());
        $this->assertSame('2026-12-04', $loan->extensions->first()->old_due_date->toDateString());

        $this->actingAs($this->staff)->postJson(route('item-loan.cancel', $id), ['reason' => 'Salah input'])->assertOk();
        $this->assertSame(ItemLoan::STATUS_CANCELLED, $loan->fresh()->status);
        $this->assertEquals(1, StockService::available($a->fresh()), 'Unit kembali tersedia setelah dibatalkan.');
    }

    public function test_overdue_and_whatsapp_reminder(): void
    {
        [$a, $b] = $this->units();
        $id = $this->lend([$a->id], ['loan_date' => '2026-11-20', 'due_date' => '2026-11-25'])->assertOk()->json('id');
        $this->lend([$b->id], ['borrower_type' => 'external', 'borrower_user_id' => null, 'borrower_name' => 'Tamu', 'borrower_phone' => null, 'due_date' => '2026-12-20'])->assertOk();

        $this->assertTrue(ItemLoan::findOrFail($id)->isOverdue());
        $this->actingAs($this->staff)->getJson(route('item-loan.data', ['draw' => 1, 'status' => 'overdue']))
            ->assertOk()->assertJsonPath('overdue_total', 1)->assertJsonPath('recordsFiltered', 1);

        $wa = $this->createMock(WhatsAppService::class);
        $wa->method('isConfigured')->willReturn(true);
        $wa->expects($this->once())->method('sendText')->with('081234567890', $this->stringContains('lewat 2 hari'))->willReturn(true);
        $this->app->instance(WhatsAppService::class, $wa);

        AppSetting::put(ItemLoanDueNotifier::SETTING_KEY, array_merge(ItemLoanDueNotifier::DEFAULTS, ['enabled' => true, 'send_time' => '08:00']));
        $result = app(ItemLoanDueNotifier::class)->send();
        $this->assertSame('sent', $result['status']);
        $this->assertStringContainsString('Lewat jatuh tempo', $result['message']);
        $this->assertSame('2026-11-27', ItemLoan::findOrFail($id)->last_reminded_on->toDateString());

        // Hari yang sama tidak dikirim ulang.
        $this->assertSame('already_sent', app(ItemLoanDueNotifier::class)->send()['status']);
    }
}

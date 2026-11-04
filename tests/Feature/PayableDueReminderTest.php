<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Module;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\WhatsAppGroup;
use App\Services\PayableDueNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Finance Tahap 6: pengingat WhatsApp jatuh tempo hutang supplier — ringkasan
 * harian, tujuan grup / user Pembayaran Supplier, tidak dobel per hari.
 */
class PayableDueReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private WhatsAppGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(8, 5));

        config(['services.wabaileys.url' => 'https://wa.test', 'services.wabaileys.api_key' => 'key', 'services.wabaileys.sender_phone' => '628111']);
        Http::fake(['wa.test/*' => Http::response(['id' => 'MSG1'], 200)]);

        $division = Division::create(['division_name' => 'Finance', 'description' => 'Finance', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'Admin']);
        $cashier = User::create(['username' => 'kasir', 'email' => 'kasir@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User', 'phone_number' => '08123456789']);
        $module = Module::create(['module_code' => 'MOD_SUPPLIER_PAYMENT', 'module_name' => 'Pembayaran Supplier', 'route_name' => 'supplier-payment', 'icon' => 'fa fa-money-bill', 'group' => 'Finance']);
        UserAccessControl::create(['user_id' => $cashier->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => false]);

        $this->group = WhatsAppGroup::create(['division_id' => $division->id, 'group_name' => 'Finance HAS', 'group_id' => '1203-99@g.us', 'status' => 'Active']);

        $supplier = Supplier::create(['name' => 'PT Alpha', 'status' => 'Active']);
        foreach ([['2026-11-10', 4000, 'INV-LATE'], ['2026-11-18', 1500, 'INV-TODAY'], ['2026-11-20', 900, 'INV-SOON'], ['2026-12-31', 700, 'INV-LATER']] as [$due, $total, $invoice]) {
            SupplierBill::create([
                'bill_number' => SupplierBill::generateNumber(), 'supplier_id' => $supplier->id, 'supplier_invoice_number' => $invoice,
                'bill_date' => '2026-10-01', 'due_date' => $due, 'currency' => 'IDR', 'subtotal' => $total, 'total' => $total,
                'status' => SupplierBill::STATUS_VERIFIED, 'created_by' => $this->admin->id,
            ]);
        }
    }

    public function test_message_groups_bills_and_send_once_per_day(): void
    {
        $notifier = app(PayableDueNotifier::class);
        $message = $notifier->buildMessage(3);
        $this->assertStringContainsString('Terlambat* — 1 tagihan, IDR 4,000', $message);
        $this->assertStringContainsString('lewat 8 hari', $message);
        $this->assertStringContainsString('Jatuh tempo hari ini* — 1 tagihan', $message);
        $this->assertStringContainsString('3 hari ke depan* — 1 tagihan, IDR 900', $message);
        $this->assertStringNotContainsString('INV-LATER', $message);

        // Nonaktif -> jadwal tidak mengirim.
        $this->assertSame('disabled', $notifier->send()['status']);
        Http::assertNothingSent();

        $this->actingAs($this->admin)->patchJson(route('finance-notification.update', 'payable'), ['enabled' => 1, 'days_ahead' => 3, 'send_time' => '08:05', 'whatsapp_group_id' => null, 'notify_payment_users' => 0])
            ->assertStatus(422);
        $this->actingAs($this->admin)->patchJson(route('finance-notification.update', 'payable'), ['enabled' => 1, 'days_ahead' => 3, 'send_time' => '08:05', 'whatsapp_group_id' => $this->group->id, 'notify_payment_users' => 1])
            ->assertOk()->assertJsonPath('success', true);

        $result = $notifier->send();
        $this->assertSame('sent', $result['status']);
        $this->assertSame(2, $result['sent']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group') && $request['groupJid'] === '1203-99@g.us');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send') && $request['to'] === '628123456789');
        $this->assertSame('2026-11-18', PayableDueNotifier::settings()['last_sent_date']);

        // Jadwal kedua di hari yang sama tidak mengirim ulang; tombol Kirim Sekarang tetap bisa.
        $this->assertSame('already_sent', $notifier->send()['status']);
        $this->actingAs($this->admin)->postJson(route('finance-notification.send', 'payable'))->assertOk()->assertJsonPath('success', true);

        $this->actingAs($this->admin)->get(route('payable-report.index'))->assertOk()->assertSee('Pengingat Jatuh Tempo')->assertSee('Aktif · setiap hari 08:05')
            ->assertSee(route('finance-notification.index'), false)->assertDontSee('Simpan Pengaturan');
        $this->actingAs($this->admin)->get(route('finance-notification.index'))->assertOk()->assertSee('Pengingat Hutang Supplier')->assertSee('Finance HAS');
        $this->artisan('finance:send-due-reminders', ['--preview' => true])->expectsOutputToContain('Pengingat Hutang Supplier')->assertSuccessful();
    }

    public function test_nothing_due_sends_nothing(): void
    {
        SupplierBill::query()->update(['due_date' => '2027-03-01']);
        $this->actingAs($this->admin)->patchJson(route('finance-notification.update', 'payable'), ['enabled' => 1, 'days_ahead' => 3, 'send_time' => '08:05', 'whatsapp_group_id' => $this->group->id, 'notify_payment_users' => 0])->assertOk();

        $this->assertSame('nothing_due', app(PayableDueNotifier::class)->send()['status']);
        Http::assertNothingSent();
        $this->actingAs($this->admin)->postJson(route('finance-notification.send', 'payable'))->assertOk()->assertJsonPath('success', false);
    }
}

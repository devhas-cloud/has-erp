<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Division;
use App\Models\Module;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\WhatsAppGroup;
use App\Services\PayableDueNotifier;
use App\Services\ReceivableDueNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Modul Notifikasi Finance (Master Data): pengaturan pengingat WhatsApp hutang
 * & piutang terpisah dari laporan, termasuk jam kirim.
 */
class FinanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;

    private User $manager;

    private WhatsAppGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(7, 0));

        config(['services.wabaileys.url' => 'https://wa.test', 'services.wabaileys.api_key' => 'key', 'services.wabaileys.sender_phone' => '628111']);
        Http::fake(['wa.test/*' => Http::response(['id' => 'MSG1'], 200)]);

        $division = Division::create(['division_name' => 'Finance', 'description' => 'Finance', 'type' => 'Internal', 'status' => 'Active']);
        $module = Module::create(['module_code' => 'MOD_FINANCE_NOTIFICATION', 'module_name' => 'Notifikasi Finance', 'route_name' => 'finance-notification', 'icon' => 'fa fa-bell', 'group' => 'Master Data']);
        $this->reader = $this->user('reader', $module, ['can_read' => true]);
        $this->manager = $this->user('manager', $module, ['can_read' => true, 'can_update' => true, 'can_create' => true]);
        $this->group = WhatsAppGroup::create(['division_id' => $division->id, 'group_name' => 'Finance HAS', 'group_id' => '1203-99@g.us', 'status' => 'Active']);

        $supplier = Supplier::create(['name' => 'PT Alpha', 'status' => 'Active']);
        SupplierBill::create([
            'bill_number' => SupplierBill::generateNumber(), 'supplier_id' => $supplier->id, 'supplier_invoice_number' => 'INV-LATE',
            'bill_date' => '2026-10-01', 'due_date' => '2026-11-10', 'currency' => 'IDR', 'subtotal' => 4000, 'total' => 4000,
            'status' => SupplierBill::STATUS_VERIFIED, 'created_by' => $this->manager->id,
        ]);
    }

    private function user(string $username, Module $module, array $access): User
    {
        $user = User::create(['username' => $username, 'email' => $username.'@has.com', 'password' => bcrypt('secret'), 'role' => 'User']);
        UserAccessControl::create(array_merge([
            'user_id' => $user->id, 'module_id' => $module->id,
            'can_create' => false, 'can_read' => false, 'can_update' => false, 'can_delete' => false, 'can_approve' => false,
        ], $access));

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['enabled' => 1, 'days_ahead' => 3, 'send_time' => '09:30', 'whatsapp_group_id' => $this->group->id, 'notify_payment_users' => 0], $overrides);
    }

    public function test_page_shows_both_reminders_with_defaults(): void
    {
        $this->actingAs($this->reader)->get(route('finance-notification.index'))->assertOk()
            ->assertSee('Pengingat Hutang Supplier')->assertSee('Pengingat Piutang Customer')
            ->assertSee('value="08:05"', false)->assertSee('value="08:10"', false)
            ->assertSee('PT Alpha')->assertSee('lewat 8 hari')
            ->assertDontSee('Simpan Pengaturan')->assertDontSee('Kirim Sekarang');

        $this->actingAs($this->manager)->get(route('finance-notification.index'))->assertOk()
            ->assertSee('Simpan Pengaturan')->assertSee('Kirim Sekarang');
    }

    public function test_permissions(): void
    {
        $this->actingAs($this->reader)->patchJson(route('finance-notification.update', 'payable'), $this->payload())->assertForbidden();
        $this->actingAs($this->reader)->postJson(route('finance-notification.send', 'payable'))->assertForbidden();
        $this->actingAs($this->manager)->patchJson('/finance-notification/other', $this->payload())->assertNotFound();
    }

    public function test_save_validates_and_keeps_settings_per_type(): void
    {
        $this->actingAs($this->manager)->patchJson(route('finance-notification.update', 'payable'), $this->payload(['send_time' => '25:00']))
            ->assertStatus(422)->assertJsonValidationErrors('send_time');
        $this->actingAs($this->manager)->patchJson(route('finance-notification.update', 'payable'), $this->payload(['whatsapp_group_id' => null]))
            ->assertStatus(422);

        $this->actingAs($this->manager)->patchJson(route('finance-notification.update', 'payable'), $this->payload())
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('09:30', PayableDueNotifier::settings()['send_time']);
        $this->assertTrue(PayableDueNotifier::settings()['enabled']);

        // Piutang memakai flag penerima sendiri dan tidak terpengaruh.
        $this->assertFalse(ReceivableDueNotifier::settings()['enabled']);
        $this->actingAs($this->manager)->patchJson(route('finance-notification.update', 'receivable'), [
            'enabled' => 0, 'days_ahead' => 5, 'send_time' => '10:00', 'whatsapp_group_id' => null, 'notify_receipt_users' => 1,
        ])->assertOk();
        $this->assertSame(5, ReceivableDueNotifier::settings()['days_ahead']);
        $this->assertSame('09:30', PayableDueNotifier::settings()['send_time']);
    }

    public function test_scheduler_waits_for_send_time_then_sends_once(): void
    {
        AppSetting::put(PayableDueNotifier::SETTING_KEY, array_merge(PayableDueNotifier::settings(), $this->payload(['enabled' => true, 'notify_payment_users' => false])));
        $notifier = app(PayableDueNotifier::class);

        // 07:00 < 09:30: belum waktunya.
        $this->assertSame('not_yet', $notifier->send()['status']);
        Http::assertNothingSent();

        // Server sempat mati: tetap terkirim pada pengecekan pertama setelah jam kirim.
        $this->travelTo(now()->setTime(11, 15));
        $this->assertSame('sent', $notifier->send()['status']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group'));
        $this->assertSame('already_sent', $notifier->send()['status']);

        // Kirim Sekarang tidak menunggu jam kirim.
        $this->actingAs($this->manager)->postJson(route('finance-notification.send', 'payable'))->assertOk()->assertJsonPath('success', true);
    }
}

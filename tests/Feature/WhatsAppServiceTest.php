<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    private function configure(array $overrides = []): void
    {
        config(array_merge([
            'services.wabaileys.url' => 'https://wa.example.test',
            'services.wabaileys.api_key' => 'secret-key',
            'services.wabaileys.sender_phone' => '6281111111111',
        ], $overrides));
    }

    public function test_is_configured_false_when_any_setting_missing(): void
    {
        $this->configure(['services.wabaileys.url' => '']);
        $this->assertFalse((new WhatsAppService)->isConfigured());

        $this->configure(['services.wabaileys.api_key' => '']);
        $this->assertFalse((new WhatsAppService)->isConfigured());

        $this->configure(['services.wabaileys.sender_phone' => '']);
        $this->assertFalse((new WhatsAppService)->isConfigured());
    }

    public function test_is_configured_true_when_all_settings_present(): void
    {
        $this->configure();
        $this->assertTrue((new WhatsAppService)->isConfigured());
    }

    public function test_format_number_normalizes_common_formats(): void
    {
        $service = new WhatsAppService;

        $this->assertSame('6281234567890', $service->formatNumber('081234567890'));
        $this->assertSame('6281234567890', $service->formatNumber('81234567890'));
        $this->assertSame('6281234567890', $service->formatNumber('6281234567890'));
        $this->assertSame('6281234567890', $service->formatNumber('+62 812-3456-7890'));
        $this->assertSame('', $service->formatNumber(''));
    }

    public function test_send_text_skips_without_making_http_call_when_not_configured(): void
    {
        $this->configure(['services.wabaileys.url' => '']);
        Http::fake();

        $result = (new WhatsAppService)->sendText('081234567890', 'Halo');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_send_text_posts_correct_payload_and_returns_true_on_success(): void
    {
        $this->configure();
        Http::fake([
            'wa.example.test/api/send' => Http::response(['success' => true, 'id' => 'MSG123'], 200),
        ]);

        $result = (new WhatsAppService)->sendText('081234567890', 'Halo dari test');

        $this->assertTrue($result);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://wa.example.test/api/send'
                && $request->hasHeader('X-API-Key', 'secret-key')
                && $request['phone'] === '6281111111111'
                && $request['to'] === '6281234567890'
                && $request['message'] === 'Halo dari test';
        });
    }

    public function test_send_text_returns_false_on_generic_failure(): void
    {
        $this->configure();
        Http::fake([
            'wa.example.test/api/send' => Http::response(['success' => false, 'error' => 'bad request'], 400),
        ]);

        $this->assertFalse((new WhatsAppService)->sendText('081234567890', 'Halo'));
    }

    /**
     * Kode 403 + code:463 = akun bot dibatasi WhatsApp (per kontrak error
     * waBaileys) — harus tetap return false dengan aman, tidak throw.
     */
    public function test_send_text_handles_account_restricted_error_without_throwing(): void
    {
        $this->configure();
        Http::fake([
            'wa.example.test/api/send' => Http::response(['success' => false, 'code' => 463], 403),
        ]);

        $this->assertFalse((new WhatsAppService)->sendText('081234567890', 'Halo'));
    }

    public function test_send_text_returns_false_and_does_not_throw_on_connection_exception(): void
    {
        $this->configure();
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        $this->assertFalse((new WhatsAppService)->sendText('081234567890', 'Halo'));
    }

    public function test_send_to_group_posts_group_jid_to_send_group_endpoint(): void
    {
        $this->configure();
        Http::fake([
            'wa.example.test/api/send-group' => Http::response(['success' => true, 'id' => 'MSG456'], 200),
        ]);

        $result = (new WhatsAppService)->sendToGroup('6281234567890-1234567890@g.us', 'Halo grup');

        $this->assertTrue($result);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://wa.example.test/api/send-group'
                && $request['groupJid'] === '6281234567890-1234567890@g.us'
                && $request['message'] === 'Halo grup';
        });
    }

    public function test_send_text_skips_on_empty_number_after_normalization(): void
    {
        $this->configure();
        Http::fake();

        $result = (new WhatsAppService)->sendText('---', 'Halo');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }
}

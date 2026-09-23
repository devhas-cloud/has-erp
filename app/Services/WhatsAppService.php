<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien untuk gateway WhatsApp waBaileys (https://github.com/abakarbit/waBaileys).
 *
 * Dibuat generik dan berdiri sendiri (bukan cuma untuk satu use-case) supaya
 * mudah dipakai di kasus-kasus lain: cukup panggil sendText()/sendToGroup()
 * dari mana saja. Service ini murni transport — penyusunan isi pesan jadi
 * tanggung jawab pemanggil, bukan service ini.
 */
class WhatsAppService
{
    private string $baseUrl;

    private string $apiKey;

    private string $senderPhone;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.wabaileys.url') ?? '', '/');
        $this->apiKey = config('services.wabaileys.api_key') ?? '';
        $this->senderPhone = config('services.wabaileys.sender_phone') ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '' && $this->senderPhone !== '';
    }

    /**
     * Normalisasi nomor ke format waBaileys: kode negara tanpa "+", 8-15 digit.
     */
    public function formatNumber(string $number): string
    {
        $number = preg_replace('/\D+/', '', trim($number));

        if ($number === '') {
            return '';
        }

        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        } elseif (str_starts_with($number, '8')) {
            $number = '62'.$number;
        }

        return $number;
    }

    /**
     * Kirim pesan teks personal ke satu nomor.
     */
    public function sendText(string $to, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('waBaileys not configured — skipping send', ['to' => $to]);

            return false;
        }

        $formatted = $this->formatNumber($to);
        if ($formatted === '') {
            Log::warning('waBaileys: nomor tujuan kosong setelah normalisasi — skip', ['to' => $to]);

            return false;
        }

        return $this->post('/api/send', [
            'phone' => $this->senderPhone,
            'to' => $formatted,
            'message' => $message,
        ], $formatted);
    }

    /**
     * Kirim pesan teks ke grup WhatsApp. $groupJid formatnya "xxxx-xxxx@g.us".
     */
    public function sendToGroup(string $groupJid, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('waBaileys not configured — skipping group send', ['group' => $groupJid]);

            return false;
        }

        return $this->post('/api/send-group', [
            'phone' => $this->senderPhone,
            'groupJid' => $groupJid,
            'message' => $message,
        ], $groupJid);
    }

    private function post(string $path, array $payload, string $target): bool
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($this->baseUrl.$path, $payload);

            if ($response->successful()) {
                Log::info('waBaileys send success', [
                    'target' => $target,
                    'message_id' => $response->json('id'),
                ]);

                return true;
            }

            // Kontrak error waBaileys: 403 + code 463 = akun bot dibatasi WhatsApp,
            // butuh campur tangan manusia — beda perlakuan dari kegagalan biasa.
            $body = $response->json();
            if ($response->status() === 403 && ($body['code'] ?? null) === 463) {
                Log::critical('waBaileys: akun bot dibatasi WhatsApp — hentikan pengiriman', [
                    'target' => $target,
                ]);
            } else {
                Log::warning('waBaileys send failed', [
                    'target' => $target,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('waBaileys service exception', [
                'target' => $target,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $baseUrl;

    private string $instance;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.evolution.url') ?? '', '/');
        $this->instance = config('services.evolution.instance') ?? '';
        $this->apiKey = config('services.evolution.apikey') ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->instance !== '' && $this->apiKey !== '';
    }

    /**
     * Normalisasi nomor telepon ke format WhatsApp (628xxxxxxxxxx)
     */
    private function formatWhatsAppNumber(string $number): string
    {
        // Hapus semua karakter selain angka
        $number = preg_replace('/\D+/', '', trim($number));

        if (empty($number)) {
            return '';
        }

        // Jika diawali 0 -> ganti menjadi 62
        if (str_starts_with($number, '0')) {
            $number = '62' . substr($number, 1);
        }

        // Jika diawali 8 -> anggap nomor Indonesia
        if (str_starts_with($number, '8')) {
            $number = '62' . $number;
        }

        // Jika sudah diawali 62, biarkan
        return $number;
    }

    /**
     * Send a text message via Evolution API.
     *
     * @param  string  $number  WhatsApp number in international format without '+' (e.g. 628123456789)
     * @param  string  $text  Message body (supports WhatsApp markdown)
     */
    public function sendText(string $number, string $text): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp service not configured — skipping send', ['number' => $number]);

            return false;
        }

        $number = $this->formatWhatsAppNumber($number);

        $url = "{$this->baseUrl}/message/sendText/{$this->instance}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $this->apiKey,
            ])->timeout(15)->post($url, [
                'number' => $number,
                'textMessage' => ['text' => $text],
                'options' => [
                    'delay' => 1200,
                    'linkPreview' => false,
                ],
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('WhatsApp send failed', [
                'number' => $number,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp service exception', [
                'number' => $number,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

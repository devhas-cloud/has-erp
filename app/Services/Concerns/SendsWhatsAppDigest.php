<?php

namespace App\Services\Concerns;

use App\Models\AppSetting;
use App\Models\WhatsAppGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Ringkasan WhatsApp harian Finance (pengingat hutang & piutang). Kelas pemakai
 * menyediakan SETTING_KEY, DEFAULTS (enabled, days_ahead, send_time,
 * whatsapp_group_id, last_sent_date, + flag penerima pribadi), isi pesan, dan
 * penerima pribadi; properti $whatsapp berisi WhatsAppService.
 *
 * Scheduler memanggil send() berkala: pesan terkirim sekali per hari pada atau
 * setelah jam kirim (bila server sempat mati, terkirim saat jadwal berikutnya).
 */
trait SendsWhatsAppDigest
{
    abstract public function buildMessage(?int $daysAhead = null): ?string;

    /** Penerima WA pribadi (user dengan nomor WhatsApp). */
    abstract public function personalRecipients(): Collection;

    /** Key pengaturan yang menyalakan pengiriman ke WA pribadi. */
    abstract public static function personalFlag(): string;

    public static function settings(): array
    {
        return AppSetting::get(static::SETTING_KEY, static::DEFAULTS);
    }

    /**
     * @return array{status: string, sent: int, failed: int, message: ?string}
     */
    public function send(bool $force = false): array
    {
        $settings = static::settings();

        if (! $force && ! $settings['enabled']) {
            return ['status' => 'disabled', 'sent' => 0, 'failed' => 0, 'message' => null];
        }
        if (! $force && $settings['last_sent_date'] === now()->toDateString()) {
            return ['status' => 'already_sent', 'sent' => 0, 'failed' => 0, 'message' => null];
        }
        if (! $force && now()->format('H:i') < $settings['send_time']) {
            return ['status' => 'not_yet', 'sent' => 0, 'failed' => 0, 'message' => null];
        }

        $message = $this->buildMessage((int) $settings['days_ahead']);
        if ($message === null) {
            return ['status' => 'nothing_due', 'sent' => 0, 'failed' => 0, 'message' => null];
        }
        if (! $this->whatsapp->isConfigured()) {
            return ['status' => 'not_configured', 'sent' => 0, 'failed' => 0, 'message' => $message];
        }

        $sent = 0;
        $failed = 0;
        $groupJid = $settings['whatsapp_group_id'] ? WhatsAppGroup::whereKey($settings['whatsapp_group_id'])->value('group_id') : null;
        if ($groupJid) {
            $this->whatsapp->sendToGroup(trim($groupJid), $message) ? $sent++ : $failed++;
        }
        if ($settings[static::personalFlag()]) {
            foreach ($this->personalRecipients()->values() as $i => $user) {
                if ($i > 0 || $groupJid) {
                    sleep(app()->runningUnitTests() ? 0 : 3);
                }
                $this->whatsapp->sendText($user->phone_number, $message) ? $sent++ : $failed++;
            }
        }

        if ($sent === 0 && $failed === 0) {
            return ['status' => 'no_recipient', 'sent' => 0, 'failed' => 0, 'message' => $message];
        }

        if ($sent > 0) {
            AppSetting::put(static::SETTING_KEY, array_merge($settings, ['last_sent_date' => now()->toDateString()]));
        }
        Log::info(class_basename(static::class).' sent', ['sent' => $sent, 'failed' => $failed]);

        return ['status' => $failed > 0 ? 'partial' : 'sent', 'sent' => $sent, 'failed' => $failed, 'message' => $message];
    }
}

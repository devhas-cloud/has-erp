<?php

namespace App\Console\Commands;

use App\Services\PayableDueNotifier;
use Illuminate\Console\Command;

class SendPayableDueReminders extends Command
{
    protected $signature = 'finance:send-due-reminders {--force : Kirim walau nonaktif / sudah terkirim hari ini} {--preview : Tampilkan pesan tanpa mengirim}';

    protected $description = 'Kirim pengingat WhatsApp tagihan supplier yang terlambat / akan jatuh tempo';

    public function handle(PayableDueNotifier $notifier): int
    {
        if ($this->option('preview')) {
            $this->line($notifier->buildMessage() ?? 'Tidak ada tagihan yang perlu diingatkan.');

            return self::SUCCESS;
        }

        $result = $notifier->send((bool) $this->option('force'));
        $this->info('Status: '.$result['status'].' · terkirim '.$result['sent'].' · gagal '.$result['failed']);

        return self::SUCCESS;
    }
}

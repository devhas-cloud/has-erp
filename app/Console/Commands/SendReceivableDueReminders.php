<?php

namespace App\Console\Commands;

use App\Services\ReceivableDueNotifier;
use Illuminate\Console\Command;

class SendReceivableDueReminders extends Command
{
    protected $signature = 'finance:send-receivable-reminders {--force : Kirim walau nonaktif / sudah terkirim hari ini} {--preview : Tampilkan pesan tanpa mengirim}';

    protected $description = 'Kirim pengingat WhatsApp piutang customer (invoice terlambat / akan jatuh tempo, termin terlambat ditagih)';

    public function handle(ReceivableDueNotifier $notifier): int
    {
        if ($this->option('preview')) {
            $this->line($notifier->buildMessage() ?? 'Tidak ada piutang yang perlu diingatkan.');

            return self::SUCCESS;
        }

        $result = $notifier->send((bool) $this->option('force'));
        $this->info('Status: '.$result['status'].' · terkirim '.$result['sent'].' · gagal '.$result['failed']);

        return self::SUCCESS;
    }
}

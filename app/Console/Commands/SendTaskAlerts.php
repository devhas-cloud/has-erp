<?php

namespace App\Console\Commands;

use App\Services\TaskAlertService;
use Illuminate\Console\Command;

class SendTaskAlerts extends Command
{
    protected $signature = 'task:send-alerts';

    protected $description = 'Kirim WhatsApp alert untuk task hari ini dan besok';

    public function handle(TaskAlertService $service): void
    {
        $this->info('Memproses task alerts...');

        $result = $service->sendAlerts();

        $this->info("Total tasks: {$result['total']}");
        $this->info("Sent: {$result['sent']}");
        $this->info("Failed: {$result['failed']}");
        $this->info("Skipped: {$result['skipped']}");

        if ($result['failed'] > 0) {
            $this->warn("Ada {$result['failed']} pengiriman yang gagal.");
        }

        if ($result['total'] === 0) {
            $this->line('Tidak ada task yang perlu dikirim alert.');
        }
    }
}

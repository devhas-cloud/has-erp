<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi WhatsApp saat task baru dibuat atau assignee ditambahkan.
 *
 * Prioritas pengiriman:
 * 1. Grup WhatsApp penanganan.
 * 2. Grup WhatsApp khusus task.
 * 3. WhatsApp personal assignee.
 *
 * Kegagalan notifikasi tidak boleh mengganggu proses pembuatan task.
 */
class TaskWhatsAppNotifier
{
    public function notifyNewTask(Task $task, array $assignees): void
    {
        $task->loadMissing([
            'creator',
            'category',
            'handlingGroup',
            'whatsappGroup',
        ]);

        $whatsapp = app(WhatsAppService::class);

        if (! $whatsapp->isConfigured()) {
            return;
        }

        $message = $this->buildMessage($task);

        // 1. Kirim ke grup penanganan jika tersedia.
        if ($task->handling_group_id) {
            $groupJid = $task->handlingGroup?->whatsapp_group_id;

            if (! $groupJid) {
                Log::info(
                    'Notifikasi WA dilewati: ID grup penanganan belum diisi.',
                    [
                        'task_id' => $task->id,
                        'handling_group_id' => $task->handling_group_id,
                    ]
                );

                return;
            }

            $whatsapp->sendToGroup(trim($groupJid), $message);

            return;
        }

        // 2. Kirim ke grup WhatsApp khusus task jika tersedia.
        if ($task->whatsapp_group_id) {
            $groupJid = $task->whatsappGroup?->group_id;

            if (! $groupJid) {
                Log::info(
                    'Notifikasi WA dilewati: ID grup WhatsApp task belum diisi.',
                    [
                        'task_id' => $task->id,
                        'whatsapp_group_id' => $task->whatsapp_group_id,
                    ]
                );

                return;
            }

            $whatsapp->sendToGroup(trim($groupJid), $message);

            return;
        }

        // 3. Jika tidak menggunakan grup, kirim ke assignee.
        $this->sendToUsers(
            $whatsapp,
            $task,
            $assignees,
            '*Informasi Tugas Baru*'
        );
    }

    /**
     * Notifikasi untuk assignee yang baru ditambahkan saat task diedit.
     * Notifikasi dikirim secara personal agar tidak mengulang pesan grup.
     */
    public function notifyAssigneesAdded(
        Task $task,
        array $assignees
    ): void {
        if (empty($assignees)) {
            return;
        }

        $task->loadMissing(['creator', 'category']);

        $whatsapp = app(WhatsAppService::class);

        if (! $whatsapp->isConfigured()) {
            return;
        }

        $this->sendToUsers(
            $whatsapp,
            $task,
            $assignees,
            '👋 *Ada Penugasan Baru untuk Kamu*'
        );
    }

    /**
     * Mengirim notifikasi personal kepada assignee yang memiliki nomor HP.
     */
    private function sendToUsers(
        WhatsAppService $whatsapp,
        Task $task,
        array $assignees,
        string $heading
    ): void {
        $message = $this->buildMessage($task, $heading);

        $sent = false;

        foreach ($assignees as $assignee) {
            $phoneNumber = $assignee->phone_number;

            if (! $phoneNumber) {
                continue;
            }

            // Beri jeda antar-pengiriman jika sudah ada pesan yang dikirim.
            if ($sent) {
                sleep(1);
            }

            $whatsapp->sendText($phoneNumber, $message);

            $sent = true;
        }

        if (! $sent) {
            Log::info(
                'Notifikasi WA dilewati: Tidak ada assignee dengan nomor HP.',
                [
                    'task_id' => $task->id,
                ]
            );
        }
    }

    /**
     * Membuat pesan WhatsApp dengan bahasa santai, sopan, dan mudah dibaca.
     */
    private function buildMessage(
        Task $task,
        string $heading = '*Informasi Tugas Baru*'
    ): string {
        $title = trim((string) $task->title);
        $category = $task->category?->name ?? 'Belum ditentukan';
        $creator = $task->creator?->display_name ?? 'Sistem';

        $dueDate = $task->due_date
            ? $task->due_date->format('d M Y')
            : 'Belum ditentukan';

        $lines = [
            $heading,
            '',
            'Halo, rekan-rekan! 👋',
            '',
            'Ada tugas yang perlu diperhatikan dan ditindaklanjuti, ya.',
            '',
            '*Judul:* ' . $title,
            '*Kategori:* ' . $category,
            '*Tenggat:* ' . $dueDate,
            '*Dari:* ' . $creator,
            '',
            'Mohon bantuannya untuk menyelesaikan tugas ini '
                . 'sesuai tenggat waktu yang telah ditentukan.',
            '',
            'Jika ada hal yang perlu dikoordinasikan, '
                . 'silakan didiskusikan bersama.',
            '',
            'Terima kasih atas kerja sama dan bantuannya! 🙏',
        ];

        return implode("\n", $lines);
    }
}

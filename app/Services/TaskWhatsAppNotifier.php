<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi WhatsApp untuk task baru, assignee tambahan, dan approval.
 *
 * Prioritas pengiriman task baru:
 * 1. Grup WhatsApp penanganan.
 * 2. Grup WhatsApp khusus task.
 * 3. WhatsApp personal assignee.
 *
 * Reminder approval selalu personal ke creator.
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

        $message = $this->buildNewTaskMessage($task);

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
        $this->sendToUsers($whatsapp, $task, $assignees);
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

        $this->sendToUsers($whatsapp, $task, $assignees);
    }

    /**
     * Reminder approval dikirim sekali saat status menjadi waiting_approval.
     */
    public function notifyApprovalRequired(Task $task): void
    {
        $task->loadMissing(['creator', 'category']);

        $whatsapp = app(WhatsAppService::class);

        if (! $whatsapp->isConfigured()) {
            return;
        }

        $phoneNumber = $task->creator?->phone_number;

        if (! $phoneNumber) {
            Log::info(
                'Notifikasi approval WA dilewati: creator tidak memiliki nomor HP.',
                ['task_id' => $task->id]
            );

            return;
        }

        $whatsapp->sendText($phoneNumber, $this->buildApprovalMessage($task));
    }

    /**
     * Mengirim notifikasi personal kepada assignee yang memiliki nomor HP.
     */
    private function sendToUsers(
        WhatsAppService $whatsapp,
        Task $task,
        array $assignees
    ): void {
        $message = $this->buildNewTaskMessage($task);

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

    private function buildNewTaskMessage(Task $task): string
    {
        return implode("\n", [
            'INFORMASI TUGAS BARU',
            '',
            'Ada tugas yang perlu diperhatikan dan ditindaklanjuti.',
            '',
            ...$this->commonLines($task),
            '',
            'Mohon bantuannya untuk menyelesaikan tugas ini sesuai tenggat waktu yang telah ditentukan pada link berikut:',
            '',
            $task->whatsappUrl(),
            '',
            'Jika ada hal yang perlu dikoordinasikan, silakan didiskusikan bersama.',
        ]);
    }

    private function buildApprovalMessage(Task $task): string
    {
        $description = preg_replace('/\s+/', ' ', trim((string) $task->description)) ?: '-';

        return implode("\n", [
            'REMINDER APPROVAL',
            '',
            'Ada pekerjaan yang membutuhkan persetujuan anda.',
            '',
            ...$this->commonLines($task),
            'Keterangan: '.$description,
            '',
            'Segera periksa dan setujui pada link berikut:',
            '',
            $task->whatsappUrl(),
            '',
            'Jika ada hal yang perlu dikoordinasikan, silakan didiskusikan bersama.',
        ]);
    }

    private function commonLines(Task $task): array
    {
        return [
            'Judul: '.trim((string) $task->title),
            'Perusahaan: '.($task->accountCompanyName() ?? '-'),
            'Kategori: '.($task->category?->name ?? 'Belum ditentukan'),
            'Tenggat: '.($task->due_date?->format('d M Y') ?? 'Belum ditentukan'),
            'Dari: '.($task->creator?->display_name ?? 'Sistem'),
        ];
    }
}

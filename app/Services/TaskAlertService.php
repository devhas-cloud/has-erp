<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\Log;

class TaskAlertService
{
    public function sendAlerts(): array
    {
        $tasks = Task::with(['creator', 'assignees', 'whatsappGroup', 'handlingGroup'])
            // whereDate, bukan whereIn: due_date bertipe datetime, jadi whereIn
            // dengan 'Y-m-d' cuma cocok kalau jamnya persis 00:00:00.
            ->where(function ($q) {
                $q->whereDate('due_date', today())
                    ->orWhereDate('due_date', today()->addDay());
            })
            ->whereNotIn('status', ['done'])
            ->where('alert_type', '!=', 'none')
            // ->where('is_alert_sent', false)
            ->where(function ($q) {
                $q->whereNull('alert_time')
                    ->orWhere('alert_time', '<=', now());
            })
            ->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $whatsapp = app(WhatsAppService::class);

        foreach ($tasks as $task) {
            $message = $this->buildMessage($task);
            $targets = $this->resolveTargets($task);

            if (empty($targets['personal']) && empty($targets['groups'])) {
                Log::info('Task alert skipped — no recipients', ['task_id' => $task->id]);
                $skipped++;

                continue;
            }

            $allSent = true;

            foreach ($targets['personal'] as $i => $number) {
                if ($i > 0) {
                    sleep(5);
                }

                if ($whatsapp->sendText($number, $message)) {
                    $sent++;
                } else {
                    $failed++;
                    $allSent = false;
                }
            }

            foreach ($targets['groups'] as $i => $groupJid) {
                if ($i > 0) {
                    sleep(5);
                }

                if ($whatsapp->sendToGroup($groupJid, $message)) {
                    $sent++;
                } else {
                    $failed++;
                    $allSent = false;
                }
            }

            if ($allSent) {
                $task->update(['is_alert_sent' => true]);
            }

            Log::info('Task alert processed', [
                'task_id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date->format('Y-m-d'),
                'recipients' => $targets['personal'],
                'groups' => $targets['groups'],
                'all_sent' => $allSent,
            ]);
        }

        Log::info('Task alert batch complete', [
            'total' => count($tasks),
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        return [
            'total' => count($tasks),
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    /**
     * Pisahkan target personal (nomor, dikirim via sendText) dari grup
     * (JID "xxxx@g.us", dikirim via sendToGroup — JID tidak boleh dinormalisasi).
     *
     * Dua kategori, sama seperti notifikasi task baru:
     * - task dengan penanganan -> hanya grup WA penanganan (alert_target diabaikan)
     * - task tanpa penanganan  -> ikut alert_target seperti biasa
     */
    private function resolveTargets(Task $task): array
    {
        $personal = [];
        $groups = [];

        if ($task->handling_group_id) {
            $groupJid = $task->handlingGroup?->whatsapp_group_id;
            if ($groupJid) {
                $groups[] = trim($groupJid);
            } else {
                Log::info('Task alert: penanganan tanpa WA Group ID — dilewati', [
                    'task_id' => $task->id,
                    'handling_group_id' => $task->handling_group_id,
                ]);
            }

            return ['personal' => [], 'groups' => array_values(array_unique($groups))];
        }

        if (in_array($task->alert_target, ['personal', 'both'])) {
            if ($task->creator?->phone_number) {
                $personal[] = $this->normalizeNumber($task->creator->phone_number);
            }

            foreach ($task->assignees as $assignee) {
                if ($assignee->phone_number) {
                    $personal[] = $this->normalizeNumber($assignee->phone_number);
                }
            }
        }

        if (in_array($task->alert_target, ['group', 'both'])) {
            $groupJid = $task->whatsappGroup?->group_id;
            if ($groupJid) {
                $groups[] = trim($groupJid);
            }
        }

        return [
            'personal' => array_values(array_unique($personal)),
            'groups' => array_values(array_unique($groups)),
        ];
    }

    private function normalizeNumber(string $raw): string
    {
        $number = preg_replace('/[^0-9]/', '', $raw);

        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return $number;
    }

    private function buildMessage(Task $task): string
    {
        $dueDate = $task->due_date->format('d M Y');
        $isToday = $task->due_date->isToday();
        $prefix = $isToday ? '⚠️ *Reminder: Task Hari Ini!*' : '📋 *Reminder: Task Besok*';

        $lines = [
            $prefix,
            '',
            '*Tugas:* '.$task->title,
            '*Status:* '.$this->statusLabel($task->status),
            '*Tenggat:* '.$dueDate,
        ];

        if ($task->time) {
            $lines[] = '*Waktu:* '.$task->time;
        }

        $lines[] = '*Dari:* '.($task->creator?->display_name ?? 'Sistem');

        return implode("\n", $lines);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'waiting_approval' => 'Waiting Approval',
            'done' => 'Done',
            default => $status,
        };
    }
}

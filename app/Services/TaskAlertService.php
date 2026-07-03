<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Facades\Log;

class TaskAlertService
{
    public function sendAlerts(): array
    {
        $tasks = Task::with(['creator', 'assignees', 'whatsappGroup'])
            ->whereIn('due_date', [today()->format('Y-m-d'), today()->addDay()->format('Y-m-d')])
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
            $recipients = $this->resolveRecipients($task);

            if (empty($recipients)) {
                Log::info('Task alert skipped — no recipients', ['task_id' => $task->id]);
                $skipped++;

                continue;
            }

            $allSent = true;
            foreach ($recipients as $i => $number) {
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

            if ($allSent) {
                $task->update(['is_alert_sent' => true]);
            }

            Log::info('Task alert processed', [
                'task_id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date->format('Y-m-d'),
                'recipients' => $recipients,
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

    private function resolveRecipients(Task $task): array
    {
        $recipients = [];

        if (in_array($task->alert_target, ['personal', 'both'])) {
            if ($task->creator?->phone_number) {
                $recipients[] = $this->normalizeNumber($task->creator->phone_number);
            }

            foreach ($task->assignees as $assignee) {
                if ($assignee->phone_number) {
                    $recipients[] = $this->normalizeNumber($assignee->phone_number);
                }
            }
        }

        if (in_array($task->alert_target, ['group', 'both'])) {
            $groupId = $task->whatsappGroup?->group_id;
            if ($groupId) {
                $recipients[] = $this->normalizeNumber($groupId);
            }
        }

        return array_unique($recipients);
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

        $lines[] = '*Dari:* '.($task->creator?->username ?? 'Sistem');

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

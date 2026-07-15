<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TaskObserver
{
    public function updated(Task $task): void
    {
        if (! $task->isDirty('status')) {
            return;
        }

        //   Ini akan me-skip seluruh blok notifikasi jika tidak ada assignee lain selain creator. Dengan begitu:
        // - Task tanpa assign (hanya creator) → tidak ada notif
        // - Task dengan assignee lain → notifikasi tetap berjalan normal (creator dapat notif saat assignee mengubah status, dan sebaliknya)

        $otherAssignees = $task->assignees->reject(fn ($a) => $a->id === $task->creator_id);
        if ($otherAssignees->isEmpty()) {
            return;
        }

        $oldStatus = $task->getOriginal('status');
        $newStatus = $task->status;
        $creator = User::find($task->creator_id);

        $statusLabels = [
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'waiting_approval' => 'Waiting Approval',
            'done' => 'Done',
        ];

        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        if ($newStatus === 'waiting_approval') {
            $task->notify(
                $creator,
                'task_approval_required',
                "Approval dibutuhkan: {$task->title}",
                'Assignee telah menyelesaikan tugas.',
                ['task_id' => $task->id]
            );

            return;
        }

        if ($newStatus === 'done' && $oldStatus === 'waiting_approval') {
            foreach ($task->assignees as $assignee) {
                if ($assignee->id === $task->creator_id) {
                    continue;
                }
                if ($assignee->id === Auth::id()) {
                    continue;
                }
                $task->notify(
                    $assignee,
                    'task_approved',
                    "Tugas disetujui: {$task->title}",
                    "{$creator->username} telah menyetujui tugas.",
                    ['task_id' => $task->id, 'approver' => $creator->username]
                );
            }

            return;
        }

        $notifiedIds = [$task->creator_id];
        $actorId = Auth::id();
        if ($actorId) {
            $notifiedIds[] = $actorId;
        }
        if ($creator) {
            $task->notify(
                $creator,
                'task_status_changed',
                "Status: {$task->title}",
                "{$oldLabel} → {$newLabel}",
                ['task_id' => $task->id]
            );
        }

        foreach ($task->assignees as $assignee) {
            if (in_array($assignee->id, $notifiedIds)) {
                continue;
            }
            $notifiedIds[] = $assignee->id;

            $task->notify(
                $assignee,
                'task_status_changed',
                "Status: {$task->title}",
                "{$oldLabel} → {$newLabel}",
                ['task_id' => $task->id]
            );
        }
    }
}

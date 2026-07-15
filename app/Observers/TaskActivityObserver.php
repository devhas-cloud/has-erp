<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Support\Str;

class TaskActivityObserver
{
    public function created(TaskActivity $activity): void
    {
        // Skip replies — handled by controller-side reply notification
        if ($activity->reply_to_id) {
            return;
        }

        $task = $activity->task()->with(['creator', 'assignees'])->first();
        if (! $task) {
            return;
        }

        $notifiedIds = [$activity->user_id];
        $actor = User::find($activity->user_id);

        $preview = $activity->content
            ? Str::limit($activity->content, 80)
            : 'Mengirim lampiran';

        if ($task->creator_id !== $activity->user_id) {
            $task->creator->notifications()->create([
                'user_id' => $task->creator_id,
                'type' => 'task_activity',
                'title' => "Aktivitas baru: {$task->title}",
                'body' => "{$actor->username}: {$preview}",
                'notifiable_type' => Task::class,
                'notifiable_id' => $task->id,
                'data' => ['task_id' => $task->id, 'activity_id' => $activity->id],
            ]);
            $notifiedIds[] = $task->creator_id;
        }

        foreach ($task->assignees as $assignee) {
            if (in_array($assignee->id, $notifiedIds)) {
                continue;
            }
            $notifiedIds[] = $assignee->id;

            $assignee->notifications()->create([
                'user_id' => $assignee->id,
                'type' => 'task_activity',
                'title' => "Aktivitas baru: {$task->title}",
                'body' => "{$actor->username}: {$preview}",
                'notifiable_type' => Task::class,
                'notifiable_id' => $task->id,
                'data' => ['task_id' => $task->id, 'activity_id' => $activity->id],
            ]);
        }
    }
}

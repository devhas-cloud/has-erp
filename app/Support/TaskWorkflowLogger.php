<?php

namespace App\Support;

use App\Models\Log;
use App\Models\Task;

class TaskWorkflowLogger
{
    /**
     * Mencatat progres task pada tab Logs task itu sendiri (MOD_TASK_PLANNER),
     * serta pada Opportunity terkait (MOD_OPPORTUNITY_MANAGEMENT) jika task
     * terikat pada sebuah opportunity.
     */
    public static function forTask(Task $task, string $action, string $description): ?Log
    {
        $log = Log::record($action, $description, 'MOD_TASK_PLANNER', $task);

        // Jika task terikat pada sebuah opportunity, catat juga pada log opportunity
        // if ($task->opportunity_id && $task->opportunity) {
        //     Log::record($action, $description, 'MOD_OPPORTUNITY_MANAGEMENT', $task->opportunity);
        // }

        return $log;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPlannerCancelTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, string $role = 'User'): User
    {
        return User::create([
            'username' => $name,
            'full_name' => ucfirst($name),
            'email' => $name.'@has.com',
            'password' => bcrypt('secret'),
            'role' => $role,
        ]);
    }

    private function task(User $creator, array $attributes = []): Task
    {
        $category = TaskCategory::create([
            'name' => 'General '.uniqid(),
            'visit' => $attributes['visit'] ?? false,
        ]);

        unset($attributes['visit']);

        return Task::create(array_merge([
            'creator_id' => $creator->id,
            'category_id' => $category->id,
            'title' => 'Task uji cancel',
            'due_date' => now()->toDateString(),
            'status' => 'todo',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ], $attributes));
    }

    public function test_creator_can_cancel_todo_task_with_reason_and_audit(): void
    {
        $creator = $this->user('creator', 'Admin');
        $task = $this->task($creator);

        $this->actingAs($creator)->postJson(route('task-planner.cancel', $task->id), [
            'cancel_reason' => '  Kebutuhan sudah tidak relevan.  ',
        ])->assertOk();

        $task->refresh();
        $this->assertSame('cancelled', $task->status);
        $this->assertSame('Kebutuhan sudah tidak relevan.', $task->cancel_reason);
        $this->assertSame($creator->id, $task->cancelled_by);
        $this->assertNotNull($task->cancelled_at);

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'user_id' => $creator->id,
            'content' => '🚫 Task dibatalkan: Kebutuhan sudah tidak relevan.',
        ]);
        $this->assertDatabaseHas('logs', [
            'action' => 'cancel_task',
            'loggable_id' => $task->id,
        ]);

        $this->actingAs($creator)->get(route('task-planner.show', $task->id))
            ->assertOk()
            ->assertSee('Task dibatalkan')
            ->assertSee('Kebutuhan sudah tidak relevan.');
    }

    public function test_assignee_cannot_cancel_task(): void
    {
        $module = Module::create([
            'module_code' => 'MOD_TASK_PLANNER',
            'module_name' => 'Task Planner',
            'route_name' => 'task-planner',
            'icon' => 'fa fa-tasks',
            'group' => 'CRM',
        ]);

        $creator = $this->user('owner', 'Admin');
        $assignee = $this->user('assignee');
        UserAccessControl::create([
            'user_id' => $assignee->id,
            'module_id' => $module->id,
            'can_read' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => false,
            'can_approve' => false,
        ]);

        $task = $this->task($creator, ['status' => 'in_progress']);
        $task->assignees()->sync([$assignee->id]);

        $this->actingAs($assignee)->postJson(route('task-planner.cancel', $task->id), [
            'cancel_reason' => 'Beban kerja dialihkan.',
        ])->assertForbidden();

        $this->assertSame('in_progress', $task->fresh()->status);

        $rows = $this->actingAs($assignee)->getJson(route('task-planner.data'))->assertOk()->json('data');
        $this->assertFalse(collect($rows)->firstWhere('id', $task->id)['can_cancel']);
    }

    public function test_unrelated_user_cannot_cancel(): void
    {
        $creator = $this->user('creator-owner', 'Admin');
        $other = $this->user('other-user', 'Admin');
        $task = $this->task($creator);

        $this->actingAs($other)->postJson(route('task-planner.cancel', $task->id), [
            'cancel_reason' => 'Bukan hak saya.',
        ])->assertForbidden();

        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_cancel_rejects_waiting_approval_done_and_missing_reason(): void
    {
        $creator = $this->user('creator-status', 'Admin');

        $waiting = $this->task($creator, ['status' => 'waiting_approval']);
        $this->actingAs($creator)->postJson(route('task-planner.cancel', $waiting->id), [
            'cancel_reason' => 'Tidak jadi.',
        ])->assertStatus(422);
        $this->assertSame('waiting_approval', $waiting->fresh()->status);

        $done = $this->task($creator, ['status' => 'done']);
        $this->actingAs($creator)->postJson(route('task-planner.cancel', $done->id), [
            'cancel_reason' => 'Tidak jadi.',
        ])->assertStatus(422);
        $this->assertSame('done', $done->fresh()->status);

        $todo = $this->task($creator);
        $this->actingAs($creator)->postJson(route('task-planner.cancel', $todo->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cancel_reason']);
        $this->assertSame('todo', $todo->fresh()->status);
    }

    public function test_cancelled_task_cannot_transition_update_or_record_visit(): void
    {
        $creator = $this->user('creator-lock', 'Admin');
        $task = $this->task($creator, ['status' => 'cancelled', 'visit' => true]);

        $this->actingAs($creator)->postJson(route('task-planner.transition', $task->id), [
            'status' => 'in_progress',
        ])->assertStatus(422);

        $this->actingAs($creator)->putJson(route('task-planner.update', $task->id), [
            'title' => 'Diubah',
            'category_id' => $task->category_id,
            'due_date' => now()->toDateString(),
            'status' => 'todo',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ])->assertStatus(422);

        $this->actingAs($creator)->postJson(route('task-planner.visit', $task->id), [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertStatus(422);

        $this->assertSame('cancelled', $task->fresh()->status);
    }

    public function test_cancelled_visit_task_cannot_be_approved_into_done(): void
    {
        $creator = $this->user('creator-visit', 'Admin');
        $task = $this->task($creator, ['status' => 'cancelled', 'visit' => true]);

        $this->actingAs($creator)->postJson(route('task-planner.approve', $task->id))
            ->assertStatus(422);

        $this->assertSame('cancelled', $task->fresh()->status);
    }

    public function test_kanban_excludes_cancelled_and_table_does_not_mark_it_overdue(): void
    {
        $creator = $this->user('creator-view', 'Admin');
        $cancelled = $this->task($creator, [
            'status' => 'cancelled',
            'title' => 'Task cancelled',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
        $this->task($creator, ['title' => 'Task aktif']);

        $kanban = $this->actingAs($creator)->getJson(route('task-planner.kanban'))->assertOk()->json();
        $titles = collect($kanban['columns'])->flatMap(fn ($cards) => array_column($cards, 'title'));
        $this->assertFalse($titles->contains($cancelled->title));

        $table = $this->actingAs($creator)->getJson(route('task-planner.data'))->assertOk()->json('data');
        $row = collect($table)->firstWhere('id', $cancelled->id);

        $this->assertSame('cancelled', $row['status']);
        $this->assertFalse($row['is_overdue']);
        $this->assertStringContainsString('Cancelled', $row['status_label']);
    }
}

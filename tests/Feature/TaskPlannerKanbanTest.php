<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskPlannerKanbanTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $other;

    private User $outsider;

    private TaskCategory $general;

    private TaskCategory $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $module = Module::create(['module_code' => 'MOD_TASK_PLANNER', 'module_name' => 'Task Planner', 'route_name' => 'task-planner', 'icon' => 'fa fa-tasks', 'group' => 'CRM']);
        $make = function (string $name) use ($module) {
            $user = User::create(['username' => strtolower($name), 'full_name' => $name, 'email' => strtolower($name).'@has.com', 'password' => bcrypt('x'), 'role' => 'User']);
            UserAccessControl::create(['user_id' => $user->id, 'module_id' => $module->id, 'can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => false, 'can_approve' => false]);

            return $user;
        };
        $this->creator = $make('Ichsan');
        $this->other = $make('Maidin');
        $this->outsider = $make('Rina');

        $this->general = TaskCategory::create(['name' => 'General']);
        $this->visit = TaskCategory::create(['name' => 'Survey', 'visit' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Task bersama',
            'category_id' => $this->general->id,
            'due_date' => now()->addDay()->toDateString(),
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ], $overrides);
    }

    public function test_assignee_picker_lists_self_once_on_top(): void
    {
        $results = $this->actingAs($this->creator)->getJson(route('task-planner.fetch-assignees'))->assertOk()->json('results');

        $this->assertSame($this->creator->id, $results[0]['id']);
        $this->assertSame('Ichsan (Saya)', $results[0]['text']);
        $this->assertCount(1, array_filter($results, fn ($r) => $r['id'] === $this->creator->id), 'Diri sendiri tidak boleh dobel.');
        $this->assertContains($this->other->id, array_column($results, 'id'));

        // Pencarian yang tidak cocok dengan diri sendiri tidak menampilkan "(Saya)".
        $results = $this->actingAs($this->creator)->getJson(route('task-planner.fetch-assignees', ['q' => 'maid']))->json('results');
        $this->assertSame([$this->other->id], array_column($results, 'id'));
    }

    public function test_creator_can_assign_self_together_with_others_without_duplicates(): void
    {
        $this->actingAs($this->creator)->postJson(route('task-planner.store'), $this->payload([
            'assignees' => [$this->creator->id, $this->other->id, (string) $this->creator->id],
        ]))->assertOk();

        $task = Task::with('assignees')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->creator->id, $this->other->id], $task->assignees->pluck('id')->all());
        $this->assertTrue($task->requires_approval, 'Ada assignee lain -> tetap butuh approval.');

        $this->actingAs($this->creator)->postJson(route('task-planner.store'), $this->payload([
            'title' => 'Sendiri', 'assignees' => [$this->creator->id],
        ]))->assertOk();
        $solo = Task::where('title', 'Sendiri')->firstOrFail();
        $this->assertFalse($solo->requires_approval);
        $this->assertSame([$this->creator->id], $solo->assignees()->pluck('users.id')->all());
    }

    public function test_kanban_groups_visible_tasks_and_limits_done_to_30_days(): void
    {
        $mk = function (string $title, string $status, array $assignees, array $attrs = []) {
            $task = Task::create(array_merge([
                'creator_id' => $this->creator->id, 'category_id' => $this->general->id, 'title' => $title,
                'due_date' => now()->addDay()->toDateString(), 'status' => $status, 'alert_type' => 'none', 'alert_target' => 'personal',
            ], $attrs));
            $task->assignees()->sync($assignees);
        };

        $mk('A todo', 'todo', [$this->other->id]);
        $mk('B progress', 'in_progress', [$this->creator->id]);
        $mk('C waiting', 'waiting_approval', [$this->other->id]);
        $mk('D done baru', 'done', [$this->other->id]);
        $mk('E done lama', 'done', [$this->other->id]);
        $mk('F visit', 'in_progress', [$this->other->id], ['category_id' => $this->visit->id]);
        DB::table('tasks')->where('title', 'E done lama')->update(['updated_at' => now()->subDays(31)]);
        Task::create(['creator_id' => $this->outsider->id, 'category_id' => $this->general->id, 'title' => 'Milik orang lain',
            'due_date' => now()->toDateString(), 'status' => 'todo', 'alert_type' => 'none', 'alert_target' => 'personal'])
            ->assignees()->sync([$this->outsider->id]);

        $res = $this->actingAs($this->other)->getJson(route('task-planner.kanban'))->assertOk()->json();
        $titles = fn ($col) => array_column($res['columns'][$col], 'title');

        $this->assertSame(['A todo'], $titles('todo'));
        $this->assertSame(['F visit'], $titles('in_progress'), 'Task B bukan milik/assignee Maidin.');
        $this->assertSame(['C waiting'], $titles('waiting_approval'));
        $this->assertSame(['D done baru'], $titles('done'));
        $this->assertSame(30, $res['done_days']);
        $this->assertTrue($res['columns']['in_progress'][0]['needs_detail']);
        $this->assertFalse($res['columns']['todo'][0]['is_creator']);
        $this->assertTrue($res['columns']['todo'][0]['is_assignee']);

        // Filter pencarian ikut berlaku.
        $res = $this->actingAs($this->other)->getJson(route('task-planner.kanban', ['q' => 'waiting']))->json();
        $this->assertSame(['C waiting'], array_column($res['columns']['waiting_approval'], 'title'));
        $this->assertSame([], $res['columns']['todo']);
    }

    public function test_only_creator_or_assignee_can_transition(): void
    {
        $task = Task::create(['creator_id' => $this->creator->id, 'category_id' => $this->general->id, 'title' => 'Transisi',
            'due_date' => now()->toDateString(), 'status' => 'todo', 'requires_approval' => true, 'alert_type' => 'none', 'alert_target' => 'personal']);
        $task->assignees()->sync([$this->other->id]);

        $this->actingAs($this->outsider)->postJson(route('task-planner.transition', $task->id), ['status' => 'in_progress'])->assertStatus(403);
        $this->assertSame('todo', $task->fresh()->status);

        $this->actingAs($this->other)->postJson(route('task-planner.transition', $task->id), ['status' => 'in_progress'])->assertOk();
        $this->actingAs($this->other)->postJson(route('task-planner.transition', $task->id), ['status' => 'done'])->assertOk();
        $this->assertSame('waiting_approval', $task->fresh()->status, 'Assignee menandai Done -> menunggu approval pembuat.');
    }

    public function test_index_renders_view_toggle(): void
    {
        $this->actingAs($this->creator)->get(route('task-planner.index'))->assertOk()
            ->assertSee('data-view="kanban"', false)
            ->assertSee('Sortable.min.js', false);
    }
}

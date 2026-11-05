<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPlannerProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TaskCategory $category;

    private AccountCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'creator',
            'email' => 'creator@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $this->category = TaskCategory::create(['name' => 'Maintenance']);
        $this->company = AccountCompany::create(['account_name' => 'PT Hybrid Energi', 'status' => 'Active']);
    }

    private function project(string $number, string $status = Project::STATUS_ACTIVE, float $net = 0): Project
    {
        return Project::create([
            'project_number' => $number,
            'project_name' => 'Hybrid '.$number,
            'company_id' => $this->company->id,
            'po_number_company' => 'PO-'.$number,
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-01',
            'po_due_date' => '2026-11-01',
            'total_sale_amount' => $net,
            'net_sale_amount' => $net,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Maintenance bulanan',
            'category_id' => $this->category->id,
            'due_date' => now()->addDays(2)->toDateString(),
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ], $overrides);
    }

    public function test_store_links_active_project(): void
    {
        $project = $this->project('PRJ-1');

        $this->actingAs($this->admin)->postJson(route('task-planner.store'), $this->payload(['project_id' => $project->id]))
            ->assertOk();

        $this->assertSame($project->id, Task::firstOrFail()->project_id);
    }

    public function test_store_rejects_inactive_project(): void
    {
        $project = $this->project('PRJ-2', Project::STATUS_COMPLETED);

        $this->actingAs($this->admin)->postJson(route('task-planner.store'), $this->payload(['project_id' => $project->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_update_keeps_existing_inactive_project_and_can_clear_it(): void
    {
        $project = $this->project('PRJ-3');
        $this->actingAs($this->admin)->postJson(route('task-planner.store'), $this->payload(['project_id' => $project->id]))->assertOk();
        $task = Task::firstOrFail();
        $project->update(['status' => Project::STATUS_COMPLETED]);

        $this->actingAs($this->admin)->putJson(route('task-planner.update', $task->id), $this->payload([
            'status' => 'todo', 'project_id' => $project->id,
        ]))->assertOk();
        $this->assertSame($project->id, $task->fresh()->project_id);

        // Select dikosongkan -> field tidak terkirim -> kaitan dilepas.
        $this->actingAs($this->admin)->putJson(route('task-planner.update', $task->id), $this->payload(['status' => 'todo']))->assertOk();
        $this->assertNull($task->fresh()->project_id);
    }

    public function test_fetch_projects_returns_only_active(): void
    {
        $this->project('PRJ-A');
        $this->project('PRJ-B', Project::STATUS_CANCELLED);

        $this->actingAs($this->admin)->getJson(route('task-planner.fetch-projects', ['q' => 'Hybrid']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.text', 'PRJ-A — Hybrid PRJ-A · PT Hybrid Energi');
    }

    public function test_fetch_projects_open_to_task_planner_users_without_project_access(): void
    {
        $module = Module::create(['module_code' => 'MOD_TASK_PLANNER', 'module_name' => 'Task Planner', 'route_name' => 'task-planner', 'icon' => 'fa fa-tasks', 'group' => 'CRM']);
        Module::create(['module_code' => 'MOD_PROJECT', 'module_name' => 'Project', 'route_name' => 'project', 'icon' => 'fa fa-x', 'group' => 'Admin']);
        $user = User::create(['username' => 'staff', 'email' => 'staff@has.com', 'password' => bcrypt('secret'), 'role' => 'User']);
        UserAccessControl::create(['user_id' => $user->id, 'module_id' => $module->id, 'can_read' => true, 'can_create' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => false]);
        $this->project('PRJ-C');

        $this->actingAs($user)->getJson(route('task-planner.fetch-projects'))->assertOk()->assertJsonCount(1, 'results');
    }

    public function test_data_filter_and_project_pages_list_tasks(): void
    {
        $a = $this->project('PRJ-X');
        $b = $this->project('PRJ-Y');
        $this->actingAs($this->admin)->postJson(route('task-planner.store'), $this->payload(['title' => 'Task X', 'project_id' => $a->id]))->assertOk();
        $this->actingAs($this->admin)->postJson(route('task-planner.store'), $this->payload(['title' => 'Task Y', 'project_id' => $b->id]))->assertOk();
        $this->actingAs($this->admin)->postJson(route('task-planner.store'), $this->payload(['title' => 'Task tanpa project']))->assertOk();

        $this->actingAs($this->admin)->getJson(route('task-planner.data', ['project_id' => $a->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.project_number', 'PRJ-X');

        $this->actingAs($this->admin)->get(route('project.show', $a->id))->assertOk()->assertSee('Task X')->assertDontSee('Task Y');
        // Detail IMS hanya menampilkan task divisi IMS; admin tanpa divisi IMS tidak terhitung.
        $this->actingAs($this->admin)->get(route('ims-project.show', $a->id))->assertOk()->assertDontSee('Task X');

        $task = Task::where('title', 'Task X')->firstOrFail();
        $this->actingAs($this->admin)->get(route('task-planner.show', $task->id))->assertOk()
            ->assertSee(route('project.show', $a->id));
        $this->actingAs($this->admin)->get(route('task-planner.edit', $task->id))->assertOk()->assertSee('PRJ-X');
        $this->actingAs($this->admin)->get(route('task-planner.index'))->assertOk();
    }

    public function test_add_task_button_follows_task_planner_permission_and_project_status(): void
    {
        $active = $this->project('PRJ-T1');
        $done = $this->project('PRJ-T2', Project::STATUS_COMPLETED);

        $this->actingAs($this->admin)->get(route('ims-project.show', $active->id))->assertOk()
            ->assertSee('ptkModal', false)->assertSee('Tambah Task');
        $this->actingAs($this->admin)->get(route('project.show', $active->id))->assertOk()->assertSee('ptkModal', false);
        $this->actingAs($this->admin)->get(route('ims-project.show', $done->id))->assertOk()
            ->assertDontSee('ptkModal', false)->assertSee('Task baru hanya untuk project Active');

        // User IMS tanpa akses Task Planner: tidak ada tombol tambah task.
        $ims = Module::create(['module_code' => 'MOD_IMS_PROJECT', 'module_name' => 'IMS Project', 'route_name' => 'ims-project', 'icon' => 'fa fa-x', 'group' => 'IMS']);
        Module::create(['module_code' => 'MOD_TASK_PLANNER', 'module_name' => 'Task Planner', 'route_name' => 'task-planner', 'icon' => 'fa fa-tasks', 'group' => 'CRM']);
        $user = User::create(['username' => 'imsonly', 'email' => 'imsonly@has.com', 'password' => bcrypt('secret'), 'role' => 'User']);
        UserAccessControl::create(['user_id' => $user->id, 'module_id' => $ims->id, 'can_read' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_approve' => false]);

        $this->actingAs($user)->get(route('ims-project.show', $active->id))->assertOk()->assertDontSee('ptkModal', false);
    }

    public function test_ims_page_only_lists_tasks_of_ims_division(): void
    {
        $ims = Division::create(['division_name' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);
        $water = Division::create(['division_name' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $imsUser = User::create(['username' => 'imsstaff', 'email' => 'imsstaff@has.com', 'password' => bcrypt('secret'), 'role' => 'Admin', 'division_id' => $ims->id]);
        $waterUser = User::create(['username' => 'waterstaff', 'email' => 'waterstaff@has.com', 'password' => bcrypt('secret'), 'role' => 'Admin', 'division_id' => $water->id]);
        $project = $this->project('PRJ-D');

        $this->actingAs($imsUser)->postJson(route('task-planner.store'), $this->payload(['title' => 'Dibuat IMS', 'project_id' => $project->id]))->assertOk();
        $this->actingAs($waterUser)->postJson(route('task-planner.store'), $this->payload(['title' => 'Ditugaskan ke IMS', 'project_id' => $project->id, 'assignees' => [$imsUser->id]]))->assertOk();
        $this->actingAs($waterUser)->postJson(route('task-planner.store'), $this->payload(['title' => 'Task Water saja', 'project_id' => $project->id]))->assertOk();

        $this->actingAs($this->admin)->get(route('ims-project.show', $project->id))->assertOk()
            ->assertSee('Dibuat IMS')
            ->assertSee('Ditugaskan ke IMS')
            ->assertDontSee('Task Water saja')
            ->assertSee('Hanya task yang dibuat oleh / ditugaskan ke divisi IMS');

        // Detail Project utama tetap menampilkan semua task.
        $this->actingAs($this->admin)->get(route('project.show', $project->id))->assertOk()
            ->assertSee('Dibuat IMS')->assertSee('Task Water saja');
    }
}

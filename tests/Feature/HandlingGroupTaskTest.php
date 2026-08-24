<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\HandlingGroup;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandlingGroupTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Division $water;

    private Division $pd;

    private TaskCategory $quote;

    private User $maidin;

    private User $frida;

    private User $abu;

    private User $maya;

    private HandlingGroup $waterGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'superadmin',
            'email' => 'admin@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $this->water = Division::create([
            'division_name' => 'WATER',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->pd = Division::create([
            'division_name' => 'PD',
            'description' => 'PD Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->quote = TaskCategory::create([
            'name' => 'Quote',
            'use_division_handler' => true,
        ]);

        $this->maidin = User::create(['username' => 'maidin', 'email' => 'maidin@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->water->id]);
        $this->frida = User::create(['username' => 'frida', 'email' => 'frida@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->water->id]);
        $this->abu = User::create(['username' => 'abu', 'email' => 'abu@has.com', 'password' => bcrypt('secret'), 'division_id' => $this->pd->id]);
        $this->maya = User::create(['username' => 'maya', 'email' => 'maya@has.com', 'password' => bcrypt('secret')]);

        $this->waterGroup = HandlingGroup::create(['name' => 'WATER']);
        $this->waterGroup->users()->sync([$this->maidin->id, $this->frida->id, $this->abu->id, $this->maya->id]);
    }

    public function test_fetch_handling_group_users_returns_roster(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('task-planner.fetch-handling-group-users').'?handling_group_id='.$this->waterGroup->id);

        $response->assertOk()
            ->assertJsonCount(4, 'results')
            ->assertJsonFragment(['id' => $this->abu->id])
            ->assertJsonFragment(['id' => $this->maya->id]);
    }

    public function test_store_task_with_handling_group_syncs_roster_into_assignees(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('task-planner.store'), [
            'title' => 'Quote Water Handling',
            'category_id' => $this->quote->id,
            'handling_group_id' => $this->waterGroup->id,
            'due_date' => '2026-08-20',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $task = Task::latest('id')->firstOrFail();

        $this->assertSame($this->waterGroup->id, $task->handling_group_id);
        $this->assertSame($this->waterGroup->id, $task->handlingGroup->id);

        $assigneeIds = $task->assignees()->pluck('users.id')->all();
        $this->assertContains($this->maidin->id, $assigneeIds);
        $this->assertContains($this->frida->id, $assigneeIds);
        $this->assertContains($this->abu->id, $assigneeIds);
        $this->assertContains($this->maya->id, $assigneeIds);
    }

    public function test_update_task_without_handling_group_keeps_manual_assignees(): void
    {
        $task = Task::create([
            'creator_id' => $this->admin->id,
            'category_id' => $this->quote->id,
            'title' => 'Task Biasa',
            'status' => 'todo',
            'due_date' => '2026-08-20',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ]);
        $task->assignees()->sync([$this->maidin->id]);

        $response = $this->actingAs($this->admin)->putJson(route('task-planner.update', $task->id), [
            'title' => 'Task Biasa Updated',
            'category_id' => $this->quote->id,
            'due_date' => '2026-08-21',
            'status' => 'todo',
            'alert_type' => 'none',
            'alert_target' => 'personal',
            'assignees' => [$this->maidin->id],
        ]);

        $response->assertOk();

        $task->refresh();
        $this->assertNull($task->handling_group_id);
        $this->assertSame([$this->maidin->id], $task->assignees()->pluck('users.id')->all());
    }

    public function test_opportunity_store_task_with_handling_group_syncs_roster(): void
    {
        $company = AccountCompany::create([
            'account_name' => 'PT Has Water',
            'status' => 'Active',
        ]);

        $opportunity = Opportunity::create([
            'opportunity_name' => 'Opportunity Quote',
            'account_companies_id' => $company->id,
            'owner_id' => $this->admin->id,
            'probability' => 50,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('opportunity-management.tasks.store', $opportunity->id),
            [
                'title' => 'Quote Handling Task',
                'category_id' => $this->quote->id,
                'handling_group_id' => $this->waterGroup->id,
                'due_date' => '2026-08-20',
                'alert_type' => 'none',
                'alert_target' => 'personal',
            ]
        );

        $response->assertOk();

        $task = Task::latest('id')->firstOrFail();

        $this->assertSame($this->waterGroup->id, $task->handling_group_id);
        $assigneeIds = $task->assignees()->pluck('users.id')->all();
        $this->assertContains($this->maidin->id, $assigneeIds);
        $this->assertContains($this->frida->id, $assigneeIds);
        $this->assertContains($this->abu->id, $assigneeIds);
        $this->assertContains($this->maya->id, $assigneeIds);
    }

    public function test_handling_groups_config_endpoints(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('configuration.list', 'handling-groups'));

        $response->assertOk()
            ->assertJsonFragment(['name' => 'WATER'])
            ->assertJsonFragment(['members' => 'maidin, frida, abu, maya']);

        $store = $this->actingAs($this->admin)->postJson(
            route('configuration.store', 'handling-groups'),
            ['name' => 'Water+IMS', 'user_ids' => [$this->maidin->id]]
        );

        $store->assertOk();

        $newGroup = HandlingGroup::where('name', 'Water+IMS')->firstOrFail();
        $this->assertSame(
            [$this->maidin->id],
            $newGroup->users()->pluck('users.id')->all()
        );

        $update = $this->actingAs($this->admin)->putJson(
            route('configuration.update', ['handling-groups', $newGroup->id]),
            ['name' => 'Water+IMS', 'user_ids' => [$this->maidin->id, $this->maya->id]]
        );

        $update->assertOk();
        $this->assertSame(
            [$this->maidin->id, $this->maya->id],
            $newGroup->fresh()->users()->pluck('users.id')->all()
        );

        $destroy = $this->actingAs($this->admin)->deleteJson(
            route('configuration.destroy', ['handling-groups', $newGroup->id])
        );

        $destroy->assertOk();
        $this->assertNull(HandlingGroup::find($newGroup->id));
    }
}

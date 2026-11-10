<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Activity;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpportunityTaskLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $assignee;

    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::fake();

        $module = Module::create([
            'module_code' => 'MOD_OPPORTUNITY_MANAGEMENT',
            'module_name' => 'Opportunity Management',
            'route_name' => 'opportunity-management',
            'icon' => 'fa fa-chart-line',
            'group' => 'CRM',
        ]);

        $this->user = User::create([
            'username' => 'opp_log_admin',
            'email' => 'opp_log_admin@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        UserAccessControl::create([
            'user_id' => $this->user->id,
            'module_id' => $module->id,
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => true,
            'can_approve' => false,
        ]);

        $this->assignee = User::create([
            'username' => 'opp_log_assignee',
            'email' => 'opp_log_assignee@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
        ]);

        $company = AccountCompany::create(['account_name' => 'PT Log Opportunity', 'status' => 'Active']);

        $this->opportunity = Opportunity::create([
            'opportunity_name' => 'Peluang Log',
            'account_companies_id' => $company->id,
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_log_category_task_file_and_description_become_task_activity(): void
    {
        $category = TaskCategory::create(['name' => 'Log Activity']);
        $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->post(route('opportunity-management.tasks.store', $this->opportunity->id), [
                'title' => 'Log kunjungan',
                'description' => 'Hasil kunjungan lapangan.',
                'category_id' => $category->id,
                'due_date' => '2020-01-01',
                'time' => '09:00',
                'assignees' => [$this->assignee->id],
                'alert_type' => 'none',
                'alert_target' => 'personal',
                'attachments' => [$file],
            ])
            ->assertOk();

        $task = Task::firstOrFail();
        $this->assertSame('done', $task->status);
        $this->assertSame(now()->toDateString(), $task->due_date->toDateString());
        $this->assertNull($task->time);
        $this->assertFalse($task->requires_approval);
        $this->assertFalse($task->assignees()->where('users.id', $this->assignee->id)->exists());

        $activity = Activity::where('task_id', $task->id)->firstOrFail();
        $this->assertSame(0, $activity->attachments()->count());

        $taskActivity = TaskActivity::where('task_id', $task->id)->firstOrFail();
        $this->assertSame('Hasil kunjungan lapangan.', $taskActivity->content);

        $attachment = $taskActivity->attachments()->firstOrFail();
        $this->assertSame('bukti.pdf', $attachment->attachment_name);
        Storage::disk('public')->assertExists($attachment->attachment_path);

        $response = $this->actingAs($this->user)
            ->getJson(route('opportunity-management.activities.fetch', $this->opportunity->id))
            ->assertOk();

        $parent = collect($response->json('data'))->firstWhere('task_id', $task->id);
        $this->assertNotNull($parent);

        $child = collect($parent['replies'])->firstWhere('from_task', true);
        $this->assertNotNull($child);
        $this->assertSame('Hasil kunjungan lapangan.', $child['content']);
        $this->assertSame('bukti.pdf', $child['attachments'][0]['attachment_name']);
    }

    public function test_non_log_category_keeps_normal_task_flow(): void
    {
        $category = TaskCategory::create(['name' => 'Follow Up']);

        $this->actingAs($this->user)
            ->post(route('opportunity-management.tasks.store', $this->opportunity->id), [
                'title' => 'Follow up biasa',
                'category_id' => $category->id,
                'due_date' => '2026-08-20',
                'time' => '10:30',
                'alert_type' => 'none',
                'alert_target' => 'personal',
            ])
            ->assertOk();

        $task = Task::firstOrFail();
        $this->assertSame('todo', $task->status);
        $this->assertSame('2026-08-20', $task->due_date->toDateString());
        $this->assertSame('10:30', $task->time);
        $this->assertSame(0, TaskActivity::where('task_id', $task->id)->count());
    }
}

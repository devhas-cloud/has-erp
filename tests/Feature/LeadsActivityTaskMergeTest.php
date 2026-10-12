<?php

namespace Tests\Feature;

use App\Models\AccountContact;
use App\Models\Activity;
use App\Models\Division;
use App\Models\Lead;
use App\Models\Module;
use App\Models\QuoteConfiguration;
use App\Models\Source;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskActivityAttachment;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadsActivityTaskMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $module = Module::create([
            'module_code' => 'MOD_LEADS_MANAGEMENT',
            'module_name' => 'Leads Management',
            'route_name' => 'leads-management',
            'icon' => 'fa fa-bullhorn',
            'group' => 'CRM',
        ]);

        $this->user = User::create([
            'username' => 'leadsupport',
            'email' => 'leads@has.com',
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

        $source = Source::create(['source_name' => 'Website']);
        $contact = AccountContact::create(['full_name' => 'Budi Santoso', 'status' => 'Inactive']);

        $this->lead = Lead::create([
            'lead_status' => 'New',
            'lead_title' => 'Proyek Instalasi Meter',
            'account_contacts_id' => $contact->id,
            'source_id' => $source->id,
            'lead_owner_id' => $this->user->id,
        ]);
    }

    public function test_fetch_activities_nests_task_activities_and_related_badge(): void
    {
        Storage::fake('public');

        $category = TaskCategory::create(['name' => 'Quote', 'use_division_handler' => true]);
        $task = Task::create([
            'creator_id' => $this->user->id,
            'category_id' => $category->id,
            'lead_id' => $this->lead->id,
            'title' => 'Kunjungan Survei',
            'start_date' => '2026-10-09 08:00:00',
            'due_date' => '2026-10-10 17:00:00',
            'status' => 'in_progress',
        ]);

        $parent = Activity::create([
            'lead_id' => $this->lead->id,
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'content' => "📋 Created task: {$task->title}",
        ]);

        $water = Division::create(['division_name' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $config = QuoteConfiguration::create([
            'division_id' => $water->id,
            'task_id' => $task->id,
            'created_by' => $this->user->id,
            'status' => QuoteConfiguration::STATUS_DRAFT,
        ]);

        $post = TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'content' => 'Hasil survei lapangan.',
        ]);
        TaskActivityAttachment::create([
            'task_activity_id' => $post->id,
            'attachment_path' => 'task-activities/survei.pdf',
            'attachment_type' => 'file',
            'attachment_name' => 'survei.pdf',
        ]);

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'content' => 'Dokumen revisi disusul.',
            'reply_to_id' => $post->id,
        ]);

        $activities = $this->actingAs($this->user)
            ->get(route('leads-management.activities.fetch', $this->lead->id))
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $activities);
        $this->assertSame($parent->id, $activities[0]['id']);

        $replies = collect($activities[0]['replies']);
        $this->assertSame(2, $replies->count());
        $taskReplies = $replies->where('from_task', true);
        $this->assertSame(2, $taskReplies->count());
        $this->assertTrue($taskReplies->contains(fn ($r) => $r['content'] === 'Hasil survei lapangan.'));
        $this->assertTrue($taskReplies->contains(fn ($r) => $r['content'] === 'Dokumen revisi disusul.'));
        $this->assertStringStartsWith('ta-', $taskReplies->first()['id']);
        $this->assertSame($task->id, $taskReplies->first()['task_id']);
        $this->assertCount(1, $taskReplies->first()['attachments']);
        $this->assertSame('survei.pdf', $taskReplies->first()['attachments'][0]['attachment_name']);

        $this->assertSame('quote', $activities[0]['related']['type']);
        $this->assertSame('Quote Water #'.$config->id, $activities[0]['related']['label']);
        $this->assertStringContainsString('water-configuration/', $activities[0]['related']['url']);
    }
}
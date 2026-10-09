<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Activity;
use App\Models\Division;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\QuoteConfiguration;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskActivityAttachment;
use App\Models\TaskCategory;
use App\Models\TaskProposal;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpportunityActivityTaskMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Opportunity $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        $module = Module::create([
            'module_code' => 'MOD_OPPORTUNITY_MANAGEMENT',
            'module_name' => 'Opportunity Management',
            'route_name' => 'opportunity-management',
            'icon' => 'fa fa-chart-line',
            'group' => 'CRM',
        ]);

        $this->user = User::create([
            'username' => 'creator',
            'email' => 'creator@has.com',
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

        $company = AccountCompany::create(['account_name' => 'PT Maju Bersama', 'status' => 'Active']);

        $this->opportunity = Opportunity::create([
            'opportunity_name' => 'Peluang Air Bersih',
            'account_companies_id' => $company->id,
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_fetch_activities_nests_task_activities_under_parent_activity(): void
    {
        Storage::fake('public');

        $category = TaskCategory::create(['name' => 'General']);
        $task = Task::create([
            'creator_id' => $this->user->id,
            'category_id' => $category->id,
            'opportunity_id' => $this->opportunity->id,
            'title' => 'Kunjungan Lapangan',
            'start_date' => '2026-10-09 08:00:00',
            'due_date' => '2026-10-10 17:00:00',
            'status' => 'in_progress',
        ]);

        // Parent opportunity activity yang terhubung ke task (dibuat saat storeTask).
        $parent = Activity::create([
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'content' => "📋 Created task: {$task->title}",
        ]);

        // Quote configuration yang dibikin dari task utk badge "Quote terkait".
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

        // Reply task activity ikut tampil (flatten) sebagai child.
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'content' => 'Dokumen revisi disusul.',
            'reply_to_id' => $post->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('opportunity-management.activities.fetch', $this->opportunity->id))
            ->assertOk();

        $activities = $response->json('data');
        $this->assertCount(1, $activities);
        $this->assertSame($parent->id, $activities[0]['id']);

        // Badge terkait: quote dari task (Q2: proposal ATAU quote).
        $this->assertIsArray($activities[0]['related']);
        $this->assertSame('quote', $activities[0]['related']['type']);
        $this->assertSame('Quote Water #'.$config->id, $activities[0]['related']['label']);
        $this->assertStringContainsString('water-configuration/', $activities[0]['related']['url']);

        $replies = collect($activities[0]['replies']);
        $this->assertSame(2, $replies->count());

        $taskReplies = $replies->where('from_task', true);
        $this->assertSame(2, $taskReplies->count());
        $this->assertTrue($taskReplies->contains(fn ($r) => $r['content'] === 'Hasil survei lapangan.'));
        $this->assertTrue($taskReplies->contains(fn ($r) => $r['content'] === 'Dokumen revisi disusul.'));

        $first = $taskReplies->first();
        $this->assertStringStartsWith('ta-', $first['id']);
        $this->assertSame($task->id, $first['task_id']);
        $this->assertSame($this->user->display_name, $first['user']['display_name']);
        $this->assertCount(1, $first['attachments']);
        $this->assertSame('survei.pdf', $first['attachments'][0]['attachment_name']);
        $this->assertSame('file', $first['attachments'][0]['attachment_type']);
    }

    public function test_done_quote_task_shows_quotation_pdf_badge(): void
    {
        $category = TaskCategory::create(['name' => 'Quote']);
        $task = Task::create([
            'creator_id' => $this->user->id,
            'category_id' => $category->id,
            'opportunity_id' => $this->opportunity->id,
            'title' => 'Penawaran Meter',
            'start_date' => '2026-10-09 08:00:00',
            'due_date' => '2026-10-10 17:00:00',
            'status' => 'done',
        ]);

        Activity::create([
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'content' => "📋 Created task: {$task->title}",
        ]);

        $quotation = Quotation::create([
            'task_id' => $task->id,
            'status' => Quotation::STATUS_APPROVED,
            'created_by' => $this->user->id,
        ]);

        $activities = $this->actingAs($this->user)
            ->get(route('opportunity-management.activities.fetch', $this->opportunity->id))
            ->assertOk()
            ->json('data');

        $this->assertSame('quote_pdf', $activities[0]['related']['type']);
        $this->assertSame('Quote Penawaran (PDF)', $activities[0]['related']['label']);
        $this->assertStringEndsWith('/quotation/'.$quotation->id.'/pdf', $activities[0]['related']['url']);
    }

    public function test_done_proposal_task_shows_latest_proposal_pdf_badge(): void
    {
        $category = TaskCategory::create(['name' => 'Proposal']);
        $task = Task::create([
            'creator_id' => $this->user->id,
            'category_id' => $category->id,
            'opportunity_id' => $this->opportunity->id,
            'title' => 'Proposal SPARING',
            'start_date' => '2026-10-09 08:00:00',
            'due_date' => '2026-10-10 17:00:00',
            'status' => 'done',
        ]);

        Activity::create([
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'content' => "📋 Created task: {$task->title}",
        ]);

        TaskProposal::create([
            'task_id' => $task->id,
            'file_path' => 'task-proposals/proposal.pdf',
            'original_name' => 'proposal.pdf',
            'version' => 1,
            'uploaded_by' => $this->user->id,
        ]);
        $latest = TaskProposal::create([
            'task_id' => $task->id,
            'file_path' => 'task-proposals/proposal-v2.pdf',
            'original_name' => 'proposal-v2.pdf',
            'version' => 2,
            'uploaded_by' => $this->user->id,
        ]);

        $activities = $this->actingAs($this->user)
            ->get(route('opportunity-management.activities.fetch', $this->opportunity->id))
            ->assertOk()
            ->json('data');

        $this->assertSame('proposal_pdf', $activities[0]['related']['type']);
        $this->assertSame('Proposal (PDF) v2', $activities[0]['related']['label']);
        $this->assertStringContainsString('/proposals/'.$latest->id.'/view', $activities[0]['related']['url']);
    }
}
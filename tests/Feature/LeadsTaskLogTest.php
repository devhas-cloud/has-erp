<?php

namespace Tests\Feature;

use App\Models\AccountContact;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Source;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadsTaskLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::fake();

        $this->user = User::create([
            'username' => 'log_admin',
            'email' => 'log_admin@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $contact = AccountContact::create([
            'full_name' => 'Budi Santoso',
            'email' => 'budi-log@example.com',
            'mobile' => '81234567890',
        ]);

        $source = Source::create(['source_name' => 'Website', 'status' => 'Active']);

        $this->lead = Lead::create([
            'lead_status' => 'New',
            'lead_title' => 'Prospek Log',
            'account_contacts_id' => $contact->id,
            'source_id' => $source->id,
            'lead_owner_id' => $this->user->id,
        ]);
    }

    public function test_log_category_task_file_and_description_become_task_activity(): void
    {
        $category = TaskCategory::create(['name' => 'Log Activity']);
        $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->post(route('leads-management.tasks.store', $this->lead->id), [
                'title' => 'Kirim laporan',
                'description' => 'Laporan kegiatan lapangan.',
                'category_id' => $category->id,
                'due_date' => '2020-01-01',
                'time' => '09:00',
                'assignees' => [$this->user->id],
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

        $activity = Activity::where('task_id', $task->id)->firstOrFail();
        $this->assertSame(0, $activity->attachments()->count());

        $taskActivity = TaskActivity::where('task_id', $task->id)->firstOrFail();
        $this->assertSame('Laporan kegiatan lapangan.', $taskActivity->content);

        $attachment = $taskActivity->attachments()->firstOrFail();
        $this->assertSame('bukti.pdf', $attachment->attachment_name);
        Storage::disk('public')->assertExists($attachment->attachment_path);

        $response = $this->actingAs($this->user)
            ->getJson(route('leads-management.activities.fetch', $this->lead->id))
            ->assertOk();

        $parent = collect($response->json('data'))->firstWhere('task_id', $task->id);
        $this->assertNotNull($parent);

        $child = collect($parent['replies'])->firstWhere('from_task', true);
        $this->assertNotNull($child);
        $this->assertSame('Laporan kegiatan lapangan.', $child['content']);
        $this->assertSame('bukti.pdf', $child['attachments'][0]['attachment_name']);
    }

    public function test_non_log_category_keeps_normal_task_flow(): void
    {
        $category = TaskCategory::create(['name' => 'Follow Up']);
        $file = UploadedFile::fake()->create('abaikan.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->post(route('leads-management.tasks.store', $this->lead->id), [
                'title' => 'Follow up biasa',
                'category_id' => $category->id,
                'due_date' => '2026-08-20',
                'time' => '10:30',
                'alert_type' => 'none',
                'alert_target' => 'personal',
                'attachments' => [$file],
            ])
            ->assertOk();

        $task = Task::firstOrFail();
        $this->assertSame('todo', $task->status);
        $this->assertSame('2026-08-20', $task->due_date->toDateString());
        $this->assertSame('10:30', $task->time);

        $activity = Activity::where('task_id', $task->id)->firstOrFail();
        $this->assertSame(0, $activity->attachments()->count());
        $this->assertSame(0, TaskActivity::where('task_id', $task->id)->count());
    }
}

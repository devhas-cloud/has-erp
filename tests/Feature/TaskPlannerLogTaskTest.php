<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskPlannerLogTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_log_task_forces_now_done_and_moves_description_file_to_activity(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 14:30:00'));
        Storage::fake('public');
        Http::fake();
        config([
            'services.wabaileys.url' => 'https://wa.test',
            'services.wabaileys.api_key' => 'key',
            'services.wabaileys.sender_phone' => '628111',
        ]);

        $creator = User::create([
            'username' => 'creator-log',
            'email' => 'creator-log@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $assignee = User::create([
            'username' => 'assignee-log',
            'email' => 'assignee-log@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081234567890',
        ]);

        $category = TaskCategory::create(['name' => 'Log Activity']);
        $file = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

        $this->actingAs($creator)->post(route('task-planner.store'), [
            'title' => 'Log kunjungan',
            'description' => 'Hasil kunjungan lapangan.',
            'category_id' => $category->id,
            'due_date' => '2020-01-01',
            'time' => '09:00',
            'assignees' => [$assignee->id],
            'alert_type' => 'whatsapp',
            'alert_target' => 'personal',
            'attachments' => [$file],
        ])->assertOk();

        $task = Task::firstOrFail();
        $this->assertSame('done', $task->status);
        $this->assertSame('2026-11-11', $task->due_date->toDateString());
        $this->assertSame('14:30', $task->time);
        $this->assertSame('none', $task->alert_type);
        $this->assertFalse($task->requires_approval);
        $this->assertTrue($task->assignees()->where('users.id', $creator->id)->exists());
        $this->assertFalse($task->assignees()->where('users.id', $assignee->id)->exists());

        $activity = TaskActivity::where('task_id', $task->id)->firstOrFail();
        $this->assertSame('Hasil kunjungan lapangan.', $activity->content);

        $attachment = $activity->attachments()->firstOrFail();
        $this->assertSame('bukti.pdf', $attachment->attachment_name);
        Storage::disk('public')->assertExists($attachment->attachment_path);

        $this->assertSame(0, Notification::where('user_id', $assignee->id)->count());
        Http::assertNothingSent();
    }
}

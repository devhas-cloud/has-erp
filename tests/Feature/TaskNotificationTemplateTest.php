<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\TaskAlertService;
use App\Services\TaskWhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaskNotificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function configureGateway(): void
    {
        config([
            'services.wabaileys.url' => 'https://wa.test',
            'services.wabaileys.api_key' => 'key',
            'services.wabaileys.sender_phone' => '628111',
        ]);
    }

    private function makeTask(array $attributes = []): Task
    {
        $creator = User::create([
            'username' => 'creator',
            'full_name' => 'Anasiah Khaairunnisa',
            'email' => 'creator@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $company = AccountCompany::create([
            'account_name' => 'PT Contoh Nusantara',
            'status' => 'Active',
        ]);

        $opportunity = Opportunity::create([
            'opportunity_name' => 'Opportunity Contoh',
            'account_companies_id' => $company->id,
            'owner_id' => $creator->id,
            'probability' => 50,
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        return Task::create(array_merge([
            'creator_id' => $creator->id,
            'opportunity_id' => $opportunity->id,
            'category_id' => $category->id,
            'title' => 'Other Cost Sparing (selain sensor)',
            'description' => 'Konfigurasi Water',
            'due_date' => today()->format('Y-m-d'),
            'status' => 'todo',
            'alert_type' => 'whatsapp',
            'alert_target' => 'personal',
        ], $attributes));
    }

    public function test_template_tugas_baru_memuat_perusahaan_dan_link(): void
    {
        $this->configureGateway();
        Http::fake();

        $assignee = User::create([
            'username' => 'assignee',
            'email' => 'assignee@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081234567890',
        ]);

        $task = $this->makeTask();

        app(TaskWhatsAppNotifier::class)->notifyNewTask($task, [$assignee]);

        Http::assertSent(function ($request) use ($task) {
            $message = $request['message'];

            return str_ends_with($request->url(), '/api/send')
                && str_contains($message, 'INFORMASI TUGAS BARU')
                && str_contains($message, 'Judul: Other Cost Sparing (selain sensor)')
                && str_contains($message, 'Perusahaan: PT Contoh Nusantara')
                && str_contains($message, 'Kategori: Quote')
                && str_contains($message, 'Dari: Anasiah Khaairunnisa')
                && str_contains($message, $task->whatsappUrl());
        });
    }

    public function test_template_reminder_tugas_memuat_perusahaan_kategori_dan_link(): void
    {
        $this->configureGateway();
        Http::fake();

        $assignee = User::create([
            'username' => 'assignee-reminder',
            'email' => 'assignee-reminder@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081234567891',
        ]);

        $task = $this->makeTask();
        $task->assignees()->sync([$assignee->id]);

        app(TaskAlertService::class)->sendAlerts();

        Http::assertSent(function ($request) use ($task) {
            $message = $request['message'];

            return str_ends_with($request->url(), '/api/send')
                && str_contains($message, 'REMINDER TUGAS')
                && str_contains($message, 'Tugas anda mendekati deadline.')
                && str_contains($message, 'Perusahaan: PT Contoh Nusantara')
                && str_contains($message, 'Kategori: Quote')
                && str_contains($message, $task->whatsappUrl());
        });
    }

    public function test_reminder_approval_dikirim_sekali_ke_creator_dengan_keterangan_dan_link(): void
    {
        $this->configureGateway();
        Http::fake();

        $task = $this->makeTask();
        $task->creator->update(['phone_number' => '081234567892']);

        $assignee = User::create([
            'username' => 'assignee-approval',
            'email' => 'assignee-approval@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081234567893',
        ]);
        $task->assignees()->sync([$assignee->id]);

        $task->update(['status' => 'waiting_approval']);
        $task->update(['status' => 'waiting_approval']);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($task) {
            $message = $request['message'];

            return str_ends_with($request->url(), '/api/send')
                && $request['to'] === '6281234567892'
                && str_contains($message, 'REMINDER APPROVAL')
                && str_contains($message, 'Keterangan: Konfigurasi Water')
                && str_contains($message, $task->whatsappUrl());
        });
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send-group'));
    }
}

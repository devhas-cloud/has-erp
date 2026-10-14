<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\Division;
use App\Models\HandlingGroup;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Source;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\WhatsAppGroup;
use App\Services\TaskAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HandlingGroupWhatsAppTest extends TestCase
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

    private function makeTask(string $alertTarget, HandlingGroup $group, string $alertType = 'whatsapp'): Task
    {
        $creator = User::create([
            'username' => 'creator',
            'email' => 'creator@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $category = TaskCategory::create([
            'name' => 'Follow Up',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        return Task::create([
            'creator_id' => $creator->id,
            'category_id' => $category->id,
            'handling_group_id' => $group->id,
            'title' => 'Cek penawaran',
            'due_date' => today()->format('Y-m-d'),
            'status' => 'todo',
            'alert_type' => $alertType,
            'alert_target' => $alertTarget,
        ]);
    }

    public function test_alert_group_dikirim_ke_grup_wa_penanganan(): void
    {
        $this->configureGateway();
        Http::fake();

        $division = Division::create([
            'division_name' => 'Water',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $group = HandlingGroup::create([
            'name' => 'Water',
            'division_id' => $division->id,
            'whatsapp_group_id' => '120363000000000000@g.us',
        ]);

        $task = $this->makeTask('group', $group);

        $result = app(TaskAlertService::class)->sendAlerts();

        $this->assertSame(1, $result['sent']);
        $this->assertTrue($task->fresh()->is_alert_sent);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group')
            && $request['groupJid'] === '120363000000000000@g.us');

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
    }

    public function test_alert_group_tanpa_jid_penanganan_di_skip(): void
    {
        $this->configureGateway();
        Http::fake();

        $division = Division::create([
            'division_name' => 'PD',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $group = HandlingGroup::create([
            'name' => 'PD',
            'division_id' => $division->id,
        ]);

        $this->makeTask('group', $group);

        $result = app(TaskAlertService::class)->sendAlerts();

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        Http::assertNothingSent();
    }

    public function test_reminder_penanganan_abaikan_alert_target_personal(): void
    {
        $this->configureGateway();
        Http::fake();

        $division = Division::create([
            'division_name' => 'Water',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $group = HandlingGroup::create([
            'name' => 'Water',
            'division_id' => $division->id,
            'whatsapp_group_id' => '120363111111111111@g.us',
        ]);

        // Assignee punya nomor HP, tapi task pakai penanganan -> personal diabaikan.
        $assignee = User::create([
            'username' => 'maidin',
            'email' => 'maidin@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081234567890',
        ]);

        $task = $this->makeTask('personal', $group);
        $task->assignees()->sync([$assignee->id]);

        $result = app(TaskAlertService::class)->sendAlerts();

        $this->assertSame(1, $result['sent']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group'));
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
    }

    public function test_reminder_task_tanpa_penanganan_tetap_personal(): void
    {
        $this->configureGateway();
        Http::fake();

        $creator = User::create([
            'username' => 'creator2',
            'email' => 'creator2@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $assignee = User::create([
            'username' => 'frida',
            'email' => 'frida@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081298765432',
        ]);

        $category = TaskCategory::create([
            'name' => 'Follow Up 2',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $task = Task::create([
            'creator_id' => $creator->id,
            'category_id' => $category->id,
            'title' => 'Task tanpa penanganan',
            'due_date' => today()->format('Y-m-d'),
            'status' => 'todo',
            'alert_type' => 'whatsapp',
            'alert_target' => 'personal',
        ]);
        $task->assignees()->sync([$assignee->id]);

        $result = app(TaskAlertService::class)->sendAlerts();

        $this->assertSame(1, $result['sent']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send')
            && $request['to'] === '6281298765432');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send-group'));
    }

    private function makeOpportunity(User $owner): Opportunity
    {
        $company = AccountCompany::create([
            'account_name' => 'PT Has Water',
            'status' => 'Active',
        ]);

        return Opportunity::create([
            'opportunity_name' => 'Opportunity Quote',
            'account_companies_id' => $company->id,
            'owner_id' => $owner->id,
            'probability' => 50,
        ]);
    }

    public function test_notif_task_baru_lead_tanpa_penanganan_kirim_personal(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin6',
            'email' => 'admin6@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $assignee = User::create([
            'username' => 'doni',
            'email' => 'doni@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081277778888',
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote Lead',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $lead = $this->makeLead($admin);

        $this->actingAs($admin)->postJson(
            route('leads-management.tasks.store', $lead->id),
            [
                'title' => 'Follow up lead',
                'category_id' => $category->id,
                'due_date' => '2026-08-20',
                'assignees' => [$assignee->id],
                'alert_type' => 'whatsapp',
                'alert_target' => 'personal',
            ]
        )->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send')
            && $request['to'] === '6281277778888');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send-group'));
    }

    public function test_notif_task_baru_lead_alert_type_none_tetap_kirim(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin7',
            'email' => 'admin7@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $assignee = User::create([
            'username' => 'rudi',
            'email' => 'rudi@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081299990000',
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote Lead 2',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $lead = $this->makeLead($admin);

        $this->actingAs($admin)->postJson(
            route('leads-management.tasks.store', $lead->id),
            [
                'title' => 'Follow up lead tanpa alert',
                'category_id' => $category->id,
                'due_date' => '2026-08-20',
                'assignees' => [$assignee->id],
                'alert_type' => 'none',
                'alert_target' => 'personal',
            ]
        )->assertOk();

        // Alert Settings hanya untuk reminder; notif create selalu dikirim.
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send')
            && $request['to'] === '6281299990000');
    }

    public function test_notif_task_baru_task_planner_penanganan_hanya_ke_grup(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin8',
            'email' => 'admin8@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $division = Division::create([
            'division_name' => 'Water',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $member = User::create([
            'username' => 'tono',
            'email' => 'tono@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081311112222',
        ]);

        $group = HandlingGroup::create([
            'name' => 'Water',
            'division_id' => $division->id,
            'whatsapp_group_id' => '120363444444444444@g.us',
        ]);
        $group->users()->sync([$member->id]);

        $category = TaskCategory::create([
            'name' => 'Quote Planner',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $this->actingAs($admin)->postJson(route('task-planner.store'), [
            'title' => 'Task planner penanganan',
            'category_id' => $category->id,
            'handling_group_id' => $group->id,
            'due_date' => '2026-08-20',
            'alert_type' => 'whatsapp',
            'alert_target' => 'personal',
        ])->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group')
            && $request['groupJid'] === '120363444444444444@g.us');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
    }

    public function test_notif_task_baru_task_planner_tanpa_penanganan_kirim_personal(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin9',
            'email' => 'admin9@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $assignee = User::create([
            'username' => 'sinta',
            'email' => 'sinta@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081333334444',
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote Planner 2',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $this->actingAs($admin)->postJson(route('task-planner.store'), [
            'title' => 'Task planner biasa',
            'category_id' => $category->id,
            'due_date' => '2026-08-20',
            'assignees' => [$assignee->id],
            'alert_type' => 'whatsapp',
            'alert_target' => 'personal',
        ])->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send')
            && $request['to'] === '6281333334444');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send-group'));
    }

    public function test_edit_task_notif_assignee_baru_saja(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin10',
            'email' => 'admin10@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $lama = User::create([
            'username' => 'lama',
            'email' => 'lama@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081344441111',
        ]);

        $baru = User::create([
            'username' => 'baru',
            'email' => 'baru@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081355552222',
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote Planner 3',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $task = Task::create([
            'creator_id' => $admin->id,
            'category_id' => $category->id,
            'title' => 'Task edit assignee',
            'due_date' => '2026-08-20',
            'status' => 'todo',
            'alert_type' => 'whatsapp',
            'alert_target' => 'personal',
        ]);
        $task->assignees()->sync([$lama->id]);

        $this->actingAs($admin)->putJson(route('task-planner.update', $task->id), [
            'title' => 'Task edit assignee',
            'category_id' => $category->id,
            'due_date' => '2026-08-20',
            'status' => 'todo',
            'assignees' => [$lama->id, $baru->id],
            'alert_type' => 'whatsapp',
            'alert_target' => 'personal',
        ])->assertOk();

        // Hanya assignee baru yang dapat WA.
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send')
            && $request['to'] === '6281355552222');

        $this->assertSame(1, Notification::where('user_id', $baru->id)->count());
        $this->assertSame(0, Notification::where('user_id', $lama->id)->count());
    }

    private function makeLead(User $owner): Lead
    {
        $contact = AccountContact::create([
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'mobile' => '81234567890',
        ]);

        $source = Source::create(['source_name' => 'Website', 'status' => 'Active']);

        return Lead::create([
            'lead_status' => 'New',
            'lead_title' => 'Prospek Air Bersih',
            'account_contacts_id' => $contact->id,
            'source_id' => $source->id,
            'lead_owner_id' => $owner->id,
        ]);
    }

    public function test_notif_task_baru_penanganan_hanya_ke_grup(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin3',
            'email' => 'admin3@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $division = Division::create([
            'division_name' => 'Water',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $member = User::create([
            'username' => 'abu',
            'email' => 'abu@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081211112222',
        ]);

        $group = HandlingGroup::create([
            'name' => 'Water',
            'division_id' => $division->id,
            'whatsapp_group_id' => '120363222222222222@g.us',
        ]);
        $group->users()->sync([$member->id]);

        $category = TaskCategory::create([
            'name' => 'Quote',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $opportunity = $this->makeOpportunity($admin);

        $this->actingAs($admin)->postJson(
            route('opportunity-management.tasks.store', $opportunity->id),
            [
                'title' => 'Quote Handling Task',
                'category_id' => $category->id,
                'handling_group_id' => $group->id,
                'due_date' => '2026-08-20',
                'alert_type' => 'whatsapp',
                'alert_target' => 'personal',
            ]
        )->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group')
            && $request['groupJid'] === '120363222222222222@g.us');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
    }

    public function test_notif_task_baru_tanpa_penanganan_hanya_personal(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin4',
            'email' => 'admin4@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $assignee = User::create([
            'username' => 'maya',
            'email' => 'maya@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081233334444',
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote 2',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $opportunity = $this->makeOpportunity($admin);

        $this->actingAs($admin)->postJson(
            route('opportunity-management.tasks.store', $opportunity->id),
            [
                'title' => 'Task Biasa',
                'category_id' => $category->id,
                'due_date' => '2026-08-20',
                'assignees' => [$assignee->id],
                'alert_type' => 'whatsapp',
                'alert_target' => 'personal',
            ]
        )->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send')
            && $request['to'] === '6281233334444');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send-group'));
    }

    public function test_notif_task_baru_alert_type_none_tetap_kirim_ke_grup_penanganan(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin5',
            'email' => 'admin5@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $assignee = User::create([
            'username' => 'budi',
            'email' => 'budi@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081255556666',
        ]);

        $division = Division::create([
            'division_name' => 'PD',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $group = HandlingGroup::create([
            'name' => 'PD',
            'division_id' => $division->id,
            'whatsapp_group_id' => '120363333333333333@g.us',
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote 3',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $opportunity = $this->makeOpportunity($admin);

        $this->actingAs($admin)->postJson(
            route('opportunity-management.tasks.store', $opportunity->id),
            [
                'title' => 'Task Tanpa Alert',
                'category_id' => $category->id,
                'handling_group_id' => $group->id,
                'assignees' => [$assignee->id],
                'due_date' => '2026-08-20',
                'alert_type' => 'none',
                'alert_target' => 'both',
            ]
        )->assertOk();

        // Alert Settings hanya untuk reminder; notif create selalu dikirim.
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group')
            && $request['groupJid'] === '120363333333333333@g.us');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
    }

    public function test_notif_task_baru_task_planner_grup_wa_per_task(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin11',
            'email' => 'admin11@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $assignee = User::create([
            'username' => 'wawan',
            'email' => 'wawan@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
            'phone_number' => '081366667777',
        ]);

        $division = Division::create([
            'division_name' => 'Enviro',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $waGroup = WhatsAppGroup::create([
            'division_id' => $division->id,
            'group_name' => 'Enviro Ops',
            'group_id' => '120363555555555555@g.us',
            'status' => 'Active',
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote Planner 4',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $this->actingAs($admin)->postJson(route('task-planner.store'), [
            'title' => 'Task grup WA per task',
            'category_id' => $category->id,
            'whatsapp_group_id' => $waGroup->id,
            'due_date' => '2026-08-20',
            'assignees' => [$assignee->id],
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ])->assertOk();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group')
            && $request['groupJid'] === '120363555555555555@g.us');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/send'));
    }

    public function test_notif_task_baru_penanganan_menang_atas_grup_wa_per_task(): void
    {
        $this->configureGateway();
        Http::fake();

        $admin = User::create([
            'username' => 'admin12',
            'email' => 'admin12@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $division = Division::create([
            'division_name' => 'Water',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $waGroup = WhatsAppGroup::create([
            'division_id' => $division->id,
            'group_name' => 'Water Ops',
            'group_id' => '120363666666666666@g.us',
            'status' => 'Active',
        ]);

        $handling = HandlingGroup::create([
            'name' => 'Water',
            'division_id' => $division->id,
            'whatsapp_group_id' => '120363777777777777@g.us',
        ]);

        $category = TaskCategory::create([
            'name' => 'Quote Planner 5',
            'use_division_handler' => 'No',
            'visit' => 'No',
        ]);

        $this->actingAs($admin)->postJson(route('task-planner.store'), [
            'title' => 'Task dua grup',
            'category_id' => $category->id,
            'handling_group_id' => $handling->id,
            'whatsapp_group_id' => $waGroup->id,
            'due_date' => '2026-08-20',
            'alert_type' => 'whatsapp',
            'alert_target' => 'personal',
        ])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send-group')
            && $request['groupJid'] === '120363777777777777@g.us');
    }
}

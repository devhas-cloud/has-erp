<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpportunityStoreTaskWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $assigneeWithPhone;

    private User $assigneeWithoutPhone;

    private Opportunity $opportunity;

    private TaskCategory $category;

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

        $this->creator = User::create([
            'username' => 'creator',
            'email' => 'creator@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        UserAccessControl::create([
            'user_id' => $this->creator->id,
            'module_id' => $module->id,
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => true,
            'can_approve' => false,
        ]);

        $this->assigneeWithPhone = User::create([
            'username' => 'assignee1',
            'email' => 'assignee1@has.com',
            'password' => bcrypt('secret'),
            'phone_number' => '081234567890',
            'role' => 'User',
        ]);

        $this->assigneeWithoutPhone = User::create([
            'username' => 'assignee2',
            'email' => 'assignee2@has.com',
            'password' => bcrypt('secret'),
            'role' => 'User',
        ]);

        $company = AccountCompany::create(['account_name' => 'PT Maju Bersama', 'status' => 'Active']);
        $this->opportunity = Opportunity::create([
            'opportunity_name' => 'Peluang Air Bersih',
            'account_companies_id' => $company->id,
            'owner_id' => $this->creator->id,
        ]);

        $this->category = TaskCategory::create(['name' => 'General']);

        config([
            'services.wabaileys.url' => 'https://wa.example.test',
            'services.wabaileys.api_key' => 'secret-key',
            'services.wabaileys.sender_phone' => '6281111111111',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Follow up client',
            'category_id' => $this->category->id,
            'due_date' => now()->addDays(2)->toDateString(),
            'alert_target' => 'personal',
            'assignees' => [$this->assigneeWithPhone->id, $this->assigneeWithoutPhone->id],
        ], $overrides);
    }

    public function test_store_task_sends_whatsapp_to_assignees_with_phone_number(): void
    {
        Http::fake([
            'wa.example.test/api/send' => Http::response(['success' => true, 'id' => 'MSG1'], 200),
        ]);

        $response = $this->actingAs($this->creator)->postJson(
            route('opportunity-management.tasks.store', $this->opportunity->id),
            $this->payload()
        );

        $response->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://wa.example.test/api/send'
                && $request['to'] === '6281234567890'
                && str_contains($request['message'], 'Follow up client');
        });
    }

    public function test_store_task_does_not_notify_creator_via_whatsapp(): void
    {
        $this->creator->update(['phone_number' => '081199998888']);
        Http::fake([
            'wa.example.test/api/send' => Http::response(['success' => true, 'id' => 'MSG1'], 200),
        ]);

        // Creator sendiri termasuk assignees[] — tapi tidak boleh dikirimi WA
        // (dia yang membuat task, bukan "ditugaskan" oleh orang lain).
        $this->actingAs($this->creator)->postJson(
            route('opportunity-management.tasks.store', $this->opportunity->id),
            $this->payload(['assignees' => [$this->creator->id, $this->assigneeWithPhone->id]])
        );

        Http::assertSent(function ($request) {
            return $request['to'] !== '6281199998888';
        });
        Http::assertSentCount(1);
    }

    public function test_store_task_skips_assignee_without_phone_number(): void
    {
        Http::fake([
            'wa.example.test/api/send' => Http::response(['success' => true, 'id' => 'MSG1'], 200),
        ]);

        $this->actingAs($this->creator)->postJson(
            route('opportunity-management.tasks.store', $this->opportunity->id),
            $this->payload(['assignees' => [$this->assigneeWithoutPhone->id]])
        );

        Http::assertNothingSent();
    }

    public function test_store_task_still_succeeds_when_wabaileys_not_configured(): void
    {
        config(['services.wabaileys.url' => '']);
        Http::fake();

        $response = $this->actingAs($this->creator)->postJson(
            route('opportunity-management.tasks.store', $this->opportunity->id),
            $this->payload()
        );

        $response->assertOk();
        $this->assertSame(1, Task::count());
        Http::assertNothingSent();
    }

    public function test_store_task_still_succeeds_when_whatsapp_gateway_fails(): void
    {
        Http::fake([
            'wa.example.test/api/send' => Http::response(['success' => false], 500),
        ]);

        $response = $this->actingAs($this->creator)->postJson(
            route('opportunity-management.tasks.store', $this->opportunity->id),
            $this->payload()
        );

        $response->assertOk();
        $this->assertSame(1, Task::count());
    }
}

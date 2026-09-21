<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPlannerAccountContactTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private TaskCategory $visitCategory;

    private TaskCategory $otherCategory;

    private AccountContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::create([
            'username' => 'creator',
            'email' => 'creator@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $this->visitCategory = TaskCategory::create(['name' => 'Visit']);
        $this->otherCategory = TaskCategory::create(['name' => 'General']);

        $company = AccountCompany::create(['account_name' => 'PT Maju Bersama', 'status' => 'Active']);
        $this->contact = AccountContact::create(['full_name' => 'Budi Santoso', 'account_companies_id' => $company->id]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Kunjungan ke PT Maju Bersama',
            'category_id' => $this->visitCategory->id,
            'due_date' => now()->addDays(2)->toDateString(),
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ], $overrides);
    }

    public function test_store_persists_account_contact_id_for_visit_category(): void
    {
        $response = $this->actingAs($this->creator)->postJson(
            route('task-planner.store'),
            $this->basePayload(['account_contact_id' => $this->contact->id])
        );

        $response->assertOk();
        $task = Task::firstOrFail();
        $this->assertSame($this->contact->id, $task->account_contact_id);
    }

    public function test_store_allows_account_contact_id_to_be_left_empty(): void
    {
        $response = $this->actingAs($this->creator)->postJson(route('task-planner.store'), $this->basePayload());

        $response->assertOk();
        $task = Task::firstOrFail();
        $this->assertNull($task->account_contact_id);
    }

    public function test_store_rejects_invalid_account_contact_id(): void
    {
        $response = $this->actingAs($this->creator)->postJson(
            route('task-planner.store'),
            $this->basePayload(['account_contact_id' => 999999])
        );

        $response->assertStatus(422);
        $this->assertSame(0, Task::count());
    }

    public function test_update_persists_account_contact_id_change(): void
    {
        $task = Task::create([
            'creator_id' => $this->creator->id,
            'category_id' => $this->visitCategory->id,
            'title' => 'Kunjungan',
            'status' => 'todo',
            'due_date' => now()->addDays(2)->toDateString(),
            'alert_type' => 'none',
            'alert_target' => 'personal',
            'requires_approval' => false,
        ]);

        $response = $this->actingAs($this->creator)->putJson(
            route('task-planner.update', $task->id),
            [
                'title' => $task->title,
                'category_id' => $this->visitCategory->id,
                'due_date' => now()->addDays(2)->toDateString(),
                'status' => 'todo',
                'alert_type' => 'none',
                'alert_target' => 'personal',
                'account_contact_id' => $this->contact->id,
            ]
        );

        $response->assertOk();
        $this->assertSame($this->contact->id, $task->fresh()->account_contact_id);
    }

    /**
     * account_contact_id tidak wajib terkait kategori Visit — field opsional
     * murni, boleh diisi/dikosongkan terlepas dari kategori task.
     */
    public function test_store_allows_account_contact_id_for_non_visit_category(): void
    {
        $response = $this->actingAs($this->creator)->postJson(
            route('task-planner.store'),
            $this->basePayload(['category_id' => $this->otherCategory->id, 'account_contact_id' => $this->contact->id])
        );

        $response->assertOk();
        $task = Task::firstOrFail();
        $this->assertSame($this->contact->id, $task->account_contact_id);
    }

    public function test_fetch_account_contacts_filters_by_query(): void
    {
        AccountContact::create(['full_name' => 'Siti Aminah']);

        $response = $this->actingAs($this->creator)->getJson(route('task-planner.fetch-account-contacts', ['q' => 'Budi']));

        $response->assertOk();
        $results = collect($response->json('results'));
        $this->assertTrue($results->contains(fn ($r) => str_contains($r['text'], 'Budi Santoso')));
        $this->assertFalse($results->contains(fn ($r) => str_contains($r['text'], 'Siti Aminah')));
    }

    public function test_fetch_account_contacts_includes_company_name_in_label(): void
    {
        $response = $this->actingAs($this->creator)->getJson(route('task-planner.fetch-account-contacts', ['q' => 'Budi']));

        $response->assertOk();
        $this->assertSame('Budi Santoso — PT Maju Bersama', $response->json('results.0.text'));
    }

    public function test_edit_page_renders_with_preselected_account_contact(): void
    {
        $task = Task::create([
            'creator_id' => $this->creator->id,
            'category_id' => $this->visitCategory->id,
            'account_contact_id' => $this->contact->id,
            'title' => 'Kunjungan',
            'status' => 'todo',
            'due_date' => now()->addDays(2)->toDateString(),
            'alert_type' => 'none',
            'alert_target' => 'personal',
            'requires_approval' => false,
        ]);

        $response = $this->actingAs($this->creator)->get(route('task-planner.edit', $task->id));

        $response->assertOk();
        $response->assertSee('Budi Santoso');
        $response->assertSee('data-visit="1"', false);
    }

    public function test_create_modal_page_renders_visit_data_attribute(): void
    {
        $response = $this->actingAs($this->creator)->get(route('task-planner.index'));

        $response->assertOk();
        $response->assertSee('data-visit="1"', false);
        $response->assertSee('id="task-account-contact-container"', false);
    }

}

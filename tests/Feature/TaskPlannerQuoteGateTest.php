<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\QuoteConfiguration;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPlannerQuoteGateTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $assignee;

    private TaskCategory $quoteCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::create([
            'username' => 'creator',
            'email' => 'creator@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $this->assignee = User::create([
            'username' => 'assignee',
            'email' => 'assignee@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $this->quoteCategory = TaskCategory::create(['name' => 'Quote']);
    }

    private function createQuoteTask(): Task
    {
        $task = Task::create([
            'creator_id' => $this->creator->id,
            'category_id' => $this->quoteCategory->id,
            'title' => 'Quote Task',
            'status' => 'in_progress',
            'due_date' => '2026-08-20',
            'alert_type' => 'none',
            'alert_target' => 'personal',
            'requires_approval' => false,
        ]);
        $task->assignees()->sync([$this->assignee->id]);

        return $task;
    }

    private function createApprovedQuotation(Task $task): Quotation
    {
        return Quotation::create([
            'task_id' => $task->id,
            'quotation_number' => '001/HAS/QT/TEST',
            'status' => Quotation::STATUS_APPROVED,
            'approved_at' => now(),
            'created_by' => $this->creator->id,
        ]);
    }

    public function test_quote_task_cannot_transition_to_done_without_approved_quotation(): void
    {
        $task = $this->createQuoteTask();

        $response = $this->actingAs($this->assignee)->postJson(
            route('task-planner.transition', $task->id),
            ['status' => 'done']
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_quote_task_can_transition_to_done_with_approved_quotation(): void
    {
        $task = $this->createQuoteTask();
        $this->createApprovedQuotation($task);

        $response = $this->actingAs($this->assignee)->postJson(
            route('task-planner.transition', $task->id),
            ['status' => 'done']
        );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_quote_task_cannot_be_approved_without_approved_quotation(): void
    {
        $task = $this->createQuoteTask();
        $task->update(['status' => 'waiting_approval']);

        $response = $this->actingAs($this->creator)->postJson(
            route('task-planner.approve', $task->id)
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('waiting_approval', $task->fresh()->status);
    }

    public function test_quote_task_can_be_approved_with_approved_quotation(): void
    {
        $task = $this->createQuoteTask();
        $task->update(['status' => 'waiting_approval']);
        $this->createApprovedQuotation($task);

        $response = $this->actingAs($this->creator)->postJson(
            route('task-planner.approve', $task->id)
        );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_show_page_loads_quotations_for_quote_task(): void
    {
        $task = $this->createQuoteTask();
        $quotation = $this->createApprovedQuotation($task);

        $response = $this->actingAs($this->creator)->get(
            route('task-planner.show', $task->id)
        );

        $response->assertOk()
            ->assertSee('Quote', false)
            ->assertSee('Approved');
    }

    public function test_show_page_displays_final_quotation_when_task_done(): void
    {
        $task = $this->createQuoteTask();
        $quotation = $this->createApprovedQuotation($task);
        $task->update(['status' => 'done']);

        $response = $this->actingAs($this->creator)->get(
            route('task-planner.show', $task->id)
        );

        $response->assertOk()
            ->assertViewHas('finalQuotation', fn ($final) => $final && $final->id === $quotation->id)
            ->assertSee('Final Quotation')
            ->assertSee('001/HAS/QT/TEST')
            ->assertSee(route('quotation.pdf', ['id' => $quotation->id, 'back' => 'task-'.$task->id]), false);
    }

    public function test_show_page_hides_final_quotation_when_task_not_done(): void
    {
        $task = $this->createQuoteTask();
        $this->createApprovedQuotation($task);

        $response = $this->actingAs($this->creator)->get(
            route('task-planner.show', $task->id)
        );

        $response->assertOk()
            ->assertViewHas('finalQuotation', null)
            ->assertSee('Final quotation akan tampil setelah quotation di-approve dan task di-complete.');
    }
}

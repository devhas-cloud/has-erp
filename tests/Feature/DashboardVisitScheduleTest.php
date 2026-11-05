<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardVisitScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $imsUser;

    private User $salesUser;

    private User $idleUser;

    private TaskCategory $visit;

    private TaskCategory $call;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-05 09:00:00'); // Kamis

        $ims = Division::create(['division_name' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);
        $sales = Division::create(['division_name' => 'Sales', 'type' => 'Internal', 'status' => 'Active']);

        // Pengguna biasa (bukan Admin) tetap melihat semua divisi; Admin dipakai agar lolos access.control.
        $this->viewer = User::create(['username' => 'viewer', 'email' => 'v@has.com', 'password' => bcrypt('x'), 'role' => 'Admin']);
        $this->imsUser = User::create(['username' => 'andi', 'full_name' => 'Andi IMS', 'email' => 'a@has.com', 'password' => bcrypt('x'), 'role' => 'User', 'division_id' => $ims->id]);
        $this->salesUser = User::create(['username' => 'citra', 'full_name' => 'Citra Sales', 'email' => 'c@has.com', 'password' => bcrypt('x'), 'role' => 'User', 'division_id' => $sales->id]);
        $this->idleUser = User::create(['username' => 'budi', 'full_name' => 'Budi Idle', 'email' => 'b@has.com', 'password' => bcrypt('x'), 'role' => 'User', 'division_id' => $ims->id]);

        $this->visit = TaskCategory::create(['name' => 'Survey', 'visit' => true]);
        $this->call = TaskCategory::create(['name' => 'Call']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function task(string $title, string $date, array $assignees, array $attrs = []): Task
    {
        $task = Task::create(array_merge([
            'creator_id' => $this->viewer->id,
            'category_id' => $this->visit->id,
            'title' => $title,
            'due_date' => $date,
            'status' => 'todo',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ], $attrs));
        $task->assignees()->sync(array_map(fn (User $u) => $u->id, $assignees));

        return $task;
    }

    /** Judul task & nama orang yang ada di jadwal visit (bukan bagian dashboard lain). */
    private function schedule(array $params = []): array
    {
        $visit = $this->actingAs($this->viewer)->get(route('dashboard-task-planner.index', $params))->assertOk()->viewData('visit');
        $rows = $visit['groups']->flatMap(fn ($g) => $g['rows']);

        return [
            'titles' => $rows->flatMap(fn ($r) => collect($r['days'])->flatten(1)->pluck('title'))->unique()->sort()->values()->all(),
            'people' => $rows->map(fn ($r) => $r['user']->display_name)->sort()->values()->all(),
            'visit' => $visit,
        ];
    }

    public function test_schedule_shows_visit_tasks_for_seven_workdays_grouped_by_assignee(): void
    {
        $this->task('Survey PLTS Gresik', '2026-11-05', [$this->imsUser], ['time' => '09:30']);
        $this->task('Kunjungan bersama', '2026-11-09', [$this->imsUser, $this->salesUser], ['status' => 'done']);
        $this->task('Visit akhir minggu', '2026-11-07', [$this->imsUser]);   // Sabtu: tidak tampil
        $this->task('Telepon customer', '2026-11-06', [$this->salesUser], ['category_id' => $this->call->id]); // bukan visit
        $this->task('Visit di luar rentang', '2026-11-16', [$this->salesUser]); // hari kerja ke-8
        $this->task('Visit terlambat', '2026-11-05', [$this->salesUser], ['due_date' => '2026-11-05']);

        $response = $this->actingAs($this->viewer)->get(route('dashboard-task-planner.index'))->assertOk();

        $response->assertSee('Jadwal Visit')
            ->assertSee('Kam 05')->assertSee('Jum 06')->assertSee('Sen 09')->assertSee('Jum 13')
            ->assertDontSee('Sab 07')
            ->assertSee('09:30');

        $schedule = $this->schedule();
        $this->assertSame(['Kunjungan bersama', 'Survey PLTS Gresik', 'Visit terlambat'], $schedule['titles']);
        $this->assertSame(['Andi IMS', 'Citra Sales'], $schedule['people']);

        $visit = $response->viewData('visit');
        $this->assertCount(7, $visit['days']);
        $this->assertSame('2026-11-13', end($visit['days'])->toDateString());
        $this->assertSame(['tasks' => 3, 'people' => 2, 'divisions' => 2], $visit['summary']);
        $this->assertSame(2, $visit['day_totals']['2026-11-09'], 'Task dengan 2 assignee tampil di 2 baris.');
    }

    public function test_filters_and_show_empty_members(): void
    {
        $this->task('Survey IMS', '2026-11-06', [$this->imsUser]);
        $this->task('Survey Sales', '2026-11-06', [$this->salesUser]);
        $imsId = $this->imsUser->division_id;

        $this->assertSame(['Survey IMS'], $this->schedule(['visit_division' => $imsId])['titles']);
        $this->assertSame(['Andi IMS'], $this->schedule(['visit_division' => $imsId])['people']);
        $this->assertSame(['Andi IMS', 'Budi Idle'], $this->schedule(['visit_division' => $imsId, 'visit_empty' => 1])['people']);
        $this->assertSame(['Survey Sales'], $this->schedule(['visit_q' => 'citra'])['titles']);
        $this->assertSame(['Survey IMS', 'Survey Sales'], $this->schedule(['visit_status' => 'todo'])['titles']);
        $this->assertSame([], $this->schedule(['visit_status' => 'late'])['titles']);
    }

    public function test_navigation_moves_by_seven_workdays(): void
    {
        $this->task('Visit minggu depan', '2026-11-16', [$this->imsUser]);

        $visit = $this->actingAs($this->viewer)->get(route('dashboard-task-planner.index'))->viewData('visit');
        $this->assertSame('2026-11-16', $visit['next_start']);
        $this->assertSame('2026-10-27', $visit['prev_start']);

        $this->actingAs($this->viewer)->get(route('dashboard-task-planner.index', ['visit_start' => '2026-11-16']))
            ->assertOk()->assertSee('Visit minggu depan');

        // Mulai di hari Sabtu digeser ke Senin.
        $visit = $this->actingAs($this->viewer)->get(route('dashboard-task-planner.index', ['visit_start' => '2026-11-07']))->viewData('visit');
        $this->assertSame('2026-11-09', $visit['days'][0]->toDateString());
    }
}

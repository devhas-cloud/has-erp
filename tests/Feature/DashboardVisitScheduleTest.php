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
        $this->imsUser = User::create(['username' => 'andi', 'full_name' => 'Andi IMS', 'email' => 'a@has.com', 'password' => bcrypt('x'), 'role' => 'User', 'division_id' => $ims->id, 'salesperson' => true]);
        $this->salesUser = User::create(['username' => 'citra', 'full_name' => 'Citra Sales', 'email' => 'c@has.com', 'password' => bcrypt('x'), 'role' => 'User', 'division_id' => $sales->id, 'salesperson' => true]);
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
        $visit = $this->actingAs($this->viewer)->get(route('dashboard-visit-planner.index', $params))->assertOk()->viewData('visit');
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

        $response = $this->actingAs($this->viewer)->get(route('dashboard-visit-planner.index'))->assertOk();

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

    public function test_schedule_filters(): void
    {
        $this->task('Survey IMS', '2026-11-06', [$this->imsUser]);
        $this->task('Survey Sales', '2026-11-06', [$this->salesUser]);
        $imsId = $this->imsUser->division_id;

        $this->assertSame(['Survey IMS'], $this->schedule(['visit_division' => $imsId])['titles']);
        $this->assertSame(['Andi IMS'], $this->schedule(['visit_division' => $imsId])['people']);
        $this->assertSame(['Survey Sales'], $this->schedule(['visit_q' => 'citra'])['titles']);
        $this->assertSame(['Survey IMS', 'Survey Sales'], $this->schedule(['visit_status' => 'todo'])['titles']);
        $this->assertSame([], $this->schedule(['visit_status' => 'late'])['titles']);
    }

    public function test_navigation_moves_by_seven_workdays(): void
    {
        $this->task('Visit minggu depan', '2026-11-16', [$this->imsUser]);

        $visit = $this->actingAs($this->viewer)->get(route('dashboard-visit-planner.index'))->viewData('visit');
        $this->assertSame('2026-11-16', $visit['next_start']);
        $this->assertSame('2026-10-27', $visit['prev_start']);

        $this->actingAs($this->viewer)->get(route('dashboard-visit-planner.index', ['visit_start' => '2026-11-16']))
            ->assertOk()->assertSee('Visit minggu depan');

        // Mulai di hari Sabtu digeser ke Senin.
        $visit = $this->actingAs($this->viewer)->get(route('dashboard-visit-planner.index', ['visit_start' => '2026-11-07']))->viewData('visit');
        $this->assertSame('2026-11-09', $visit['days'][0]->toDateString());
    }

    private function done(array $params = []): array
    {
        return $this->actingAs($this->viewer)->get(route('dashboard-visit-planner.index', $params))->assertOk()->viewData('visitDone');
    }

    public function test_visit_done_defaults_to_current_week_monday_to_friday(): void
    {
        $a = $this->task('Selesai Senin', '2026-11-02', [$this->imsUser, $this->salesUser], ['status' => 'done']);
        $a->visits()->create(['user_id' => $this->imsUser->id, 'latitude' => -7.1, 'longitude' => 112.6]);
        $this->task('Selesai Jumat', '2026-11-06', [$this->imsUser], ['status' => 'done']);
        $this->task('Belum selesai', '2026-11-04', [$this->salesUser]);
        $this->task('Selesai minggu lalu', '2026-10-30', [$this->imsUser], ['status' => 'done']);
        $this->task('Telepon selesai', '2026-11-03', [$this->imsUser], ['status' => 'done', 'category_id' => $this->call->id]);

        $done = $this->done();

        $this->assertSame('week', $done['period']);
        $this->assertSame('2026-11-02', $done['start']->toDateString());
        $this->assertSame('2026-11-06', $done['end']->toDateString());
        $this->assertSame(2, $done['total']);
        $this->assertSame(3, $done['planned']);
        $this->assertSame(67, $done['rate']);
        $this->assertSame(1, $done['checked_in']);

        // Per salesperson: [selesai, rencana, check-in], urut terbanyak.
        $people = $done['people']->mapWithKeys(fn ($p) => [$p['user']->display_name => [$p['done'], $p['planned'], $p['checked_in']]])->all();
        $this->assertSame(['Andi IMS' => [2, 2, 1], 'Citra Sales' => [1, 2, 0]], $people);

        $this->actingAs($this->viewer)->get(route('dashboard-visit-planner.index'))
            ->assertSee('Report Visit')->assertSee('vd-person', false)->assertSee('Minggu ini');
    }

    public function test_visit_done_quarter_and_custom_range(): void
    {
        $this->task('Selesai Q3', '2026-08-14', [$this->imsUser], ['status' => 'done']);
        $this->task('Selesai Q4', '2026-10-01', [$this->salesUser], ['status' => 'done']);
        $this->task('Selesai Q4 akhir', '2026-12-31', [$this->salesUser], ['status' => 'done']);

        $q3 = $this->done(['done_period' => 'quarter', 'done_quarter' => 3, 'done_year' => 2026]);
        $this->assertSame(['2026-07-01', '2026-09-30', 1], [$q3['start']->toDateString(), $q3['end']->toDateString(), $q3['total']]);

        $q4 = $this->done(['done_period' => 'quarter', 'done_quarter' => 4, 'done_year' => 2026]);
        $this->assertSame(['2026-10-01', '2026-12-31', 2], [$q4['start']->toDateString(), $q4['end']->toDateString(), $q4['total']]);

        // Rentang bebas, urutan tanggal terbalik tetap dinormalkan.
        $custom = $this->done(['done_period' => 'custom', 'done_start' => '2026-10-15', 'done_end' => '2026-08-01']);
        $this->assertSame(['2026-08-01', '2026-10-15', 2], [$custom['start']->toDateString(), $custom['end']->toDateString(), $custom['total']]);

        // Rentang tidak lengkap -> kembali ke minggu ini.
        $this->assertSame('week', $this->done(['done_period' => 'custom', 'done_start' => '2026-10-15'])['period']);
    }

    public function test_only_salesperson_users_are_shown_and_counted(): void
    {
        // Budi (IMS) bukan salesperson.
        $this->task('Visit non-sales', '2026-11-05', [$this->idleUser], ['status' => 'done']);
        $this->task('Visit campuran', '2026-11-05', [$this->idleUser, $this->salesUser], ['status' => 'done']);

        $schedule = $this->schedule();
        $this->assertSame(['Visit campuran'], $schedule['titles']);
        $this->assertSame(['Citra Sales'], $schedule['people']);

        $done = $this->done();
        $this->assertSame(1, $done['total']);
        $this->assertSame(1, $done['planned']);
        // Semua salesperson tampil (Andi 0 visit), non-salesperson (Budi) tidak.
        $this->assertSame(['Citra Sales' => 1, 'Andi IMS' => 0], $done['people']->mapWithKeys(fn ($p) => [$p['user']->display_name => $p['done']])->all());

        $this->actingAs($this->viewer)->get(route('dashboard-visit-planner.index'))
            ->assertSee('Report Visit')
            ->assertSee('Citra Sales')
            ->assertSee('vd-person zero', false)->assertSee('Sales');
    }
}

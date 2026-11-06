<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_achievement_from_project_achievements(): void
    {
        $water = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        Division::create(['division_name' => 'Enviro', 'description' => 'Enviro', 'type' => 'Internal', 'status' => 'Active']);
        Division::create(['division_name' => 'IH', 'description' => 'IH', 'type' => 'Internal', 'status' => 'Active']);
        Division::create(['division_name' => 'Gas', 'description' => 'Gas', 'type' => 'Internal', 'status' => 'Active']);
        Division::create(['division_name' => 'IMS', 'description' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);

        $admin = User::create(['username' => 'admin', 'email' => 'admin@has.com', 'password' => bcrypt('secret'), 'role' => 'Admin']);
        $owner = User::create(['username' => 'sales', 'email' => 'sales@has.com', 'password' => bcrypt('secret'), 'division_id' => $water->id, 'role' => 'User']);

        $company = AccountCompany::create(['account_name' => 'PT Maju', 'status' => 'Active']);
        $ims = Division::where('division_name', 'IMS')->first();
        $sales = Division::create(['division_name' => 'Sales', 'description' => 'Sales', 'type' => 'Internal', 'status' => 'Active']);

        $makeProject = fn (string $no, string $status = Project::STATUS_ACTIVE) => Project::create([
            'project_number' => $no,
            'project_name' => 'Project '.$no,
            'company_id' => $company->id,
            'po_number_company' => 'PO-'.$no,
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-01',
            'po_due_date' => '2026-11-01',
            'status' => $status,
            'created_by' => $admin->id,
        ]);

        $p1 = $makeProject('PRJ-1');
        $p1->achievements()->createMany([
            ['device_name' => 'Sensor pH', 'brand' => 'HAS', 'qty' => 2, 'amount' => 600000, 'division_id' => $water->id],
            ['device_name' => 'Instalasi', 'brand' => null, 'qty' => 1, 'amount' => 400000, 'division_id' => $water->id],
            ['device_name' => 'Gateway', 'brand' => 'HAS', 'qty' => 1, 'amount' => 200000, 'division_id' => $ims->id],
        ]);
        $makeProject('PRJ-2')->achievements()->create(['device_name' => 'Logger', 'brand' => 'HAS', 'qty' => 3, 'amount' => 300000, 'division_id' => $water->id]);
        // Divisi di luar daftar tetap masuk total; project Cancelled tidak dihitung.
        $makeProject('PRJ-3')->achievements()->create(['device_name' => 'Jasa', 'qty' => 1, 'amount' => 50000, 'division_id' => $sales->id]);
        $makeProject('PRJ-4', Project::STATUS_CANCELLED)->achievements()->create(['device_name' => 'Batal', 'brand' => 'HAS', 'qty' => 1, 'amount' => 999000, 'division_id' => $water->id]);

        // Jumlah opportunity per probabilitas (tanpa syarat quotation).
        foreach ([25, 50, 70] as $prob) {
            Opportunity::create([
                'opportunity_name' => 'Opp '.$prob,
                'account_companies_id' => $company->id,
                'owner_id' => $owner->id,
                'division_id' => $water->id,
                'probability' => $prob,
            ]);
        }
        // Probabilitas di luar 25/50/70 tidak ikut dihitung.
        Opportunity::create([
            'opportunity_name' => 'Opp Nol',
            'account_companies_id' => $company->id,
            'owner_id' => $owner->id,
            'division_id' => $water->id,
            'probability' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard-achievement.index'))->assertOk();

        $this->assertEquals(1550000, $response->viewData('totalAchievement'));

        $divisions = $response->viewData('divisions');
        $this->assertSame(['Enviro', 'Water', 'IH', 'Gas', 'IMS'], $divisions->pluck('division_name')->all());

        $waterRow = $divisions->firstWhere('division_name', 'Water');
        $this->assertSame(2, $waterRow->project_count);
        $this->assertEquals(1300000, $waterRow->total);
        $this->assertEquals(200000, $divisions->firstWhere('division_name', 'IMS')->total);
        // Divisi lain tampil dengan nilai 0.
        foreach (['Enviro', 'IH', 'Gas'] as $name) {
            $this->assertSame(0, $divisions->firstWhere('division_name', $name)->project_count);
        }

        // Brand per divisi dari rincian device; tanpa brand di baris terakhir.
        $brands = $response->viewData('brandsByDivision')->get($water->id, collect());
        $this->assertSame(['HAS', null], $brands->pluck('brand')->all());
        $this->assertEquals([900000, 400000], $brands->pluck('total_value')->all());
        $this->assertSame([2, 1], $brands->pluck('item_count')->all());
        $this->assertEquals(5, $brands->first()->qty_total);

        $response->assertSee('Tanpa Brand')->assertSee('2 Project');

        // Count probabilitas 25/50/70 = masing-masing 1.
        $counts = $response->viewData('probabilityCountsByDivision')[$water->id] ?? [];
        $this->assertSame([25 => 1, 50 => 1, 70 => 1], $counts);
    }
}

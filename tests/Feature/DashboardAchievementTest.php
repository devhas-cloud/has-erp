<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\Stage;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_fixed_divisions_with_opportunity_probability_counts(): void
    {
        $water = Division::create(['division_name' => 'WATER', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        Division::create(['division_name' => 'Enviro', 'description' => 'Enviro', 'type' => 'Internal', 'status' => 'Active']);
        Division::create(['division_name' => 'IH', 'description' => 'IH', 'type' => 'Internal', 'status' => 'Active']);
        Division::create(['division_name' => 'Gas', 'description' => 'Gas', 'type' => 'Internal', 'status' => 'Active']);
        Division::create(['division_name' => 'IMS', 'description' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);

        $admin = User::create(['username' => 'admin', 'email' => 'admin@has.com', 'password' => bcrypt('secret'), 'role' => 'Admin']);
        $owner = User::create(['username' => 'sales', 'email' => 'sales@has.com', 'password' => bcrypt('secret'), 'division_id' => $water->id, 'role' => 'User']);

        $category = TaskCategory::create(['name' => 'Quote', 'use_division_handler' => true]);
        $won = Stage::create(['stage_name' => 'Closed Won', 'status' => 'Active']);
        $company = AccountCompany::create(['account_name' => 'PT Maju', 'status' => 'Active']);

        $opportunity = Opportunity::create([
            'opportunity_name' => 'Opp WATER',
            'account_companies_id' => $company->id,
            'owner_id' => $owner->id,
            'division_id' => $water->id,
            'stage_id' => $won->id,
            'probability' => 100,
        ]);

        $task = Task::create([
            'creator_id' => $owner->id,
            'category_id' => $category->id,
            'opportunity_id' => $opportunity->id,
            'title' => 'Quote Achievement',
            'due_date' => '2026-08-20',
            'status' => 'in_progress',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ]);

        $quotation = Quotation::create([
            'task_id' => $task->id,
            'opportunity_id' => $opportunity->id,
            'quotation_number' => '001/HAS/QT-A/2026',
            'to_name' => 'PT Maju',
            'status' => Quotation::STATUS_FINISH,
            'grand_total' => 1000000,
            'created_by' => $admin->id,
        ]);

        $product = MasterProduct::create(['name' => 'Sensor', 'code' => 'BR-1', 'brand' => 'HAS']);
        $quotation->items()->create(['part_number' => $product->code, 'qty' => 2, 'price' => 500000]);

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

        $divisions = $response->viewData('divisions');
        $this->assertSame(['Enviro', 'WATER', 'IH', 'Gas', 'IMS'], $divisions->pluck('division_name')->all());

        $waterRow = $divisions->firstWhere('division_name', 'WATER');
        $this->assertSame(1, (int) $waterRow->quotation_count);
        $this->assertEquals(1000000, (float) $waterRow->total);
        // Divisi lain tampil dengan nilai 0.
        foreach (['Enviro', 'IH', 'Gas', 'IMS'] as $name) {
            $this->assertSame(0, (int) $divisions->firstWhere('division_name', $name)->quotation_count);
        }

        // Brand terjual tercatat di divisi WATER.
        $brands = $response->viewData('brandsByDivision')->get($water->id, collect());
        $this->assertSame('HAS', $brands->first()->brand);
        $this->assertEquals(1000000, (float) $brands->first()->total_value);

        // Count probabilitas 25/50/70 = masing-masing 1.
        $counts = $response->viewData('probabilityCountsByDivision')[$water->id] ?? [];
        $this->assertSame([25 => 1, 50 => 1, 70 => 1], $counts);
    }
}
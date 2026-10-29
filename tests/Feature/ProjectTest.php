<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\ProfitEstimate;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $creator;

    private User $viewer;

    private AccountCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->division = Division::create([
            'division_name' => 'Water',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->admin = User::create([
            'username' => 'superadmin',
            'email' => 'superadmin@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'Admin',
        ]);

        $this->company = AccountCompany::create([
            'account_name' => 'PT Maju Bersama',
            'address_billing_street' => 'Jl. Industri No. 1',
            'address_billing_city' => 'Jakarta',
            'address_billing_province' => 'DKI Jakarta',
            'status' => 'Active',
        ]);

        $module = Module::create([
            'module_code' => 'MOD_PROJECT',
            'module_name' => 'Project',
            'route_name' => 'project',
            'icon' => 'fa fa-diagram-project',
            'group' => 'Admin',
        ]);

        $this->creator = $this->makeUser('creator', $module, [
            'can_read' => true,
            'can_create' => true,
            'can_update' => true,
        ]);
        $this->viewer = $this->makeUser('viewer', $module, ['can_read' => true]);
    }

    private function makeUser(string $username, Module $module, array $access): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        UserAccessControl::create(array_merge([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'can_create' => false,
            'can_read' => false,
            'can_update' => false,
            'can_delete' => false,
            'can_approve' => false,
        ], $access));

        return $user;
    }

    private function makeQuotation(float $grandTotal = 1000000, ?float $net = 900000): Quotation
    {
        $opportunity = Opportunity::create([
            'opportunity_name' => 'SPARING Gresik',
            'account_companies_id' => $this->company->id,
            'owner_id' => $this->admin->id,
            'probability' => 70,
        ]);

        $quotation = Quotation::create([
            'opportunity_id' => $opportunity->id,
            'to_name' => $this->company->account_name,
            'quotation_number' => '001/HAS/QT-AB/'.Quotation::romanMonth(10).'/2026',
            'status' => Quotation::STATUS_APPROVED,
            'grand_total' => $grandTotal,
            'created_by' => $this->admin->id,
        ]);

        if ($net !== null) {
            ProfitEstimate::create([
                'quotation_id' => $quotation->id,
                'opportunity_id' => $opportunity->id,
                'real_project_value' => $net,
                'created_by' => $this->admin->id,
            ]);
        }

        return $quotation;
    }

    private function payload(Quotation $quotation, array $overrides = []): array
    {
        return array_merge([
            'quotation_id' => $quotation->id,
            'project_name' => 'Project SPARING',
            'project_description' => 'Instalasi water treatment',
            'po_number_company' => 'PO-CUST-001',
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-11-01',
            'is_retention' => 0,
            'type_of_achievement' => 'project',
            'po_document' => UploadedFile::fake()->create('po.pdf', 500, 'application/pdf'),
            'sales' => [$this->creator->id, $this->admin->id],
        ], $overrides);
    }

    public function test_index_requires_read_permission(): void
    {
        $this->actingAs($this->viewer)->get(route('project.index'))->assertOk();

        $noAccess = $this->makeUser('noaccess', Module::where('module_code', 'MOD_PROJECT')->firstOrFail(), []);
        $this->actingAs($noAccess)->get(route('project.index'))->assertStatus(302);
    }

    public function test_store_requires_create_permission(): void
    {
        $quotation = $this->makeQuotation();

        $this->actingAs($this->viewer)->postJson(route('project.store'), $this->payload($quotation))
            ->assertStatus(403);

        $this->assertSame(0, Project::count());
    }

    public function test_create_from_quotation_forces_readonly_values_and_finishes_quotation(): void
    {
        $quotation = $this->makeQuotation(1000000, 900000);

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation, [
            // Nilai dari client harus diabaikan saat quotation dipilih.
            'company_id' => $this->company->id,
            'total_sale_amount' => 1,
            'net_sale_amount' => 1,
        ]))->assertOk();

        $project = Project::firstOrFail();

        $this->assertSame(1000000.0, $project->total_sale_amount);
        $this->assertSame(900000.0, $project->net_sale_amount);
        $this->assertSame($this->company->id, $project->company_id);
        $this->assertSame(Quotation::STATUS_FINISH, $quotation->fresh()->status);
        $this->assertNotNull($project->po_document_path);
        Storage::disk('public')->assertExists($project->po_document_path);

        $this->assertSame(0, $project->achievements()->count());
        $this->assertSame(Project::STATUS_ACTIVE, $project->status);
        $this->assertSame(2, $project->salesAchievements()->count());
        $this->assertSame(450000.0, $project->salesAchievements()->orderBy('id')->firstOrFail()->amount_achieved);
    }

    public function test_project_name_defaults_to_opportunity_name(): void
    {
        $quotation = $this->makeQuotation();

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation, [
            'project_name' => '',
            'po_number_company' => 'PO-DEFAULT-NAME',
        ]))->assertOk();

        $this->assertSame('SPARING Gresik', Project::firstOrFail()->project_name);
    }

    public function test_create_blocks_when_quotation_has_no_profit_estimate(): void
    {
        $quotation = $this->makeQuotation(1000000, null);

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Profit Estimate belum tersedia untuk quotation ini. Buat Profit Estimate terlebih dahulu.');

        $this->assertSame(0, Project::count());
        $this->assertSame(Quotation::STATUS_APPROVED, $quotation->fresh()->status);
    }

    public function test_separate_achievement_form_saves_device_rows(): void
    {
        $quotation = $this->makeQuotation(1000000, 900000);

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->creator)->putJson(route('project.achievements.update', $project->id), [
            'achievements' => [
                ['device_name' => 'RO 1000 LPD', 'qty' => 1, 'unit' => 'unit', 'amount' => 500000, 'division_id' => $this->division->id],
                ['device_name' => 'Softener', 'qty' => 2, 'unit' => 'unit', 'amount' => 400000, 'division_id' => $this->division->id],
            ],
        ])->assertOk();

        $this->assertSame(2, $project->achievements()->count());
        $this->assertSame(900000.0, (float) $project->achievements()->sum('amount'));
    }

    public function test_separate_achievement_form_rejects_total_mismatch(): void
    {
        $quotation = $this->makeQuotation(1000000, 900000);

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->creator)->putJson(route('project.achievements.update', $project->id), [
            'achievements' => [
                ['device_name' => 'RO', 'qty' => 1, 'unit' => 'unit', 'amount' => 100000, 'division_id' => $this->division->id],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, $project->achievements()->count());
    }

    public function test_separate_achievement_form_requires_update_permission(): void
    {
        $quotation = $this->makeQuotation();
        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->viewer)->putJson(route('project.achievements.update', $project->id), [
            'achievements' => [
                ['device_name' => 'RO', 'qty' => 1, 'unit' => 'unit', 'amount' => 900000, 'division_id' => $this->division->id],
            ],
        ])->assertStatus(403);

        $this->assertSame(0, $project->achievements()->count());
    }

    public function test_create_rejects_duplicate_po_number(): void
    {
        $quotation = $this->makeQuotation();

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))
            ->assertStatus(422)
            ->assertJsonValidationErrors('po_number_company');

        $this->assertSame(1, Project::count());
    }

    public function test_one_quotation_can_have_multiple_projects(): void
    {
        $quotation = $this->makeQuotation();

        $this->actingAs($this->creator)
            ->postJson(route('project.store'), $this->payload($quotation, ['po_number_company' => 'PO-1']))
            ->assertOk();

        // Setelah project pertama, quotation menjadi Finish — tetap boleh dipilih.
        $this->assertSame(Quotation::STATUS_FINISH, $quotation->fresh()->status);

        $this->actingAs($this->creator)
            ->postJson(route('project.store'), $this->payload($quotation, ['po_number_company' => 'PO-2']))
            ->assertOk();

        $this->assertSame(2, Project::where('quotation_id', $quotation->id)->count());
    }

    public function test_legacy_project_without_quotation_uses_manual_values(): void
    {
        $payload = $this->payload($this->makeQuotation(), [
            'quotation_id' => null,
            'po_document' => null,
            'company_id' => $this->company->id,
            'project_name' => 'Project Lama',
            'total_sale_amount' => 500000,
            'net_sale_amount' => 400000,
        ]);

        $this->actingAs($this->creator)->postJson(route('project.store'), $payload)->assertOk();

        $project = Project::firstOrFail();
        $this->assertNull($project->quotation_id);
        $this->assertSame(500000.0, $project->total_sale_amount);
        $this->assertSame(400000.0, $project->net_sale_amount);
        $this->assertSame(2, $project->salesAchievements()->count());
    }

    public function test_sales_selected_in_project_form_are_split_equally_with_remainder_on_last(): void
    {
        $quotation = $this->makeQuotation(1000000, 1000000);

        $sales = [
            $this->creator->id,
            $this->admin->id,
            $this->viewer->id,
        ];

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation, [
            'po_number_company' => 'PO-SPLIT',
            'sales' => $sales,
        ]))->assertOk();

        $project = Project::firstOrFail();

        $amounts = $project->salesAchievements()
            ->whereIn('sales_person_id', $sales)
            ->orderBy('id')
            ->pluck('amount_achieved')
            ->map(fn ($v) => (float) $v)
            ->all();

        $this->assertSame([333333.33, 333333.33, 333333.34], $amounts);
        $this->assertSame(1000000.0, round(array_sum($amounts), 2));
    }

    public function test_project_number_uses_invoice_format_and_resets_yearly(): void
    {
        $quotation = $this->makeQuotation(1000000, 900000);
        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();

        $other = $this->makeQuotation(1000000, 900000);
        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($other, ['po_number_company' => 'PO-CUST-002']))->assertOk();

        $numbers = Project::orderBy('id')->pluck('project_number')->all();
        $this->assertSame(now()->format('ym').'0001', $numbers[0]);
        $this->assertSame(now()->format('ym').'0002', $numbers[1]);
    }

    public function test_number_format_helpers_use_consistent_format(): void
    {
        $this->assertSame('1,000,000', Project::formatNumber(1000000));
        $this->assertSame('1,000,000.5', Project::formatNumber(1000000.5));
        $this->assertSame('0', Project::formatNumber(0));
        $this->assertSame('Rp 1,000,000', Project::formatMoney(1000000));
    }

    public function test_pages_render(): void
    {
        $quotation = $this->makeQuotation();

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->creator)->get(route('project.create'))
            ->assertOk()
            ->assertSee('Buat Project');

        $this->actingAs($this->creator)->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee($project->project_number)
            ->assertSee('Rp 1,000,000')
            ->assertSee('Rp 900,000');

        $this->actingAs($this->creator)->get(route('project.edit', $project->id))
            ->assertOk()
            ->assertSee($project->po_number_company)
            ->assertSee('value="1,000,000"', false)
            ->assertSee('value="900,000"', false);

        $this->actingAs($this->creator)->get(route('project.achievements', $project->id))
            ->assertOk()
            ->assertSee('Rincian Achievement per Device')
            ->assertSee('Rp 900,000');
    }

    public function test_device_name_autocomplete_uses_existing_names(): void
    {
        $quotation = $this->makeQuotation(1000000, 900000);

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->creator)->putJson(route('project.achievements.update', $project->id), [
            'achievements' => [
                ['device_name' => 'RO 1000 LPD', 'qty' => 1, 'unit' => 'unit', 'amount' => 900000, 'division_id' => $this->division->id],
            ],
        ])->assertOk();

        $response = $this->actingAs($this->creator)->get(route('project.achievements', $project->id))
            ->assertOk()
            ->assertSee('pjDeviceNames', false)
            ->assertSee('RO 1000 LPD');

        $this->assertTrue($response->viewData('deviceNames')->contains('RO 1000 LPD'));
    }

    public function test_achievements_tab_data(): void
    {
        $quotation = $this->makeQuotation(1000000, 900000);

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->creator)
            ->putJson(route('project.achievements.update', $project->id), [
                'achievements' => [
                    ['device_name' => 'RO 1000 LPD', 'qty' => 1, 'unit' => 'unit', 'amount' => 500000, 'division_id' => $this->division->id],
                    ['device_name' => 'Softener', 'qty' => 2, 'unit' => 'unit', 'amount' => 400000, 'division_id' => $this->division->id],
                ],
            ])->assertOk();

        $response = $this->actingAs($this->creator)->getJson(route('project.achievements-data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.project_number', $project->project_number)
            ->assertJsonPath('data.0.company_name', 'PT Maju Bersama')
            ->assertJsonPath('data.0.device_name', 'RO 1000 LPD')
            ->assertJsonPath('data.0.qty', 1)
            ->assertJsonPath('data.0.unit', 'unit')
            ->assertJsonPath('data.0.po_date', '01/10/2026')
            ->assertJsonPath('data.0.division_name', 'Water')
            ->assertJsonPath('data.0.po_number_company', 'PO-CUST-001')
            ->assertJsonPath('data.1.device_name', 'Softener');

        $this->assertSame(500000.0, (float) $response->json('data.0.amount'));
        $this->assertStringContainsString('creator', $response->json('data.0.sales_names'));
    }

    public function test_update_keeps_status_and_replaces_sales_from_project_form(): void
    {
        $quotation = $this->makeQuotation(1000000, 900000);

        $this->actingAs($this->creator)->postJson(route('project.store'), $this->payload($quotation))->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->creator)->putJson(route('project.achievements.update', $project->id), [
            'achievements' => [
                ['device_name' => 'RO 1000 LPD', 'qty' => 1, 'unit' => 'unit', 'amount' => 900000, 'division_id' => $this->division->id],
            ],
        ])->assertOk();

        $this->actingAs($this->creator)->postJson(route('project.update', $project->id), [
            '_method' => 'PUT',
            'project_name' => 'Project SPARING Revisi',
            'company_id' => $this->company->id,
            'po_number_company' => 'PO-CUST-001',
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-11-01',
            'is_retention' => 1,
            'type_of_achievement' => 'retail',
            // Status dari client harus diabaikan; penentuan di backend.
            'status' => 'completed',
            'sales' => [$this->viewer->id],
        ])->assertOk();

        $project->refresh();
        $this->assertSame('Project SPARING Revisi', $project->project_name);
        $this->assertTrue($project->is_retention);
        $this->assertSame('retail', $project->type_of_achievement);
        $this->assertSame(Project::STATUS_ACTIVE, $project->status);

        // Device achievement dikelola form terpisah; update project tidak menyentuhnya.
        $this->assertSame(1, $project->achievements()->count());
        $this->assertSame('RO 1000 LPD', $project->achievements()->firstOrFail()->device_name);

        // Sales dari form project mengganti pembagian lama.
        $this->assertSame(1, $project->salesAchievements()->count());
        $this->assertSame($this->viewer->id, $project->salesAchievements()->firstOrFail()->sales_person_id);
        $this->assertSame(900000.0, $project->salesAchievements()->firstOrFail()->amount_achieved);
    }
}

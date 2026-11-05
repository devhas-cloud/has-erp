<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Document;
use App\Models\Division;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImsProjectTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $ims;

    private User $viewer;

    private AccountCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->division = Division::create([
            'division_name' => 'IMS',
            'description' => 'IMS',
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
            'account_name' => 'PT Hybrid Energi',
            'address_billing_street' => 'Jl. Industri No. 1',
            'address_billing_city' => 'Jakarta',
            'address_billing_province' => 'DKI Jakarta',
            'status' => 'Active',
        ]);

        $module = Module::create([
            'module_code' => 'MOD_IMS_PROJECT',
            'module_name' => 'IMS Project',
            'route_name' => 'ims-project',
            'icon' => 'fa fa-shield-halved',
            'group' => 'IMS',
        ]);

        $this->ims = $this->makeUser('ims', $module, [
            'can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true,
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
            'can_create' => false, 'can_read' => false, 'can_update' => false,
            'can_delete' => false, 'can_approve' => false,
        ], $access));

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'project_name' => 'Hybrid PLTS Gresik',
            'project_description' => 'Sistem hybrid PLTS + genset',
            'company_id' => $this->company->id,
            'po_number_company' => 'PO-HYB-001',
            'po_date' => '2025-03-01',
            'po_date_received' => '2025-03-03',
            'po_due_date' => '2025-04-01',
            'po_document' => UploadedFile::fake()->create('po.pdf', 200, 'application/pdf'),
            'agreement_files' => [UploadedFile::fake()->create('bast.pdf', 100, 'application/pdf')],
            'agreement_notes' => 'BAST',
            'warranty_start_date' => '2025-06-01',
            'warranty_end_date' => '2026-06-01',
        ], $overrides);
    }

    private function fullProject(): Project
    {
        return Project::create([
            'project_number' => 'PRJ-26110099',
            'project_name' => 'Project lengkap',
            'company_id' => $this->company->id,
            'po_number_company' => 'PO-FULL',
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-01',
            'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1110000,
            'net_sale_amount' => 1000000,
            'manual_subtotal' => 1000000,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_creates_hybrid_project_without_values(): void
    {
        $this->actingAs($this->ims)->post(route('ims-project.store'), $this->payload(), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $project = Project::with('warranty', 'documents')->firstOrFail();

        $this->assertSame('Hybrid PLTS Gresik', $project->project_name);
        $this->assertNull($project->quotation_id);
        $this->assertSame('2025-04-01', $project->po_due_date->toDateString());
        $this->assertSame('2025-03-03', $project->po_date_received->toDateString());
        $this->assertEquals(0, (float) $project->total_sale_amount);
        $this->assertEquals(0, (float) $project->net_sale_amount);
        $this->assertSame(Project::TYPE_PROJECT, $project->type_of_achievement);
        $this->assertSame(0, $project->salesAchievements()->count());
        $this->assertTrue($project->isImsHybrid());
        $this->assertNotNull($project->po_document_path);
        Storage::disk('public')->assertExists($project->po_document_path);
        $this->assertSame('2026-06-01', $project->warranty->end_date->toDateString());
        $this->assertSame(1, $project->documents->where('type', Document::TYPE_AGREEMENT)->count());
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->ims)->postJson(route('ims-project.store'), [
            'warranty_start_date' => '2025-06-01',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'project_name', 'company_id', 'po_number_company', 'po_date', 'po_date_received', 'po_due_date', 'warranty_end_date',
        ]);
    }

    public function test_due_date_cannot_be_before_po_date(): void
    {
        $this->actingAs($this->ims)->postJson(route('ims-project.store'), $this->payload(['po_due_date' => '2025-02-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['po_due_date']);
    }

    public function test_store_requires_create_permission(): void
    {
        $this->actingAs($this->viewer)->postJson(route('ims-project.store'), $this->payload())->assertStatus(403);
        $this->assertSame(0, Project::count());
    }

    public function test_list_shows_all_projects_same_as_project_module(): void
    {
        $this->fullProject();
        $this->actingAs($this->ims)->post(route('ims-project.store'), $this->payload(), ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($this->viewer)->getJson(route('ims-project.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.project_name', 'Hybrid PLTS Gresik')
            ->assertJsonPath('data.0.is_hybrid', true)
            ->assertJsonPath('data.1.project_name', 'Project lengkap')
            ->assertJsonPath('data.1.is_hybrid', false)
            ->assertJsonPath('data.0.po_due_date', '01/04/2025')
            ->assertJsonPath('data.0.warranty_period', '01/06/2025 – 01/06/2026');

        // Sebaliknya: project dari IMS juga tampil di daftar Project utama.
        $this->actingAs($this->admin)->getJson(route('project.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.is_hybrid', true)
            ->assertJsonPath('data.0.warranty_end', '01/06/2026');
    }

    public function test_complete_project_can_be_viewed_and_complemented_but_not_deleted_from_ims(): void
    {
        $full = $this->fullProject();

        $this->actingAs($this->ims)->get(route('ims-project.show', $full->id))->assertOk()->assertSee('Project lengkap');
        $this->actingAs($this->ims)->get(route('ims-project.edit', $full->id))->assertOk();

        $this->actingAs($this->ims)->post(route('ims-project.update', $full->id), $this->payload([
            '_method' => 'PUT', 'project_name' => 'Project lengkap', 'po_number_company' => 'PO-FULL',
        ]), ['Accept' => 'application/json'])->assertOk();

        $full->refresh();
        $this->assertEquals(1110000, (float) $full->total_sale_amount, 'Nilai kontrak tidak tersentuh oleh IMS.');
        $this->assertEquals(1000000, (float) $full->manual_subtotal);
        $this->assertNotNull($full->warranty()->first());

        $this->actingAs($this->ims)->deleteJson(route('ims-project.destroy', $full->id))->assertStatus(422);
        $this->assertNotNull(Project::find($full->id));
    }

    public function test_update_changes_only_partial_fields_and_clears_warranty(): void
    {
        $this->actingAs($this->ims)->post(route('ims-project.store'), $this->payload(), ['Accept' => 'application/json'])->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->ims)->post(route('ims-project.update', $project->id), [
            '_method' => 'PUT',
            'project_name' => 'Hybrid PLTS Gresik (rev)',
            'company_id' => $this->company->id,
            'po_number_company' => 'PO-HYB-001',
            'po_date' => '2025-03-01',
            'po_date_received' => '2025-03-03',
            'po_due_date' => '2025-05-01',
            'warranty_start_date' => '',
            'warranty_end_date' => '',
        ], ['Accept' => 'application/json'])->assertOk();

        $project->refresh();
        $this->assertSame('Hybrid PLTS Gresik (rev)', $project->project_name);
        $this->assertSame('2025-05-01', $project->po_due_date->toDateString());
        $this->assertNotNull($project->po_document_path, 'Dokumen PO lama tetap ada bila tidak diganti.');
        $this->assertNull($project->warranty()->first());
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->ims)->post(route('ims-project.store'), $this->payload(), ['Accept' => 'application/json'])->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->ims)->get(route('ims-project.index'))->assertOk()->assertSee('IMS Project');
        $this->actingAs($this->ims)->get(route('ims-project.create'))->assertOk()->assertSee('Tanggal PO Diterima');
        $this->actingAs($this->ims)->get(route('ims-project.edit', $project->id))->assertOk()->assertSee('PO-HYB-001');
        $this->actingAs($this->ims)->get(route('ims-project.show', $project->id))->assertOk()
            ->assertSee('Hybrid PLTS Gresik')
            ->assertSee('bast.pdf')
            ->assertDontSee('Lengkapi di Project');

        $this->actingAs($this->admin)->get(route('ims-project.show', $project->id))->assertSee('Lengkapi di Project');
        $this->actingAs($this->admin)->get(route('project.show', $project->id))->assertOk()->assertSee('Nilai &amp; sales belum diisi', false);
        $this->actingAs($this->admin)->get(route('project.edit', $project->id))->assertOk();
    }

    public function test_project_completed_in_project_module_stays_listed_as_complete(): void
    {
        $this->actingAs($this->ims)->post(route('ims-project.store'), $this->payload(), ['Accept' => 'application/json'])->assertOk();
        $project = Project::firstOrFail();

        $this->actingAs($this->admin)->post(route('project.update', $project->id), [
            '_method' => 'PUT',
            'project_name' => $project->project_name,
            'company_id' => $this->company->id,
            'po_number_company' => 'PO-HYB-001',
            'po_date' => '2025-03-01',
            'po_date_received' => '2025-03-03',
            'po_due_date' => '2025-04-01',
            'manual_subtotal' => 1000000,
            'manual_ppn_percent' => 11,
            'is_retention' => 0,
            'type_of_achievement' => 'project',
            'sales' => [$this->admin->id],
            'warranty_start_date' => '2025-06-01',
            'warranty_end_date' => '2026-06-01',
        ], ['Accept' => 'application/json'])->assertOk();

        $project->refresh();
        $this->assertFalse($project->isImsHybrid());
        $this->assertEquals(1110000, (float) $project->total_sale_amount);
        $this->assertNotNull($project->warranty()->first());

        $this->actingAs($this->ims)->getJson(route('ims-project.data'))
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.is_hybrid', false);
    }

    public function test_document_routes_are_scoped_to_project(): void
    {
        $this->actingAs($this->ims)->post(route('ims-project.store'), $this->payload(), ['Accept' => 'application/json'])->assertOk();
        $project = Project::with('documents')->firstOrFail();
        $doc = $project->documents->first();

        $this->actingAs($this->ims)->post(route('ims-project.store'), $this->payload(['po_number_company' => 'PO-HYB-002']), ['Accept' => 'application/json'])->assertOk();
        $other = Project::where('po_number_company', 'PO-HYB-002')->firstOrFail();

        $this->actingAs($this->ims)->get(route('ims-project.documents.show', [$other->id, $doc->id]))->assertNotFound();
        $this->actingAs($this->ims)->get(route('ims-project.documents.show', [$project->id, $doc->id]))->assertOk();

        $this->actingAs($this->ims)->deleteJson(route('ims-project.documents.destroy', [$project->id, $doc->id]))->assertOk();
        $this->assertSame(0, $project->documents()->count());
    }
}

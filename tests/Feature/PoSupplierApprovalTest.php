<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\ProjectPoSupplierApproval;
use App\Models\Quotation;
use App\Models\Stage;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoSupplierApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $approver1;

    private User $approver2;

    private User $approver3;

    private User $userWithoutApprove;

    private AccountCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

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
            'module_code' => 'MOD_PO_SUPPLIER_APPROVAL',
            'module_name' => 'PO Supplier Approval',
            'route_name' => 'po-supplier-approval',
            'icon' => 'fa fa-truck-fast',
            'group' => 'Admin',
        ]);

        $this->approver1 = $this->makeApprover('approver1', $module, true);
        $this->approver2 = $this->makeApprover('approver2', $module, true);
        $this->approver3 = $this->makeApprover('approver3', $module, true);
        $this->userWithoutApprove = $this->makeApprover('nobody', $module, false);
    }

    private function makeApprover(string $username, Module $module, bool $canApprove): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        UserAccessControl::create([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'can_create' => false,
            'can_read' => true,
            'can_update' => false,
            'can_delete' => false,
            'can_approve' => $canApprove,
        ]);

        return $user;
    }

    private function createProject(?Quotation $quotation = null, string $status = Project::STATUS_ACTIVE, string $poNumber = 'PO-CUST-001'): Project
    {
        static $seq = 0;
        $seq++;

        return Project::create([
            'project_number' => now()->format('ym').str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'project_name' => 'Project SPARING '.$seq,
            'quotation_id' => $quotation?->id,
            'company_id' => $this->company->id,
            'po_number_company' => $poNumber.'-'.$seq,
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1000000,
            'net_sale_amount' => 900000,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    private function createOpportunityWithQuotation(string $name = 'SPARING Kawasan Industri Gresik'): Quotation
    {
        // stage_id 5 = "Close Won" (hardcode di PoSupplierApprovalController).
        $openStage = Stage::create(['stage_name' => 'New', 'status' => 'Active', 'probability' => 10]);
        foreach ([1, 2, 3, 4] as $i) {
            Stage::create(['stage_name' => 'Stage '.$i, 'status' => 'Active', 'probability' => $i * 10]);
        }
        Stage::create(['stage_name' => 'Close Won', 'status' => 'Active', 'probability' => 100]);

        $opportunity = Opportunity::create([
            'stage_id' => $openStage->id,
            'opportunity_name' => $name,
            'account_companies_id' => $this->company->id,
            'owner_id' => $this->admin->id,
            'probability' => 70,
        ]);

        return Quotation::create([
            'opportunity_id' => $opportunity->id,
            'status' => Quotation::STATUS_FINISH,
            'created_by' => $this->admin->id,
            'quotation_number' => '001/HAS/QT-T/VIII/2026',
            'to_name' => 'PT Maju Bersama',
        ]);
    }

    public function test_first_approval_does_not_mark_ready_for_supplier_po(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $project->id))
            ->assertOk();

        $this->assertFalse($response->json('ready_for_supplier_po'));
        $this->assertSame(1, ProjectPoSupplierApproval::count());
    }

    public function test_second_distinct_approval_marks_ready_for_supplier_po_without_changing_status(): void
    {
        $project = $this->createProject();

        $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $project->id))
            ->assertOk();

        $response = $this->actingAs($this->approver2)
            ->postJson(route('po-supplier-approval.approve', $project->id))
            ->assertOk();

        $this->assertTrue($response->json('ready_for_supplier_po'));
        $this->assertSame(2, ProjectPoSupplierApproval::count());
        $this->assertTrue($project->fresh()->isReadyForSupplierPo());

        // Status project TIDAK berubah setelah kuorum terpenuhi.
        $this->assertSame(Project::STATUS_ACTIVE, $project->fresh()->status);
    }

    public function test_quorum_sets_opportunity_of_project_quotation_to_close_won(): void
    {
        $quotation = $this->createOpportunityWithQuotation();
        $project = $this->createProject($quotation);

        $this->actingAs($this->approver1)->postJson(route('po-supplier-approval.approve', $project->id))->assertOk();
        $this->actingAs($this->approver2)->postJson(route('po-supplier-approval.approve', $project->id))->assertOk();

        $opportunity = $quotation->fresh()->opportunity;
        $this->assertSame(5, (int) $opportunity->stage_id);
        $this->assertNotNull($opportunity->close_won_date);
    }

    public function test_same_user_cannot_approve_twice(): void
    {
        $project = $this->createProject();

        $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $project->id))
            ->assertOk();

        $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $project->id))
            ->assertStatus(422);

        $this->assertSame(1, ProjectPoSupplierApproval::count());
    }

    public function test_approval_is_per_project_not_per_quotation(): void
    {
        $quotation = $this->createOpportunityWithQuotation();
        $first = $this->createProject($quotation);
        $second = $this->createProject($quotation);

        $this->actingAs($this->approver1)->postJson(route('po-supplier-approval.approve', $first->id))->assertOk();
        $this->actingAs($this->approver2)->postJson(route('po-supplier-approval.approve', $first->id))->assertOk();

        $this->assertTrue($first->fresh()->isReadyForSupplierPo());
        $this->assertFalse($second->fresh()->isReadyForSupplierPo());
    }

    public function test_user_without_can_approve_is_rejected(): void
    {
        $project = $this->createProject();

        $this->actingAs($this->userWithoutApprove)
            ->postJson(route('po-supplier-approval.approve', $project->id))
            ->assertStatus(403);

        $this->assertSame(0, ProjectPoSupplierApproval::count());
    }

    public function test_approve_rejects_inactive_project(): void
    {
        $project = $this->createProject(null, Project::STATUS_COMPLETED);

        $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $project->id))
            ->assertStatus(422);

        $this->assertSame(0, ProjectPoSupplierApproval::count());
    }

    public function test_data_listing_only_shows_active_projects(): void
    {
        $active1 = $this->createProject();
        $active2 = $this->createProject();
        $this->createProject(null, Project::STATUS_COMPLETED);
        $this->createProject(null, Project::STATUS_CANCELLED);

        $response = $this->actingAs($this->approver1)
            ->getJson(route('po-supplier-approval.data'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$active1->id, $active2->id], $ids);
        $this->assertSame($active2->project_number, $response->json('data.0.project_number'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Module;
use App\Models\Quotation;
use App\Models\QuotationPoSupplierApproval;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create([
            'division_name' => 'WATER',
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

    private function createFinishedQuotation(): Quotation
    {
        return Quotation::create([
            'status' => Quotation::STATUS_FINISH,
            'created_by' => $this->admin->id,
            'po_document_path' => 'quotation-po/dummy.pdf',
            'po_document_name' => 'dummy.pdf',
            'po_uploaded_by' => $this->admin->id,
            'po_uploaded_at' => now(),
        ]);
    }

    public function test_first_approval_does_not_mark_ready_for_supplier_po(): void
    {
        $quotation = $this->createFinishedQuotation();

        $response = $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $quotation->id))
            ->assertOk();

        $this->assertFalse($response->json('ready_for_supplier_po'));
        $this->assertSame(1, QuotationPoSupplierApproval::count());
    }

    public function test_second_distinct_approval_marks_ready_for_supplier_po_without_changing_status(): void
    {
        $quotation = $this->createFinishedQuotation();

        $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $quotation->id))
            ->assertOk();

        $response = $this->actingAs($this->approver2)
            ->postJson(route('po-supplier-approval.approve', $quotation->id))
            ->assertOk();

        $this->assertTrue($response->json('ready_for_supplier_po'));
        $this->assertSame(2, QuotationPoSupplierApproval::count());
        $this->assertTrue($quotation->fresh()->isReadyForSupplierPo());

        // Status quotation TIDAK berubah lagi setelah kuorum terpenuhi.
        $this->assertSame(Quotation::STATUS_FINISH, $quotation->fresh()->status);
    }

    public function test_same_user_cannot_approve_twice(): void
    {
        $quotation = $this->createFinishedQuotation();

        $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $quotation->id))
            ->assertOk();

        $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $quotation->id))
            ->assertStatus(422);

        $this->assertSame(1, QuotationPoSupplierApproval::count());
    }

    public function test_user_without_can_approve_is_rejected(): void
    {
        $quotation = $this->createFinishedQuotation();

        $this->actingAs($this->userWithoutApprove)
            ->postJson(route('po-supplier-approval.approve', $quotation->id))
            ->assertStatus(403);

        $this->assertSame(0, QuotationPoSupplierApproval::count());
    }

    public function test_approve_rejects_when_status_not_finish(): void
    {
        $quotation = Quotation::create([
            'status' => Quotation::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->approver1)
            ->postJson(route('po-supplier-approval.approve', $quotation->id))
            ->assertStatus(422);

        $this->assertSame(0, QuotationPoSupplierApproval::count());
    }

    public function test_data_listing_only_shows_finish_status(): void
    {
        $finish1 = $this->createFinishedQuotation();
        $finish2 = $this->createFinishedQuotation();

        Quotation::create(['status' => Quotation::STATUS_DRAFT, 'created_by' => $this->admin->id]);
        Quotation::create(['status' => Quotation::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        Quotation::create(['status' => Quotation::STATUS_REJECTED, 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->approver1)
            ->getJson(route('po-supplier-approval.data'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$finish1->id, $finish2->id], $ids);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Module;
use App\Models\Quotation;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationPoFinishTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $admin;

    private User $userWithUpdate;

    private User $userWithoutUpdate;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

        $this->userWithUpdate = User::create([
            'username' => 'purchasing',
            'email' => 'purchasing@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        $this->userWithoutUpdate = User::create([
            'username' => 'viewer',
            'email' => 'viewer@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        $module = Module::create([
            'module_code' => 'MOD_QUOTATION',
            'module_name' => 'Quotation',
            'route_name' => 'quotation',
            'icon' => 'fa fa-file-invoice',
            'group' => 'Admin',
        ]);

        UserAccessControl::create([
            'user_id' => $this->userWithUpdate->id,
            'module_id' => $module->id,
            'can_create' => false,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => false,
            'can_approve' => false,
        ]);

        UserAccessControl::create([
            'user_id' => $this->userWithoutUpdate->id,
            'module_id' => $module->id,
            'can_create' => false,
            'can_read' => true,
            'can_update' => false,
            'can_delete' => false,
            'can_approve' => false,
        ]);
    }

    private function createQuotation(string $status): Quotation
    {
        return Quotation::create([
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_upload_po_transitions_approved_to_finish(): void
    {
        $quotation = $this->createQuotation(Quotation::STATUS_APPROVED);

        $response = $this->actingAs($this->userWithUpdate)->postJson(
            route('quotation.upload-po', $quotation->id),
            ['po_document' => UploadedFile::fake()->create('po.pdf', 500, 'application/pdf')]
        )->assertOk();

        $this->assertTrue($response->json('success'));

        $fresh = $quotation->fresh();
        $this->assertSame(Quotation::STATUS_FINISH, $fresh->status);
        $this->assertNotNull($fresh->po_document_path);
        $this->assertSame('po.pdf', $fresh->po_document_name);
        $this->assertSame($this->userWithUpdate->id, $fresh->po_uploaded_by);
        $this->assertNotNull($fresh->po_uploaded_at);
        Storage::disk('public')->assertExists($fresh->po_document_path);
    }

    public function test_upload_po_rejects_when_not_approved(): void
    {
        $quotation = $this->createQuotation(Quotation::STATUS_DRAFT);

        $this->actingAs($this->userWithUpdate)->postJson(
            route('quotation.upload-po', $quotation->id),
            ['po_document' => UploadedFile::fake()->create('po.pdf', 500, 'application/pdf')]
        )->assertStatus(422);

        $this->assertSame(Quotation::STATUS_DRAFT, $quotation->fresh()->status);
    }

    public function test_upload_po_rejects_without_can_update_permission(): void
    {
        $quotation = $this->createQuotation(Quotation::STATUS_APPROVED);

        $this->actingAs($this->userWithoutUpdate)->postJson(
            route('quotation.upload-po', $quotation->id),
            ['po_document' => UploadedFile::fake()->create('po.pdf', 500, 'application/pdf')]
        )->assertStatus(403);

        $this->assertSame(Quotation::STATUS_APPROVED, $quotation->fresh()->status);
    }

    public function test_upload_po_reupload_replaces_old_file(): void
    {
        $quotation = $this->createQuotation(Quotation::STATUS_APPROVED);

        $this->actingAs($this->userWithUpdate)->postJson(
            route('quotation.upload-po', $quotation->id),
            ['po_document' => UploadedFile::fake()->create('po-1.pdf', 500, 'application/pdf')]
        )->assertOk();

        $oldPath = $quotation->fresh()->po_document_path;
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($this->userWithUpdate)->postJson(
            route('quotation.upload-po', $quotation->id),
            ['po_document' => UploadedFile::fake()->create('po-2.pdf', 500, 'application/pdf')]
        )->assertOk();

        $fresh = $quotation->fresh();
        $this->assertNotSame($oldPath, $fresh->po_document_path);
        $this->assertSame('po-2.pdf', $fresh->po_document_name);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($fresh->po_document_path);
    }

    public function test_upload_po_rejects_invalid_mime(): void
    {
        $quotation = $this->createQuotation(Quotation::STATUS_APPROVED);

        $this->actingAs($this->userWithUpdate)->postJson(
            route('quotation.upload-po', $quotation->id),
            ['po_document' => UploadedFile::fake()->create('po.exe', 100)]
        )->assertStatus(422);

        $this->assertNull($quotation->fresh()->po_document_path);
    }

    public function test_view_po_streams_uploaded_file(): void
    {
        $quotation = $this->createQuotation(Quotation::STATUS_APPROVED);

        $this->actingAs($this->userWithUpdate)->postJson(
            route('quotation.upload-po', $quotation->id),
            ['po_document' => UploadedFile::fake()->create('po.pdf', 500, 'application/pdf')]
        )->assertOk();

        $this->actingAs($this->userWithUpdate)
            ->get(route('quotation.view-po', $quotation->id))
            ->assertOk();
    }
}

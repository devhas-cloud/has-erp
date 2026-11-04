<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\CustomerReceipt;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectBillingTerm;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Timeline penagihan project -> invoice per termin, dokumen kontrak project dan
 * dokumen pendukung termin (disk private).
 */
class ProjectBillingTimelineTest extends TestCase
{
    use RefreshDatabase;

    private AccountCompany $company;

    private User $admin;

    private User $staff;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Document::DISK);

        $this->company = AccountCompany::create([
            'account_name' => 'PT Maju Bersama',
            'address_billing_province' => 'DKI Jakarta',
            'status' => 'Active',
        ]);

        $projectModule = Module::create(['module_code' => 'MOD_PROJECT', 'module_name' => 'Project', 'route_name' => 'project', 'icon' => 'fa fa-diagram-project', 'group' => 'Admin']);
        $invoiceModule = Module::create(['module_code' => 'MOD_INVOICE', 'module_name' => 'Invoice', 'route_name' => 'invoice', 'icon' => 'fa fa-file-invoice-dollar', 'group' => 'Admin']);

        $this->admin = User::create(['username' => 'superadmin', 'email' => 'admin@has.com', 'password' => bcrypt('secret'), 'role' => 'Admin']);
        $this->staff = User::create(['username' => 'staff', 'email' => 'staff@has.com', 'password' => bcrypt('secret'), 'role' => 'User']);
        $this->outsider = User::create(['username' => 'outsider', 'email' => 'out@has.com', 'password' => bcrypt('secret'), 'role' => 'User']);

        $full = ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false];
        foreach ([$projectModule, $invoiceModule] as $module) {
            UserAccessControl::create(array_merge(['user_id' => $this->staff->id, 'module_id' => $module->id], $full));
        }
    }

    private function projectPayload(array $overrides = []): array
    {
        return array_merge([
            'project_name' => 'Project SPARING',
            'company_id' => $this->company->id,
            'po_number_company' => 'PO-'.uniqid(),
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-12-01',
            'total_sale_amount' => 2000000,
            'net_sale_amount' => 1500000,
            'is_retention' => 0,
            'type_of_achievement' => 'project',
            'sales' => [$this->staff->id],
        ], $overrides);
    }

    private function termsPayload(): array
    {
        return [
            ['name' => 'DP', 'percent' => 30, 'description' => 'Ditagih untuk proses'],
            ['name' => 'Termin 1', 'percent' => 50, 'description' => 'Ditagih saat barang akan dikirim', 'target_date' => '2026-11-15'],
            ['name' => 'Termin 2', 'percent' => 20, 'description' => 'Ditagih saat instalasi'],
        ];
    }

    /**
     * Project dengan 3 termin. Termin baru selalu Terkunci; secara default semua
     * dibuka lewat endpoint gerbang tagihan agar bisa dibuatkan invoice.
     */
    private function createProjectWithTerms(bool $unlock = true): Project
    {
        $id = $this->actingAs($this->staff)->post(route('project.store'), $this->projectPayload([
            'billing_terms' => $this->termsPayload(),
        ]), ['Accept' => 'application/json'])->assertOk()->json('id');

        $project = Project::with('billingTerms')->findOrFail($id);
        if ($unlock) {
            foreach ($project->billingTerms as $term) {
                $this->setTermAccess($project, $term, false)->assertOk();
            }
        }

        return $project->load('billingTerms');
    }

    private function setTermAccess(Project $project, ProjectBillingTerm $term, bool $lock, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->patchJson(
            route('project.billing-terms.access', [$project->id, $term->id]),
            ['locked' => $lock]
        );
    }

    private function invoicePayload(Project $project, ?int $termId, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $project->id,
            'billing_term_id' => $termId,
            'invoice_date' => '2026-10-20',
            'terms' => 14,
            'noted' => 'DP',
            'percent_payment' => 99,
            'ppn' => 11,
            // Project manual: dasar invoice termin = net sale 1.500.000 (sebelum PPN).
            'items' => [['description' => 'Paket SPARING', 'qty' => 1, 'unit' => 'lot', 'unit_price' => 1500000]],
        ], $overrides);
    }

    public function test_project_store_saves_timeline_and_agreement_documents_privately(): void
    {
        $id = $this->actingAs($this->staff)->post(route('project.store'), $this->projectPayload([
            'billing_terms' => $this->termsPayload(),
            'agreement_files' => [UploadedFile::fake()->create('kontrak.pdf', 200, 'application/pdf')],
            'agreement_notes' => 'Kontrak induk',
        ]), ['Accept' => 'application/json'])->assertOk()->json('id');

        $project = Project::with(['billingTerms', 'documents'])->findOrFail($id);
        $this->assertSame(['DP', 'Termin 1', 'Termin 2'], $project->billingTerms->pluck('name')->all());
        $this->assertSame([30.0, 50.0, 20.0], $project->billingTerms->pluck('percent')->all());
        $this->assertSame('2026-11-15', $project->billingTerms[1]->target_date->toDateString());

        $doc = $project->documents->first();
        $this->assertSame(Document::TYPE_AGREEMENT, $doc->type);
        $this->assertSame('Kontrak induk', $doc->notes);
        Storage::disk(Document::DISK)->assertExists($doc->file_path);

        $this->actingAs($this->staff)->get(route('project.documents.show', [$project->id, $doc->id]))->assertOk();
        // Pengguna tanpa akses modul project tidak bisa membuka dokumen.
        $this->actingAs($this->outsider)->get(route('project.documents.show', [$project->id, $doc->id]))->assertStatus(302);

        $this->actingAs($this->staff)->get(route('project.show', $id))
            ->assertOk()->assertSee('Timeline Penagihan')->assertSee('Termin 1')->assertSee('Open')->assertSee('kontrak.pdf');
        $this->actingAs($this->staff)->get(route('project.edit', $id))->assertOk()->assertSee('Termin 2', false);
    }

    public function test_timeline_is_optional_and_total_cannot_exceed_100_percent(): void
    {
        $this->actingAs($this->staff)->post(route('project.store'), $this->projectPayload(), ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($this->staff)->post(route('project.store'), $this->projectPayload([
            'billing_terms' => [['name' => 'DP', 'percent' => 60], ['name' => 'Pelunasan', 'percent' => 50]],
        ]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, '110'));

        // Di bawah 100% boleh (timeline bisa dilengkapi menyusul).
        $this->actingAs($this->staff)->post(route('project.store'), $this->projectPayload([
            'billing_terms' => [['name' => 'DP', 'percent' => 30]],
        ]), ['Accept' => 'application/json'])->assertOk();
    }

    public function test_invoice_from_term_locks_percent_and_marks_term_as_billed(): void
    {
        $project = $this->createProjectWithTerms();
        [$dp, $termin1] = $project->billingTerms->all();

        $this->actingAs($this->staff)->get(route('invoice.create', ['project_id' => $project->id, 'billing_term_id' => $dp->id]))
            ->assertOk()->assertSee('"'.$dp->id.'"', false);

        $this->actingAs($this->staff)->getJson(route('invoice.fetch-project', $project->id))
            ->assertJsonPath('data.billing_terms.0.name', 'DP')
            ->assertJsonPath('data.billing_terms.0.available', true);

        // Persen dari client (99) diabaikan: mengikuti termin (30%).
        $invoiceId = $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id))
            ->assertOk()->json('redirect');
        $invoice = Invoice::firstOrFail();
        $this->assertSame($dp->id, $invoice->billing_term_id);
        $this->assertSame(30.0, $invoice->percent_payment);

        // Satu invoice aktif per termin.
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id))
            ->assertStatus(422)->assertJsonValidationErrors('billing_term_id');

        $this->actingAs($this->staff)->getJson(route('invoice.fetch-project', $project->id))
            ->assertJsonPath('data.billing_terms.0.available', false)
            ->assertJsonPath('data.billing_terms.0.invoice_number', $invoice->invoice_number);
        // Saat mengedit invoice itu sendiri, termin tetap tersedia.
        $this->actingAs($this->staff)->getJson(route('invoice.fetch-project', [$project->id, 'invoice_id' => $invoice->id]))
            ->assertJsonPath('data.billing_terms.0.available', true);

        $this->actingAs($this->staff)->get(route('project.show', $project->id))
            ->assertOk()->assertSee($invoice->invoice_number)->assertSee('Draft');

        // Termin dari project lain ditolak.
        $other = Project::create(array_merge($this->projectPayload(['po_number_company' => 'PO-OTHER']), [
            'project_number' => '26109999', 'status' => Project::STATUS_ACTIVE, 'created_by' => $this->admin->id,
        ]));
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($other, $termin1->id))
            ->assertStatus(422)->assertJsonValidationErrors('billing_term_id');

        // Invoice tanpa termin (di luar timeline) tetap boleh, tetapi alasan wajib.
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, null, ['percent_payment' => 10]))
            ->assertStatus(422)->assertJsonValidationErrors('non_term_reason');
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, null, [
            'percent_payment' => 10,
            'non_term_reason' => 'Pekerjaan tambahan di luar PO',
        ]))->assertOk();
        $extra = Invoice::latest('id')->first();
        $this->assertSame(10.0, $extra->percent_payment);
        $this->assertSame('Pekerjaan tambahan di luar PO', $extra->non_term_reason);
        $this->assertTrue($extra->isOutsideTimeline());
    }

    public function test_term_invoice_must_match_project_contract_value(): void
    {
        $project = $this->createProjectWithTerms();
        $dp = $project->billingTerms->first();

        // Nilai kontrak project tanpa quotation = net sale 1.500.000 (bukan total sale 2.000.000 yang termasuk PPN).
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id, [
            'items' => [['description' => 'Paket SPARING', 'qty' => 1, 'unit' => 'lot', 'unit_price' => 2000000]],
        ]))->assertStatus(422)->assertJsonValidationErrors('items');

        // Diskon yang membuat nilai sebelum PPN berbeda juga ditolak.
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id, [
            'discount_amount' => 100000,
        ]))->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(0, Invoice::count());
    }

    public function test_last_term_bills_remaining_contract_value_without_rounding_gap(): void
    {
        $id = $this->actingAs($this->staff)->post(route('project.store'), $this->projectPayload([
            'total_sale_amount' => 1110002,
            'net_sale_amount' => 1000001,
            'billing_terms' => [
                ['name' => 'Termin 1', 'percent' => 33.33],
                ['name' => 'Termin 2', 'percent' => 33.33],
                ['name' => 'Termin 3', 'percent' => 33.34],
            ],
        ]), ['Accept' => 'application/json'])->assertOk()->json('id');
        $project = Project::with('billingTerms')->findOrFail($id);
        foreach ($project->billingTerms as $term) {
            $this->setTermAccess($project, $term, false)->assertOk();
        }

        $items = ['items' => [['description' => 'Paket', 'qty' => 1, 'unit' => 'lot', 'unit_price' => 1000001]]];
        // Termin terakhir ditagih lebih dulu: belum melengkapi 100%, tetap persen x dasar.
        foreach ($project->billingTerms->reverse() as $term) {
            $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $term->id, $items))->assertOk();
        }

        $invoices = Invoice::orderBy('id')->get();
        $this->assertSame(333400.33, $invoices[0]->amount_payment);
        $this->assertSame(333300.33, $invoices[1]->amount_payment);
        // Invoice yang melengkapi timeline = sisa nilai kontrak.
        $this->assertSame(333300.34, $invoices[2]->amount_payment);
        $this->assertSame(100000100, (int) round($invoices->sum('amount_payment') * 100));
        $this->assertSame(round(333300.34 * 1.11, 2), $invoices[2]->total_payment);
    }

    public function test_manual_project_net_sale_is_billing_base_and_locks_after_term_invoice(): void
    {
        // Net (sebelum PPN) tidak boleh melebihi total (termasuk PPN).
        $this->actingAs($this->staff)->post(route('project.store'), $this->projectPayload([
            'total_sale_amount' => 1000000, 'net_sale_amount' => 1200000,
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', 'Net Sale Amount (sebelum PPN) tidak boleh melebihi Total Sale Amount.');

        $project = $this->createProjectWithTerms();
        $this->assertSame(1500000.0, $project->billingBaseAmount());
        $this->actingAs($this->staff)->get(route('project.show', $project->id))
            ->assertOk()->assertSee('Rp 450,000')->assertSee('(Net Sale Amount)');

        // Sebelum ada invoice termin, net sale masih boleh diubah.
        $update = fn (array $overrides) => $this->actingAs($this->staff)->post(route('project.update', $project->id), array_merge($this->projectPayload([
            'po_number_company' => $project->po_number_company,
            'billing_terms' => $project->billingTerms->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'percent' => $t->percent])->all(),
        ]), ['_method' => 'PUT'], $overrides), ['Accept' => 'application/json']);
        $update(['net_sale_amount' => 1600000])->assertOk();
        $update(['net_sale_amount' => 1500000])->assertOk();

        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $project->billingTerms->first()->id))->assertOk();
        $invoice = Invoice::firstOrFail();
        $this->assertSame(450000.0, $invoice->amount_payment);

        $update(['net_sale_amount' => 1600000])
            ->assertStatus(422)->assertJsonPath('message', 'Net Sale Amount terkunci karena sudah ada invoice termin '.$invoice->invoice_number.'. Batalkan invoice tersebut dulu bila nilai project berubah.');
        // Field lain tetap bisa diubah selama net sale sama.
        $update(['project_name' => 'Project SPARING Revisi'])->assertOk();
        $this->actingAs($this->staff)->get(route('project.edit', $project->id))->assertOk()->assertSee('Terkunci karena sudah ada invoice termin');
    }

    public function test_cancelled_invoice_reopens_term(): void
    {
        $project = $this->createProjectWithTerms();
        $dp = $project->billingTerms->first();

        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id))->assertOk();
        Invoice::firstOrFail()->update(['status' => Invoice::STATUS_CANCELLED]);

        $this->assertTrue($dp->fresh()->isOpen());
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id))->assertOk();
        $this->assertSame(2, $dp->invoices()->count());
    }

    public function test_billed_term_percent_is_locked_and_cannot_be_deleted(): void
    {
        $project = $this->createProjectWithTerms();
        [$dp, $termin1, $termin2] = $project->billingTerms->all();
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id))->assertOk();

        $update = fn (array $terms) => $this->actingAs($this->staff)->post(route('project.update', $project->id), array_merge(
            $this->projectPayload(['po_number_company' => $project->po_number_company]),
            ['_method' => 'PUT', 'billing_terms' => $terms]
        ), ['Accept' => 'application/json']);

        $termRow = fn (ProjectBillingTerm $t, array $o = []) => array_merge(['id' => $t->id, 'name' => $t->name, 'percent' => $t->percent], $o);

        $update([$termRow($dp, ['percent' => 25]), $termRow($termin1), $termRow($termin2)])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'terkunci'));
        $update([$termRow($termin1), $termRow($termin2)])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'tidak bisa dihapus'));

        // Nama & keterangan termin yang sudah ditagih boleh diubah; termin Open boleh dihapus.
        $update([$termRow($dp, ['name' => 'Uang Muka', 'description' => 'Untuk proses produksi']), $termRow($termin1, ['percent' => 70])])->assertOk();

        $terms = $project->billingTerms()->get();
        $this->assertSame(['Uang Muka', 'Termin 1'], $terms->pluck('name')->all());
        $this->assertSame([30.0, 70.0], $terms->pluck('percent')->all());
        $this->assertNull(ProjectBillingTerm::find($termin2->id));
    }

    public function test_term_documents_upload_from_project_and_invoice_and_lock_after_approval(): void
    {
        $project = $this->createProjectWithTerms();
        $termin1 = $project->billingTerms[1];

        $docs = $this->actingAs($this->staff)->post(route('project.documents.store', $project->id), [
            'billing_term_id' => $termin1->id,
            'files' => [UploadedFile::fake()->create('surat-jalan.pdf', 100, 'application/pdf')],
            'notes' => 'Surat jalan',
        ], ['Accept' => 'application/json'])->assertOk()->json('documents');
        $this->assertCount(1, $docs);
        $this->assertSame(Document::TYPE_SUPPORT, Document::firstOrFail()->type);

        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $termin1->id))->assertOk();
        $invoice = Invoice::firstOrFail();

        // Dokumen menyusul diupload dari halaman invoice, melekat ke termin yang sama.
        $this->actingAs($this->staff)->post(route('invoice.documents.store', $invoice->id), [
            'files' => [UploadedFile::fake()->image('bast.jpg')],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'documents');
        $this->assertSame(2, $termin1->documents()->count());

        $bast = $termin1->documents()->latest('id')->first();
        $this->actingAs($this->staff)->get(route('invoice.documents.show', [$invoice->id, $bast->id]))->assertOk();
        $this->actingAs($this->staff)->get(route('invoice.show', $invoice->id))->assertOk()->assertSee('Dokumen Pendukung')->assertSee('bast.jpg');

        // Dokumen termin lain tidak bisa dibuka lewat invoice ini.
        $otherDoc = Document::storeFor($project->billingTerms[2], UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), Document::TYPE_SUPPORT);
        $this->actingAs($this->staff)->get(route('invoice.documents.show', [$invoice->id, $otherDoc->id]))->assertNotFound();

        // Setelah invoice Approved, dokumen tidak bisa dihapus (hanya bisa ditambah).
        $invoice->update(['approval_status' => Invoice::APPROVAL_APPROVED]);
        $this->actingAs($this->staff)->deleteJson(route('project.documents.destroy', [$project->id, $bast->id]))->assertStatus(422);
        $this->actingAs($this->staff)->deleteJson(route('invoice.documents.destroy', [$invoice->id, $bast->id]))->assertStatus(422);

        $invoice->update(['approval_status' => Invoice::APPROVAL_DRAFT]);
        $this->actingAs($this->staff)->deleteJson(route('invoice.documents.destroy', [$invoice->id, $bast->id]))->assertOk();
        Storage::disk(Document::DISK)->assertMissing($bast->file_path);
    }

    public function test_document_upload_validates_files_and_ownership(): void
    {
        $project = $this->createProjectWithTerms();
        $other = Project::create(array_merge($this->projectPayload(['po_number_company' => 'PO-OTHER']), [
            'project_number' => '26109999', 'status' => Project::STATUS_ACTIVE, 'created_by' => $this->admin->id,
        ]));
        $otherTerm = $other->billingTerms()->create(['name' => 'DP', 'percent' => 100, 'sort_order' => 1]);

        $this->actingAs($this->staff)->post(route('project.documents.store', $project->id), [
            'files' => [UploadedFile::fake()->create('virus.exe', 10)],
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('files.0');

        $this->actingAs($this->staff)->post(route('project.documents.store', $project->id), [
            'billing_term_id' => $otherTerm->id,
            'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('billing_term_id');

        $doc = Document::storeFor($other, UploadedFile::fake()->create('kontrak-lain.pdf', 10, 'application/pdf'), Document::TYPE_AGREEMENT);
        $this->actingAs($this->staff)->get(route('project.documents.show', [$project->id, $doc->id]))->assertNotFound();

        // Hapus project ikut menghapus file dokumennya.
        $this->actingAs($this->staff)->deleteJson(route('project.destroy', $other->id))->assertOk();
        Storage::disk(Document::DISK)->assertMissing($doc->file_path);
        $this->assertSame(0, Document::where('documentable_id', $otherTerm->id)->count());
    }

    public function test_project_with_issued_invoice_cannot_be_deleted(): void
    {
        $project = $this->createProjectWithTerms();
        $dp = $project->billingTerms->first();

        // Invoice Draft ikut terhapus bersama project.
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id))->assertOk();
        $invoice = Invoice::firstOrFail();
        $this->assertSame([], $project->fresh()->deletionBlockers());

        // Invoice yang sudah diajukan / disetujui (atau punya penerimaan) mengunci project.
        $invoice->update(['approval_status' => Invoice::APPROVAL_APPROVED]);
        CustomerReceipt::create([
            'receipt_number' => '26100001', 'invoice_id' => $invoice->id, 'receipt_date' => '2026-10-21',
            'method' => 'transfer', 'amount' => $invoice->total_payment, 'status' => CustomerReceipt::STATUS_POSTED,
        ]);
        $invoice->update(['status' => Invoice::STATUS_PAID]);

        $this->actingAs($this->staff)->deleteJson(route('project.destroy', $project->id))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Project tidak bisa dihapus karena sudah memiliki 1 invoice yang sudah diajukan / disetujui. Bila project batal, ubah statusnya menjadi Cancelled agar riwayat transaksi tetap utuh.');
        $this->assertNotNull($project->fresh());
        $this->assertSame(1, CustomerReceipt::count());

        // Project lain dengan invoice Draft saja tetap bisa dihapus; invoicenya ikut terhapus.
        $other = $this->createProjectWithTerms();
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($other, $other->billingTerms->first()->id))->assertOk();
        $this->actingAs($this->staff)->deleteJson(route('project.destroy', $other->id))->assertOk();
        $this->assertSame(1, Invoice::count());
    }

    public function test_terms_start_locked_and_unlock_requires_update_permission(): void
    {
        $projectModule = Module::where('route_name', 'project')->firstOrFail();
        $invoiceModule = Module::where('route_name', 'invoice')->firstOrFail();

        // Tim invoice (penerima notifikasi) tanpa hak edit project.
        $invoicer = User::create(['username' => 'invoicer', 'email' => 'inv@has.com', 'password' => bcrypt('secret'), 'role' => 'User']);
        UserAccessControl::create(['user_id' => $invoicer->id, 'module_id' => $projectModule->id, 'can_read' => true]);
        UserAccessControl::create(['user_id' => $invoicer->id, 'module_id' => $invoiceModule->id, 'can_read' => true, 'can_create' => true]);

        $project = $this->createProjectWithTerms(false);
        [$dp, $termin1] = $project->billingTerms->all();

        $this->assertNull($dp->unlocked_at);
        $this->assertFalse($dp->isOpen());
        $this->actingAs($this->staff)->get(route('project.show', $project->id))
            ->assertOk()->assertSee('Terkunci')->assertSee('Buka Tagihan');
        $this->actingAs($invoicer)->get(route('project.show', $project->id))
            ->assertOk()->assertSee('Menunggu dibuka')->assertDontSee('onclick="pjSetTermAccess', false);

        // Termin terkunci tidak bisa ditagih.
        $this->actingAs($this->staff)->getJson(route('invoice.fetch-project', $project->id))
            ->assertJsonPath('data.billing_terms.0.unlocked', false)
            ->assertJsonPath('data.billing_terms.0.available', false);
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id))
            ->assertStatus(422)->assertJsonValidationErrors('billing_term_id');

        // Tanpa can_update Project tidak bisa membuka termin.
        $this->setTermAccess($project, $dp, false, $invoicer)->assertStatus(403);
        $this->assertNull($dp->fresh()->unlocked_at);

        $this->setTermAccess($project, $dp, false)->assertOk();
        $dp->refresh();
        $this->assertNotNull($dp->unlocked_at);
        $this->assertSame($this->staff->id, $dp->unlocked_by);
        $this->assertTrue($dp->isOpen());

        // Notifikasi ke tim invoice (bukan ke pelaku), dengan link ke timeline project.
        $notification = $invoicer->notifications()->firstOrFail();
        $this->assertSame('billing_term_unlocked', $notification->type);
        $this->assertStringContainsString('DP', $notification->body);
        $this->assertSame(route('project.show', $project->id).'#timeline-penagihan', $notification->data['url']);
        $this->assertSame(0, Notification::where('user_id', $this->staff->id)->count());
        $this->actingAs($invoicer)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonPath('data.0.notifications.0.url', $notification->data['url']);

        $this->actingAs($this->staff)->get(route('project.show', $project->id))->assertOk()->assertSee('Dibuka staff');
        $this->actingAs($this->staff)->postJson(route('invoice.store'), $this->invoicePayload($project, $dp->id))->assertOk();
        $invoice = Invoice::firstOrFail();

        // Sudah ditagih: tidak bisa dikunci lagi.
        $this->setTermAccess($project, $dp, true)->assertStatus(422);

        // Invoice dibatalkan -> termin kembali Open (tetap terbuka), bisa ditagih ulang.
        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
        $this->assertTrue($dp->fresh()->isOpen());

        // Dua arah: termin Open yang belum ditagih bisa dikunci lagi.
        $this->setTermAccess($project, $termin1, false)->assertOk();
        $this->setTermAccess($project, $termin1, true)->assertOk();
        $this->assertNull($termin1->fresh()->unlocked_at);
        $this->assertFalse($termin1->fresh()->isOpen());

        // Dokumen tetap boleh diupload saat termin terkunci.
        $this->actingAs($this->staff)->post(route('project.documents.store', $project->id), [
            'billing_term_id' => $termin1->id,
            'files' => [UploadedFile::fake()->create('persiapan.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();
    }
}

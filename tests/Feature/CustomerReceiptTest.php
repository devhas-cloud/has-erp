<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $approver;

    private User $viewer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $invoiceModule = Module::create(['module_code' => 'MOD_INVOICE', 'module_name' => 'Invoice', 'route_name' => 'invoice', 'icon' => 'fa fa-file-invoice-dollar', 'group' => 'Finance']);
        $receiptModule = Module::create(['module_code' => 'MOD_CUSTOMER_RECEIPT', 'module_name' => 'Penerimaan Customer', 'route_name' => 'customer-receipt', 'icon' => 'fa fa-hand-holding-dollar', 'group' => 'Finance']);

        $this->staff = $this->makeUser('staff', [
            [$receiptModule, ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true]],
            [$invoiceModule, ['can_read' => true]],
        ]);
        $this->approver = $this->makeUser('approver', [
            [$receiptModule, ['can_read' => true, 'can_create' => true, 'can_approve' => true]],
            [$invoiceModule, ['can_read' => true, 'can_create' => true]],
        ]);
        $this->viewer = $this->makeUser('viewer', [
            [$receiptModule, ['can_read' => true]],
        ]);

        $company = AccountCompany::create(['account_name' => 'PT Maju Bersama', 'status' => 'Active']);
        $this->project = Project::create([
            'project_number' => '26100001',
            'project_name' => 'Project SPARING',
            'company_id' => $company->id,
            'po_number_company' => 'PO-CUST-001',
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-12-01',
            'total_sale_amount' => 1000000,
            'net_sale_amount' => 1000000,
            'type_of_achievement' => Project::TYPE_PROJECT,
            'status' => Project::STATUS_ACTIVE,
        ]);
    }

    private function makeUser(string $username, array $grants): User
    {
        $user = User::create(['username' => $username, 'email' => $username.'@has.com', 'password' => bcrypt('secret'), 'role' => 'User']);

        foreach ($grants as [$module, $access]) {
            UserAccessControl::create(array_merge([
                'user_id' => $user->id,
                'module_id' => $module->id,
                'can_create' => false,
                'can_read' => false,
                'can_update' => false,
                'can_delete' => false,
                'can_approve' => false,
            ], $access));
        }

        return $user;
    }

    private function invoice(array $overrides = []): Invoice
    {
        static $seq = 0;
        $seq++;

        return Invoice::create(array_merge([
            'project_id' => $this->project->id,
            'invoice_number' => '2610000'.$seq,
            'invoice_date' => '2026-10-20',
            'terms' => 14,
            'due_date' => '2026-11-03',
            'noted' => 'DP',
            'non_term_reason' => 'Pekerjaan tambahan',
            'subtotal' => 1000000,
            'percent_payment' => 100,
            'amount_payment' => 1000000,
            'ppn' => 11,
            'total_payment' => 1110000,
            'status' => Invoice::STATUS_UNPAID,
            'approval_status' => Invoice::APPROVAL_APPROVED,
            'approved_at' => now(),
        ], $overrides));
    }

    private function payload(Invoice $invoice, array $overrides = []): array
    {
        return array_merge([
            'invoice_id' => $invoice->id,
            'receipt_date' => now()->toDateString(),
            'method' => 'transfer',
            'deposit_account' => 'BCA Operasional',
            'reference_number' => 'TRF-001',
            'notes' => null,
        ], $overrides);
    }

    private function draft(Invoice $invoice): CustomerReceipt
    {
        $this->actingAs($this->staff)->postJson(route('customer-receipt.store'), $this->payload($invoice))->assertOk();

        return CustomerReceipt::latest('id')->firstOrFail();
    }

    public function test_draft_amount_follows_invoice_total_and_validates_input(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->staff)->postJson(route('customer-receipt.store'), $this->payload($invoice, ['reference_number' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('reference_number');
        $this->actingAs($this->staff)->postJson(route('customer-receipt.store'), $this->payload($invoice, ['receipt_date' => now()->addDay()->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('receipt_date');

        // Nominal dari client diabaikan: selalu total invoice.
        $this->actingAs($this->staff)->postJson(route('customer-receipt.store'), $this->payload($invoice, ['amount' => 5]))->assertOk();
        $receipt = CustomerReceipt::firstOrFail();
        $this->assertSame(1110000.0, $receipt->amount);
        $this->assertSame(CustomerReceipt::STATUS_DRAFT, $receipt->status);
        $this->assertSame($this->project->company_id, $receipt->company_id);
        $this->assertMatchesRegularExpression('/^RV-\d{8}$/', $receipt->receipt_number);

        // Draft belum melunasi invoice.
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->fresh()->status);

        // Tunai tidak wajib no. referensi.
        $this->actingAs($this->staff)->postJson(route('customer-receipt.store'), $this->payload($this->invoice(), ['method' => 'cash', 'reference_number' => '']))->assertOk();
    }

    public function test_only_approved_unpaid_invoice_with_single_active_receipt(): void
    {
        $draftInvoice = $this->invoice(['approval_status' => Invoice::APPROVAL_DRAFT, 'approved_at' => null]);
        $this->actingAs($this->staff)->postJson(route('customer-receipt.store'), $this->payload($draftInvoice))->assertStatus(422);

        $invoice = $this->invoice();
        $receipt = $this->draft($invoice);
        $this->actingAs($this->staff)->postJson(route('customer-receipt.store'), $this->payload($invoice))
            ->assertStatus(422)->assertJsonPath('message', 'Invoice '.$invoice->invoice_number.' sudah dicatat di penerimaan '.$receipt->receipt_number.' (Draft).');

        // Invoice dengan penerimaan aktif tidak muncul di pilihan.
        $ids = collect($this->actingAs($this->staff)->getJson(route('customer-receipt.open-invoices'))->assertOk()->json('results'))->pluck('id');
        $this->assertNotContains($invoice->id, $ids);
        $this->assertNotContains($draftInvoice->id, $ids);
    }

    public function test_posting_marks_invoice_paid_and_creator_may_post(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->draft($invoice);

        $this->actingAs($this->staff)->postJson(route('customer-receipt.approve', $receipt->id))->assertForbidden();

        // Pembuat yang punya can_approve boleh memposting penerimaannya sendiri.
        $ownInvoice = $this->invoice();
        $this->actingAs($this->approver)->postJson(route('customer-receipt.store'), $this->payload($ownInvoice))->assertOk();
        $own = CustomerReceipt::latest('id')->first();
        $this->actingAs($this->approver)->get(route('customer-receipt.show', $own->id))->assertOk()->assertSee('cr-post-btn', false);
        $this->actingAs($this->approver)->postJson(route('customer-receipt.approve', $own->id))->assertOk();
        $this->assertSame(Invoice::STATUS_PAID, $ownInvoice->fresh()->status);

        $this->actingAs($this->approver)->postJson(route('customer-receipt.approve', $receipt->id))->assertOk();

        $receipt->refresh();
        $invoice->refresh();
        $this->assertSame(CustomerReceipt::STATUS_POSTED, $receipt->status);
        $this->assertSame($this->approver->id, $receipt->posted_by);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(now()->toDateString(), $invoice->date_payment->toDateString());
        $this->assertSame('TRF-001', $invoice->reference_number);
        $this->assertSame($this->approver->id, $invoice->paid_by);

        // Detail invoice merujuk ke Penerimaan Customer (satu istilah).
        $this->actingAs($this->approver)->get(route('invoice.show', $invoice->id))->assertOk()
            ->assertSee('Riwayat Penerimaan Customer')
            ->assertSee(route('customer-receipt.show', $receipt->id), false)
            ->assertDontSee('Penerimaan Pembayaran');

        // Posted tidak bisa diedit, dihapus, atau diposting ulang.
        $this->actingAs($this->staff)->putJson(route('customer-receipt.update', $receipt->id), $this->payload($invoice))->assertStatus(422);
        $this->actingAs($this->staff)->deleteJson(route('customer-receipt.destroy', $receipt->id))->assertStatus(422);
        $this->actingAs($this->approver)->postJson(route('customer-receipt.approve', $receipt->id))->assertStatus(422);
    }

    public function test_void_reopens_invoice_for_a_new_receipt(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->draft($invoice);

        $this->actingAs($this->approver)->postJson(route('customer-receipt.void', $receipt->id), ['void_reason' => 'x'])->assertStatus(422);
        $this->actingAs($this->approver)->postJson(route('customer-receipt.approve', $receipt->id))->assertOk();

        $this->actingAs($this->approver)->postJson(route('customer-receipt.void', $receipt->id), [])
            ->assertStatus(422)->assertJsonValidationErrors('void_reason');
        $this->actingAs($this->approver)->postJson(route('customer-receipt.void', $receipt->id), ['void_reason' => 'Salah pilih invoice'])->assertOk();

        $this->assertSame(CustomerReceipt::STATUS_VOID, $receipt->fresh()->status);
        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
        $this->assertNull($invoice->date_payment);
        $this->assertNull($invoice->reference_number);
        $this->assertNull($invoice->paid_by);

        // Invoice bisa dicatat ulang.
        $this->draft($invoice);
        $this->assertSame(2, $invoice->receipts()->count());
    }

    public function test_invoice_cannot_be_cancelled_while_receipt_draft_exists(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->draft($invoice);

        $this->actingAs($this->approver)->postJson(route('invoice.cancel', $invoice->id), ['cancel_reason' => 'Batal'])
            ->assertStatus(422)->assertJsonPath('message', 'Invoice ini sedang dicatat di penerimaan '.$receipt->receipt_number.'. Hapus draft penerimaan itu dulu.');

        $this->actingAs($this->staff)->deleteJson(route('customer-receipt.destroy', $receipt->id))->assertOk();
        $this->actingAs($this->approver)->postJson(route('invoice.cancel', $invoice->id), ['cancel_reason' => 'Batal'])->assertOk();
    }

    public function test_draft_update_keeps_invoice(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->draft($invoice);

        $this->actingAs($this->staff)->putJson(route('customer-receipt.update', $receipt->id), $this->payload($this->invoice()))
            ->assertStatus(422);
        $this->actingAs($this->staff)->putJson(route('customer-receipt.update', $receipt->id), $this->payload($invoice, ['reference_number' => 'TRF-009']))
            ->assertOk();
        $this->assertSame('TRF-009', $receipt->fresh()->reference_number);
    }

    public function test_permissions_and_pages(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->viewer)->postJson(route('customer-receipt.store'), $this->payload($invoice))->assertForbidden();
        $receipt = $this->draft($invoice);

        $this->actingAs($this->viewer)->get(route('customer-receipt.index'))->assertOk()->assertSee('Penerimaan Customer');
        $this->actingAs($this->viewer)->getJson(route('customer-receipt.data'))->assertOk()
            ->assertJsonPath('data.0.receipt_number', $receipt->receipt_number)
            ->assertJsonPath('status_counts.draft', 1);
        $this->actingAs($this->viewer)->get(route('customer-receipt.show', $receipt->id))->assertOk()
            ->assertSee($invoice->invoice_number)->assertDontSee('Posting Penerimaan');
        $this->actingAs($this->approver)->get(route('customer-receipt.show', $receipt->id))->assertOk()->assertSee('Posting Penerimaan');
        $this->actingAs($this->staff)->get(route('customer-receipt.edit', $receipt->id))->assertOk();

        $other = $this->invoice();
        $this->actingAs($this->staff)->get(route('customer-receipt.create', ['invoice_id' => $other->id]))->assertOk()->assertSee($other->invoice_number);

        // Detail invoice: tautan ke draft penerimaan / tombol catat penerimaan.
        $this->actingAs($this->approver)->get(route('invoice.show', $invoice->id))->assertOk()
            ->assertSee('Penerimaan '.$receipt->receipt_number.' (Draft)');
        $this->actingAs($this->approver)->get(route('invoice.show', $other->id))->assertOk()
            ->assertSee(route('customer-receipt.create', ['invoice_id' => $other->id]), false);
    }
}

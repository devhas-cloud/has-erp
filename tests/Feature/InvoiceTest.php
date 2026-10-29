<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Invoice;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountCompany $company;

    private Project $project;

    private MasterProduct $product;

    private User $creator;

    private User $viewer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = AccountCompany::create([
            'account_name' => 'PT Maju Bersama',
            'address_billing_street' => 'Jl. Industri No. 1',
            'address_billing_city' => 'Jakarta',
            'address_billing_province' => 'DKI Jakarta',
            'address_billing_postal_code' => '12345',
            'address_billing_country' => 'Indonesia',
            'status' => 'Active',
        ]);

        $module = Module::create([
            'module_code' => 'MOD_INVOICE',
            'module_name' => 'Invoice',
            'route_name' => 'invoice',
            'icon' => 'fa fa-file-invoice-dollar',
            'group' => 'Admin',
        ]);

        $admin = User::create([
            'username' => 'superadmin',
            'email' => 'superadmin@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);

        $this->creator = $this->makeUser('creator', $module, [
            'can_read' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);
        $this->viewer = $this->makeUser('viewer', $module, ['can_read' => true]);
        $this->approver = $this->makeUser('approver', $module, [
            'can_read' => true,
            'can_approve' => true,
        ]);

        $this->project = Project::create([
            'project_number' => '26100001',
            'project_name' => 'Project SPARING',
            'company_id' => $this->company->id,
            'po_number_company' => 'PO-CUST-001',
            'po_date' => '2026-10-01',
            'po_date_received' => '2026-10-02',
            'po_due_date' => '2026-11-01',
            'total_sale_amount' => 1250000,
            'net_sale_amount' => 1000000,
            'type_of_achievement' => Project::TYPE_PROJECT,
            'status' => Project::STATUS_ACTIVE,
            'created_by' => $admin->id,
        ]);

        $this->product = MasterProduct::create([
            'name' => 'RO 1000 LPD',
            'code' => 'RO-1000',
            'selling_price' => 500000,
            'status' => 'Active',
        ]);
    }

    private function makeUser(string $username, Module $module, array $access): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'invoice_date' => now()->toDateString(),
            'ship_to' => 'DKI Jakarta',
            'shipping_method' => 'Truck',
            'do_number' => 'DO-001',
            'terms' => 30,
            'noted' => 'DP',
            'percent_payment' => 50,
            'ppn' => 11,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => 'RO 1000 LPD',
                    'description' => 'Item RO 1000 LPD',
                    'qty' => 2,
                    'unit' => 'unit',
                    'unit_price' => 500000,
                ],
                [
                    'product_id' => null,
                    'product_name' => 'Instalasi',
                    'description' => 'Jasa instalasi',
                    'qty' => 1,
                    'unit' => 'paket',
                    'unit_price' => 250000,
                ],
            ],
        ], $overrides);
    }

    public function test_index_requires_read_permission(): void
    {
        $this->actingAs($this->viewer)->get(route('invoice.index'))->assertOk();

        $noAccess = $this->makeUser('noaccess', Module::where('module_code', 'MOD_INVOICE')->firstOrFail(), []);
        $this->actingAs($noAccess)->get(route('invoice.index'))->assertStatus(302);
    }

    public function test_status_schema_is_unpaid_paid_cancelled(): void
    {
        $this->assertSame(['unpaid', 'paid', 'cancelled'], array_keys(Invoice::STATUS_LABELS));
        $this->assertSame('Unpaid', Invoice::STATUS_LABELS[Invoice::STATUS_UNPAID]);
        $this->assertSame('Paid', Invoice::STATUS_LABELS[Invoice::STATUS_PAID]);
        $this->assertSame('Cancelled', Invoice::STATUS_LABELS[Invoice::STATUS_CANCELLED]);

        $invoice = new Invoice(['status' => Invoice::STATUS_UNPAID]);
        $this->assertSame('Unpaid', $invoice->status_label);
        $this->assertStringContainsString('Unpaid', $invoice->statusBadgeHtml());

        $invoice->status = Invoice::STATUS_PAID;
        $this->assertSame('Paid', $invoice->status_label);
        $this->assertStringContainsString('Paid', $invoice->statusBadgeHtml());

        $invoice->status = Invoice::STATUS_CANCELLED;
        $this->assertSame('Cancelled', $invoice->status_label);
        $this->assertStringContainsString('Cancelled', $invoice->statusBadgeHtml());
    }

    public function test_store_creates_invoice_with_items_and_calculated_totals(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'status' => Invoice::STATUS_PAID,
            'approval_status' => Invoice::APPROVAL_APPROVED,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $invoice = Invoice::firstOrFail();

        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
        $this->assertSame(Invoice::APPROVAL_DRAFT, $invoice->approval_status);
        $this->assertSame(now()->format('ym').'0001', $invoice->invoice_number);
        $this->assertSame(1250000.0, $invoice->subtotal);
        $this->assertSame(625000.0, $invoice->amount_payment);
        $this->assertSame(11.0, $invoice->ppn);
        $this->assertSame(693750.0, $invoice->total_payment);
        $this->assertSame(2, $invoice->items()->count());

        $first = $invoice->items()->orderBy('id')->firstOrFail();
        $this->assertSame($this->product->id, $first->product_id);
        $this->assertSame('RO 1000 LPD', $first->product_name);
        $this->assertSame('Item RO 1000 LPD', $first->description);
        $this->assertSame(1000000.0, $first->total_price);
    }

    public function test_item_description_is_required_and_sanitized(): void
    {
        $payload = $this->payload();
        $payload['items'][1]['description'] = '   ';

        $this->actingAs($this->creator)->postJson(route('invoice.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.1.description');

        $payload['items'][1]['description'] = 'Instalasi';
        $payload['items'][0]['description'] = '<script>alert(1)</script>Baris <b>tebal</b>';

        $this->actingAs($this->creator)->postJson(route('invoice.store'), $payload)->assertOk();

        $item = Invoice::firstOrFail()->items()->firstOrFail();
        $this->assertStringNotContainsString('<script', $item->description);
        $this->assertStringContainsString('<b>tebal</b>', $item->description);
    }

    public function test_item_name_is_derived_from_description_when_product_name_is_empty(): void
    {
        $payload = $this->payload();
        unset($payload['items'][0]['product_name']);
        $payload['items'][0]['product_id'] = null;
        $payload['items'][0]['description'] = 'Baris <b>pertama</b><br>detail item';

        $this->actingAs($this->creator)->postJson(route('invoice.store'), $payload)->assertOk();

        $item = Invoice::firstOrFail()->items()->firstOrFail();
        $this->assertSame('Baris pertama detail item', $item->product_name);
        $this->assertSame('Baris <b>pertama</b><br>detail item', $item->description);
    }

    public function test_due_date_follows_terms_with_default_fourteen_days(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'invoice_date' => '2026-10-01',
        ]))->assertOk();
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'invoice_date' => '2026-10-01',
            'terms' => null,
        ]))->assertOk();

        [$first, $second] = Invoice::orderBy('id')->get()->all();

        $this->assertSame('2026-10-31', $first->due_date->toDateString());
        $this->assertSame('30', (string) $first->terms);
        $this->assertSame('2026-10-15', $second->due_date->toDateString());
        $this->assertSame('14', (string) $second->terms);
    }

    public function test_signer_defaults_and_can_be_overridden(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'signer_name' => 'Budi Santoso',
            'signer_position' => 'Direktur Utama',
        ]))->assertOk();

        [$first, $second] = Invoice::orderBy('id')->get()->all();

        $this->assertSame(Invoice::DEFAULT_SIGNER_NAME, $first->signer_name);
        $this->assertSame(Invoice::DEFAULT_SIGNER_POSITION, $first->signer_position);
        $this->assertSame('Budi Santoso', $second->signer_name);
        $this->assertSame('Direktur Utama', $second->signer_position);
    }

    public function test_update_keeps_payment_reference_when_not_sent(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();
        $invoice->update(['reference_number' => 'REF-001', 'date_payment' => '2026-10-20']);

        $this->actingAs($this->creator)->putJson(route('invoice.update', $invoice->id), $this->payload())
            ->assertOk();

        $invoice->refresh();
        $this->assertSame('REF-001', $invoice->reference_number);
        $this->assertSame('2026-10-20', $invoice->date_payment->toDateString());
    }

    public function test_fetch_project_returns_province_and_quotation_items(): void
    {
        $quotation = Quotation::create([
            'status' => Quotation::STATUS_FINISH,
            'created_by' => $this->creator->id,
            'quotation_number' => '001/HAS/QT-T/X/2026',
            'to_name' => 'PT Maju Bersama',
        ]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'description' => 'Paket Sistem', 'qty' => 1, 'selling_price' => 0, 'sort_order' => 1]);
        QuotationItem::create(['quotation_id' => $quotation->id, 'part_number' => 'RO-1000', 'description' => '<p>Alat <b>SPARING</b></p>', 'qty' => 2, 'unit' => 'unit', 'selling_price' => 750000, 'sort_order' => 2]);
        $this->project->update(['quotation_id' => $quotation->id]);

        $this->actingAs($this->creator)->getJson(route('invoice.fetch-project', $this->project->id))
            ->assertOk()
            ->assertJsonPath('data.ship_to', 'DKI Jakarta')
            ->assertJsonPath('data.quotation_number', '001/HAS/QT-T/X/2026')
            ->assertJsonCount(1, 'data.quotation_items')
            ->assertJsonPath('data.quotation_items.0.product_id', $this->product->id)
            ->assertJsonPath('data.quotation_items.0.product_name', 'RO 1000 LPD')
            ->assertJsonPath('data.quotation_items.0.description', 'Alat <b>SPARING</b>')
            ->assertJsonPath('data.quotation_items.0.unit', 'unit');
    }

    public function test_fetch_project_uses_quotation_value_before_ppn_and_discount_defaults(): void
    {
        $quotation = Quotation::create([
            'status' => Quotation::STATUS_FINISH,
            'created_by' => $this->creator->id,
            'quotation_number' => '002/HAS/QT-T/X/2026',
            'to_name' => 'PT Maju Bersama',
            'subtotal' => 2000000,
            'discount_percent' => 10,
            'ppn' => 198000,
            'grand_total' => 1998000,
        ]);
        $this->project->update(['quotation_id' => $quotation->id, 'total_sale_amount' => 1998000]);

        $this->actingAs($this->creator)->getJson(route('invoice.fetch-project', $this->project->id))
            ->assertOk()
            ->assertJsonPath('data.before_ppn', 1800000)
            ->assertJsonPath('data.discount_percent', 10)
            ->assertJsonPath('data.discount_amount', 200000);

        $quotation->update(['discount_percent' => null, 'discount_amount' => 150000]);

        $this->actingAs($this->creator)->getJson(route('invoice.fetch-project', $this->project->id))
            ->assertJsonPath('data.before_ppn', 1850000)
            ->assertJsonPath('data.discount_percent', null)
            ->assertJsonPath('data.discount_amount', 150000);
    }

    public function test_discount_is_applied_before_payment_percent_and_ppn(): void
    {
        // Sub total 1.250.000, diskon 10% = 125.000 -> sebelum PPN 1.125.000.
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'discount_percent' => 10,
        ]))->assertOk();
        // Diskon nominal 250.000 -> sebelum PPN 1.000.000.
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'discount_amount' => 250000,
        ]))->assertOk();

        [$byPercent, $byAmount] = Invoice::orderBy('id')->get()->all();

        $this->assertSame(10.0, $byPercent->discount_percent);
        $this->assertSame(125000.0, $byPercent->discount_amount);
        $this->assertSame(1125000.0, $byPercent->net_subtotal);
        $this->assertSame(562500.0, $byPercent->amount_payment);
        $this->assertSame(624375.0, $byPercent->total_payment);

        $this->assertNull($byAmount->discount_percent);
        $this->assertSame(250000.0, $byAmount->discount_amount);
        $this->assertSame(500000.0, $byAmount->amount_payment);
        $this->assertSame(555000.0, $byAmount->total_payment);

        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'discount_amount' => 2000000,
        ]))->assertStatus(422)->assertJsonValidationErrors('discount_amount');

        $this->actingAs($this->creator)->get(route('invoice.show', $byPercent->id))
            ->assertOk()
            ->assertSee('Diskon 10%')
            ->assertSee('Rp 1,125,000');
        $this->actingAs($this->creator)->get(route('invoice.pdf', $byPercent->id))->assertOk();
    }

    public function test_invoice_number_starts_at_0001_and_increments(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();

        $numbers = Invoice::orderBy('id')->pluck('invoice_number')->all();

        $this->assertSame(now()->format('ym').'0001', $numbers[0]);
        $this->assertSame(now()->format('ym').'0002', $numbers[1]);
    }

    public function test_invoice_number_sequence_resets_each_year(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'invoice_date' => '2026-12-31',
            'due_date' => '2027-01-14',
        ]))->assertOk();

        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'invoice_date' => '2027-01-01',
            'due_date' => '2027-01-15',
        ]))->assertOk();

        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload([
            'invoice_date' => '2026-11-01',
            'due_date' => '2026-11-15',
        ]))->assertOk();

        $numbers = Invoice::orderBy('id')->pluck('invoice_number')->all();

        $this->assertSame('26120001', $numbers[0]);
        $this->assertSame('27010001', $numbers[1]);
        $this->assertSame('26110002', $numbers[2]);
    }

    public function test_update_recalculates_items_and_keeps_status_under_backend_control(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->creator)->putJson(route('invoice.update', $invoice->id), $this->payload([
            'percent_payment' => 100,
            'ppn' => 10,
            'status' => Invoice::STATUS_PAID,
            'date_payment' => null,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => 'RO 1000 LPD',
                    'description' => 'Item RO 1000 LPD',
                    'qty' => 3,
                    'unit' => 'unit',
                    'unit_price' => 100000,
                ],
            ],
        ]))->assertOk();

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
        $this->assertSame(Invoice::APPROVAL_DRAFT, $invoice->approval_status);
        $this->assertSame(300000.0, $invoice->subtotal);
        $this->assertSame(300000.0, $invoice->amount_payment);
        $this->assertSame(330000.0, $invoice->total_payment);
        $this->assertSame(1, $invoice->items()->count());
    }

    public function test_approval_lifecycle_submit_and_approve(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->creator)->postJson(route('invoice.submit', $invoice->id))
            ->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::APPROVAL_WAITING_APPROVAL, $invoice->approval_status);

        $this->actingAs($this->approver)->postJson(route('invoice.approve', $invoice->id))
            ->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::APPROVAL_APPROVED, $invoice->approval_status);
        $this->assertSame($this->approver->id, $invoice->final_checked_by);
        $this->assertNotNull($invoice->approved_at);
        $this->assertNull($invoice->rejected_at);

        $this->actingAs($this->viewer)->getJson(route('invoice.data'))
            ->assertOk()
            ->assertJsonPath('data.0.approval_status', Invoice::APPROVAL_APPROVED)
            ->assertJsonPath('data.0.status', Invoice::STATUS_UNPAID);
    }

    public function test_reject_requires_note_and_resubmit_clears_approval_metadata(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();
        $this->actingAs($this->creator)->postJson(route('invoice.submit', $invoice->id))->assertOk();

        $this->actingAs($this->approver)->postJson(route('invoice.reject', $invoice->id), [
            'approval_note' => '',
        ])->assertStatus(422);

        $this->actingAs($this->approver)->postJson(route('invoice.reject', $invoice->id), [
            'approval_note' => 'Harga belum sesuai PO.',
        ])->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::APPROVAL_REJECTED, $invoice->approval_status);
        $this->assertSame('Harga belum sesuai PO.', $invoice->approval_note);
        $this->assertSame($this->approver->id, $invoice->final_checked_by);
        $this->assertNotNull($invoice->rejected_at);

        $this->actingAs($this->creator)->putJson(route('invoice.update', $invoice->id), $this->payload())
            ->assertOk();

        $this->actingAs($this->creator)->postJson(route('invoice.submit', $invoice->id))
            ->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::APPROVAL_WAITING_APPROVAL, $invoice->approval_status);
        $this->assertNull($invoice->approval_note);
        $this->assertNull($invoice->approved_at);
        $this->assertNull($invoice->rejected_at);
        $this->assertNull($invoice->final_checked_by);
    }

    public function test_approval_actions_require_can_approve(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();
        $this->actingAs($this->creator)->postJson(route('invoice.submit', $invoice->id))->assertOk();

        $this->actingAs($this->creator)->postJson(route('invoice.approve', $invoice->id))
            ->assertStatus(403);
        $this->actingAs($this->viewer)->postJson(route('invoice.reject', $invoice->id), [
            'approval_note' => 'Tidak setuju.',
        ])->assertStatus(403);
    }

    public function test_invoice_cannot_be_edited_or_deleted_after_submit_or_approval(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();
        $this->actingAs($this->creator)->postJson(route('invoice.submit', $invoice->id))->assertOk();

        $this->actingAs($this->creator)->putJson(route('invoice.update', $invoice->id), $this->payload())
            ->assertStatus(422);
        $this->actingAs($this->creator)->deleteJson(route('invoice.destroy', $invoice->id))
            ->assertStatus(422);
        $this->actingAs($this->creator)->get(route('invoice.edit', $invoice->id))
            ->assertRedirect(route('invoice.show', $invoice->id));

        $this->actingAs($this->approver)->postJson(route('invoice.approve', $invoice->id))->assertOk();

        $this->actingAs($this->creator)->putJson(route('invoice.update', $invoice->id), $this->payload())
            ->assertStatus(422);
        $this->actingAs($this->creator)->deleteJson(route('invoice.destroy', $invoice->id))
            ->assertStatus(422);
    }

    public function test_pdf_uses_watermark_until_invoice_is_approved(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();

        $html = view('invoice.pdf', ['invoice' => $invoice])->render();
        $this->assertStringContainsString('class="watermark"', $html);
        $this->assertStringContainsString('DRAFT', $html);

        $this->actingAs($this->creator)->postJson(route('invoice.submit', $invoice->id))->assertOk();

        $html = view('invoice.pdf', ['invoice' => $invoice->fresh()])->render();
        $this->assertStringContainsString('WAITING APPROVAL', $html);

        $this->actingAs($this->approver)->postJson(route('invoice.reject', $invoice->id), [
            'approval_note' => 'Perbaiki nominal.',
        ])->assertOk();

        $html = view('invoice.pdf', ['invoice' => $invoice->fresh()])->render();
        $this->assertStringContainsString('REJECTED', $html);

        $this->actingAs($this->creator)->postJson(route('invoice.submit', $invoice->id))->assertOk();
        $this->actingAs($this->approver)->postJson(route('invoice.approve', $invoice->id))->assertOk();

        $html = view('invoice.pdf', ['invoice' => $invoice->fresh()])->render();
        $this->assertStringNotContainsString('class="watermark"', $html);
    }

    public function test_store_requires_create_permission(): void
    {
        $this->actingAs($this->viewer)->postJson(route('invoice.store'), $this->payload())
            ->assertStatus(403);

        $this->assertSame(0, Invoice::count());
    }

    public function test_update_and_delete_require_permission_and_delete_cascades_items(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->viewer)->putJson(route('invoice.update', $invoice->id), $this->payload())
            ->assertStatus(403);
        $this->actingAs($this->viewer)->deleteJson(route('invoice.destroy', $invoice->id))
            ->assertStatus(403);

        $this->actingAs($this->creator)->deleteJson(route('invoice.destroy', $invoice->id))->assertOk();

        $this->assertSame(0, Invoice::count());
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->creator)->get(route('invoice.index'))
            ->assertOk()
            ->assertSee('Daftar Invoice');

        $this->actingAs($this->creator)->get(route('invoice.create'))
            ->assertOk()
            ->assertSee('Buat Invoice');

        $this->actingAs($this->creator)->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Item RO 1000 LPD')
            ->assertSee('Total Payment');

        $this->actingAs($this->creator)->get(route('invoice.edit', $invoice->id))
            ->assertOk()
            ->assertSee('Edit Invoice')
            ->assertDontSee('id="inv-status"', false);

        $this->actingAs($this->creator)->get(route('invoice.pdf', $invoice->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function approvedInvoice(): Invoice
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();
        $this->actingAs($this->creator)->postJson(route('invoice.submit', $invoice->id))->assertOk();
        $this->actingAs($this->approver)->postJson(route('invoice.approve', $invoice->id))->assertOk();

        return $invoice->fresh();
    }

    public function test_pay_requires_payment_date_and_reference(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->approver)->postJson(route('invoice.pay', $invoice->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_payment', 'reference_number']);

        $this->actingAs($this->approver)->postJson(route('invoice.pay', $invoice->id), [
            'date_payment' => now()->addDay()->toDateString(),
            'reference_number' => 'TRF-001',
        ])->assertStatus(422)->assertJsonValidationErrors(['date_payment']);

        $this->actingAs($this->approver)->postJson(route('invoice.pay', $invoice->id), [
            'date_payment' => now()->toDateString(),
            'reference_number' => 'TRF-001',
        ])->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(now()->toDateString(), $invoice->date_payment->toDateString());
        $this->assertSame('TRF-001', $invoice->reference_number);
        $this->assertSame($this->approver->id, $invoice->paid_by);

        // Sudah lunas: tidak bisa dicatat ulang atau dibatalkan.
        $this->actingAs($this->approver)->postJson(route('invoice.pay', $invoice->id), [
            'date_payment' => now()->toDateString(),
            'reference_number' => 'TRF-002',
        ])->assertStatus(422);
        $this->actingAs($this->creator)->postJson(route('invoice.cancel', $invoice->id), ['cancel_reason' => 'x'])
            ->assertStatus(422);
    }

    public function test_pay_requires_approved_invoice_and_approve_permission(): void
    {
        $this->actingAs($this->creator)->postJson(route('invoice.store'), $this->payload())->assertOk();
        $invoice = Invoice::firstOrFail();
        $data = ['date_payment' => now()->toDateString(), 'reference_number' => 'TRF-001'];

        $this->actingAs($this->approver)->postJson(route('invoice.pay', $invoice->id), $data)->assertStatus(422);

        $invoice = $this->approvedInvoice();
        $this->actingAs($this->creator)->postJson(route('invoice.pay', $invoice->id), $data)->assertStatus(403);
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->fresh()->status);
    }

    public function test_cancel_requires_reason(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->creator)->postJson(route('invoice.cancel', $invoice->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cancel_reason']);

        $this->actingAs($this->creator)->postJson(route('invoice.cancel', $invoice->id), [
            'cancel_reason' => 'Customer membatalkan PO',
        ])->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->status);
        $this->assertSame('Customer membatalkan PO', $invoice->cancel_reason);
        $this->assertSame($this->creator->id, $invoice->cancelled_by);
        $this->assertNotNull($invoice->cancelled_at);

        $this->actingAs($this->approver)->postJson(route('invoice.pay', $invoice->id), [
            'date_payment' => now()->toDateString(),
            'reference_number' => 'TRF-001',
        ])->assertStatus(422);

        $this->actingAs($this->creator)->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee('Customer membatalkan PO');
    }
}

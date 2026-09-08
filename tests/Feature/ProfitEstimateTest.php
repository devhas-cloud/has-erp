<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Currency;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\ProfitEstimate;
use App\Models\Quotation;
use App\Models\QuoteConfiguration;
use App\Models\QuoteConfigurationItem;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitEstimateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Division $division;

    private Opportunity $opportunity;

    private Currency $usd;

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

        $company = AccountCompany::create([
            'account_name' => 'PT Kawasan Industri Gresik (Site Tuban)',
            'status' => 'Active',
        ]);

        $this->opportunity = Opportunity::create([
            'opportunity_name' => 'SPARING Kawasan Industri Gresik',
            'account_companies_id' => $company->id,
            'owner_id' => $this->admin->id,
            'probability' => 70,
        ]);

        foreach ([
            ['MOD_QUOTATION', 'Quotation', 'quotation'],
            ['MOD_PROFIT_ESTIMATE', 'Estimasi PL', 'profit-estimate'],
        ] as [$code, $name, $route]) {
            $module = Module::create([
                'module_code' => $code,
                'module_name' => $name,
                'route_name' => $route,
                'icon' => 'fa fa-file',
                'group' => 'Admin',
            ]);
            UserAccessControl::create([
                'user_id' => $this->admin->id,
                'module_id' => $module->id,
                'can_create' => true,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => true,
                'can_approve' => true,
            ]);
        }

        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 17500, 'is_base' => false, 'status' => 'Active']);
        Currency::create(['name' => 'EUR', 'symbol' => '€', 'rate' => 20000, 'is_base' => false, 'status' => 'Active']);
    }

    /**
     * Quotation approved dengan 2 item leaf, 1 di antaranya terhubung ke
     * master product bermerek "Badger Meter" berharga USD.
     */
    private function createQuotation(array $override = []): Quotation
    {
        $category = TaskCategory::create(['name' => 'Quote', 'use_division_handler' => true]);

        $task = Task::create([
            'creator_id' => $this->admin->id,
            'category_id' => $category->id,
            'opportunity_id' => $this->opportunity->id,
            'title' => 'Quote SPARING',
            'due_date' => '2026-08-11',
            'status' => 'in_progress',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ]);

        $product = MasterProduct::create([
            'name' => 'pHlyser',
            'code' => 'PH-001',
            'brand' => 'Badger Meter',
            'division_id' => $this->division->id,
            'price' => 2000,
            'currency_id' => $this->usd->id,
            'status' => 'Active',
        ]);

        $config = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'date' => '2026-08-11',
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ]);
        $config->update(['group_id' => $config->id]);

        QuoteConfigurationItem::create([
            'quote_configuration_id' => $config->id,
            'product_id' => $product->id,
            'part_number' => 'PH-001',
            'description' => 'pHlyser',
            'qty' => 2,
            'sort_order' => 1,
        ]);

        $quotation = Quotation::create(array_merge([
            'quotation_number' => '001/HAS/QT-ZM/IX/2026',
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'date' => '2026-09-01',
            'to_name' => 'PT Kawasan Industri Gresik (Site Tuban)',
            'from_name' => 'Zuri',
            'subtotal' => 1_749_900_000,
            'dpp' => 1_749_900_000,
            'ppn' => 100_000,
            'discount_amount' => 0,
            'grand_total' => 1_750_000_000,
            'status' => Quotation::STATUS_APPROVED,
            'created_by' => $this->admin->id,
        ], $override));
        $quotation->update(['group_id' => $quotation->id]);
        $quotation->configurations()->attach($config->id);

        $quotation->items()->create(['item_no' => '1', 'part_number' => 'PH-001', 'description' => 'pHlyser<br>with cable', 'qty' => 2, 'price' => 100, 'unit' => 'Each', 'sort_order' => 1]);
        $quotation->items()->create(['item_no' => '2', 'description' => 'Kalibrasi lokal', 'qty' => 1, 'price' => 100, 'unit' => 'Lot', 'sort_order' => 2]);

        $quotation->costItems()->create(['item_no' => '1', 'description' => 'Survei', 'qty' => 2, 'price' => 1_000_000, 'unit' => 'Lot', 'sort_order' => 1]);

        return $quotation->fresh();
    }

    /** Rumus diverifikasi terhadap angka lembar contoh "Estimasi Perhitungan Pendapatan". */
    public function test_calculate_matches_reference_sheet(): void
    {
        $rates = ['USD' => 17500, 'EUR' => 20000, 'GBP' => 22500];

        $lines = [
            ['section' => 'product', 'label' => 'pHlyser', 'qty' => 1, 'unit' => 'Each', 'vendor' => 'Badger Meter'],
            ['section' => 'hpp', 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 4715.9375],
            ['section' => 'hpp', 'vendor' => 'S::CAN', 'currency' => 'EUR', 'amount' => 24357.80],
            ['section' => 'hpp', 'vendor' => 'BD', 'currency' => 'IDR', 'amount' => 7_000_000],
            ['section' => 'hpp', 'vendor' => 'Lab lokal', 'currency' => 'IDR', 'amount' => 25_000_000],
            ['section' => 'hpp', 'vendor' => 'Bu Yaya/B Abdul', 'currency' => 'IDR', 'amount' => 65_000_000],
            ['section' => 'cost_spent', 'label' => 'Biaya kirim barang masuk', 'currency' => 'IDR', 'amount' => 102_543_283.13],
            ['section' => 'cost_spent', 'label' => 'Biaya kirim barang keluar', 'currency' => 'IDR', 'amount' => 40_000_000],
            ['section' => 'cost_spent', 'label' => 'Biaya operational custom duty tax', 'currency' => 'IDR', 'amount' => 39_877_943.44],
            ['section' => 'cost_planned', 'label' => 'Survei, Installation, Komisioning, Kalibrasi', 'currency' => 'IDR', 'amount' => 25_700_000],
            ['section' => 'cost_planned', 'label' => 'Maintenance 2x visit', 'currency' => 'IDR', 'amount' => 11_200_000],
        ];

        $result = ProfitEstimate::calculate([
            'nilai_awal' => 1_750_000_000,
            'ppn_amount' => 100_000,
            'discount_amount' => 0,
            'referral_percent' => 13.10,
            'investment_percent' => 15,
            'marketing_fee_percent' => 1,
            'pm_fee_percent' => 0.5,
        ], $lines, $rates);

        $h = $result['header'];

        $this->assertSame(1_749_900_000.0, $h['subtotal_1']);
        $this->assertSame(1_749_900_000.0, $h['subtotal_2']);
        $this->assertSame(229_236_900.0, $h['referral_amount']);
        $this->assertSame(1_520_663_100.0, $h['real_project_value']);
        $this->assertSame(228_099_465.0, $h['investment_amount']);
        $this->assertEqualsWithDelta(1_114_105_597.82, $h['total_cost'], 0.02);
        $this->assertEqualsWithDelta(406_557_502.18, $h['estimated_profit'], 0.02);
        $this->assertSame(26.74, $h['profit_percent']);
        $this->assertSame(15_206_631.0, $h['marketing_fee_amount']);
        $this->assertSame(7_603_315.5, $h['pm_fee_amount']);
        $this->assertEqualsWithDelta(383_747_555.68, $h['real_profit'], 0.02);
        $this->assertSame(25.24, $h['real_profit_percent']);

        // Konversi kurs: USD 4.715,9375 x 17.500 dan EUR 24.357,80 x 20.000.
        $this->assertSame(82_528_906.25, $result['lines'][1]['amount_idr']);
        $this->assertSame(487_156_000.0, $result['lines'][2]['amount_idr']);
        // Baris produk tidak ikut dihitung sebagai biaya.
        $this->assertSame(0.0, $result['lines'][0]['amount_idr']);
    }

    public function test_derive_hpp_groups_items_per_vendor_and_keeps_manual_override(): void
    {
        $lines = [
            ['section' => 'product', 'label' => 'pHlyser', 'qty' => 1, 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 3000],
            ['section' => 'product', 'label' => 'M2000', 'qty' => 1, 'vendor' => 'badger meter ', 'currency' => 'USD', 'amount' => 1715.9375],
            ['section' => 'product', 'label' => 'ammolyser', 'qty' => 1, 'vendor' => 'S::CAN', 'currency' => 'EUR', 'amount' => 24357.80],
            ['section' => 'product', 'label' => 'Kalibrasi', 'qty' => 1, 'vendor' => 'Lab lokal', 'currency' => 'IDR', 'amount' => 20_000_000],
            // Override manual untuk Lab lokal; baris HPP asing (BD) dibuang.
            ['section' => 'hpp', 'vendor' => 'Lab lokal', 'currency' => 'IDR', 'amount' => 25_000_000, 'is_manual' => 1],
            ['section' => 'hpp', 'vendor' => 'BD', 'currency' => 'IDR', 'amount' => 7_000_000, 'is_manual' => 1],
            ['section' => 'hpp', 'vendor' => 'S::CAN', 'currency' => 'EUR', 'amount' => 1, 'is_manual' => 0],
            ['section' => 'cost_planned', 'label' => 'Maintenance', 'currency' => 'IDR', 'amount' => 11_200_000],
        ];

        $result = ProfitEstimate::deriveHppLines($lines);
        $hpp = array_values(array_filter($result, fn ($l) => $l['section'] === 'hpp'));

        $this->assertCount(3, $hpp);
        $this->assertSame(['Badger Meter', 'USD', 4715.9375, false], [$hpp[0]['vendor'], $hpp[0]['currency'], $hpp[0]['amount'], $hpp[0]['is_manual']]);
        $this->assertSame(['S::CAN', 'EUR', 24357.8, false], [$hpp[1]['vendor'], $hpp[1]['currency'], $hpp[1]['amount'], $hpp[1]['is_manual']]);
        $this->assertSame(['Lab lokal', 'IDR', 25_000_000.0, true], [$hpp[2]['vendor'], $hpp[2]['currency'], $hpp[2]['amount'], $hpp[2]['is_manual']]);
        $this->assertSame(20_000_000.0, $hpp[2]['derived_amount']);
        // Urutan: item, HPP, biaya lain.
        $this->assertSame(['product', 'product', 'product', 'product', 'hpp', 'hpp', 'hpp', 'cost_planned'], array_column($result, 'section'));

        // Item tanpa vendor terdeteksi.
        $this->assertSame(['X'], ProfitEstimate::productLabelsWithoutVendor([
            ['section' => 'product', 'label' => 'X', 'vendor' => ' '],
            ['section' => 'product', 'label' => 'Y', 'vendor' => 'V'],
        ]));

        // calculate(): amount item ikut dikonversi tetapi tidak masuk biaya.
        $calc = ProfitEstimate::calculate(['nilai_awal' => 1000], [
            ['section' => 'product', 'label' => 'pHlyser', 'vendor' => 'V', 'currency' => 'USD', 'amount' => 2],
            ['section' => 'hpp', 'vendor' => 'V', 'currency' => 'USD', 'amount' => 2],
        ], ['USD' => 100]);
        $this->assertSame(200.0, $calc['lines'][0]['amount_idr']);
        $this->assertSame(200.0, $calc['header']['total_cost']);
    }

    public function test_create_form_is_prefilled_from_quotation(): void
    {
        $quotation = $this->createQuotation();

        $response = $this->actingAs($this->admin)
            ->get(route('profit-estimate.create', ['quotation_id' => $quotation->id]));

        $response->assertOk();
        $response->assertViewHas('data', function (array $data) {
            $products = array_values(array_filter($data['lines'], fn ($l) => $l['section'] === 'product'));
            $hpp = array_values(array_filter($data['lines'], fn ($l) => $l['section'] === 'hpp'));
            $planned = array_values(array_filter($data['lines'], fn ($l) => $l['section'] === 'cost_planned'));

            return $data['nilai_awal'] === 1_750_000_000.0
                && $data['ppn_amount'] === 100_000.0
                && $data['rates']['USD'] === 17500.0
                && $data['investment_percent'] === ProfitEstimate::DEFAULT_INVESTMENT_PERCENT
                && $data['sales_person_name'] === 'Zuri'
                && count($products) === 2
                && $products[0]['label'] === 'pHlyser'           // baris pertama deskripsi saja
                && $products[0]['vendor'] === 'Badger Meter'     // brand dari master product
                && $products[0]['currency'] === 'USD'
                && $products[0]['amount'] === 4000.0             // amount baris = 2 x USD 2.000
                && $products[1]['vendor'] === null               // tanpa master product -> diisi user
                && $products[1]['amount'] === 0
                && $hpp[0]['vendor'] === 'Badger Meter'          // HPP turunan per vendor
                && $hpp[0]['currency'] === 'USD'
                && $hpp[0]['amount'] === 4000.0
                && $hpp[0]['is_manual'] === false
                && $planned[0]['amount'] === 2_000_000.0;        // total biaya quotation
        });
    }

    public function test_store_creates_estimate_and_rejects_duplicate(): void
    {
        $quotation = $this->createQuotation();

        $payload = [
            'quotation_id' => $quotation->id,
            'date' => '2026-09-04',
            'project_name' => 'PT Kawasan Industri Gresik (Site Tuban)',
            'rates' => ['USD' => 17500, 'EUR' => 20000],
            'nilai_awal' => 1_750_000_000,
            'ppn_amount' => 100_000,
            'discount_amount' => 0,
            'referral_percent' => 13.10,
            'investment_percent' => 15,
            'marketing_fee_percent' => 1,
            'pm_fee_percent' => 0.5,
            'sales_person_name' => 'Zuri',
            'lines' => [
                ['section' => 'product', 'label' => 'pHlyser', 'qty' => 1, 'unit' => 'Each', 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 3000],
                ['section' => 'product', 'label' => 'M2000', 'qty' => 1, 'unit' => 'Each', 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 1715.9375],
                ['section' => 'product', 'label' => 'Kalibrasi lokal', 'qty' => 1, 'unit' => 'Lot', 'vendor' => 'Lab lokal', 'currency' => 'IDR', 'amount' => 20_000_000],
                // HPP kiriman client: Lab lokal dikoreksi manual, baris vendor asing dibuang.
                ['section' => 'hpp', 'vendor' => 'Lab lokal', 'currency' => 'IDR', 'amount' => 25_000_000, 'is_manual' => 1],
                ['section' => 'hpp', 'vendor' => 'Vendor Palsu', 'currency' => 'IDR', 'amount' => 999, 'is_manual' => 1],
                ['section' => 'cost_spent', 'label' => 'Biaya kirim barang masuk', 'currency' => 'IDR', 'amount' => 1_000_000],
                ['section' => 'cost_planned', 'label' => '', 'currency' => 'IDR', 'amount' => 0], // baris kosong dibuang
            ],
        ];

        // Item tanpa vendor ditolak.
        $bad = $payload;
        $bad['lines'][2]['vendor'] = '';
        $this->actingAs($this->admin)->postJson(route('profit-estimate.store'), $bad)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Setiap item harus memiliki vendor. Item tanpa vendor: Kalibrasi lokal.']);
        $this->assertSame(0, ProfitEstimate::count());

        $response = $this->actingAs($this->admin)->postJson(route('profit-estimate.store'), $payload);
        $response->assertOk()->assertJson(['success' => true]);

        $estimate = ProfitEstimate::with('lines')->first();
        $this->assertNotNull($estimate);
        $this->assertSame($quotation->id, $estimate->quotation_id);
        $this->assertSame(1_520_663_100.0, $estimate->real_project_value);
        $this->assertSame(228_099_465.0, $estimate->investment_amount);
        $this->assertEqualsWithDelta(82_528_906.25 + 25_000_000 + 1_000_000 + 228_099_465, $estimate->total_cost, 0.01);
        $this->assertFalse($estimate->is_outdated);
        $this->assertCount(6, $estimate->lines); // 3 item + 2 HPP turunan + 1 biaya

        $hpp = $estimate->linesOf('hpp');
        $this->assertSame(['Badger Meter', 'USD', 4715.9375, false, 82_528_906.25], [$hpp[0]->vendor, $hpp[0]->currency, $hpp[0]->amount, $hpp[0]->is_manual, $hpp[0]->amount_idr]);
        $this->assertSame(['Lab lokal', 'IDR', 25_000_000.0, true], [$hpp[1]->vendor, $hpp[1]->currency, $hpp[1]->amount, $hpp[1]->is_manual]);
        // Amount item tersimpan dan terkonversi, tetapi tidak dihitung ganda sebagai biaya.
        $this->assertSame(52_500_000.0, $estimate->linesOf('product')->first()->amount_idr);
        $this->assertEquals(['USD' => 17500, 'EUR' => 20000], $estimate->rates);

        // Satu PL per quotation.
        $this->actingAs($this->admin)->postJson(route('profit-estimate.store'), $payload)
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        // Halaman detail & PDF.
        $this->actingAs($this->admin)->get(route('profit-estimate.show', $estimate->id))
            ->assertOk()
            ->assertSee('1,520,663,100.00');

        $this->actingAs($this->admin)->get(route('profit-estimate.pdf', $estimate->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_quotation_revision_marks_estimate_outdated_and_sync_form_then_update_clears_it(): void
    {
        $quotation = $this->createQuotation(['status' => Quotation::STATUS_REJECTED]);

        $estimate = ProfitEstimate::create([
            'quotation_id' => $quotation->id,
            'task_id' => $quotation->task_id,
            'date' => '2026-09-04',
            'rates' => ['USD' => 17500],
            'nilai_awal' => 1,
            'investment_percent' => 15,
            'created_by' => $this->admin->id,
        ]);
        // Item dengan harga negosiasi + koreksi manual HPP.
        $estimate->lines()->create(['section' => 'product', 'label' => 'pHlyser', 'qty' => 2, 'unit' => 'Each', 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 3500, 'amount_idr' => 61_250_000, 'sort_order' => 1]);
        $estimate->lines()->create(['section' => 'hpp', 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 3400, 'amount_idr' => 59_500_000, 'is_manual' => true, 'sort_order' => 2]);

        // Revisi quotation -> PL versi lama outdated.
        $this->actingAs($this->admin)->postJson(route('quotation.revise', $quotation->id))
            ->assertOk();

        $this->assertTrue($estimate->fresh()->is_outdated);

        $expectManualCarried = function (array $data) {
            $products = array_values(array_filter($data['lines'], fn ($l) => $l['section'] === 'product'));
            $hpp = array_values(array_filter($data['lines'], fn ($l) => $l['section'] === 'hpp'));

            return $data['nilai_awal'] === 1_750_000_000.0
                && $products[0]['label'] === 'pHlyser'
                && $products[0]['amount'] === 3500.0                 // harga negosiasi ikut (bukan 4000 master product)
                && $products[1]['label'] === 'Kalibrasi lokal'
                && $hpp[0]['vendor'] === 'Badger Meter'
                && $hpp[0]['amount'] === 3400.0 && $hpp[0]['is_manual'] === true;
        };

        // Quotation versi baru belum punya PL -> form create menyalin input manual dari PL lama.
        $revision = Quotation::where('parent_id', $quotation->id)->firstOrFail();
        $this->actingAs($this->admin)
            ->get(route('profit-estimate.create', ['quotation_id' => $revision->id]))
            ->assertOk()
            ->assertViewHas('previous', fn ($prev) => $prev && $prev->id === $estimate->id)
            ->assertViewHas('data', $expectManualCarried);

        // Sinkronkan = form edit terisi ulang dari quotation, input manual dibawa.
        $this->actingAs($this->admin)->get(route('profit-estimate.sync', $estimate->id))
            ->assertOk()
            ->assertViewHas('syncing', true)
            ->assertViewHas('data', $expectManualCarried);

        // Simpan (update) menghapus tanda outdated.
        $this->actingAs($this->admin)->putJson(route('profit-estimate.update', $estimate->id), [
            'nilai_awal' => 1_750_000_000,
            'ppn_amount' => 100_000,
            'rates' => ['USD' => 17500],
            'lines' => [
                ['section' => 'product', 'label' => 'pHlyser', 'qty' => 2, 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 3500],
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $estimate = $estimate->fresh(['lines']);
        $this->assertFalse($estimate->is_outdated);
        $this->assertSame(1_750_000_000.0, $estimate->nilai_awal);
        $this->assertSame(3500.0, $estimate->linesOf('hpp')->first()->amount); // override lama tidak dikirim -> otomatis
    }

    public function test_quotation_show_links_to_profit_estimate(): void
    {
        $quotation = $this->createQuotation();

        $this->actingAs($this->admin)->get(route('quotation.show', $quotation->id))
            ->assertOk()
            ->assertSee('Buat Estimasi PL');

        ProfitEstimate::create([
            'quotation_id' => $quotation->id,
            'date' => '2026-09-04',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('quotation.show', $quotation->id))
            ->assertOk()
            ->assertSee('Estimasi PL')
            ->assertDontSee('Buat Estimasi PL');
    }
}

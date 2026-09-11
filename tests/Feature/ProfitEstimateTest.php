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

    /**
     * Toggle "+40" (is_up) disimpan sebagai status tersendiri, bukan ditebak
     * dari nominal. deriveHppLines() SELALU menghitung ulang otomatis + 40
     * dari item saat ini, sehingga tetap benar walau item berubah di antara
     * dua kali edit/simpan (nominal tersimpan yang stale diabaikan).
     */
    public function test_derive_hpp_up_toggle_is_independent_of_stored_amount_and_recomputes(): void
    {
        // Baris HPP tersimpan dengan is_up=1 tapi nominal STALE (bukan otomatis+40
        // dari item saat ini) — mensimulasikan item yang berubah sejak simpan terakhir.
        $lines = [
            ['section' => 'product', 'label' => 'pHlyser', 'qty' => 1, 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 5000],
            ['section' => 'hpp', 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 999999, 'is_up' => 1],
        ];

        $result = ProfitEstimate::deriveHppLines($lines);
        $hpp = array_values(array_filter($result, fn ($l) => $l['section'] === 'hpp'));

        $this->assertCount(1, $hpp);
        // Bukan 999999 (nilai stale) — dihitung ulang: 5000 (item) + HPP_UP_AMOUNT.
        $this->assertSame(5000 + ProfitEstimate::HPP_UP_AMOUNT, $hpp[0]['amount']);
        $this->assertTrue($hpp[0]['is_up']);
        $this->assertFalse($hpp[0]['is_manual']);
        $this->assertSame(5000.0, $hpp[0]['derived_amount']);

        // is_up hanya berlaku untuk mata uang non-IDR; vendor IDR tetap otomatis penuh.
        $idrLines = [
            ['section' => 'product', 'label' => 'Kalibrasi', 'qty' => 1, 'vendor' => 'Lab lokal', 'currency' => 'IDR', 'amount' => 1000],
            ['section' => 'hpp', 'vendor' => 'Lab lokal', 'currency' => 'IDR', 'amount' => 999, 'is_up' => 1],
        ];
        $idrHpp = array_values(array_filter(ProfitEstimate::deriveHppLines($idrLines), fn ($l) => $l['section'] === 'hpp'));
        $this->assertSame(1000.0, $idrHpp[0]['amount']);
        $this->assertFalse($idrHpp[0]['is_up']);
    }

    /**
     * Biaya operasional berpersentase: nominal = percent% x total HPP (Rp) vendor
     * non-IDR; HPP IDR tidak ikut. Disimpan sebagai IDR. Tanpa percent = manual.
     */
    public function test_calculate_percent_cost_lines_from_foreign_hpp_total(): void
    {
        $rates = ['USD' => 17500, 'EUR' => 20000];

        $result = ProfitEstimate::calculate(['nilai_awal' => 1000], [
            ['section' => 'hpp', 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 4715.9375],   // 82.528.906,25
            ['section' => 'hpp', 'vendor' => 'S::CAN', 'currency' => 'EUR', 'amount' => 24357.80],          // 487.156.000
            ['section' => 'hpp', 'vendor' => 'Lab lokal', 'currency' => 'IDR', 'amount' => 25_000_000],      // tidak dihitung
            ['section' => 'cost_spent', 'label' => 'Biaya kirim barang masuk', 'currency' => 'USD', 'amount' => '', 'percent' => 18],
            ['section' => 'cost_spent', 'label' => 'Nominal menang', 'currency' => 'IDR', 'amount' => 5_000_000, 'percent' => 18],
            ['section' => 'cost_spent', 'label' => 'Biaya kirim barang keluar', 'currency' => 'IDR', 'amount' => 40_000_000, 'percent' => ''],
            ['section' => 'cost_spent', 'label' => 'Custom duty', 'currency' => 'IDR', 'amount' => '', 'percent' => 0],
        ], $rates);

        $lines = collect($result['lines'])->keyBy('label');

        // 18% x 569.684.906,25 = 102.543.283,13 (lembar contoh); currency/amount kiriman diabaikan.
        $this->assertSame(102_543_283.13, $lines['Biaya kirim barang masuk']['amount_idr']);
        $this->assertSame('IDR', $lines['Biaya kirim barang masuk']['currency']);
        $this->assertSame(102_543_283.13, $lines['Biaya kirim barang masuk']['amount']);
        $this->assertSame(18.0, $lines['Biaya kirim barang masuk']['percent']);
        // Nominal terisi -> persen diabaikan (disimpan null), nominal dipakai apa adanya.
        $this->assertNull($lines['Nominal menang']['percent']);
        $this->assertSame(5_000_000.0, $lines['Nominal menang']['amount_idr']);
        // Percent kosong -> manual.
        $this->assertNull($lines['Biaya kirim barang keluar']['percent']);
        $this->assertSame(40_000_000.0, $lines['Biaya kirim barang keluar']['amount_idr']);
        // Nominal kosong + percent 0 -> 0 dari persen (bukan manual).
        $this->assertSame(0.0, $lines['Custom duty']['percent']);
        $this->assertSame(0.0, $lines['Custom duty']['amount_idr']);
        // Baris HPP tidak pernah membawa percent.
        $this->assertNull($lines['Badger Meter'] ?? null);
        $this->assertNull(collect($result['lines'])->firstWhere('vendor', 'Badger Meter')['percent']);

        $this->assertEqualsWithDelta(569_684_906.25 + 25_000_000 + 102_543_283.13 + 5_000_000 + 40_000_000, $result['header']['total_cost'], 0.01);
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
            $spent = array_values(array_filter($data['lines'], fn ($l) => $l['section'] === 'cost_spent'));

            return $data['nilai_awal'] === 1_750_000_000.0
                && $data['ppn_amount'] === 100_000.0
                && $data['rates']['USD'] === 17500.0
                && $data['investment_percent'] === ProfitEstimate::DEFAULT_INVESTMENT_PERCENT
                && $data['sales_person_name'] === 'Zuri'
                && $products === []                              // daftar item kosong: user memilih dari configuration
                && $hpp === []
                && $planned[0]['amount'] === 2_000_000.0         // total biaya quotation
                && $spent[0]['percent'] === 18                   // kirim masuk: 18% dari HPP non-IDR
                && $spent[1]['percent'] === 0
                && $spent[2]['percent'] === null;                // custom duty: manual
        });

        // Snapshot List Configuration quotation tersedia untuk modal pilih item.
        $response->assertViewHas('configItems', fn ($items) => is_array($items));
    }

    public function test_create_form_exposes_quotation_config_items_for_picker(): void
    {
        $quotation = $this->createQuotation();
        $quotation->configItems()->create(['category' => 'SPARING', 'part_number' => 'PH-001', 'description' => 'pHlyser<br>with cable', 'qty' => 2, 'unit' => 'Unit', 'price' => 35_000_000, 'price_currency' => 2000, 'currency' => 'USD']);
        $quotation->configItems()->create(['category' => 'SPARING', 'part_number' => 'CAB-1', 'description' => 'Kabel', 'qty' => 1, 'price' => 500_000]);

        $this->actingAs($this->admin)
            ->get(route('profit-estimate.create', ['quotation_id' => $quotation->id]))
            ->assertOk()
            ->assertViewHas('configItems', function (array $items) {
                return count($items) === 2
                    && $items[0]['description'] === 'pHlyser'
                    && $items[0]['currency'] === 'USD'
                    && $items[0]['price_currency'] === 2000.0
                    && $items[0]['price'] === 35_000_000.0
                    && $items[0]['qty'] === 2.0
                    && $items[1]['currency'] === 'IDR'
                    && $items[1]['price_currency'] === 500_000.0;
            })
            ->assertViewHas('currencySymbols', fn ($s) => ($s['USD'] ?? null) === '$');
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
                ['section' => 'cost_spent', 'label' => 'Biaya kirim barang keluar', 'currency' => 'IDR', 'amount' => 0, 'percent' => 10], // 10% x HPP USD
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
        // HPP USD 82.528.906,25 -> kirim keluar 10% = 8.252.890,63 (HPP IDR Lab lokal tidak ikut).
        $this->assertEqualsWithDelta(82_528_906.25 + 25_000_000 + 1_000_000 + 8_252_890.63 + 228_099_465, $estimate->total_cost, 0.01);
        $this->assertFalse($estimate->is_outdated);
        $this->assertCount(7, $estimate->lines); // 3 item + 2 HPP turunan + 2 biaya
        $pctLine = $estimate->linesOf('cost_spent')->firstWhere('label', 'Biaya kirim barang keluar');
        $this->assertSame([10.0, 8_252_890.63, 'IDR'], [$pctLine->percent, $pctLine->amount_idr, $pctLine->currency]);

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

    /**
     * Toggle "+40" (is_up) tersimpan sebagai kolom sendiri dan dibaca kembali
     * apa adanya di form edit. Saat item berubah lalu disimpan lagi, nominal
     * HPP dihitung ULANG dari item terbaru + HPP_UP_AMOUNT — server tidak
     * pernah percaya nominal HPP yang dikirim client untuk baris is_up=1.
     */
    public function test_hpp_up_toggle_persists_and_recomputes_flexibly_on_edit(): void
    {
        $quotation = $this->createQuotation();

        $store = [
            'quotation_id' => $quotation->id,
            'nilai_awal' => 1_750_000_000,
            'rates' => ['USD' => 17500],
            'lines' => [
                ['section' => 'product', 'label' => 'pHlyser', 'qty' => 1, 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 3000],
                ['section' => 'hpp', 'vendor' => 'Badger Meter', 'currency' => 'USD', 'amount' => 3040, 'is_up' => 1],
            ],
        ];

        $this->actingAs($this->admin)->postJson(route('profit-estimate.store'), $store)->assertOk();

        $estimate = ProfitEstimate::with('lines')->where('quotation_id', $quotation->id)->firstOrFail();
        $hpp = $estimate->linesOf('hpp')->first();
        $this->assertTrue($hpp->is_up);
        $this->assertFalse($hpp->is_manual);
        $this->assertSame(3040.0, $hpp->amount); // 3000 (item) + HPP_UP_AMOUNT (40)

        // Form edit membawa status is_up apa adanya (bukan ditebak dari nominal).
        $this->actingAs($this->admin)->get(route('profit-estimate.edit', $estimate->id))
            ->assertOk()
            ->assertViewHas('data', function (array $data) {
                $hppLine = collect($data['lines'])->firstWhere('section', 'hpp');

                return $hppLine['is_up'] === true && $hppLine['is_manual'] === false;
            });

        // Item diubah (mis. user mengganti amount) lalu disimpan lagi. Klien mengirim
        // is_up=1 dengan nominal STALE (tidak dihitung ulang di sisi klien) — server
        // harus tetap menghasilkan nominal yang benar: item baru (5000) + 40 = 5040.
        $update = $store;
        $update['lines'][0]['amount'] = 5000;
        $update['lines'][1]['amount'] = 999999; // stale, harus diabaikan
        $this->actingAs($this->admin)->putJson(route('profit-estimate.update', $estimate->id), $update)->assertOk();

        $hpp = $estimate->fresh(['lines'])->linesOf('hpp')->first();
        $this->assertTrue($hpp->is_up);
        $this->assertSame(5040.0, $hpp->amount);
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
                && count($products) === 1                            // hanya baris item PL sebelumnya yang disalin
                && $products[0]['label'] === 'pHlyser'
                && $products[0]['amount'] === 3500.0                 // harga negosiasi ikut
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

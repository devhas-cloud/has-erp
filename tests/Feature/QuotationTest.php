<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\AccountContact;
use App\Models\Currency;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\QuoteConfiguration;
use App\Models\QuoteConfigurationItem;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private Division $imsDivision;

    private User $admin;

    private User $user;

    private TaskCategory $quoteCategory;

    private AccountCompany $company;

    private AccountContact $contact;

    private Opportunity $opportunity;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create([
            'division_name' => 'WATER',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $this->imsDivision = Division::create([
            'division_name' => 'IMS',
            'description' => 'Integrated Monitoring System',
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

        $this->user = User::create([
            'username' => 'maidin',
            'email' => 'maidin@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        $this->sales = User::create([
            'username' => 'zuri',
            'email' => 'zuri@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
            'phone_number' => '0813-1308-3131',
        ]);

        $this->quoteCategory = TaskCategory::create([
            'name' => 'Quote',
            'use_division_handler' => true,
        ]);

        $this->company = AccountCompany::create([
            'account_name' => 'Kawasan Industri Gresik (Site Tuban)',
            'address_billing_street' => 'Kantor Pusat Kawasan Industri Gresik',
            'address_billing_city' => 'Jl. Tri Dharma No. 3, Karangturi, Kec. Kebomas',
            'address_billing_province' => 'Gresik, Jawa Timur 61121',
            'status' => 'Active',
        ]);

        $this->contact = AccountContact::create([
            'account_companies_id' => $this->company->id,
            'full_name' => 'Bapak Anang Subagya',
            'email' => 'anang.subagya@sig.id',
            'mobile' => '081234567890',
        ]);

        $this->opportunity = Opportunity::create([
            'opportunity_name' => 'SPARING Kawasan Industri Gresik',
            'account_companies_id' => $this->company->id,
            'account_contacts_id' => $this->contact->id,
            'owner_id' => $this->sales->id,
            'probability' => 70,
        ]);

        $module = Module::create([
            'module_code' => 'MOD_QUOTATION',
            'module_name' => 'Quotation',
            'route_name' => 'quotation',
            'icon' => 'fa fa-file-invoice',
            'group' => 'Admin',
        ]);

        foreach ([$this->admin, $this->user] as $user) {
            UserAccessControl::create([
                'user_id' => $user->id,
                'module_id' => $module->id,
                'can_create' => true,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => true,
                'can_approve' => true,
            ]);
        }
    }

    private function createTask(): Task
    {
        return Task::create([
            'creator_id' => $this->sales->id,
            'category_id' => $this->quoteCategory->id,
            'opportunity_id' => $this->opportunity->id,
            'title' => 'Quote SPARING',
            'due_date' => '2026-08-11',
            'status' => 'in_progress',
            'alert_type' => 'none',
            'alert_target' => 'personal',
        ]);
    }

    /**
     * Buat quote configuration approved (data sumber quotation).
     * Item memakai angka contoh quote.pdf agar total bisa diverifikasi.
     */
    private function createApprovedConfiguration(?Division $division = null): QuoteConfiguration
    {
        $task = $this->createTask();

        $config = QuoteConfiguration::create([
            'division_id' => ($division ?? $this->division)->id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'date' => '2026-08-11',
            'parameter_note' => 'PH, NH3-N, TSS, COD, Debit',
            'notes' => 'Bangunan pelindung atau shelter disediakan oleh customer.',
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->user->id,
            'final_checked_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $config->update(['group_id' => $config->id]);

        $items = [
            ['category' => 'Sistem Pemantauan Kualitas Air Secara Terus Menerus dan Dalam Jaringan (SPARING)', 'description' => 'Multiparameter Sensor', 'qty' => 1, 'price' => 123100000, 'unit' => 'Unit'],
            ['category' => 'Sistem Pemantauan Kualitas Air Secara Terus Menerus dan Dalam Jaringan (SPARING)', 'description' => 'Ammo::lyser', 'qty' => 1, 'price' => 257100000, 'unit' => 'Unit'],
            ['category' => 'Sistem Pemantauan Kualitas Air Secara Terus Menerus dan Dalam Jaringan (SPARING)', 'description' => 'Measuring Flow Rate (Debit)', 'qty' => 1, 'price' => 105100000, 'unit' => 'Unit'],
            ['category' => 'Sistem Pemantauan Kualitas Air Secara Terus Menerus dan Dalam Jaringan (SPARING)', 'description' => 'Smart Logger and Telemetry', 'qty' => 1, 'price' => 293000000, 'unit' => 'Unit'],
            ['category' => 'Sistem Pemantauan Kualitas Air Secara Terus Menerus dan Dalam Jaringan (SPARING)', 'description' => 'Installation Materials', 'qty' => 1, 'price' => 50000000, 'unit' => 'Lot'],
        ];

        foreach (array_values($items) as $i => $item) {
            QuoteConfigurationItem::create([
                'quote_configuration_id' => $config->id,
                'category' => $item['category'],
                'description' => $item['description'],
                'qty' => $item['qty'],
                'price' => $item['price'],
                'unit' => $item['unit'],
                'sort_order' => $i + 1,
            ]);
        }

        return $config->fresh();
    }

    private function itemPayload(QuoteConfiguration $config, array $override = []): array
    {
        return array_merge([
            '_key' => $override['_key'] ?? 'row-1',
            'parent_key' => $override['parent_key'] ?? null,
            'item_no' => $override['item_no'] ?? '1',
            'quote_configuration_id' => $config->id,
            'category' => $config->items->first()?->category,
            'part_number' => $override['part_number'] ?? null,
            'description' => $override['description'] ?? 'Item',
            'qty' => $override['qty'] ?? 1,
            'price' => $override['price'] ?? 1000,
            'unit' => $override['unit'] ?? 'Unit',
        ], $override);
    }

    public function test_create_page_lists_eligible_tasks(): void
    {
        $config = $this->createApprovedConfiguration();

        $response = $this->actingAs($this->admin)->get(route('quotation.create'));
        $response->assertOk();
        $response->assertSee('Quote SPARING');
        $response->assertSee($config->task_id);
    }

    public function test_create_page_hides_task_when_one_latest_config_not_approved(): void
    {
        $task = $this->createTask();

        // Config WATER approved (versi terakhir).
        $water = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'date' => '2026-08-11',
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->user->id,
            'final_checked_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        $water->update(['group_id' => $water->id]);

        // Config IMS versi terakhir BELUM approved (draft).
        QuoteConfiguration::create([
            'division_id' => $this->imsDivision->id,
            'group_id' => 2,
            'version' => 1,
            'is_current' => true,
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'date' => '2026-08-11',
            'status' => QuoteConfiguration::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('quotation.create'));
        $response->assertOk();
        $response->assertDontSee('Quote SPARING');
    }

    public function test_create_page_hides_task_with_only_one_config_not_approved(): void
    {
        $task = $this->createTask();

        // Hanya 1 config, status draft (belum approved) -> task tidak layak.
        QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'date' => '2026-08-11',
            'status' => QuoteConfiguration::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('quotation.create'));
        $response->assertOk();
        $response->assertDontSee('Quote SPARING');
    }

    public function test_create_page_hides_task_that_already_has_quotation(): void
    {
        $config = $this->createApprovedConfiguration();

        // Sudah ada quotation untuk task ini.
        Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('quotation.create'));
        $response->assertOk();
        $response->assertDontSee('Quote SPARING');
    }

    public function test_create_page_lists_quotations_as_templates(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'quotation_number' => '001/HAS/QT-T/VIII/2026',
            'to_name' => 'PT Maju Bersama',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);
        $quotation->items()->create(['description' => 'Multiparameter Sensor', 'qty' => 1, 'price' => 123100000]);

        $response = $this->actingAs($this->admin)->get(route('quotation.create'));
        $response->assertOk();
        $response->assertSee('001/HAS/QT-T/VIII/2026');
        $response->assertSee('PT Maju Bersama');
    }

    public function test_fetch_template_returns_hierarchy_in_dfs_order(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'quotation_number' => '001/HAS/QT-T/VIII/2026',
            'to_name' => 'PT Maju Bersama',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $parent = $quotation->items()->create(['item_no' => '1', 'description' => 'Multiparameter Sensor', 'qty' => 1, 'unit' => 'Kit', 'sort_order' => 1]);
        $child = $quotation->items()->create(['item_no' => 'a', 'parent_id' => $parent->id, 'description' => 'pH::lyser', 'qty' => 1, 'unit' => 'Unit', 'price' => 123100000, 'sort_order' => 2]);
        $quotation->items()->create(['item_no' => '2', 'description' => 'Flow Meter', 'qty' => 1, 'unit' => 'Unit', 'price' => 105100000, 'sort_order' => 3]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-template').'?quotation_id='.$quotation->id)
            ->assertOk();

        $this->assertTrue($response->json('success'));
        $items = $response->json('data.items');
        $this->assertCount(3, $items);

        $this->assertSame('tpl-'.$parent->id, $items[0]['_key']);
        $this->assertNull($items[0]['parent_key']);
        $this->assertSame('1', $items[0]['item_no']);
        $this->assertSame('Kit', $items[0]['unit']);

        $this->assertSame('tpl-'.$child->id, $items[1]['_key']);
        $this->assertSame('tpl-'.$parent->id, $items[1]['parent_key']);
        $this->assertSame('a', $items[1]['item_no']);
        $this->assertSame(123100000.0, (float) $items[1]['price']);

        $this->assertNull($items[2]['parent_key']);
        $this->assertSame('2', $items[2]['item_no']);
    }

    public function test_fetch_template_rejects_missing_quotation(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-template').'?quotation_id=99999')
            ->assertStatus(422);
    }

    public function test_fetch_task_returns_customer_configs_items_and_products(): void
    {
        $config = $this->createApprovedConfiguration();

        $response = $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-task').'?task_id='.$config->task_id)
            ->assertOk();

        $this->assertTrue($response->json('success'));
        $this->assertSame('Kawasan Industri Gresik (Site Tuban)', $response->json('data.to_name'));
        $this->assertSame('Bapak Anang Subagya', $response->json('data.attn_name'));
        $this->assertSame('anang.subagya@sig.id', $response->json('data.attn_email'));
        $this->assertSame('zuri', $response->json('data.from_name'));
        $this->assertSame('0813-1308-3131', $response->json('data.contact_phone'));
        $this->assertSame(1, count($response->json('data.configs')));
        $this->assertSame($config->id, $response->json('data.configs.0.id'));
        $this->assertSame(5, count($response->json('data.items')));
        $this->assertSame(5, count($response->json('data.products')));
        $this->assertSame(123100000.0, (float) $response->json('data.items.0.price'));
    }

    public function test_fetch_task_merges_water_and_ims_configs(): void
    {
        $water = $this->createApprovedConfiguration($this->division);
        $ims = $this->createApprovedConfiguration($this->imsDivision);
        $taskId = $water->task_id;
        // Kedua config harus menunjuk task yang sama (createTask membuat task baru)
        $ims->update(['task_id' => $taskId]);
        $ims->refresh();

        $response = $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-task').'?task_id='.$taskId)
            ->assertOk();

        $this->assertSame(2, count($response->json('data.configs')));
        $this->assertSame(10, count($response->json('data.items')));
    }

    public function test_fetch_task_rejects_task_without_approved_config(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-task').'?task_id='.$task->id)
            ->assertStatus(422);
    }

    public function test_search_products_filters_by_division(): void
    {
        MasterProduct::create([
            'division_id' => $this->division->id,
            'name' => 'pH Analyzer',
            'code' => 'PH-100',
            'brand' => 'HAS',
            'category' => 'Analyzer',
            'price' => 5000000,
            'status' => 'Active',
        ]);
        MasterProduct::create([
            'division_id' => $this->imsDivision->id,
            'name' => 'UV Spectro',
            'code' => 'UV-200',
            'brand' => 'S::CAN',
            'category' => 'Spectral',
            'price' => 25000000,
            'status' => 'Active',
        ]);
        MasterProduct::create([
            'division_id' => $this->division->id,
            'name' => 'Non Aktif',
            'code' => 'OFF-1',
            'brand' => 'HAS',
            'category' => 'Lainnya',
            'price' => 1000,
            'status' => 'Inactive',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('quotation.search-products').'?division_id='.$this->imsDivision->id)
            ->assertOk();

        $data = collect($response->json('data'));
        $this->assertSame(1, $data->count());
        $this->assertSame('UV-200', $data->first()['code']);
        $this->assertSame(25000000.0, (float) $data->first()['price']);

        // Tanpa filter divisi -> semua product aktif.
        $responseAll = $this->actingAs($this->admin)
            ->getJson(route('quotation.search-products'))
            ->assertOk();
        $this->assertSame(2, count($responseAll->json('data')));

        // Pencarian teks.
        $responseFiltered = $this->actingAs($this->admin)
            ->getJson(route('quotation.search-products').'?search[value]=S::CAN')
            ->assertOk();
        $this->assertSame(1, count($responseFiltered->json('data')));
    }

    public function test_search_products_price_follows_currency_rate(): void
    {
        $base = Currency::create([
            'name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active',
        ]);
        $usd = Currency::create([
            'name' => 'USD', 'symbol' => '$', 'rate' => 20000, 'is_base' => false, 'status' => 'Active',
        ]);

        MasterProduct::create([
            'division_id' => $this->division->id,
            'name' => 'Produk IDR',
            'code' => 'IDR-1',
            'brand' => 'HAS',
            'category' => 'Analyzer',
            'price' => 5000000,
            'currency_id' => $base->id,
            'status' => 'Active',
        ]);
        MasterProduct::create([
            'division_id' => $this->division->id,
            'name' => 'Produk USD',
            'code' => 'USD-1',
            'brand' => 'HAS',
            'category' => 'Analyzer',
            'price' => 100,
            'currency_id' => $usd->id,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('quotation.search-products').'?division_id='.$this->division->id)
            ->assertOk();

        $data = collect($response->json('data'))->keyBy('code');

        // Produk base: price apa adanya.
        $this->assertEquals(5000000.0, (float) $data['IDR-1']['price']);
        // Produk USD: price × rate = 100 × 20.000 = 2.000.000.
        $this->assertEquals(2000000.0, (float) $data['USD-1']['price']);
    }

    public function test_store_creates_quotation_with_hierarchy_and_totals(): void
    {
        $config = $this->createApprovedConfiguration();

        $items = [
            $this->itemPayload($config, ['_key' => 'row-1', 'item_no' => '1', 'description' => 'Multiparameter Sensor', 'qty' => null, 'price' => null]),
            $this->itemPayload($config, ['_key' => 'row-2', 'parent_key' => 'row-1', 'item_no' => '1.1', 'description' => 'pH::lyser', 'qty' => 1, 'price' => 123100000]),
            $this->itemPayload($config, ['_key' => 'row-3', 'parent_key' => 'row-2', 'item_no' => '1.1.1', 'description' => 'pH Sensor', 'qty' => 1, 'price' => 50000000]),
            $this->itemPayload($config, ['_key' => 'row-4', 'item_no' => '2', 'description' => 'Flow Meter', 'qty' => null, 'price' => null]),
            $this->itemPayload($config, ['_key' => 'row-5', 'parent_key' => 'row-4', 'item_no' => '2.1', 'description' => 'Flow Sensor', 'qty' => 2, 'price' => 100000]),
        ];

        $configItems = [
            ['_key' => 'c-1', 'quote_configuration_id' => $config->id, 'category' => 'PH', 'part_number' => '324234', 'description' => 'phpsjdajd', 'qty' => 1, 'price' => 3000],
            ['_key' => 'c-2', 'quote_configuration_id' => $config->id, 'category' => 'PH', 'part_number' => '324235', 'description' => 'phpsjdajd 2', 'qty' => 1, 'price' => 3000],
            ['_key' => 'c-3', 'quote_configuration_id' => $config->id, 'category' => 'RO', 'part_number' => '324236', 'description' => 'phpsjdajd 3', 'qty' => 2, 'price' => 5000],
        ];

        $response = $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'date' => '2026-08-11',
            'currency' => 'Rupiah',
            'no_of_pages' => 3,
            'to_name' => 'Kawasan Industri Gresik (Site Tuban)',
            'from_name' => 'Zuri Muriani',
            'attn_name' => 'Bapak Anang Subagya',
            'ppn_percent' => 11,
            'items' => $items,
            'config_items' => $configItems,
        ])->assertOk();

        $quotation = Quotation::latest('id')->firstOrFail();

        $this->assertSame($config->task_id, $quotation->task_id);
        $this->assertSame($config->id, $quotation->quote_configuration_id);
        $this->assertSame('draft', $quotation->status);
        $this->assertSame(5, $quotation->items()->count());

        $this->assertTrue($quotation->configurations->contains('id', $config->id));
        $this->assertCount(1, $quotation->configurations);

        // Snapshot config items tersimpan
        $this->assertCount(3, $quotation->configItems);
        $this->assertSame(2, $quotation->configItems->where('category', 'PH')->count());
        $this->assertSame(1, $quotation->configItems->where('category', 'RO')->count());
        $this->assertSame(3000.0, (float) $quotation->configItems->firstWhere('part_number', '324234')->price);
        $this->assertSame(2, $quotation->configItems->firstWhere('part_number', '324236')->qty);

        // Struktur hierarki tersimpan
        $parent = $quotation->items()->where('item_no', '1')->first();
        $child = $quotation->items()->where('item_no', '1.1')->first();
        $grand = $quotation->items()->where('item_no', '1.1.1')->first();
        $flow = $quotation->items()->where('item_no', '2')->first();
        $flowChild = $quotation->items()->where('item_no', '2.1')->first();

        $this->assertNull($parent->parent_id);
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame($child->id, $grand->parent_id);
        $this->assertNull($flow->parent_id);
        $this->assertSame($flow->id, $flowChild->parent_id);

        // Total hanya dari baris ber-qty & ber-harga:
        // 123.100.000 + 50.000.000 + 2 x 100.000 = 173.300.000
        // DPP = Netto (tanpa diskon) = subtotal; PPN 11% x netto.
        $this->assertSame(173300000.0, $quotation->subtotal);
        $this->assertSame(0.0, (float) $quotation->discount_amount);
        $this->assertSame(173300000.0, $quotation->dpp);
        $this->assertSame(19063000.0, $quotation->ppn);
        $this->assertSame(11.0, (float) $quotation->ppn_percent);
        $this->assertSame(192363000.0, $quotation->grand_total);

        // flattenTree mengembalikan urutan DFS dengan depth
        $rows = $quotation->fresh()->flattenTree();
        $this->assertSame('1', $rows[0]['item']->item_no);
        $this->assertSame(0, $rows[0]['depth']);
        $this->assertSame('1.1', $rows[1]['item']->item_no);
        $this->assertSame(1, $rows[1]['depth']);
        $this->assertSame('1.1.1', $rows[2]['item']->item_no);
        $this->assertSame(2, $rows[2]['depth']);
        $this->assertSame('2', $rows[3]['item']->item_no);
        $this->assertSame('2.1', $rows[4]['item']->item_no);

        $this->assertSame($response->json('id'), $quotation->id);
        $this->assertMatchesRegularExpression('/^\d{3}\/HAS\/QT-ZM\/VIII\/2026$/', $quotation->quotation_number);
    }

    public function test_store_with_discount_percent_and_ppn_percent(): void
    {
        $config = $this->createApprovedConfiguration();

        $items = [$this->itemPayload($config, ['_key' => 'row-1', 'description' => 'Item A', 'qty' => 1, 'price' => 1000000])];

        $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'discount_percent' => 10,
            'ppn_percent' => 11,
            'items' => $items,
        ])->assertOk();

        $quotation = Quotation::latest('id')->firstOrFail();

        $this->assertSame(1000000.0, $quotation->subtotal);
        $this->assertSame(10.0, (float) $quotation->discount_percent);
        $this->assertSame(100000.0, (float) $quotation->discount_amount);
        $this->assertSame(900000.0, (float) $quotation->dpp); // netto
        $this->assertSame(11.0, (float) $quotation->ppn_percent);
        $this->assertSame(99000.0, (float) $quotation->ppn); // 11% x netto
        $this->assertSame(999000.0, (float) $quotation->grand_total); // netto + ppn
    }

    public function test_store_with_manual_discount_and_ppn_amount(): void
    {
        $config = $this->createApprovedConfiguration();

        $items = [$this->itemPayload($config, ['_key' => 'row-1', 'description' => 'Item A', 'qty' => 1, 'price' => 1000000])];

        $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'discount_amount' => 250000,
            'ppn_amount' => 50000,
            'items' => $items,
        ])->assertOk();

        $quotation = Quotation::latest('id')->firstOrFail();

        $this->assertSame(1000000.0, $quotation->subtotal);
        $this->assertNull($quotation->discount_percent);
        $this->assertSame(250000.0, (float) $quotation->discount_amount);
        $this->assertSame(750000.0, (float) $quotation->dpp); // netto
        $this->assertNull($quotation->ppn_percent);
        $this->assertSame(50000.0, (float) $quotation->ppn);
        $this->assertSame(800000.0, (float) $quotation->grand_total); // 750000 + 50000
    }

    public function test_store_persists_formula(): void
    {
        $config = $this->createApprovedConfiguration();

        $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'items' => [
                $this->itemPayload($config, ['_key' => 'row-1', 'description' => 'Item A', 'qty' => 2, 'price' => 500000, 'formula' => ['qty' => '=B1*2', 'price' => '=A2*B1']]),
            ],
            'formula' => ['discount_amount' => '=A1*0.1', 'ppn_amount' => '=A3*0.11'],
        ])->assertOk();

        $quotation = Quotation::latest('id')->firstOrFail();

        $item = $quotation->items()->first();
        $this->assertSame(['qty' => '=B1*2', 'price' => '=A2*B1'], $item->formula);
        $this->assertSame(500000.0, (float) $item->price);

        $this->assertSame(['discount_amount' => '=A1*0.1', 'ppn_amount' => '=A3*0.11'], $quotation->formula);
    }

    public function test_revise_copies_formula(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_APPROVED,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'quotation_number' => '001/HAS/QT-ZM/VIII/2026',
            'unlocked_at' => now(),
            'unlocked_by' => $this->admin->id,
            'created_by' => $this->user->id,
            'formula' => ['discount_amount' => '=A1*0.05'],
        ]);
        $quotation->update(['group_id' => $quotation->id]);
        $quotation->items()->create(['description' => 'Item', 'qty' => 1, 'price' => 1000, 'formula' => ['price' => '=A1*B1']]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('quotation.revise', $quotation->id))
            ->assertOk();

        $revision = Quotation::findOrFail($response->json('id'));
        $this->assertSame(['discount_amount' => '=A1*0.05'], $revision->formula);
        $this->assertSame(['price' => '=A1*B1'], $revision->items()->first()->formula);
    }

    public function test_store_rejected_when_task_has_unapproved_current_config(): void
    {
        $task = $this->createTask();

        // Config WATER approved (versi terakhir).
        $water = QuoteConfiguration::create([
            'division_id' => $this->division->id,
            'group_id' => 1,
            'version' => 1,
            'is_current' => true,
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'date' => '2026-08-11',
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->user->id,
            'final_checked_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        $water->update(['group_id' => $water->id]);

        // Config IMS versi terakhir BELUM approved (draft).
        $ims = QuoteConfiguration::create([
            'division_id' => $this->imsDivision->id,
            'group_id' => 2,
            'version' => 1,
            'is_current' => true,
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $task->id,
            'date' => '2026-08-11',
            'status' => QuoteConfiguration::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('quotation.store'), [
                'task_id' => $task->id,
                'quote_configuration_ids' => [$water->id],
                'items' => [$this->itemPayload($water, ['_key' => 'row-1', 'description' => 'Item'])],
            ])
            ->assertStatus(422);

        $this->assertSame(0, Quotation::count());
    }

    public function test_update_rejected_when_task_has_unapproved_current_config(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->admin->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        // Task kini punya config baru (IMS) versi terakhir yang belum approved.
        QuoteConfiguration::create([
            'division_id' => $this->imsDivision->id,
            'group_id' => 99,
            'version' => 1,
            'is_current' => true,
            'opportunity_id' => $this->opportunity->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => QuoteConfiguration::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('quotation.update', $quotation->id), [
                'task_id' => $config->task_id,
                'quote_configuration_ids' => [$config->id],
                'items' => [$this->itemPayload($config, ['_key' => 'row-1', 'description' => 'edited'])],
            ])
            ->assertStatus(422);

        $this->assertSame(0, $quotation->fresh()->items()->count());
    }

    public function test_store_with_cost_items_hierarchy(): void
    {
        $config = $this->createApprovedConfiguration();

        $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'ppn_percent' => 11,
            'items' => [$this->itemPayload($config, ['_key' => 'row-1', 'description' => 'Item A', 'qty' => 1, 'price' => 1000000])],
            'cost_title' => 'Biaya Mobilisasi',
            'cost_items' => [
                ['_key' => 'c-1', 'parent_key' => null, 'item_no' => '1', 'description' => 'Mobilisasi Umum', 'qty' => 1, 'price' => 250000],
                ['_key' => 'c-2', 'parent_key' => 'c-1', 'item_no' => '1.1', 'description' => 'Transport', 'qty' => 1, 'price' => 500000],
                ['_key' => 'c-3', 'parent_key' => 'c-1', 'item_no' => '1.2', 'description' => 'Pemasangan', 'qty' => 2, 'price' => 250000],
            ],
            'cost_notes' => 'Mobilisasi dikenakan biaya tambahan.',
        ])->assertOk();

        $quotation = Quotation::latest('id')->firstOrFail();

        $this->assertCount(3, $quotation->costItems);
        $this->assertSame('Biaya Mobilisasi', $quotation->cost_title);

        $mobilisasi = $quotation->costItems()->where('description', 'Mobilisasi Umum')->first();
        $child = $quotation->costItems()->where('description', 'Transport')->first();
        $this->assertNull($mobilisasi->parent_id);
        $this->assertSame($mobilisasi->id, $child->parent_id);
        $this->assertSame('1.1', $child->item_no);
        $this->assertSame(500000.0, (float) $child->price);

        $instChild = $quotation->costItems()->where('description', 'Pemasangan')->first();
        $this->assertSame($mobilisasi->id, $instChild->parent_id);
        $this->assertSame(2, $instChild->qty);
        $this->assertSame(250000.0, (float) $instChild->price);

        // Biaya TIDAK memengaruhi subtotal/grand_total.
        $this->assertSame(1000000.0, $quotation->subtotal);
        $this->assertSame(1110000.0, (float) $quotation->grand_total);
        $this->assertSame('Mobilisasi dikenakan biaya tambahan.', $quotation->cost_notes);
    }

    public function test_fetch_cost_template_returns_dfs_without_price(): void
    {
        $config = $this->createApprovedConfiguration();

        $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'ppn_percent' => 11,
            'items' => [$this->itemPayload($config, ['_key' => 'row-1', 'description' => 'Item A', 'qty' => 1, 'price' => 1000000])],
            'cost_title' => 'Biaya Mobilisasi',
            'cost_items' => [
                ['_key' => 'c-1', 'parent_key' => null, 'item_no' => '1', 'description' => 'Mobilisasi Umum', 'qty' => 1, 'price' => 250000],
                ['_key' => 'c-2', 'parent_key' => 'c-1', 'item_no' => '1.1', 'description' => 'Transport', 'qty' => 1, 'price' => 500000],
                ['_key' => 'c-3', 'parent_key' => 'c-1', 'item_no' => '1.2', 'description' => 'Pemasangan', 'qty' => 2, 'price' => 250000],
            ],
        ])->assertOk();

        $quotation = Quotation::latest('id')->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-cost-template').'?quotation_id='.$quotation->id)
            ->assertOk();

        $this->assertTrue($response->json('success'));
        $rows = $response->json('data.items');
        $this->assertCount(3, $rows);

        // Judul biaya dikembalikan terpisah.
        $this->assertSame('Biaya Mobilisasi', $response->json('data.cost_title'));

        // Urutan DFS: parent sebelum child.
        $this->assertSame('Mobilisasi Umum', $rows[0]['description']);
        $this->assertSame('Transport', $rows[1]['description']);
        $this->assertNull($rows[0]['parent_key']);
        $this->assertSame($rows[0]['_key'], $rows[1]['parent_key']);
        $this->assertSame($rows[0]['_key'], $rows[2]['parent_key']);

        // Harga TIDAK diikutsertakan pada template; qty/unit tetap dipertahankan.
        $this->assertArrayNotHasKey('price', $rows[1]);
        $this->assertSame(1, $rows[1]['qty']);
        $this->assertSame(2, $rows[2]['qty']);
    }

    public function test_fetch_cost_template_rejects_missing_quotation(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-cost-template').'?quotation_id=99999')
            ->assertStatus(422);
    }

    public function test_revise_copies_cost_items(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_APPROVED,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'quotation_number' => '001/HAS/QT-ZM/VIII/2026',
            'unlocked_at' => now(),
            'unlocked_by' => $this->admin->id,
            'created_by' => $this->user->id,
            'cost_notes' => 'Catatan biaya awal.',
            'cost_title' => 'Biaya Mobilisasi',
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        $parent = $quotation->costItems()->create(['description' => 'Mobilisasi Umum', 'qty' => 1, 'price' => 250000, 'item_no' => '1', 'sort_order' => 1]);
        $quotation->costItems()->create(['parent_id' => $parent->id, 'description' => 'Transport', 'qty' => 1, 'price' => 500000, 'item_no' => '1.1', 'sort_order' => 2]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('quotation.revise', $quotation->id))
            ->assertOk();

        $revision = Quotation::findOrFail($response->json('id'));
        $this->assertSame('Catatan biaya awal.', $revision->cost_notes);
        $this->assertSame('Biaya Mobilisasi', $revision->cost_title);
        $this->assertCount(2, $revision->costItems);

        $revParent = $revision->costItems()->where('description', 'Mobilisasi Umum')->first();
        $revChild = $revision->costItems()->where('description', 'Transport')->first();
        $this->assertSame($revParent->id, $revChild->parent_id);
        $this->assertSame(500000.0, (float) $revChild->price);
    }

    public function test_store_rejects_invalid_config_not_belonging_to_task(): void
    {
        $configA = $this->createApprovedConfiguration();
        $configB = $this->createApprovedConfiguration();

        $this->actingAs($this->admin)
            ->postJson(route('quotation.store'), [
                'task_id' => $configA->task_id,
                'quote_configuration_ids' => [$configA->id, $configB->id],
                'items' => [$this->itemPayload($configA)],
            ])
            ->assertStatus(422);

        $this->assertSame(0, Quotation::count());
    }

    public function test_store_rejects_task_without_approved_config(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin)
            ->postJson(route('quotation.store'), [
                'task_id' => $task->id,
                'quote_configuration_ids' => [],
                'items' => [['_key' => 'row-1', 'description' => 'Item', 'qty' => 1, 'price' => 1000]],
            ])
            ->assertStatus(422);

        $this->assertSame(0, Quotation::count());
    }

    public function test_update_replaces_config_items(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);
        $quotation->configurations()->sync([$config->id]);
        $quotation->items()->create(['description' => 'item', 'qty' => 1, 'price' => 1000]);

        $this->actingAs($this->admin)->putJson(route('quotation.update', $quotation->id), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'items' => [$this->itemPayload($config, ['_key' => 'row-1', 'description' => 'edited item'])],
            'config_items' => [
                ['_key' => 'c-1', 'quote_configuration_id' => $config->id, 'category' => 'PH', 'part_number' => 'ABC', 'description' => 'sensor', 'qty' => 1, 'price' => 9000],
                ['_key' => 'c-2', 'quote_configuration_id' => $config->id, 'category' => 'RO', 'part_number' => 'DEF', 'description' => 'flow', 'qty' => 2, 'price' => 500],
            ],
        ])->assertOk();

        $fresh = $quotation->fresh();
        $this->assertCount(2, $fresh->configItems);
        $this->assertSame(1, $fresh->configItems->where('category', 'PH')->count());
        $this->assertSame('ABC', $fresh->configItems->firstWhere('category', 'PH')->part_number);
        $this->assertSame(9000.0, (float) $fresh->configItems->firstWhere('category', 'PH')->price);
        $this->assertSame('edited item', $fresh->items()->first()->description);
    }

    /**
     * Item config: price_currency (sebelum kurs) + currency dikirim client,
     * price (IDR) dihitung server dari kurs master. Payload lama yang hanya
     * membawa price tetap diterima (price dianggap IDR).
     */
    public function test_store_config_items_compute_price_from_price_currency_and_rate(): void
    {
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 17500, 'is_base' => false, 'status' => 'Active']);

        $config = $this->createApprovedConfiguration();

        $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'items' => [$this->itemPayload($config, ['_key' => 'row-1'])],
            'config_items' => [
                ['_key' => 'c-1', 'quote_configuration_id' => $config->id, 'category' => 'PH', 'part_number' => 'USD-1', 'description' => 'sensor usd', 'qty' => 2, 'currency' => 'usd', 'price_currency' => 4715.94, 'price' => 1],
                ['_key' => 'c-2', 'quote_configuration_id' => $config->id, 'category' => 'PH', 'part_number' => 'IDR-1', 'description' => 'sensor idr', 'qty' => 1, 'currency' => '', 'price_currency' => 1000],
                ['_key' => 'c-3', 'quote_configuration_id' => $config->id, 'category' => 'PH', 'part_number' => 'OLD-1', 'description' => 'payload lama', 'qty' => 1, 'price' => 9000],
            ],
        ])->assertOk();

        $items = Quotation::latest('id')->firstOrFail()->configItems->keyBy('part_number');

        // USD: kode dinormalkan, price = 4.715,94 x 17.500; price kiriman client diabaikan.
        $this->assertSame('USD', $items['USD-1']->currency);
        $this->assertSame(4715.94, $items['USD-1']->price_currency);
        $this->assertSame(82_528_950.0, $items['USD-1']->price);
        // Kosong -> base (IDR), rate 1.
        $this->assertSame('IDR', $items['IDR-1']->currency);
        $this->assertSame(1000.0, $items['IDR-1']->price);
        // Payload lama: price dianggap IDR, price_currency = price.
        $this->assertSame('IDR', $items['OLD-1']->currency);
        $this->assertSame(9000.0, $items['OLD-1']->price_currency);
        $this->assertSame(9000.0, $items['OLD-1']->price);
    }

    public function test_search_products_returns_price_currency_and_currency(): void
    {
        $base = Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
        $usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 20000, 'is_base' => false, 'status' => 'Active']);

        MasterProduct::create(['division_id' => $this->division->id, 'name' => 'Produk USD', 'code' => 'USD-1', 'brand' => 'HAS', 'category' => 'Analyzer', 'price' => 100, 'currency_id' => $usd->id, 'status' => 'Active']);
        MasterProduct::create(['division_id' => $this->division->id, 'name' => 'Produk IDR', 'code' => 'IDR-1', 'brand' => 'HAS', 'category' => 'Analyzer', 'price' => 5000, 'currency_id' => $base->id, 'status' => 'Active']);

        $data = collect($this->actingAs($this->admin)
            ->getJson(route('quotation.search-products').'?division_id='.$this->division->id)
            ->assertOk()
            ->json('data'))->keyBy('code');

        $this->assertSame(['USD', 100.0, 2000000.0], [$data['USD-1']['currency'], (float) $data['USD-1']['price_currency'], (float) $data['USD-1']['price']]);
        $this->assertSame(['IDR', 5000.0, 5000.0], [$data['IDR-1']['currency'], (float) $data['IDR-1']['price_currency'], (float) $data['IDR-1']['price']]);
    }

    /**
     * Buat revisi approved (v2) dari configuration $v1 di group yang sama,
     * dengan daftar item baru. v1 menjadi arsip (bukan current).
     */
    private function approveRevisionOf(QuoteConfiguration $v1, array $items): QuoteConfiguration
    {
        $v1->update(['status' => QuoteConfiguration::STATUS_ARCHIVED, 'is_current' => false]);

        $v2 = QuoteConfiguration::create([
            'division_id' => $v1->division_id,
            'group_id' => $v1->group_id,
            'version' => $v1->version + 1,
            'parent_id' => $v1->id,
            'is_current' => true,
            'opportunity_id' => $v1->opportunity_id,
            'task_id' => $v1->task_id,
            'date' => '2026-08-20',
            'status' => QuoteConfiguration::STATUS_APPROVED,
            'created_by' => $this->user->id,
            'final_checked_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        foreach (array_values($items) as $i => $item) {
            QuoteConfigurationItem::create(array_merge([
                'quote_configuration_id' => $v2->id,
                'category' => 'SPARING',
                'qty' => 1,
                'sort_order' => $i + 1,
            ], $item));
        }

        return $v2->fresh();
    }

    public function test_fetch_task_flags_item_changes_against_quotation_snapshot(): void
    {
        $v1 = $this->createApprovedConfiguration();

        // Quotation dibuat dari v1: snapshot A (harga 100), B, dan C (tanpa part number).
        $quotation = Quotation::create([
            'quote_configuration_id' => $v1->id,
            'task_id' => $v1->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);
        $quotation->configurations()->sync([$v1->id]);
        $snapA = $quotation->configItems()->create(['quote_configuration_id' => $v1->id, 'category' => 'SPARING', 'part_number' => 'A-1', 'description' => 'Sensor A', 'qty' => 1, 'price' => 100]);
        // B adalah anak A di snapshot; nanti dihapus di v2 -> harus tetap menunjuk induk A (id live).
        $quotation->configItems()->create(['quote_configuration_id' => $v1->id, 'parent_id' => $snapA->id, 'category' => 'SPARING', 'part_number' => 'B-1', 'description' => 'Sensor B', 'qty' => 1, 'price' => 200]);
        $quotation->configItems()->create(['quote_configuration_id' => $v1->id, 'category' => 'SPARING', 'part_number' => null, 'description' => 'Kabel <b>lokal</b>', 'qty' => 3, 'price' => 50]);

        // Configuration direvisi: A harga naik, B hilang, D baru, C tetap (dicocokkan lewat deskripsi).
        $v2 = $this->approveRevisionOf($v1, [
            ['part_number' => 'A-1', 'description' => 'Sensor A', 'qty' => 1, 'price' => 150],
            ['part_number' => 'D-1', 'description' => 'Sensor D', 'qty' => 2, 'price' => 300],
            ['part_number' => null, 'description' => 'Kabel lokal', 'qty' => 3, 'price' => 50],
        ]);

        $data = $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-task', ['task_id' => $v1->task_id, 'quotation_id' => $quotation->id]))
            ->assertOk()
            ->json('data');

        // Hanya configuration terbaru yang dikirim (tidak ganda).
        $this->assertSame([$v2->id], array_column($data['configs'], 'id'));

        $items = collect($data['items'])->keyBy(fn ($it) => $it['part_number'] ?: 'desc');
        $this->assertSame('berubah', $items['A-1']['change_status']);
        $this->assertSame(100.0, (float) $items['A-1']['previous']['price']);
        $this->assertSame('baru', $items['D-1']['change_status']);
        $this->assertSame('sama', $items['desc']['change_status']);

        $this->assertCount(1, $data['removed_items']);
        $this->assertSame('B-1', $data['removed_items'][0]['part_number']);
        $this->assertSame('dihapus', $data['removed_items'][0]['change_status']);
        $this->assertSame($v2->id, $data['removed_items'][0]['quote_configuration_id']);
        // Induk B (A) masih ada di v2 -> parent_id menunjuk id item A versi terbaru.
        $this->assertSame($items['A-1']['id'], $data['removed_items'][0]['parent_id']);
        $this->assertSame('SPARING', $data['removed_items'][0]['category']);

        $this->assertSame(['sama' => 1, 'berubah' => 1, 'baru' => 1, 'dihapus' => 1], $data['change_summary']);
        $this->assertSame([$v1->id, 1, $v2->id, 2], [
            $data['config_changes'][0]['from_id'], $data['config_changes'][0]['from_version'],
            $data['config_changes'][0]['to_id'], $data['config_changes'][0]['to_version'],
        ]);

        // Tanpa quotation_id: tidak ada perbandingan.
        $plain = $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-task', ['task_id' => $v1->task_id]))
            ->assertOk()
            ->json('data');
        $this->assertNull($plain['change_summary']);
        $this->assertArrayNotHasKey('change_status', $plain['items'][0]);

        // Quotation sudah disimpan setelah revisi (pivot menunjuk v2) walau snapshot
        // tetap berbeda (penyesuaian manual admin): pemberitahuan tidak muncul lagi.
        $quotation->configurations()->sync([$v2->id]);
        $again = $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-task', ['task_id' => $v1->task_id, 'quotation_id' => $quotation->id]))
            ->assertOk()
            ->json('data');
        $this->assertNull($again['change_summary']);
        $this->assertSame([], $again['config_changes']);
        $this->assertSame([], $again['removed_items']);
        $this->assertArrayNotHasKey('change_status', $again['items'][0]);
    }

    /**
     * Regresi: form mengirim id terpilih versi terbaru (v2) tetapi baris snapshot
     * masih membawa id v1 -> snapshot harus ikut dipetakan ke v2, dan halaman edit
     * tetap merender snapshot yang pivotnya sudah berpindah versi.
     */
    public function test_update_keeps_snapshot_attached_when_selected_ids_are_already_latest(): void
    {
        $v1 = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $v1->id,
            'task_id' => $v1->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);
        $quotation->configurations()->sync([$v1->id]);
        $quotation->items()->create(['description' => 'item', 'qty' => 1, 'price' => 1000]);

        $v2 = $this->approveRevisionOf($v1, [['part_number' => 'A-1', 'description' => 'Sensor A', 'price' => 150]]);

        $this->actingAs($this->admin)->putJson(route('quotation.update', $quotation->id), [
            'task_id' => $v1->task_id,
            'quote_configuration_ids' => [$v2->id],
            'items' => [$this->itemPayload($v1, ['_key' => 'row-1', 'description' => 'edited item'])],
            'config_items' => [
                ['_key' => 'c-1', 'quote_configuration_id' => $v1->id, 'category' => 'SPARING', 'part_number' => 'A-1', 'description' => 'Sensor A', 'qty' => 1, 'price' => 100],
            ],
        ])->assertOk();

        $fresh = $quotation->fresh(['configurations', 'configItems', 'items']);
        $this->assertSame([$v2->id], $fresh->configurations->pluck('id')->all());
        $this->assertSame($v2->id, $fresh->configItems->first()->quote_configuration_id);
        $this->assertSame($v2->id, $fresh->items->first()->quote_configuration_id);

        // Data lama yang terlanjur terpisah (pivot v2, snapshot v1) tetap dirender di halaman edit.
        $fresh->configItems()->update(['quote_configuration_id' => $v1->id]);
        $this->actingAs($this->admin)->get(route('quotation.edit', $quotation->id))
            ->assertOk()
            ->assertViewHas('snapshotConfigs', fn ($cfgs) => $cfgs->pluck('id')->sort()->values()->all() === collect([$v1->id, $v2->id])->sort()->values()->all());
    }

    public function test_update_repoints_configuration_and_revise_keeps_source_pivot(): void
    {
        $v1 = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $v1->id,
            'task_id' => $v1->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);
        $quotation->configurations()->sync([$v1->id]);
        $quotation->items()->create(['description' => 'item', 'qty' => 1, 'price' => 1000]);

        $v2 = $this->approveRevisionOf($v1, [['part_number' => 'A-1', 'description' => 'Sensor A', 'price' => 150]]);

        // Form edit masih mengirim id v1 (pivot lama) + snapshot yang menunjuk v1.
        $this->actingAs($this->admin)->putJson(route('quotation.update', $quotation->id), [
            'task_id' => $v1->task_id,
            'quote_configuration_ids' => [$v1->id],
            'items' => [$this->itemPayload($v1, ['_key' => 'row-1', 'description' => 'edited item'])],
            'config_items' => [
                ['_key' => 'c-1', 'quote_configuration_id' => $v1->id, 'category' => 'SPARING', 'part_number' => 'A-1', 'description' => 'Sensor A', 'qty' => 1, 'price' => 100],
            ],
        ])->assertOk();

        $fresh = $quotation->fresh(['configurations', 'configItems']);
        $this->assertSame([$v2->id], $fresh->configurations->pluck('id')->all());
        $this->assertSame($v2->id, $fresh->configItems->first()->quote_configuration_id);
        $this->assertDatabaseHas('logs', ['action' => 'update_quotation_config_version', 'loggable_id' => $quotation->id]);

        // Revisi quotation dari sumber yang pivot-nya masih v1: pivot & snapshot disalin
        // apa adanya, sehingga form edit revisi menampilkan pemberitahuan revisi configuration.
        $quotation->update(['status' => Quotation::STATUS_REJECTED]);
        $quotation->configurations()->sync([$v1->id]);
        $quotation->configItems()->update(['quote_configuration_id' => $v1->id]);

        $response = $this->actingAs($this->admin)->postJson(route('quotation.revise', $quotation->id))->assertOk();
        $revision = Quotation::with(['configurations', 'configItems'])->findOrFail($response->json('id'));
        $this->assertSame([$v1->id], $revision->configurations->pluck('id')->all());
        $this->assertSame($v1->id, $revision->configItems->first()->quote_configuration_id);

        $data = $this->actingAs($this->admin)
            ->getJson(route('quotation.fetch-task', ['task_id' => $v1->task_id, 'quotation_id' => $revision->id]))
            ->assertOk()
            ->json('data');
        $this->assertSame([$v1->id, $v2->id], [$data['config_changes'][0]['from_id'], $data['config_changes'][0]['to_id']]);
    }

    public function test_store_sanitizes_config_item_description(): void
    {
        $config = $this->createApprovedConfiguration();

        $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'items' => [$this->itemPayload($config, ['_key' => 'row-1'])],
            'config_items' => [
                ['_key' => 'c-1', 'quote_configuration_id' => $config->id, 'category' => 'PH', 'description' => '<b>sensor</b><i>probe</i><script>alert(1)</script>', 'qty' => 1, 'price' => 5000],
            ],
        ])->assertOk();

        $quotation = Quotation::latest('id')->firstOrFail();
        $this->assertSame('<b>sensor</b><i>probe</i>', $quotation->configItems()->first()->description);
    }

    public function test_show_page_and_pdf(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'opportunity_id' => $config->opportunity_id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'quotation_number' => '087/HAS/QT-ZM/II/2026',
            'to_name' => 'Kawasan Industri Gresik (Site Tuban)',
            'from_name' => 'Zuri Muriani',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
            'subtotal' => 173300000,
            'dpp' => 158858333.33,
            'ppn' => 19063000,
            'grand_total' => 192363000,
        ]);
        $quotation->configurations()->sync([$config->id]);

        $parent = $quotation->items()->create([
            'item_no' => '1',
            'quote_configuration_id' => $config->id,
            'description' => 'Multiparameter Sensor',
            'sort_order' => 1,
        ]);
        $child = $quotation->items()->create([
            'item_no' => '1.1',
            'quote_configuration_id' => $config->id,
            'parent_id' => $parent->id,
            'description' => 'pH::lyser',
            'qty' => 1,
            'price' => 123100000,
            'sort_order' => 2,
        ]);
        $quotation->items()->create([
            'item_no' => '1.1.1',
            'quote_configuration_id' => $config->id,
            'parent_id' => $child->id,
            'description' => 'pH Sensor',
            'qty' => 1,
            'price' => 50000000,
            'sort_order' => 3,
        ]);

        $this->actingAs($this->admin)->get(route('quotation.show', $quotation->id))->assertOk();
        $this->actingAs($this->admin)->get(route('quotation.pdf', $quotation->id))->assertOk();
        $this->actingAs($this->admin)->get(route('quotation.pdf-cost', $quotation->id))->assertOk();
    }

    public function test_approval_flow_submit_approve_locks(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->user->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);
        $quotation->items()->create(['description' => 'test item', 'qty' => 1, 'price' => 1000]);

        // Submit oleh pembuat.
        $this->actingAs($this->user)
            ->postJson(route('quotation.submit', $quotation->id))
            ->assertOk();

        $this->assertSame(Quotation::STATUS_WAITING_APPROVAL, $quotation->fresh()->status);

        // Approve oleh pembuat sendiri yang punya can_approve -> diizinkan.
        $this->actingAs($this->user)
            ->postJson(route('quotation.approve', $quotation->id))
            ->assertOk();

        $fresh = $quotation->fresh();
        $this->assertSame(Quotation::STATUS_APPROVED, $fresh->status);
        $this->assertTrue($fresh->isLocked());
        $this->assertTrue($fresh->is_current);
        $this->assertSame($this->user->id, $fresh->final_checked_by);
        $this->assertNotNull($fresh->approved_at);

        $this->actingAs($this->admin)
            ->deleteJson(route('quotation.destroy', $quotation->id))
            ->assertStatus(422);
    }

    public function test_approve_rejected_for_user_without_can_approve(): void
    {
        $config = $this->createApprovedConfiguration();

        // User tanpa can_approve (hanya can_create).
        $noApprover = User::create([
            'username' => 'noapprover',
            'email' => 'noapprover@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);
        UserAccessControl::create([
            'user_id' => $noApprover->id,
            'module_id' => Module::where('module_code', 'MOD_QUOTATION')->first()->id,
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => false,
            'can_approve' => false,
        ]);

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_WAITING_APPROVAL,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->user->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        // Middleware access.control memblokir approve untuk user tanpa can_approve.
        $this->actingAs($noApprover)
            ->postJson(route('quotation.approve', $quotation->id))
            ->assertStatus(302);

        $this->assertSame(Quotation::STATUS_WAITING_APPROVAL, $quotation->fresh()->status);
    }

    public function test_reject_requires_note(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_WAITING_APPROVAL,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->user->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        $this->actingAs($this->admin)
            ->postJson(route('quotation.reject', $quotation->id), [])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson(route('quotation.reject', $quotation->id), ['approval_note' => 'Harga terlalu tinggi'])
            ->assertOk();

        $fresh = $quotation->fresh();
        $this->assertSame(Quotation::STATUS_REJECTED, $fresh->status);
        $this->assertSame('Harga terlalu tinggi', $fresh->approval_note);
        $this->assertNotNull($fresh->rejected_at);
    }

    public function test_unlock_only_approver(): void
    {
        $config = $this->createApprovedConfiguration();

        $noApprover = User::create([
            'username' => 'noapprover2',
            'email' => 'noapprover2@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);
        UserAccessControl::create([
            'user_id' => $noApprover->id,
            'module_id' => Module::where('module_code', 'MOD_QUOTATION')->first()->id,
            'can_create' => true,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => false,
            'can_approve' => false,
        ]);

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_APPROVED,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->user->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        // User tanpa can_approve tidak bisa unlock.
        $this->actingAs($noApprover)
            ->postJson(route('quotation.unlock', $quotation->id))
            ->assertStatus(302);

        // Approver (admin) bisa unlock.
        $this->actingAs($this->admin)
            ->postJson(route('quotation.unlock', $quotation->id))
            ->assertOk();

        $fresh = $quotation->fresh();
        $this->assertNotNull($fresh->unlocked_at);
        $this->assertFalse($fresh->isLocked());
    }

    public function test_revise_creates_new_version_and_copies_data(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_APPROVED,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'quotation_number' => '001/HAS/QT-ZM/VIII/2026',
            'unlocked_at' => now(),
            'unlocked_by' => $this->admin->id,
            'created_by' => $this->user->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        $parent = $quotation->items()->create(['item_no' => '1', 'description' => 'Multiparameter Sensor', 'qty' => 1, 'unit' => 'Kit']);
        $quotation->items()->create(['item_no' => '1.1', 'parent_id' => $parent->id, 'description' => 'pH::lyser', 'qty' => 1, 'price' => 123100000]);
        $quotation->configItems()->create(['quote_configuration_id' => $config->id, 'description' => 'snapshot config', 'qty' => 1, 'price' => 52_500_000, 'price_currency' => 3000, 'currency' => 'USD']);
        $quotation->configurations()->sync([$config->id]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('quotation.revise', $quotation->id))
            ->assertOk();

        $revision = Quotation::findOrFail($response->json('id'));

        $this->assertNotSame($quotation->id, $revision->id);
        $this->assertSame($quotation->group_id, $revision->group_id);
        $this->assertSame(2, $revision->version);
        $this->assertSame($quotation->id, $revision->parent_id);
        $this->assertSame(Quotation::STATUS_DRAFT, $revision->status);
        // Revisi TIDAK membuat nomor quotation baru — mewarisi nomor sumber.
        $this->assertSame($quotation->quotation_number, $revision->quotation_number);
        $this->assertSame(2, $revision->items()->count());
        $this->assertSame(1, $revision->configItems()->count());
        // Snapshot mata uang config item ikut tersalin apa adanya.
        $revCfg = $revision->configItems()->first();
        $this->assertSame(['USD', 3000.0, 52_500_000.0], [$revCfg->currency, $revCfg->price_currency, $revCfg->price]);
        $this->assertTrue($revision->configurations->contains('id', $config->id));

        // Sumber approved menjadi archived.
        $this->assertSame(Quotation::STATUS_ARCHIVED, $quotation->fresh()->status);
        $this->assertFalse($quotation->fresh()->is_current);
    }

    public function test_revise_rejected_allowed(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_REJECTED,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->user->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        $this->actingAs($this->admin)
            ->postJson(route('quotation.revise', $quotation->id))
            ->assertOk();

        $revision = Quotation::latest('id')->firstOrFail();
        $this->assertSame(2, $revision->version);
        $this->assertSame(Quotation::STATUS_DRAFT, $revision->status);
    }

    public function test_versions_returns_group_history(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_APPROVED,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->user->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        $revision = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'group_id' => $quotation->group_id,
            'parent_id' => $quotation->id,
            'version' => 2,
            'is_current' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('quotation.versions', $quotation->id))
            ->assertOk();

        $versions = $response->json('versions');
        $this->assertCount(2, $versions);
        $this->assertSame([2, 1], collect($versions)->pluck('version')->all());
        $this->assertSame($revision->id, $versions[0]['id']);
    }

    public function test_data_only_returns_latest_version_per_group(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_APPROVED,
            'group_id' => null,
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->user->id,
        ]);
        $quotation->update(['group_id' => $quotation->id]);

        $revision = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'group_id' => $quotation->group_id,
            'parent_id' => $quotation->id,
            'version' => 2,
            'is_current' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('quotation.data').'?draw=1&start=0&length=10')
            ->assertOk();

        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($revision->id, $response->json('data.0.id'));
        $this->assertSame(2, $response->json('data.0.version'));
    }

    public function test_store_generates_unique_quotation_number(): void
    {
        $config = $this->createApprovedConfiguration();

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $task = $this->createTask();
            $config2 = QuoteConfiguration::create([
                'division_id' => $this->division->id,
                'group_id' => 1,
                'version' => 1,
                'is_current' => true,
                'opportunity_id' => $this->opportunity->id,
                'task_id' => $task->id,
                'date' => '2026-08-11',
                'status' => QuoteConfiguration::STATUS_APPROVED,
                'created_by' => $this->user->id,
            ]);
            $config2->update(['group_id' => $config2->id]);

            $this->actingAs($this->admin)->postJson(route('quotation.store'), [
                'task_id' => $task->id,
                'quote_configuration_ids' => [$config2->id],
                'items' => [$this->itemPayload($config2, ['_key' => 'row-'.$i, 'description' => 'Item '.$i])],
            ])->assertOk();

            $q = Quotation::latest('id')->firstOrFail();
            $numbers[] = $q->quotation_number;
        }

        $this->assertCount(3, array_unique($numbers));
    }

    public function test_destroy_only_draft(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson(route('quotation.destroy', $quotation->id))
            ->assertOk();

        $this->assertSame(0, Quotation::count());
    }

    public function test_initials_and_roman_month_helpers(): void
    {
        $this->assertSame('ZM', Quotation::initials('Zuri Muriani'));
        $this->assertSame('Z', Quotation::initials('Zuri'));
        $this->assertSame('XX', Quotation::initials(null));
        $this->assertSame('II', Quotation::romanMonth(2));
        $this->assertSame('XII', Quotation::romanMonth(12));
    }

    public function test_render_description_whitelists_tags(): void
    {
        $this->assertSame('<b>bold</b>', Quotation::renderDescription('<b>bold</b>'));
        $this->assertSame('x', Quotation::renderDescription('<script>alert(1)</script>x'));
        $this->assertSame('a<br>b', Quotation::renderDescription("a\nb"));
        $this->assertSame('', Quotation::renderDescription(null));
        $this->assertSame('<i>t</i>', Quotation::renderDescription('<i>t</i><iframe src="x"></iframe>'));
        $this->assertSame('<b>Judul</b><br>isi', Quotation::renderDescription("<b>Judul</b>\nisi"));
        $this->assertSame('a<br>b', Quotation::renderDescription('a<div>b</div>'));
        $this->assertSame('<u>u</u>', Quotation::renderDescription('<u>u</u>'));
        $this->assertSame('<em>e</em>', Quotation::renderDescription('<em>e</em>'));
        $this->assertSame('<strong>s</strong>', Quotation::renderDescription('<strong>s</strong>'));
    }

    public function test_store_sanitizes_item_description_keeps_bold_italic_underline(): void
    {
        $config = $this->createApprovedConfiguration();

        $this->actingAs($this->admin)->postJson(route('quotation.store'), [
            'task_id' => $config->task_id,
            'quote_configuration_ids' => [$config->id],
            'items' => [
                $this->itemPayload($config, [
                    '_key' => 'row-1',
                    'description' => '<b>bold</b><i>italic</i><u>under</u><script>alert(1)</script>',
                ]),
            ],
        ])->assertOk();

        $quotation = Quotation::latest('id')->firstOrFail();
        $this->assertSame('<b>bold</b><i>italic</i><u>under</u>', $quotation->items()->first()->description);
    }

    public function test_creator_can_update_notes_on_draft_quotation(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'notes' => 'Catatan awal.',
        ]);

        $this->actingAs($this->user)
            ->putJson(route('quotation.update-notes', $quotation->id), ['notes' => "Catatan baru.\nBaris kedua."])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('notes', "Catatan baru.\nBaris kedua.");

        $this->assertSame("Catatan baru.\nBaris kedua.", $quotation->fresh()->notes);
    }

    public function test_creator_can_update_notes_on_approved_locked_quotation(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_APPROVED,
            'approved_at' => now(),
            'created_by' => $this->user->id,
            'notes' => 'Catatan lama.',
        ]);

        $this->actingAs($this->user)
            ->putJson(route('quotation.update-notes', $quotation->id), ['notes' => 'Catatan setelah approval.'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('Catatan setelah approval.', $quotation->fresh()->notes);
    }

    public function test_admin_can_update_notes_even_when_not_creator(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'notes' => null,
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('quotation.update-notes', $quotation->id), ['notes' => 'Catatan admin.'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('Catatan admin.', $quotation->fresh()->notes);
    }

    public function test_non_creator_non_admin_cannot_update_notes(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'notes' => 'Tidak boleh diubah.',
        ]);

        $other = User::create([
            'username' => 'oranglain',
            'email' => 'lain@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        $this->actingAs($other)
            ->putJson(route('quotation.update-notes', $quotation->id), ['notes' => 'Diubah orang lain.'])
            ->assertForbidden();

        $this->assertSame('Tidak boleh diubah.', $quotation->fresh()->notes);
    }

    public function test_creator_can_clear_notes_to_null(): void
    {
        $config = $this->createApprovedConfiguration();

        $quotation = Quotation::create([
            'quote_configuration_id' => $config->id,
            'task_id' => $config->task_id,
            'date' => '2026-08-11',
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'notes' => 'Isi catatan.',
        ]);

        $this->actingAs($this->user)
            ->putJson(route('quotation.update-notes', $quotation->id), ['notes' => ''])
            ->assertOk()
            ->assertJsonPath('notes', null);

        $this->assertNull($quotation->fresh()->notes);
    }
}

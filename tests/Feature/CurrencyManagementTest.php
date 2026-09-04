<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin',
            'email' => 'admin@has.com',
            'password' => bcrypt('secret'),
            'role' => 'Admin',
        ]);
    }

    private function seedCurrencies(): void
    {
        Currency::create([
            'name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active',
        ]);
        Currency::create([
            'name' => 'USD', 'symbol' => '$', 'rate' => 20000, 'is_base' => false, 'status' => 'Active',
        ]);
        Currency::create([
            'name' => 'EUR', 'symbol' => '€', 'rate' => 18000, 'is_base' => false, 'status' => 'Active',
        ]);
    }

    private function createProduct(array $overrides = []): MasterProduct
    {
        $division = Division::create([
            'division_name' => 'WATER',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        return MasterProduct::create(array_merge([
            'name' => 'pH::lyser pro',
            'code' => 'E-514-4-075',
            'brand' => 's::can',
            'division_id' => $division->id,
            'price' => 12500000.00,
            'status' => 'Active',
        ], $overrides));
    }

    public function test_admin_can_open_currency_index_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('currency.index'))
            ->assertOk()
            ->assertSee('Currency Management')
            ->assertSee('Tambah Currency');
    }

    public function test_store_creates_currency(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('currency.store'), [
                'name' => 'USD',
                'symbol' => '$',
                'rate' => 20000,
                'is_base' => false,
                'status' => 'Active',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('currencies', ['name' => 'USD', 'rate' => 20000.0000]);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        $this->seedCurrencies();

        $this->actingAs($this->admin)
            ->postJson(route('currency.store'), [
                'name' => 'USD',
                'rate' => 20000,
                'is_base' => false,
                'status' => 'Active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_second_base_currency(): void
    {
        $this->seedCurrencies();

        $this->actingAs($this->admin)
            ->postJson(route('currency.store'), [
                'name' => 'SGD',
                'rate' => 15000,
                'is_base' => true,
                'status' => 'Active',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_base_currency_rate_forced_to_one(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('currency.store'), [
                'name' => 'IDR',
                'symbol' => 'Rp',
                'rate' => 500,
                'is_base' => true,
                'status' => 'Active',
            ])
            ->assertOk();

        $this->assertDatabaseHas('currencies', ['name' => 'IDR', 'rate' => 1.0000]);
    }

    public function test_data_endpoint_returns_currencies(): void
    {
        $this->seedCurrencies();

        $response = $this->actingAs($this->admin)
            ->getJson(route('currency.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 3);

        $this->assertSame('IDR', $response->json('data.0.name'));
        $this->assertSame('Ya', $response->json('data.0.is_base_label'));
        $this->assertSame('18,000.00', $response->json('data.1.rate_formatted'));
    }

    public function test_update_changes_rate(): void
    {
        $this->seedCurrencies();
        $usd = Currency::where('name', 'USD')->first();

        $this->actingAs($this->admin)
            ->putJson(route('currency.update', $usd->id), [
                'name' => 'USD',
                'symbol' => '$',
                'rate' => 21000,
                'is_base' => false,
                'status' => 'Active',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('currencies', ['id' => $usd->id, 'rate' => 21000.0000]);
    }

    public function test_update_base_currency_cannot_be_deactivated(): void
    {
        $this->seedCurrencies();
        $idr = Currency::where('name', 'IDR')->first();

        $this->actingAs($this->admin)
            ->putJson(route('currency.update', $idr->id), [
                'name' => 'IDR',
                'rate' => 1,
                'is_base' => true,
                'status' => 'Inactive',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame('Active', $idr->fresh()->status);
    }

    public function test_base_currency_cannot_be_deleted(): void
    {
        $this->seedCurrencies();
        $idr = Currency::where('name', 'IDR')->first();

        $this->actingAs($this->admin)
            ->deleteJson(route('currency.destroy', $idr->id))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('currencies', ['id' => $idr->id]);
    }

    public function test_currency_in_use_by_product_cannot_be_deleted(): void
    {
        $this->seedCurrencies();
        $usd = Currency::where('name', 'USD')->first();
        $this->createProduct(['currency_id' => $usd->id]);

        $this->actingAs($this->admin)
            ->deleteJson(route('currency.destroy', $usd->id))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('currencies', ['id' => $usd->id]);
    }

    public function test_destroy_deletes_unused_currency(): void
    {
        $this->seedCurrencies();
        $eur = Currency::where('name', 'EUR')->first();

        $this->actingAs($this->admin)
            ->deleteJson(route('currency.destroy', $eur->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('currencies', ['id' => $eur->id]);
    }

    public function test_product_store_without_currency_falls_back_to_base(): void
    {
        $this->seedCurrencies();
        $idr = Currency::where('name', 'IDR')->first();

        $this->actingAs($this->admin)
            ->postJson(route('product-management.store'), [
                'code' => 'NEW-001',
                'price' => 5000,
                'status' => 'Active',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('master_products', [
            'code' => 'NEW-001',
            'currency_id' => $idr->id,
        ]);
    }

    public function test_product_store_with_selected_currency(): void
    {
        $this->seedCurrencies();
        $usd = Currency::where('name', 'USD')->first();

        $this->actingAs($this->admin)
            ->postJson(route('product-management.store'), [
                'code' => 'NEW-USD-001',
                'price' => 100,
                'currency_id' => $usd->id,
                'status' => 'Active',
            ])
            ->assertOk();

        $this->assertDatabaseHas('master_products', [
            'code' => 'NEW-USD-001',
            'currency_id' => $usd->id,
        ]);
    }

    public function test_product_data_includes_currency_info(): void
    {
        $this->seedCurrencies();
        $usd = Currency::where('name', 'USD')->first();
        $this->createProduct(['currency_id' => $usd->id]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('product-management.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1);

        $this->assertSame('USD', $response->json('data.0.currency_name'));
        $this->assertSame('$', $response->json('data.0.currency_symbol'));
    }

    public function test_product_edit_includes_currency_rate_and_base_info(): void
    {
        $this->seedCurrencies();
        $usd = Currency::where('name', 'USD')->first();
        $product = $this->createProduct(['currency_id' => $usd->id, 'price' => 100]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('product-management.show', $product->id))
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame('USD', $data['currency_name']);
        $this->assertSame('$', $data['currency_symbol']);
        $this->assertEquals(20000.0, $data['currency_rate']);
        $this->assertFalse($data['currency_is_base']);
        $this->assertSame('IDR', $data['base_currency_name']);
        $this->assertSame('Rp', $data['base_currency_symbol']);
    }

    public function test_product_edit_for_base_currency_marks_is_base(): void
    {
        $this->seedCurrencies();
        $idr = Currency::where('name', 'IDR')->first();
        $product = $this->createProduct(['currency_id' => $idr->id]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('product-management.show', $product->id))
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame('IDR', $data['currency_name']);
        $this->assertSame('Rp', $data['currency_symbol']);
        $this->assertEquals(1.0, $data['currency_rate']);
        $this->assertTrue($data['currency_is_base']);
    }

    public function test_export_includes_currency_column(): void
    {
        $this->seedCurrencies();
        $usd = Currency::where('name', 'USD')->first();
        $this->createProduct(['currency_id' => $usd->id]);

        $products = MasterProduct::with(['division', 'currency'])->get();
        $headers = [
            'Name', 'Code', 'Brand', 'Category', 'Division',
            'Description', 'Price', 'Currency', 'Status',
        ];

        $service = new \App\Services\ProductExportService;
        $filePath = $service->export($products, $headers);

        $this->assertFileExists($filePath);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($filePath), 'xlsx export harus valid zip');
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        unlink($filePath);

        $this->assertStringContainsString('Currency', $xml, 'Header berisi kolom Currency');
        $this->assertStringContainsString('USD', $shared, 'Isi berisi kode currency USD');
    }

    public function test_import_without_currency_falls_back_to_base(): void
    {
        $this->seedCurrencies();
        $idr = Currency::where('name', 'IDR')->first();

        $csv = "Name,Code,Price,Status\n"
            ."Produk Import,IMP-001,15000,Active\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('result.success', 1);

        $this->assertDatabaseHas('master_products', [
            'code' => 'IMP-001',
            'currency_id' => $idr->id,
        ]);
    }

    public function test_import_with_currency_code(): void
    {
        $this->seedCurrencies();
        $usd = Currency::where('name', 'USD')->first();

        $csv = "Name,Code,Price,Currency,Status\n"
            ."Produk USD,IMP-USD-001,100,USD,Active\n"
            ."Produk Euro,IMP-EUR-001,200,eur,Active\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('result.success', 2);

        $this->assertDatabaseHas('master_products', [
            'code' => 'IMP-USD-001',
            'currency_id' => $usd->id,
        ]);
        $this->assertDatabaseHas('master_products', [
            'code' => 'IMP-EUR-001',
            'currency_id' => Currency::where('name', 'EUR')->value('id'),
        ]);
    }

    public function test_import_with_unknown_currency_code_fails_row(): void
    {
        $this->seedCurrencies();

        $csv = "Name,Code,Price,Currency,Status\n"
            ."Produk Typo,IMP-TYPO-001,15000,USX,Active\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('result.failed', 1)
            ->assertJsonPath('result.success', 0);

        $this->assertDatabaseMissing('master_products', ['code' => 'IMP-TYPO-001']);
    }

    public function test_import_update_existing_product_changes_currency(): void
    {
        $this->seedCurrencies();
        $idr = Currency::where('name', 'IDR')->first();
        $usd = Currency::where('name', 'USD')->first();
        $product = $this->createProduct(['currency_id' => $idr->id]);

        $csv = "Name,Code,Price,Currency,Status\n"
            ."pH::lyser pro,{$product->code},13000000,USD,Active\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('result.updated', 1);

        $this->assertDatabaseHas('master_products', [
            'code' => $product->code,
            'currency_id' => $usd->id,
        ]);
    }
}

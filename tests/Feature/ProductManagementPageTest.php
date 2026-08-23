<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductManagementPageTest extends TestCase
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

    private function createDivision(): Division
    {
        return Division::create([
            'division_name' => 'WATER',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);
    }

    private function createProduct(array $overrides = []): MasterProduct
    {
        return MasterProduct::create(array_merge([
            'name' => 'pH::lyser pro',
            'code' => 'E-514-4-075',
            'brand' => 's::can',
            'category' => 'Sensor',
            'division_id' => $this->createDivision()->id,
            'description' => 'Sensor pH untuk water monitoring',
            'price' => 12500000.00,
            'status' => 'Active',
        ], $overrides));
    }

    public function test_admin_can_open_index_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('product-management.index'))
            ->assertOk()
            ->assertSee('Product Management')
            ->assertSee('Tambah Product');
    }

    public function test_index_page_renders_division_options(): void
    {
        $this->createDivision();

        $this->actingAs($this->admin)
            ->get(route('product-management.index'))
            ->assertOk()
            ->assertSee('WATER');
    }

    public function test_data_endpoint_returns_products(): void
    {
        $this->createProduct();

        $response = $this->actingAs($this->admin)
            ->getJson(route('product-management.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1);

        $this->assertSame('E-514-4-075', $response->json('data.0.code'));
        $this->assertSame('WATER', $response->json('data.0.division_name'));
        $this->assertSame('12,500,000', $response->json('data.0.price_formatted'));
    }

    public function test_store_creates_product(): void
    {
        $division = $this->createDivision();

        $this->actingAs($this->admin)
            ->postJson(route('product-management.store'), [
                'name' => 'ammo::lyser pro',
                'code' => 'E-532-pro-075',
                'brand' => 's::can',
                'category' => 'Sensor',
                'division_id' => $division->id,
                'description' => 'Sensor ammonia',
                'price' => 15000000.00,
                'status' => 'Active',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('master_products', ['code' => 'E-532-pro-075']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $this->createProduct();

        $this->actingAs($this->admin)
            ->postJson(route('product-management.store'), [
                'name' => 'Duplikat',
                'code' => 'E-514-4-075',
                'price' => 1000,
                'status' => 'Active',
            ])
            ->assertUnprocessable();
    }

    public function test_show_returns_product_detail_json(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->admin)
            ->getJson(route('product-management.show', $product->id))
            ->assertOk()
            ->assertJsonPath('data.name', 'pH::lyser pro')
            ->assertJsonPath('data.code', 'E-514-4-075')
            ->assertJsonPath('data.division_name', 'WATER')
            ->assertJsonPath('data.price', '12500000.00');
    }

    public function test_update_changes_product(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->admin)
            ->putJson(route('product-management.update', $product->id), [
                'name' => 'pH::lyser pro V2',
                'code' => 'E-514-4-075',
                'brand' => 's::can',
                'price' => 13000000.00,
                'status' => 'Inactive',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $product->fresh();
        $this->assertSame('pH::lyser pro V2', $fresh->name);
        $this->assertSame('Inactive', $fresh->status);
    }

    public function test_destroy_deletes_product(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->admin)
            ->deleteJson(route('product-management.destroy', $product->id))
            ->assertOk();

        $this->assertDatabaseMissing('master_products', ['id' => $product->id]);
    }

    public function test_template_download_returns_xlsx(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('product-management.template'))
            ->assertOk();

        $this->assertStringContainsString('Product_Import_Template.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_export_downloads_xlsx(): void
    {
        $this->createProduct();

        $response = $this->actingAs($this->admin)
            ->get(route('product-management.export'))
            ->assertOk();

        $this->assertStringContainsString('products-export-', $response->headers->get('content-disposition'));
    }

    public function test_import_uploads_csv_and_creates_products(): void
    {
        $water = $this->createDivision(); // lookup divisi WATER untuk import

        $csv = "Name,Code,Brand,Category,Division,Description,Price,Status\n"
            ."pH::lyser pro,E-514-4-075,s::can,Sensor,WATER,Sensor pH,12500000,Active\n"
            ."ammo::lyser pro,E-532-pro-075,s::can,Sensor,WATER,Sensor ammonia,\"Rp 15.000.000,50\",Inactive\n"
            .",E-000-EMP,,,WATER,,5000,Active\n"; // nama kosong => tetap boleh

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.success', 3);

        $this->assertDatabaseHas('master_products', [
            'code' => 'E-514-4-075',
            'name' => 'pH::lyser pro',
            'division_id' => $water->id,
            'price' => 12500000.00,
            'status' => 'Active',
        ]);
        $this->assertDatabaseHas('master_products', [
            'code' => 'E-532-pro-075',
            'price' => 15000000.50,
            'status' => 'Inactive',
        ]);
        $this->assertDatabaseHas('master_products', [
            'code' => 'E-000-EMP',
            'name' => null,
        ]);
    }

    public function test_import_updates_existing_allows_empty_name_rejects_duplicate_name(): void
    {
        $this->createProduct(['code' => 'E-514-4-075', 'name' => 'Nama Lama']);

        $csv = "Name,Code,Price,Status\n"
            ."Nama Lama,E-999-X,1000,Active\n"         // nama duplikat (dipakai E-514-4-075) => gagal
            ."Nama Baru,E-514-4-075,999999,Active\n"   // update by code
            .",E-999,1000,Active\n"                    // nama kosong => boleh
            ."Tanpa Code,,1000,Active\n";              // code kosong => gagal

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('result.updated', 1)
            ->assertJsonPath('result.success', 1)
            ->assertJsonPath('result.failed', 2)
            ->assertJsonPath('result.errors.0', fn ($e) => str_contains($e, 'Nama Lama'));

        $this->assertDatabaseHas('master_products', [
            'code' => 'E-514-4-075',
            'name' => 'Nama Baru',
            'price' => 999999.00,
        ]);
        $this->assertDatabaseHas('master_products', ['code' => 'E-999', 'name' => null]);
        $this->assertDatabaseMissing('master_products', ['code' => 'E-999-X']);
    }

    public function test_import_rejects_duplicate_name_within_same_file(): void
    {
        $csv = "Name,Code,Price,Status\n"
            ."Sensor pH,E-111,1000,Active\n"
            ."sensor ph,E-222,2000,Active\n"; // case-insensitive => dianggap sama

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('result.success', 1)
            ->assertJsonPath('result.failed', 1);

        $this->assertDatabaseHas('master_products', ['code' => 'E-111']);
        $this->assertDatabaseMissing('master_products', ['code' => 'E-222']);
    }

    public function test_store_allows_empty_name_and_multiple_empty_names(): void
    {
        $payload = [
            'name' => '',
            'code' => 'EMP-001',
            'price' => 1000,
            'status' => 'Active',
        ];

        $this->actingAs($this->admin)->postJson(route('product-management.store'), $payload)->assertOk();
        $this->actingAs($this->admin)->postJson(route('product-management.store'), [
            'name' => '',
            'code' => 'EMP-002',
            'price' => 2000,
            'status' => 'Active',
        ])->assertOk();

        $this->assertDatabaseHas('master_products', ['code' => 'EMP-001', 'name' => null]);
        $this->assertDatabaseHas('master_products', ['code' => 'EMP-002', 'name' => null]);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        $this->createProduct(['name' => 'pH::lyser pro']);

        $this->actingAs($this->admin)
            ->postJson(route('product-management.store'), [
                'name' => 'pH::lyser pro',
                'code' => 'CODE-BARU',
                'price' => 1000,
                'status' => 'Active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseMissing('master_products', ['code' => 'CODE-BARU']);
    }

    public function test_update_allows_keeping_own_name(): void
    {
        $product = $this->createProduct(['name' => 'pH::lyser pro']);

        $this->actingAs($this->admin)
            ->putJson(route('product-management.update', $product->id), [
                'name' => 'pH::lyser pro',
                'code' => 'E-514-4-075',
                'price' => 13000000.00,
                'status' => 'Active',
            ])
            ->assertOk();

        $this->assertSame('pH::lyser pro', $product->fresh()->name);
    }

    public function test_update_via_put_with_full_form_payload(): void
    {
        // Simulasi payload FormData lengkap seperti yang dikirim browser saat edit.
        $division = $this->createDivision();
        $product = $this->createProduct(['division_id' => $division->id]);

        $this->actingAs($this->admin)
            ->put(route('product-management.update', $product->id), [
                'name' => 'pH::lyser pro V2',
                'code' => 'E-514-4-075',
                'brand' => 's::can',
                'category' => 'Sensor',
                'division_id' => $division->id,
                'description' => 'Deskripsi baru',
                'price' => 13000000.00,
                'status' => 'Inactive',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $fresh = $product->fresh();
        $this->assertSame('pH::lyser pro V2', $fresh->name);
        $this->assertSame('Sensor', $fresh->category);
        $this->assertSame('Deskripsi baru', $fresh->description);
        $this->assertSame('Inactive', $fresh->status);
        $this->assertSame($division->id, $fresh->division_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\User;
use App\Services\ProductImportService;
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
            'type' => 'Main',
            'parameter' => 's::can / 0.01pH',
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
        $this->assertSame('Main', $response->json('data.0.type'));
        $this->assertSame('s::can / 0.01pH', $response->json('data.0.parameter'));
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
                'type' => 'Main',
                'parameter' => 's::can / 0.05ppm',
                'division_id' => $division->id,
                'description' => 'Sensor ammonia',
                'price' => 15000000.00,
                'status' => 'Active',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('master_products', [
            'code' => 'E-532-pro-075',
            'type' => 'Main',
            'parameter' => 's::can / 0.05ppm',
        ]);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('product-management.store'), [
                'code' => 'INV-001',
                'price' => 1000,
                'status' => 'Active',
                'type' => 'Spare',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseMissing('master_products', ['code' => 'INV-001']);
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
                'type' => 'Service',
                'parameter' => 'kalibrasi tahunan',
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
        $this->assertSame('Service', $fresh->type);
        $this->assertSame('kalibrasi tahunan', $fresh->parameter);
        $this->assertSame('Deskripsi baru', $fresh->description);
        $this->assertSame('Inactive', $fresh->status);
        $this->assertSame($division->id, $fresh->division_id);
    }

    public function test_import_parses_type_parameter(): void
    {
        $csv = "Name,Code,Type,Parameter,Price,Status\n"
            ."Servis Kalibrasi,SVC-001,Service,Kalibrasi 1 tahun,250000,Active\n"
            ."Sparepart X,SPR-001,Acc,s::can,50000,Active\n"
            ."Tanpa Klasifikasi,EMP-001,,,5000,Active\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.success', 3);

        $this->assertDatabaseHas('master_products', [
            'code' => 'SVC-001',
            'type' => 'Service',
            'parameter' => 'Kalibrasi 1 tahun',
        ]);
        $this->assertDatabaseHas('master_products', [
            'code' => 'SPR-001',
            'type' => 'Acc',
            'parameter' => 's::can',
        ]);
        $this->assertDatabaseHas('master_products', [
            'code' => 'EMP-001',
            'type' => null,
            'parameter' => null,
        ]);
    }

    public function test_import_rejects_invalid_type(): void
    {
        $csv = "Code,Type,Price,Status\n"
            ."BAD-1,Spare,1000,Active\n"
            ."OK-1,Acc,1000,Active\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('result.success', 1)
            ->assertJsonPath('result.failed', 1);

        $this->assertDatabaseHas('master_products', [
            'code' => 'OK-1',
            'type' => 'Acc',
        ]);
        $this->assertDatabaseMissing('master_products', ['code' => 'BAD-1']);
    }

    public function test_import_rejects_external_division(): void
    {
        Division::create([
            'division_name' => 'VENDOR-EXT',
            'description' => 'Eksternal',
            'type' => 'External',
            'status' => 'Active',
        ]);
        $internal = $this->createDivision(); // WATER, tipe Internal

        $csv = "Code,Division,Price,Status\n"
            ."EXT-1,VENDOR-EXT,1000,Active\n"
            ."INT-1,WATER,1000,Active\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('product-management.import'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('result.success', 1)
            ->assertJsonPath('result.failed', 1)
            ->assertJsonPath('result.errors.0', fn ($e) => str_contains($e, 'VENDOR-EXT'));

        $this->assertDatabaseMissing('master_products', ['code' => 'EXT-1']);
        $this->assertDatabaseHas('master_products', [
            'code' => 'INT-1',
            'division_id' => $internal->id,
        ]);
    }

    public function test_get_reference_data_only_lists_internal_divisions(): void
    {
        Division::create([
            'division_name' => 'VENDOR-EXT',
            'description' => 'Eksternal',
            'type' => 'External',
            'status' => 'Active',
        ]);
        $this->createDivision(); // WATER, tipe Internal

        $references = ProductImportService::getReferenceData();

        $this->assertContains('WATER', $references['division']);
        $this->assertNotContains('VENDOR-EXT', $references['division']);
    }

    public function test_data_endpoint_applies_filters(): void
    {
        $division = $this->createDivision();

        $this->createProduct(['name' => 'Produk 1', 'code' => 'P-1', 'status' => 'Active', 'type' => 'Acc']);
        $this->createProduct(['name' => 'Produk 2', 'code' => 'P-2', 'status' => 'Inactive', 'type' => 'Service', 'category' => 'Aksesoris']);
        $this->createProduct(['name' => 'Produk 3', 'code' => 'P-3', 'status' => 'Active', 'type' => 'Main', 'division_id' => $division->id]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('product-management.data').'?status=Active')
            ->assertOk();

        $this->assertSame(2, $response->json('recordsFiltered'));

        $response = $this->actingAs($this->admin)
            ->getJson(route('product-management.data').'?type=Main')
            ->assertOk();

        $this->assertSame(1, $response->json('recordsFiltered'));

        $response = $this->actingAs($this->admin)
            ->getJson(route('product-management.data').'?category=Aksesoris')
            ->assertOk();

        $this->assertSame(1, $response->json('recordsFiltered'));

        $response = $this->actingAs($this->admin)
            ->getJson(route('product-management.data').'?division_id='.$division->id)
            ->assertOk();

        $this->assertSame(1, $response->json('recordsFiltered'));
    }

    public function test_export_applies_filters(): void
    {
        $this->createProduct(['name' => 'Produk A', 'code' => 'P-1', 'status' => 'Active']);
        $this->createProduct(['name' => 'Produk B', 'code' => 'P-2', 'status' => 'Inactive']);

        $response = $this->actingAs($this->admin)
            ->get(route('product-management.export', ['status' => 'Inactive']))
            ->assertOk();

        $this->assertStringContainsString('products-export-', $response->headers->get('content-disposition'));
    }

    public function test_data_endpoint_accepts_full_datatables_payload(): void
    {
        $this->createProduct(['name' => 'Produk Filter', 'code' => 'P-1', 'status' => 'Active']);

        $params = [
            'draw' => 1,
            'columns[0][data]' => 'DT_RowIndex',
            'columns[0][name]' => '',
            'columns[0][searchable]' => 'false',
            'columns[0][orderable]' => 'false',
            'columns[1][data]' => 'name_display',
            'columns[1][name]' => '',
            'columns[1][searchable]' => 'true',
            'columns[1][orderable]' => 'true',
            'columns[1][search][value]' => '',
            'columns[1][search][regex]' => 'false',
            'order[0][column]' => '1',
            'order[0][dir]' => 'asc',
            'start' => '0',
            'length' => '10',
            'search[value]' => '',
            'search[regex]' => 'false',
            'category' => '',
            'type' => '',
            'division_id' => '',
            'status' => '',
        ];

        $this->actingAs($this->admin)
            ->getJson(route('product-management.data').'?'.http_build_query($params))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1);

        $params['search[value]'] = 'Filter';
        $this->actingAs($this->admin)
            ->getJson(route('product-management.data').'?'.http_build_query($params))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1);
    }
}

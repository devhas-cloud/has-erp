<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Harga beli master product: role Admin / divisi Admin semua produk; divisi IMS hanya
 * produk divisinya (lihat, ubah harga & currency, edit, hapus).
 * Harga jual master product: hanya role Admin atau divisi Admin.
 * Aturan sama di view (canViewPrice / canViewSellingPrice) dan di server.
 */
class ProductPriceAccessTest extends TestCase
{
    use RefreshDatabase;

    private MasterProduct $product;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Module::create(['module_code' => 'MOD_PRODUCT_MANAGEMENT', 'module_name' => 'Product Management', 'route_name' => 'product-management', 'icon' => 'fa fa-box', 'group' => 'Master Data']);
        $this->product = MasterProduct::create(['name' => 'Sensor', 'code' => 'PN-1', 'purchase_price' => 1000000, 'selling_price' => 1500000, 'status' => 'Active']);
    }

    private function user(string $username, ?string $division, string $role = 'User'): User
    {
        $divisionId = $division ? Division::firstOrCreate(['division_name' => $division], ['description' => $division, 'type' => 'Internal', 'status' => 'Active'])->id : null;
        $user = User::create(['username' => $username, 'email' => $username.'@has.com', 'password' => bcrypt('secret'), 'role' => $role, 'division_id' => $divisionId]);
        UserAccessControl::create(['user_id' => $user->id, 'module_id' => $this->module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => false, 'can_approve' => false]);

        return $user;
    }

    public function test_price_viewer_rule(): void
    {
        $this->assertTrue($this->user('ims', 'IMS')->canViewProductPrice());
        $this->assertTrue($this->user('ims2', 'ims')->canViewProductPrice(), 'tidak peka huruf besar/kecil');
        $this->assertTrue($this->user('admindiv', 'Admin')->canViewProductPrice());
        $this->assertTrue($this->user('superadmin', 'Water', 'Admin')->canViewProductPrice());
        $this->assertFalse($this->user('water', 'Water')->canViewProductPrice());
        $this->assertFalse($this->user('nodiv', null)->canViewProductPrice());
    }

    public function test_selling_price_viewer_rule(): void
    {
        $this->assertTrue($this->user('admindiv', 'Admin')->canViewSellingPrice());
        $this->assertTrue($this->user('admindiv2', 'admin')->canViewSellingPrice(), 'tidak peka huruf besar/kecil');
        $this->assertTrue($this->user('superadmin', 'Water', 'Admin')->canViewSellingPrice());
        $this->assertFalse($this->user('ims', 'IMS')->canViewSellingPrice());
        $this->assertFalse($this->user('water', 'Water')->canViewSellingPrice());
        $this->assertFalse($this->user('nodiv', null)->canViewSellingPrice());
    }

    private function imsProduct(): MasterProduct
    {
        $ims = Division::firstOrCreate(['division_name' => 'IMS'], ['description' => 'IMS', 'type' => 'Internal', 'status' => 'Active']);

        return MasterProduct::create(['name' => 'Analyzer IMS', 'code' => 'IMS-1', 'division_id' => $ims->id, 'purchase_price' => 500000, 'selling_price' => 0, 'status' => 'Active']);
    }

    public function test_ims_sees_purchase_price_of_own_division_only(): void
    {
        $ims = $this->user('ims', 'IMS');
        $admin = $this->user('admindiv', 'Admin');
        $water = $this->user('water', 'Water');
        $own = $this->imsProduct();

        // Produk divisi IMS: harga beli terlihat & bisa diubah; harga jual tidak.
        $this->actingAs($ims)->getJson(route('product-management.show', $own->id))
            ->assertOk()
            ->assertJsonPath('data.purchase_price', '500000.00')
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_edit_price', true)
            ->assertJsonPath('data.selling_price', null)
            ->assertJsonPath('data.selling_price_formula', null);
        // Produk divisi lain / tanpa divisi: harga tersembunyi, tidak bisa dikelola.
        $this->actingAs($ims)->getJson(route('product-management.show', $this->product->id))
            ->assertOk()
            ->assertJsonPath('data.purchase_price', null)
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_edit_price', false);
        $this->actingAs($admin)->getJson(route('product-management.show', $this->product->id))
            ->assertOk()
            ->assertJsonPath('data.purchase_price', '1000000.00')
            ->assertJsonPath('data.selling_price', '1500000.00')
            ->assertJsonPath('data.can_edit_price', true);
        $this->actingAs($water)->getJson(route('product-management.show', $this->product->id))
            ->assertOk()
            ->assertJsonPath('data.purchase_price', null)
            ->assertJsonPath('data.selling_price', null)
            ->assertJsonPath('data.can_manage', true);

        $rows = collect($this->actingAs($ims)->getJson(route('product-management.data'))->assertOk()->json('data'))->keyBy('code');
        $this->assertEquals(500000, $rows['IMS-1']['purchase_price']);
        $this->assertTrue($rows['IMS-1']['can_manage']);
        $this->assertNull($rows['PN-1']['purchase_price']);
        $this->assertFalse($rows['PN-1']['can_manage']);
        $this->assertNull($rows['PN-1']['selling_price']);
        $this->actingAs($admin)->getJson(route('product-management.data'))
            ->assertOk()->assertJsonPath('data.0.selling_price_formatted', fn ($v) => $v !== null);

        $this->actingAs($admin)->get(route('product-management.index'))->assertOk()->assertSee('<th>Harga Jual</th>', false);
        $this->actingAs($ims)->get(route('product-management.index'))->assertOk()
            ->assertDontSee('<th>Harga Jual</th>', false)->assertSee('<th>Harga Beli</th>', false)
            ->assertSee('<label class="form-label">Harga Beli</label>', false);
        $this->actingAs($water)->get(route('product-management.index'))->assertOk()
            ->assertDontSee('<th>Harga Jual</th>', false)->assertDontSee('<th>Harga Beli</th>', false)
            ->assertDontSee('<label class="form-label">Harga Beli</label>', false);
    }

    public function test_ims_can_update_price_and_currency_of_own_division_product(): void
    {
        $own = $this->imsProduct();
        $usd = \App\Models\Currency::create(['name' => 'USD', 'symbol' => '$', 'rate' => 16000, 'is_base' => false, 'status' => 'Active']);
        $other = Division::firstOrCreate(['division_name' => 'Water'], ['description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);

        // division_id dikirim divisi lain -> server tetap mencatat divisi IMS.
        $this->actingAs($this->user('ims', 'IMS'))->putJson(route('product-management.update', $own->id), [
            'code' => 'IMS-1', 'purchase_price' => 2000000, 'currency_id' => $usd->id, 'division_id' => $other->id, 'status' => 'Active',
        ])->assertOk();

        $own->refresh();
        $this->assertEquals(2000000, (float) $own->purchase_price);
        $this->assertSame($usd->id, (int) $own->currency_id);
        $this->assertSame('IMS', $own->division->division_name);
    }

    public function test_ims_cannot_edit_or_delete_other_division_product(): void
    {
        $ims = $this->user('ims', 'IMS');
        UserAccessControl::where('user_id', $ims->id)->update(['can_delete' => true]);

        $this->actingAs($ims)->putJson(route('product-management.update', $this->product->id), [
            'code' => 'PN-1', 'name' => 'Diubah', 'purchase_price' => 1, 'status' => 'Active',
        ])->assertForbidden();
        $this->actingAs($ims)->deleteJson(route('product-management.destroy', $this->product->id))->assertForbidden();

        $fresh = $this->product->fresh();
        $this->assertSame('Sensor', $fresh->name);
        $this->assertEquals(1000000, (float) $fresh->purchase_price);

        // Produk baru IMS selalu berdivisi IMS dan harganya boleh diisi.
        $this->actingAs($ims)->postJson(route('product-management.store'), [
            'code' => 'IMS-NEW', 'purchase_price' => 750000, 'status' => 'Active',
        ])->assertOk();
        $new = MasterProduct::where('code', 'IMS-NEW')->firstOrFail();
        $this->assertSame((int) $ims->division_id, (int) $new->division_id);
        $this->assertEquals(750000, (float) $new->purchase_price);

        // Divisi lain (non-IMS) tetap bisa mengedit field selain harga semua produk.
        $this->actingAs($this->user('water', 'Water'))->putJson(route('product-management.update', $this->product->id), [
            'code' => 'PN-1', 'name' => 'Sensor Baru', 'purchase_price' => 1, 'status' => 'Active',
        ])->assertOk();
        $this->assertSame('Sensor Baru', $this->product->fresh()->name);
        $this->assertEquals(1000000, (float) $this->product->fresh()->purchase_price);
    }

    public function test_ims_import_only_touches_own_division(): void
    {
        $own = $this->imsProduct();
        $ims = $this->user('ims', 'IMS');
        $csv = "code,name,division,purchase_price,currency,status\n"
            ."IMS-1,Analyzer IMS,Water,900000,,Active\n"
            ."PN-1,Sensor Hack,,1,,Active\n"
            ."IMS-2,Produk Baru,Water,300000,,Active\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($ims)->post(route('product-management.import'), ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('result.failed', 1);

        $this->assertEquals(900000, (float) $own->fresh()->purchase_price);
        $this->assertSame((int) $ims->division_id, (int) $own->fresh()->division_id);
        $this->assertSame('Sensor', $this->product->fresh()->name);
        $this->assertEquals(1000000, (float) $this->product->fresh()->purchase_price);
        $new = MasterProduct::where('code', 'IMS-2')->firstOrFail();
        $this->assertSame((int) $ims->division_id, (int) $new->division_id);
        $this->assertEquals(300000, (float) $new->purchase_price);
    }

    public function test_export_columns_follow_price_permissions(): void
    {
        $sheet = function (User $user): string {
            $res = $this->actingAs($user)->get(route('product-management.export'));
            $res->assertOk();
            $zip = new \ZipArchive;
            $zip->open($res->getFile()->getPathname());
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            return $xml;
        };

        $ims = $sheet($this->user('ims', 'IMS'));
        $this->assertStringContainsString('Purchase Price', $ims);
        $this->assertStringNotContainsString('Selling Price', $ims);

        $water = $sheet($this->user('water', 'Water'));
        $this->assertStringNotContainsString('Purchase Price', $water);
        $this->assertStringNotContainsString('Selling Price', $water);
        $this->assertStringNotContainsString('1,000,000.00', $water);

        $admin = $sheet($this->user('admindiv', 'Admin'));
        $this->assertStringContainsString('Purchase Price', $admin);
        $this->assertStringContainsString('Selling Price', $admin);
    }
}

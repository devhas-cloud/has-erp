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
 * Harga beli master product: role Admin, divisi Admin, divisi IMS.
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

    public function test_ims_sees_purchase_price_but_not_selling_price(): void
    {
        $ims = $this->user('ims', 'IMS');
        $admin = $this->user('admindiv', 'Admin');
        $water = $this->user('water', 'Water');

        $this->actingAs($ims)->getJson(route('product-management.show', $this->product->id))
            ->assertOk()
            ->assertJsonPath('data.purchase_price', '1000000.00')
            ->assertJsonPath('data.selling_price', null)
            ->assertJsonPath('data.selling_price_formula', null);
        $this->actingAs($admin)->getJson(route('product-management.show', $this->product->id))
            ->assertOk()
            ->assertJsonPath('data.purchase_price', '1000000.00')
            ->assertJsonPath('data.selling_price', '1500000.00');
        $this->actingAs($water)->getJson(route('product-management.show', $this->product->id))
            ->assertOk()
            ->assertJsonPath('data.purchase_price', null)
            ->assertJsonPath('data.selling_price', null);

        $this->actingAs($ims)->getJson(route('product-management.data'))
            ->assertOk()->assertJsonPath('data.0.selling_price', null)->assertJsonPath('data.0.purchase_price', 1000000);
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

    public function test_ims_can_update_purchase_price(): void
    {
        $this->actingAs($this->user('ims', 'IMS'))->putJson(route('product-management.update', $this->product->id), [
            'code' => 'PN-1', 'purchase_price' => 2000000, 'status' => 'Active',
        ])->assertOk();

        $this->assertEquals(2000000, (float) $this->product->fresh()->purchase_price);
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

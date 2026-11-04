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
 * Harga beli/jual master product hanya untuk role Admin, divisi Admin, dan
 * divisi IMS — aturan sama di view (canViewPrice) dan di server.
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

    public function test_ims_division_sees_and_updates_prices_while_others_do_not(): void
    {
        $ims = $this->user('ims', 'IMS');
        $water = $this->user('water', 'Water');

        $this->actingAs($ims)->getJson(route('product-management.show', $this->product->id))
            ->assertOk()->assertJsonPath('data.purchase_price', '1000000.00');
        $this->actingAs($water)->getJson(route('product-management.show', $this->product->id))
            ->assertOk()->assertJsonPath('data.purchase_price', null);

        $this->actingAs($ims)->get(route('product-management.index'))->assertOk()->assertSee('<th>Harga Jual</th>', false);
        $this->actingAs($water)->get(route('product-management.index'))->assertOk()->assertDontSee('<th>Harga Jual</th>', false);
    }
}

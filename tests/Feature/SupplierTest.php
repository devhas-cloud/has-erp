<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private Division $division;

    private User $creator;

    private User $userWithoutAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->division = Division::create([
            'division_name' => 'WATER',
            'description' => 'Water Management',
            'type' => 'Internal',
            'status' => 'Active',
        ]);

        $module = Module::create([
            'module_code' => 'MOD_SUPPLIER',
            'module_name' => 'Supplier',
            'route_name' => 'supplier',
            'icon' => 'fa fa-truck',
            'group' => 'Master Data',
        ]);

        $this->creator = $this->makeUser('creator', $module, ['can_create' => true, 'can_update' => true, 'can_delete' => true]);
        $this->userWithoutAccess = $this->makeUser('nobody', $module, []);
    }

    private function makeUser(string $username, Module $module, array $access): User
    {
        $user = User::create([
            'username' => $username,
            'email' => $username.'@has.com',
            'password' => bcrypt('secret'),
            'division_id' => $this->division->id,
            'role' => 'User',
        ]);

        UserAccessControl::create(array_merge([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'can_create' => false,
            'can_read' => true,
            'can_update' => false,
            'can_delete' => false,
            'can_approve' => false,
        ], $access));

        return $user;
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->creator)->get(route('supplier.index'))->assertOk();
    }

    public function test_store_requires_module_access(): void
    {
        $this->actingAs($this->userWithoutAccess)->postJson(route('supplier.store'), [
            'name' => 'CV. Riztech Engineering',
            'status' => 'Active',
        ])->assertStatus(403);

        $this->assertSame(0, Supplier::count());
    }

    public function test_store_persists_supplier(): void
    {
        $response = $this->actingAs($this->creator)->postJson(route('supplier.store'), [
            'name' => 'CV. Riztech Engineering',
            'address' => "Kp. Ranca Sabir, Desa Malakasari\nKecamatan Baleendah Kab. Bandung Jawa Barat 40375",
            'phone' => '0812-2221-2911',
            'attn_name' => 'CV. Riztech',
            'status' => 'Active',
        ])->assertOk();

        $this->assertTrue($response->json('success'));
        $supplier = Supplier::firstOrFail();
        $this->assertSame('CV. Riztech Engineering', $supplier->name);
        $this->assertSame('0812-2221-2911', $supplier->phone);
        $this->assertSame('CV. Riztech', $supplier->attn_name);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        Supplier::create(['name' => 'CV. Riztech Engineering', 'status' => 'Active']);

        $this->actingAs($this->creator)->postJson(route('supplier.store'), [
            'name' => 'CV. Riztech Engineering',
            'status' => 'Active',
        ])->assertStatus(422);
    }

    public function test_update_persists_changes(): void
    {
        $supplier = Supplier::create(['name' => 'CV. Riztech Engineering', 'status' => 'Active']);

        $this->actingAs($this->creator)->putJson(route('supplier.update', $supplier->id), [
            'name' => 'CV. Riztech Engineering',
            'phone' => '021-555555',
            'status' => 'Inactive',
        ])->assertOk();

        $fresh = $supplier->fresh();
        $this->assertSame('021-555555', $fresh->phone);
        $this->assertSame('Inactive', $fresh->status);
    }

    public function test_destroy_rejected_when_supplier_used_by_purchase_order(): void
    {
        $supplier = Supplier::create(['name' => 'CV. Riztech Engineering', 'status' => 'Active']);
        PurchaseOrder::create([
            'supplier_name' => $supplier->name,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'created_by' => $this->creator->id,
        ]);

        $response = $this->actingAs($this->creator)->deleteJson(route('supplier.destroy', $supplier->id));

        $response->assertStatus(422);
        $this->assertNotNull($supplier->fresh());
    }

    public function test_destroy_allowed_when_supplier_unused(): void
    {
        $supplier = Supplier::create(['name' => 'CV. Riztech Engineering', 'status' => 'Active']);

        $this->actingAs($this->creator)->deleteJson(route('supplier.destroy', $supplier->id))->assertOk();

        $this->assertNull($supplier->fresh());
    }

    /**
     * Endpoint pencarian dipakai Select2 di form Purchase Order — formatnya
     * harus {results: [...]} sesuai kontrak Select2, hanya supplier Active,
     * dan membawa field snapshot (address/phone/fax/attn_name) supaya form
     * bisa auto-isi tanpa AJAX tambahan.
     */
    public function test_search_returns_select2_format_with_snapshot_fields_active_only(): void
    {
        Supplier::create([
            'name' => 'CV. Riztech Engineering',
            'address' => 'Bandung',
            'phone' => '0812-2221-2911',
            'attn_name' => 'CV. Riztech',
            'status' => 'Active',
        ]);
        Supplier::create(['name' => 'CV. Nonaktif', 'status' => 'Inactive']);

        $response = $this->actingAs($this->creator)
            ->getJson(route('supplier.search', ['q' => 'Riztech']))
            ->assertOk();

        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('CV. Riztech Engineering', $results[0]['text']);
        $this->assertSame('0812-2221-2911', $results[0]['phone']);
        $this->assertSame('CV. Riztech', $results[0]['attn_name']);
    }
}

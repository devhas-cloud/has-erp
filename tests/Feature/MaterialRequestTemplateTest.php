<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\MaterialRequestTemplate;
use App\Models\Module;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Template Material Request (tab Template Material) + popup Pilih Produk.
 * Template dipakai bersama semua user; hak mengikuti modul Material Request.
 */
class MaterialRequestTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $viewer;

    private MasterProduct $cable;

    private MasterProduct $lamp;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Ops', 'description' => 'Ops', 'type' => 'Internal', 'status' => 'Active']);
        Branch::create(['code' => 'JKT', 'name' => 'HQ Jakarta', 'status' => 'Active']);
        $make = fn (string $name) => User::create(['username' => $name, 'email' => $name.'@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);
        $this->user = $make('produksi');
        $this->viewer = $make('viewer');

        $module = Module::create(['module_code' => 'MOD_MATERIAL_REQUEST', 'module_name' => 'Material Request', 'route_name' => 'material-request', 'icon' => 'fa fa-box', 'group' => 'Produksi']);
        UserAccessControl::create(['user_id' => $this->user->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
        UserAccessControl::create(['user_id' => $this->viewer->id, 'module_id' => $module->id, 'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false, 'can_approve' => false]);

        $this->cable = MasterProduct::create(['code' => 'CBL-1', 'name' => 'Kabel NYY', 'unit' => 'Meter', 'category' => 'Kabel', 'division_id' => $division->id, 'status' => 'Active', 'is_material' => true]);
        $this->lamp = MasterProduct::create(['code' => 'LMP-1', 'name' => 'Lampu LED', 'unit' => 'Unit', 'category' => 'Lampu', 'division_id' => $division->id, 'status' => 'Active', 'is_material' => false]);
        MasterProduct::create(['code' => 'OLD-1', 'name' => 'Produk Lama', 'division_id' => $division->id, 'status' => 'Inactive', 'is_material' => true]);
    }

    private function storeTemplate(array $extra = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->user)->postJson(route('material-request.template-store'), array_merge([
            'name' => 'Panel Standar',
            'description' => 'Panel 1 set',
            'items' => [
                ['master_product_id' => $this->cable->id, 'qty' => 2.5, 'note' => 'NYY 4x10'],
                ['master_product_id' => $this->lamp->id, 'qty' => 3],
            ],
        ], $extra));
    }

    public function test_template_crud_duplicate_and_validation(): void
    {
        $this->actingAs($this->user)->get(route('material-request.index'))->assertOk()->assertSee('Template Material')->assertSee('List Material');
        $this->actingAs($this->user)->get(route('material-request.template-create'))->assertOk()->assertSee('Pilih Produk');

        // Validasi: nama wajib, qty SN bulat, produk tidak boleh dobel.
        $this->storeTemplate(['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->storeTemplate(['items' => [['master_product_id' => $this->lamp->id, 'qty' => 1.5]]])->assertStatus(422);
        $this->storeTemplate(['items' => [['master_product_id' => $this->cable->id, 'qty' => 1], ['master_product_id' => $this->cable->id, 'qty' => 2]]])->assertStatus(422);

        $id = $this->storeTemplate()->assertOk()->json('id');
        $this->storeTemplate()->assertStatus(422)->assertJsonValidationErrors('name');

        $template = MaterialRequestTemplate::with('items')->findOrFail($id);
        $this->assertSame(MaterialRequestTemplate::STATUS_ACTIVE, $template->status);
        $this->assertCount(2, $template->items);
        $this->assertEquals(2.5, $template->items[0]->qty);

        $this->actingAs($this->user)->getJson(route('material-request.template-data', ['draw' => 1, 'search' => ['value' => 'LMP']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.item_count', 2);
        $this->actingAs($this->user)->get(route('material-request.template-edit', $id))->assertOk()->assertSee('Panel Standar');

        $this->actingAs($this->user)->putJson(route('material-request.template-update', $id), [
            'name' => 'Panel Standar v2', 'status' => 'Inactive',
            'items' => [['master_product_id' => $this->lamp->id, 'qty' => 4]],
        ])->assertOk();
        $template->refresh()->load('items');
        $this->assertSame('Panel Standar v2', $template->name);
        $this->assertCount(1, $template->items);

        // Template nonaktif tidak muncul di popup form MR.
        $this->actingAs($this->user)->getJson(route('material-request.template-options'))->assertOk()->assertJsonCount(0, 'data');

        $copyId = $this->actingAs($this->user)->postJson(route('material-request.template-duplicate', $id))->assertOk()->json('id');
        $this->assertSame('Panel Standar v2 (Salinan)', MaterialRequestTemplate::find($copyId)->name);
        $this->assertCount(1, MaterialRequestTemplate::find($copyId)->items);

        $this->actingAs($this->user)->deleteJson(route('material-request.template-destroy', $copyId))->assertOk();
        $this->assertDatabaseMissing('material_request_templates', ['id' => $copyId]);
        $this->assertDatabaseMissing('material_request_template_items', ['material_request_template_id' => $copyId]);
    }

    public function test_template_items_feed_mr_form_and_permissions(): void
    {
        $id = $this->storeTemplate()->assertOk()->json('id');

        // Template dipakai bersama: user lain (hanya baca) tetap bisa mengambil isinya.
        $this->actingAs($this->viewer)->getJson(route('material-request.template-options', ['q' => 'kabel']))
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.item_count', 2);
        $this->actingAs($this->viewer)->getJson(route('material-request.template-items', $id))
            ->assertOk()
            ->assertJsonPath('items.0.code', 'CBL-1')
            ->assertJsonPath('items.0.unit', 'Meter')
            ->assertJsonPath('items.0.is_material', true)
            ->assertJsonPath('items.0.active', true)
            ->assertJsonPath('items.1.is_material', false);

        // Tanpa hak buat / ubah / hapus -> ditolak.
        $this->storeTemplate(['name' => 'Lain'], $this->viewer)->assertForbidden();
        $this->actingAs($this->viewer)->putJson(route('material-request.template-update', $id), ['name' => 'X', 'items' => [['master_product_id' => $this->lamp->id, 'qty' => 1]]])->assertForbidden();
        $this->actingAs($this->viewer)->deleteJson(route('material-request.template-destroy', $id))->assertForbidden();

        $this->actingAs($this->user)->get(route('material-request.create'))->assertOk()
            ->assertSee('Ambil dari Template')->assertSee('Simpan sebagai Template');
    }

    public function test_product_picker_search_is_paginated_and_filtered(): void
    {
        $res = $this->actingAs($this->user)->getJson(route('material-request.search-products', ['with_categories' => 1, 'per_page' => 10]))->assertOk();
        $this->assertSame(2, $res->json('meta.total'), 'produk nonaktif tidak muncul');
        $this->assertSame(['Kabel', 'Lampu'], $res->json('categories'));

        $this->actingAs($this->user)->getJson(route('material-request.search-products', ['kind' => 'serial']))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.code', 'LMP-1')->assertJsonPath('data.0.unit', 'Unit');
        $this->actingAs($this->user)->getJson(route('material-request.search-products', ['q' => 'nyy']))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.is_material', true);
        $this->actingAs($this->user)->getJson(route('material-request.search-products', ['category' => 'Lampu']))
            ->assertOk()->assertJsonPath('meta.total', 1);
    }
}

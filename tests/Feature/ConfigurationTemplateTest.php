<?php

namespace Tests\Feature;

use App\Models\ConfigurationTemplate;
use App\Models\ConfigurationTemplateItem;
use App\Models\Division;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurationTemplateTest extends TestCase
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

    /** @return array<string, array{0:string, 1:string}> */
    public static function modules(): array
    {
        return [
            'water' => ['water-configuration', 'Water'],
            'enviro' => ['enviro-configuration', 'Enviro'],
            'ih' => ['ih-configuration', 'IH'],
            'gas' => ['gas-configuration', 'Gas'],
            'ims' => ['ims-configuration', 'IMS'],
        ];
    }

    private function createDivision(string $name): Division
    {
        return Division::firstOrCreate([
            'division_name' => $name,
        ], [
            'description' => $name,
            'type' => 'Internal',
            'status' => 'Active',
        ]);
    }

    private function payload(array $root, MasterProduct $product, bool $withChildren = true): array
    {
        $items = [
            ['_key' => 'new-1', 'category' => 'pH', 'description' => 'Parent pH', 'qty' => 1],
        ];
        if ($withChildren) {
            $items[] = ['_key' => 'new-2', 'parent_key' => 'new-1', 'product_id' => $product->id, 'part_number' => $product->code, 'description' => 'Child sensor', 'qty' => 2];
        }

        return [
            'name' => $root[1].' Template',
            'description' => 'Paket lengkap',
            'items' => $items,
        ];
    }

    /** @dataProvider modules */
    public function test_store_creates_template_with_hierarchy_and_recomputed_price(string $root, string $divisionName): void
    {
        $division = $this->createDivision($divisionName);
        $product = MasterProduct::create([
            'name' => 'E-514',
            'code' => 'E-514',
            'brand' => 'brand',
            'category' => 'pH',
            'division_id' => $division->id,
            'price' => 12500000,
            'status' => 'Active',
        ]);

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), $this->payload([$root, $divisionName], $product))
            ->assertOk()->assertJson(['success' => true]);

        $template = ConfigurationTemplate::latest('id')->firstOrFail();
        $this->assertSame($divisionName.' Template', $template->name);
        $this->assertSame($division->id, $template->division_id);
        $this->assertSame($this->admin->id, $template->created_by);

        $parent = $template->items()->whereNull('parent_id')->firstOrFail();
        $child = $template->items()->whereNotNull('parent_id')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame($child->product_id, $product->id);
        $this->assertEquals(12500000, $child->price);
        $this->assertEquals(12500000, $child->price_currency);
        $this->assertSame(2, $child->qty);
    }

    /** @dataProvider modules */
    public function test_store_rejects_unknown_product(string $root, string $divisionName): void
    {
        $this->createDivision($divisionName);

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => 'Template Rusak',
            'items' => [
                ['_key' => 'new-1', 'product_id' => 999999, 'description' => 'Produk tidak ada', 'qty' => 1],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, ConfigurationTemplate::count());
    }

    /** @dataProvider modules */
    public function test_store_validates_name_and_min_items(string $root, string $divisionName): void
    {
        $this->createDivision($divisionName);

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => '',
            'items' => [],
        ])->assertStatus(422);
    }

    /** @dataProvider modules */
    public function test_update_replaces_items(string $root, string $divisionName): void
    {
        $division = $this->createDivision($divisionName);

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => 'Template Awal',
            'items' => [['_key' => 'new-1', 'description' => 'Item lama', 'qty' => 1]],
        ])->assertOk();

        $template = ConfigurationTemplate::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->putJson(route($root.'.template-update', $template->id), [
            'name' => 'Template Baru',
            'items' => [
                ['_key' => 'new-1', 'description' => 'Item baru 1', 'qty' => 1],
                ['_key' => 'new-2', 'parent_key' => 'new-1', 'description' => 'Item baru 2', 'qty' => 3],
            ],
        ])->assertOk();

        $template->refresh();
        $this->assertSame('Template Baru', $template->name);
        $this->assertSame($division->id, $template->division_id);
        $this->assertSame(2, $template->items()->count());
        $this->assertSame('Item baru 1', $template->items()->whereNull('parent_id')->first()->description);
        $this->assertSame(3, $template->items()->whereNotNull('parent_id')->first()->qty);
    }

    /** @dataProvider modules */
    public function test_destroy_cascades_items(string $root, string $divisionName): void
    {
        $this->createDivision($divisionName);

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => 'Template Dihapus',
            'items' => [
                ['_key' => 'new-1', 'description' => 'Item 1', 'qty' => 1],
                ['_key' => 'new-2', 'parent_key' => 'new-1', 'description' => 'Item 2', 'qty' => 1],
            ],
        ])->assertOk();

        $template = ConfigurationTemplate::latest('id')->firstOrFail();
        $this->assertSame(2, ConfigurationTemplateItem::where('template_id', $template->id)->count());

        $this->actingAs($this->admin)->deleteJson(route($root.'.template-destroy', $template->id))->assertOk();

        $this->assertDatabaseMissing('configuration_templates', ['id' => $template->id]);
        $this->assertSame(0, ConfigurationTemplateItem::where('template_id', $template->id)->count());
    }

    /** @dataProvider modules */
    public function test_create_page_lists_only_own_division_templates(string $root, string $divisionName): void
    {
        $this->createDivision($divisionName);
        $other = $this->createDivision($divisionName.'-Other');

        // Template milik divisi ini.
        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => 'Tpl '.$divisionName,
            'items' => [['_key' => 'new-1', 'description' => 'Item 1', 'qty' => 1]],
        ])->assertOk();

        $this->actingAs($this->admin)->get(route($root.'.create'))
            ->assertOk()
            ->assertSee('Tpl '.$divisionName);

        // Template divisi lain (butuh modul lain) tidak tampil di page ini.
        $otherRoot = $root === 'water-configuration' ? 'enviro-configuration' : 'water-configuration';
        $this->actingAs($this->admin)->postJson(route($otherRoot.'.template-store'), [
            'name' => 'Tpl Lain '.$divisionName,
            'items' => [['_key' => 'new-1', 'description' => 'Item 1', 'qty' => 1]],
        ])->assertOk();

        $this->actingAs($this->admin)->get(route($root.'.create'))
            ->assertOk()
            ->assertDontSee('Tpl Lain '.$divisionName);
    }

    /** @dataProvider modules */
    public function test_fetch_template_returns_parent_and_children_scoped(string $root, string $divisionName): void
    {
        $division = $this->createDivision($divisionName);

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => 'Template pH',
            'items' => [
                ['_key' => 'new-1', 'description' => 'Parent pH', 'qty' => 1],
                ['_key' => 'new-2', 'parent_key' => 'new-1', 'description' => 'Child sensor A', 'qty' => 1],
                ['_key' => 'new-3', 'parent_key' => 'new-1', 'description' => 'Child sensor B', 'qty' => 1],
                ['_key' => 'new-4', 'description' => 'Parent NH3', 'qty' => 1],
            ],
        ])->assertOk();

        $template = ConfigurationTemplate::latest('id')->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->getJson(route($root.'.fetch-template', $template->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $items = $response->json('items');
        $this->assertCount(4, $items);
        $this->assertSame('Parent pH', $items[0]['description']);
        $this->assertSame('Child sensor A', $items[1]['description']);
        $this->assertSame('Child sensor B', $items[2]['description']);
        $this->assertSame('Parent NH3', $items[3]['description']);
        $this->assertNull($items[0]['parent_key']);
        $this->assertSame($items[0]['_key'], $items[1]['parent_key']);

        // Template divisi lain tidak bisa di-fetch lewat route divisi ini.
        $this->createDivision('Outside');
        $this->actingAs($this->admin)
            ->getJson(route($root.'.fetch-template', $template->id + 1000))
            ->assertNotFound();
    }

    /** @dataProvider modules */
    public function test_store_preserves_bold_italic_and_inline_style_formatting(string $root, string $divisionName): void
    {
        $this->createDivision($divisionName);

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => 'Format Template',
            'items' => [
                [
                    '_key' => 'new-1',
                    'description' => '<b>bold</b><i>italic</i><span style="font-weight: bold">span bold</span><script>alert(1)</script>',
                    'qty' => 1,
                    'category' => 'pH',
                ],
            ],
        ])->assertOk();

        $template = ConfigurationTemplate::latest('id')->firstOrFail();
        $this->assertSame(
            '<b>bold</b><i>italic</i><b>span bold</b>',
            $template->items()->first()->description
        );
    }

    /** @dataProvider modules */
    public function test_template_form_and_show_pages_render(string $root, string $divisionName): void
    {
        $this->createDivision($divisionName);

        $this->actingAs($this->admin)->get(route($root.'.template-create'))
            ->assertOk()
            ->assertSee('Buat Template');

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => 'Template Show',
            'description' => 'Deskripsi <b>bold</b>',
            'items' => [
                ['_key' => 'new-1', 'category' => 'pH', 'item_no' => '1', 'description' => 'Parent pH', 'qty' => 1],
                ['_key' => 'new-2', 'parent_key' => 'new-1', 'item_no' => '1.1', 'description' => 'Child sensor <i>A</i>', 'qty' => 2],
            ],
        ])->assertOk();

        $template = ConfigurationTemplate::latest('id')->firstOrFail();

        $this->actingAs($this->admin)->get(route($root.'.template-show', $template->id))
            ->assertOk()
            ->assertSee('Template Show')
            ->assertSee('1.1')
            ->assertSee('<i>', false);

        $this->actingAs($this->admin)->get(route($root.'.template-edit', $template->id))
            ->assertOk()
            ->assertSee('Edit Template')
            ->assertSee('Parent');
    }

    /** @dataProvider modules */
    public function test_template_data_returns_rows_scoped(string $root, string $divisionName): void
    {
        $this->createDivision($divisionName);

        $this->actingAs($this->admin)->postJson(route($root.'.template-store'), [
            'name' => 'Data Template',
            'items' => [['_key' => 'new-1', 'description' => 'Item 1', 'qty' => 1]],
        ])->assertOk();

        $response = $this->actingAs($this->admin)
            ->getJson(route($root.'.template-data', ['draw' => 1, 'length' => 10]))
            ->assertOk();

        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertSame('Data Template', $response->json('data.0.name'));
    }

    public function test_ims_template_store_keeps_client_price_and_unit(): void
    {
        $this->createDivision('IMS');

        $templateId = $this->actingAs($this->admin)
            ->postJson(route('ims-configuration.template-store'), [
                'name' => 'IMS Template Harga',
                'items' => [
                    ['_key' => 'new-1', 'category' => 'pH', 'description' => 'Parent', 'qty' => 1],
                    ['_key' => 'new-2', 'parent_key' => 'new-1', 'description' => 'Child sensor', 'qty' => 2, 'price' => 50000, 'unit' => 'pcs'],
                ],
            ])->assertOk()
            ->json('id');

        $template = ConfigurationTemplate::findOrFail($templateId);
        $child = $template->items()->whereNotNull('parent_id')->firstOrFail();
        $this->assertEquals(50000, $child->price);
        $this->assertEquals(50000, $child->price_currency);
        $this->assertSame('IDR', $child->currency);
        $this->assertSame('pcs', $child->unit);

        // fetch-template mengembalikan price + unit agar editor bisa prefill.
        $items = $this->actingAs($this->admin)
            ->getJson(route('ims-configuration.fetch-template', $templateId))
            ->assertOk()
            ->json('items');

        $childPayload = collect($items)->firstWhere('parent_key', $items[0]['_key']);
        $this->assertEquals(50000, $childPayload['price']);
        $this->assertSame('pcs', $childPayload['unit']);
    }

    public function test_ims_template_form_shows_unit_and_price_columns(): void
    {
        $this->createDivision('IMS');

        $this->actingAs($this->admin)
            ->get(route('ims-configuration.template-create'))
            ->assertOk()
            ->assertSee('Unit')
            ->assertSee('Harga');
    }
}

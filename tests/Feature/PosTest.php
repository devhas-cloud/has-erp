<?php

namespace Tests\Feature;

use App\Models\AccountCompany;
use App\Models\Division;
use App\Models\Module;
use App\Models\Opportunity;
use App\Models\Pos;
use App\Models\PosItem;
use App\Models\ProfitEstimate;
use App\Models\Quotation;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POS (Point of Sale = Sales Order): dokumen penjualan datar berbasis quotation
 * (wajib sudah ada estimasi PL) -> draft -> submit -> approve/reject. Item =
 * penamaan customer. Syarat DP mengikuti quotation.requires_dp (snapshot).
 * Project dibuat dari quotation ber-POS lewat modul Project.
 */
class PosTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $approver;

    private AccountCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $division = Division::create(['division_name' => 'Sales', 'description' => 'Sales', 'type' => 'Internal', 'status' => 'Active']);
        $make = fn (string $name) => User::create(['username' => $name, 'email' => $name.'@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);
        $this->creator = $make('sales');
        $this->approver = $make('manager');

        $module = Module::create(['module_code' => 'MOD_POS', 'module_name' => 'POS', 'route_name' => 'pos', 'icon' => 'fa fa-cash-register', 'group' => 'Admin']);
        foreach ([$this->creator, $this->approver] as $user) {
            UserAccessControl::create([
                'user_id' => $user->id, 'module_id' => $module->id,
                'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true,
                'can_approve' => $user->is($this->approver),
            ]);
        }

        $this->company = AccountCompany::create(['account_name' => 'PT Pelanggan', 'status' => 'Active']);
    }

    private function makeQuotation(bool $requiresDp = false, string $number = '001/HAS/QT-AB/X/2026'): Quotation
    {
        $opportunity = Opportunity::create([
            'opportunity_name' => 'SPARING Gresik',
            'account_companies_id' => $this->company->id,
            'owner_id' => $this->creator->id,
            'probability' => 70,
        ]);

        $quotation = Quotation::create([
            'opportunity_id' => $opportunity->id,
            'to_name' => $this->company->account_name,
            'quotation_number' => $number,
            'status' => Quotation::STATUS_APPROVED,
            'requires_dp' => $requiresDp,
            'grand_total' => 1000000,
            'created_by' => $this->creator->id,
        ]);

        ProfitEstimate::create([
            'quotation_id' => $quotation->id,
            'opportunity_id' => $opportunity->id,
            'real_project_value' => 900000,
            'created_by' => $this->creator->id,
        ]);

        return $quotation;
    }

    private function store(Quotation $quotation, array $items, array $extra = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->creator)->postJson(route('pos.store'), array_merge([
            'date' => '2026-11-20',
            'quotation_id' => $quotation->id,
            'ppn_percent' => 11,
            'items' => $items,
        ], $extra));
    }

    public function test_full_flow_no_dp(): void
    {
        $quotation = $this->makeQuotation(false);

        // POS wajib berbasis quotation: tanpa quotation_id -> validasi gagal.
        $this->actingAs($this->creator)->postJson(route('pos.store'), [
            'date' => '2026-11-20', 'ppn_percent' => 11,
            'items' => [['description' => 'X', 'qty' => 1, 'unit' => 'Unit', 'unit_price' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('quotation_id');

        // Simpan = Draft; customer & requires_dp ikut quotation; subtotal/PPN dihitung dari item.
        $res = $this->store($quotation, [
            ['description' => 'Panel Kontrol A', 'qty' => 2, 'unit' => 'Unit', 'unit_price' => 100],
            ['description' => 'Kabel NYY 4x16', 'qty' => 3, 'unit' => 'Meter', 'unit_price' => 50],
        ])->assertOk()->json('id');

        $pos = Pos::with('items')->findOrFail($res);
        $this->assertSame('POS-26110001', $pos->pos_number);
        $this->assertSame(Pos::STATUS_DRAFT, $pos->status);
        $this->assertSame($quotation->id, $pos->quotation_id);
        $this->assertSame($this->company->id, $pos->company_id);
        $this->assertFalse($pos->requires_dp);
        $this->assertSame(2, $pos->items->count());
        $this->assertSame('Panel Kontrol A', $pos->items->first()->description);
        $this->assertEqualsWithDelta(350, $pos->subtotal, 0.01);
        $this->assertEqualsWithDelta(38.5, $pos->ppn_amount, 0.01);
        $this->assertEqualsWithDelta(388.5, $pos->grand_total, 0.01);

        // Halaman edit draft (form berisi item tersimpan).
        $this->actingAs($this->creator)->get(route('pos.edit', $pos->id))->assertOk()->assertSee('Panel Kontrol A');

        // Ajukan -> Waiting Approval; tanpa can_approve pembuat tidak bisa menyetujui.
        $this->actingAs($this->creator)->postJson(route('pos.submit', $pos->id))->assertOk();
        $this->assertTrue($pos->fresh()->isWaitingApproval());
        $this->actingAs($this->creator)->postJson(route('pos.approve', $pos->id))->assertForbidden();
        $this->actingAs($this->approver)->postJson(route('pos.approve', $pos->id))->assertOk();
        $this->assertTrue($pos->fresh()->isApproved());

        // Setelah disetujui, tersedia tombol "Buat Project" (link ke form project).
        $this->actingAs($this->creator)->get(route('pos.show', $pos->id))
            ->assertOk()
            ->assertSee('Buat Project');
    }

    public function test_pos_requires_profit_estimate(): void
    {
        $opportunity = Opportunity::create([
            'opportunity_name' => 'Tanpa PL',
            'account_companies_id' => $this->company->id,
            'owner_id' => $this->creator->id,
            'probability' => 70,
        ]);
        $quotation = Quotation::create([
            'opportunity_id' => $opportunity->id,
            'to_name' => $this->company->account_name,
            'quotation_number' => '009/HAS/QT-AB/X/2026',
            'status' => Quotation::STATUS_APPROVED,
            'grand_total' => 1000000,
            'created_by' => $this->creator->id,
        ]);

        $this->store($quotation, [
            ['description' => 'Panel', 'qty' => 1, 'unit' => 'Unit', 'unit_price' => 100],
        ])->assertStatus(422)->assertJsonFragment([
            'message' => 'Estimasi PL belum dibuat untuk quotation ini. Buat Profit Estimate terlebih dahulu.',
        ]);
    }

    public function test_reject_edit_resubmit_and_requires_dp_snapshot(): void
    {
        $quotation = $this->makeQuotation(false, '002/HAS/QT-AB/X/2026');
        $id = $this->store($quotation, [
            ['description' => 'Panel A', 'qty' => 1, 'unit' => 'Unit', 'unit_price' => 100],
        ])->assertOk()->json('id');

        // Ajukan dulu sebelum tolak.
        $this->actingAs($this->creator)->postJson(route('pos.submit', $id))->assertOk();

        // Tolak butuh can_approve; alasan wajib.
        $this->actingAs($this->creator)->postJson(route('pos.reject', $id), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->approver)->postJson(route('pos.reject', $id), [])->assertStatus(422);
        $this->actingAs($this->approver)->postJson(route('pos.reject', $id), ['reason' => 'deskripsi salah'])->assertOk();
        $this->assertSame(Pos::STATUS_REJECTED, Pos::find($id)->status);

        // Perbaiki -> otomatis kembali Draft, bisa diajukan ulang.
        $this->actingAs($this->creator)->putJson(route('pos.update', $id), [
            'date' => '2026-11-20', 'quotation_id' => $quotation->id,
            'items' => [['description' => 'Panel B', 'qty' => 2, 'unit' => 'Unit', 'unit_price' => 150]],
        ])->assertOk();
        $pos = Pos::with('items')->find($id);
        $this->assertSame(Pos::STATUS_DRAFT, $pos->status);
        $this->assertNull($pos->reject_reason);
        $this->assertSame('Panel B', $pos->items->first()->description);
        $this->assertEquals(2, $pos->items->first()->qty);

        // Quotation ber-requires_dp -> POS snapshot requires_dp = true (badge "Perlu DP").
        $quotationDp = $this->makeQuotation(true, '003/HAS/QT-AB/X/2026');
        $dpId = $this->store($quotationDp, [
            ['description' => 'Panel DP', 'qty' => 1, 'unit' => 'Unit', 'unit_price' => 100],
        ])->assertOk()->json('id');
        $this->assertTrue(Pos::find($dpId)->requires_dp);
        $this->actingAs($this->creator)->postJson(route('pos.submit', $dpId))->assertOk();
        $this->actingAs($this->approver)->postJson(route('pos.approve', $dpId))->assertOk();

        // Halaman.
        foreach (['index', 'create'] as $page) {
            $this->actingAs($this->creator)->get(route('pos.'.$page))->assertOk();
        }
        $this->actingAs($this->creator)->getJson(route('pos.data'))->assertOk();
        $this->actingAs($this->creator)->get(route('pos.show', $pos->id))->assertOk()->assertSee('Panel B');

        // Hapus hanya Draft/Rejected.
        $this->actingAs($this->creator)->deleteJson(route('pos.destroy', $id))->assertOk();
        $this->assertNull(Pos::find($id));
        $this->actingAs($this->creator)->deleteJson(route('pos.destroy', $dpId))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\DirectPurchase;
use App\Models\Division;
use App\Models\Document;
use App\Models\GoodsReceipt;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\UserAccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Arsip dokumen pembelian (opsional) di PO, Direct Purchase, dan Penerimaan
 * Barang; approve PO hanya dari halaman detail.
 */
class PurchaseDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Document::DISK);

        $division = Division::create(['division_name' => 'Water', 'description' => 'Water', 'type' => 'Internal', 'status' => 'Active']);
        $this->admin = User::create(['username' => 'superadmin', 'email' => 'superadmin@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'Admin']);
        $this->staff = User::create(['username' => 'staff', 'email' => 'staff@has.com', 'password' => bcrypt('secret'), 'division_id' => $division->id, 'role' => 'User']);

        foreach (['purchase-order' => 'MOD_PURCHASE_ORDER', 'goods-receipt' => 'MOD_GOODS_RECEIPT', 'direct-purchase' => 'MOD_DIRECT_PURCHASE'] as $route => $code) {
            $module = Module::create(['module_code' => $code, 'module_name' => $code, 'route_name' => $route, 'icon' => 'fa fa-box', 'group' => 'Purchasing']);
            UserAccessControl::create(['user_id' => $this->staff->id, 'module_id' => $module->id, 'can_create' => true, 'can_read' => true, 'can_update' => true, 'can_delete' => true, 'can_approve' => false]);
        }
    }

    private function upload(string $route, int $id, string $type, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->staff)->post(route($route.'.documents.store', $id), [
            'type' => $type,
            'files' => [UploadedFile::fake()->create('penawaran.pdf', 120, 'application/pdf')],
            'notes' => 'Rev 1',
        ], ['Accept' => 'application/json']);
    }

    public function test_po_documents_are_optional_and_locked_after_decision(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->staff->id]);

        // Jenis yang tidak berlaku untuk PO ditolak.
        $this->upload('purchase-order', $po->id, Document::TYPE_DELIVERY_NOTE)->assertStatus(422)->assertJsonValidationErrors('type');

        $docs = $this->upload('purchase-order', $po->id, Document::TYPE_SUPPLIER_QUOTE)
            ->assertOk()
            ->assertJsonPath('documents.0.type_label', 'Penawaran Supplier')
            ->json('documents');
        $this->assertNotNull($docs[0]['delete_url']);

        $document = Document::firstOrFail();
        Storage::disk(Document::DISK)->assertExists($document->file_path);
        $this->actingAs($this->staff)->get(route('purchase-order.documents.show', [$po->id, $document->id]))->assertOk();
        $this->actingAs($this->staff)->get(route('purchase-order.show', $po->id))->assertOk()->assertSee('Dokumen Pembelian')->assertSee('penawaran.pdf');

        // Setelah approved: hanya bisa ditambah, tidak bisa dihapus.
        $po->update(['status' => PurchaseOrder::STATUS_APPROVED]);
        $this->actingAs($this->staff)->deleteJson(route('purchase-order.documents.destroy', [$po->id, $document->id]))->assertStatus(422);
        $this->upload('purchase-order', $po->id, Document::TYPE_SUPPLIER_CONFIRMATION)
            ->assertOk()
            ->assertJsonCount(2, 'documents')
            ->assertJsonPath('documents.1.delete_url', null);

        // Dokumen PO lain tidak bisa dibuka lewat PO ini.
        $other = PurchaseOrder::create(['supplier_name' => 'PT Lain', 'status' => PurchaseOrder::STATUS_DRAFT, 'created_by' => $this->staff->id]);
        $this->actingAs($this->staff)->get(route('purchase-order.documents.show', [$other->id, $document->id]))->assertNotFound();
    }

    public function test_draft_po_document_can_be_deleted(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_WAITING_APPROVAL, 'created_by' => $this->staff->id]);
        $this->upload('purchase-order', $po->id, Document::TYPE_OTHER)->assertOk();

        $document = Document::firstOrFail();
        $this->actingAs($this->staff)->deleteJson(route('purchase-order.documents.destroy', [$po->id, $document->id]))
            ->assertOk()->assertJsonCount(0, 'documents');
        Storage::disk(Document::DISK)->assertMissing($document->file_path);
    }

    public function test_approve_button_only_on_detail_page(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_WAITING_APPROVAL, 'created_by' => $this->staff->id]);

        $this->actingAs($this->admin)->get(route('purchase-order.index'))->assertOk()->assertDontSee('approvePo')->assertDontSee('purchase-order/__ID__/approve');
        $this->actingAs($this->admin)->get(route('purchase-order.show', $po->id))->assertOk()->assertSee('poApprove()', false);
    }

    public function test_goods_receipt_document_deletable_only_by_uploader_or_admin(): void
    {
        $po = PurchaseOrder::create(['supplier_name' => 'PT Supplier', 'status' => PurchaseOrder::STATUS_APPROVED, 'created_by' => $this->admin->id]);
        $receipt = GoodsReceipt::create(['receipt_number' => '26110001', 'purchase_order_id' => $po->id, 'status' => GoodsReceipt::STATUS_POSTED, 'received_date' => '2026-11-02', 'created_by' => $this->admin->id]);

        $this->upload('goods-receipt', $receipt->id, Document::TYPE_SUPPLIER_QUOTE)->assertStatus(422);
        $this->upload('goods-receipt', $receipt->id, Document::TYPE_DELIVERY_NOTE, $this->admin)->assertOk();
        $document = Document::firstOrFail();

        $this->actingAs($this->staff)->deleteJson(route('goods-receipt.documents.destroy', [$receipt->id, $document->id]))->assertStatus(422);
        $this->actingAs($this->staff)->get(route('goods-receipt.show', $receipt->id))->assertOk()->assertSee('Surat Jalan Supplier', false);
        $this->actingAs($this->admin)->deleteJson(route('goods-receipt.documents.destroy', [$receipt->id, $document->id]))->assertOk();
    }

    public function test_direct_purchase_documents(): void
    {
        $dp = DirectPurchase::create([
            'supplier_name' => 'Toko Jaya',
            'purpose' => DirectPurchase::PURPOSE_STOCK,
            'status' => DirectPurchase::STATUS_DRAFT,
            'created_by' => $this->staff->id,
        ]);

        $this->upload('direct-purchase', $dp->id, Document::TYPE_SUPPLIER_INVOICE)->assertOk()->assertJsonPath('documents.0.type_label', 'Invoice / Nota Supplier');
        $this->actingAs($this->staff)->get(route('direct-purchase.show', $dp->id))->assertOk()->assertSee('Dokumen Pembelian');
    }

    public function test_direct_purchase_catalog_search_is_paginated_with_filters(): void
    {
        $division = Division::first();
        foreach (range(1, 7) as $i) {
            MasterProduct::create(['code' => 'CAT-'.$i, 'name' => 'Sensor '.$i, 'category' => $i <= 3 ? 'Sensor' : 'Kabel', 'division_id' => $division->id, 'status' => 'Active', 'purchase_price' => 1000 * $i]);
        }

        $this->actingAs($this->staff)->getJson(route('direct-purchase.search-products', ['per_page' => 5, 'with_filters' => 1]))
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 7)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('filters.categories', ['Kabel', 'Sensor'])
            ->assertJsonPath('filters.divisions.0.name', 'Water');

        $this->actingAs($this->staff)->getJson(route('direct-purchase.search-products', ['category' => 'Sensor', 'q' => 'CAT-2']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.price', 2000)
            ->assertJsonPath('data.0.division_name', 'Water');

        $this->actingAs($this->staff)->get(route('direct-purchase.create'))->assertOk()->assertSee('dpProductPickerModal', false)->assertSee('Tambah dari Katalog');
    }
}

<?php

namespace Tests\Feature;

use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\DirectPurchase;
use App\Models\GoodsReceipt;
use App\Models\GoodsRequest;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\StockEntry;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\SupplierRefund;
use App\Models\SupplierReturn;
use App\Support\NumberSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nomor dokumen: PREFIX-YYMMNNNN, prefix bahasa Inggris dari config/document_numbers.php,
 * urut per jenis dokumen dan reset tiap tahun.
 */
class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_document_type_uses_its_own_prefix(): void
    {
        $this->travelTo('2026-11-04 09:00:00');

        $expected = [
            'PRJ-26110001' => (new Project)->generateProjectNumber(),
            'PR-26110001' => GoodsRequest::generateNumber(),
            'PO-26110001' => PurchaseOrder::generatePoNumber(PurchaseOrder::TYPE_MANUFACTURE),
            'PO-26110001-L' => PurchaseOrder::generatePoNumber(PurchaseOrder::TYPE_LOCAL),
            'DPO-26110001' => DirectPurchase::generateNumber(),
            'RTV-26110001' => SupplierReturn::generateNumber(),
            'GRN-26110001' => GoodsReceipt::generateNumber(),
            'DO-26110001' => DeliveryOrder::generateNumber(),
            'ST-26110001' => StockTransfer::generateNumber(),
            'SE-26110001' => StockEntry::generateNumber(),
            'SC-26110001' => StockOpname::generateNumber(),
            'INV-26110001' => Invoice::generateInvoiceNumber(),
            'RV-26110001' => CustomerReceipt::generateNumber(),
            'BILL-26110001' => SupplierBill::generateNumber(),
            'PV-26110001' => SupplierPayment::generateNumber(),
            'RFD-26110001' => SupplierRefund::generateNumber(),
        ];

        foreach ($expected as $number => $generated) {
            $this->assertSame($number, $generated);
        }

        // Semua prefix unik.
        $prefixes = array_values(config('document_numbers'));
        $this->assertSame(count($prefixes), count(array_unique($prefixes)));
    }

    public function test_sequence_counts_only_numbers_with_its_own_prefix_and_resets_yearly(): void
    {
        $supplier = Supplier::create(['name' => 'PT Alpha', 'status' => 'Active']);
        foreach (['PO-26110007', 'PO-26110008-L', 'PO-26110005REV-C', '26119999'] as $number) {
            PurchaseOrder::create(['supplier_id' => $supplier->id, 'supplier_name' => 'PT Alpha', 'po_number' => $number, 'status' => PurchaseOrder::STATUS_DRAFT]);
        }

        // Nomor tanpa prefix & nomor revisi tidak memajukan urutan; suffix jenis ikut dihitung.
        $this->assertSame('PO-26110009-C', PurchaseOrder::generatePoNumber(PurchaseOrder::TYPE_CALIBRATION, '2026-11-20'));
        $this->assertSame('PO-27010001', PurchaseOrder::generatePoNumber(PurchaseOrder::TYPE_MANUFACTURE, '2027-01-02'));
        $this->assertSame('PO-26110005REV2-L', PurchaseOrder::revisionNumber('PO-26110005REV-C', PurchaseOrder::TYPE_LOCAL));
        $this->assertSame('PO-', NumberSequence::prefix('purchase_orders'));
        $this->assertSame('', NumberSequence::prefix('tabel_tanpa_prefix'));
    }

    public function test_source_labels_do_not_repeat_the_prefix(): void
    {
        $po = new PurchaseOrder(['po_number' => 'PO-26110001']);
        $dp = new DirectPurchase(['dp_number' => 'DPO-26110001']);

        $poBill = (new SupplierBill(['purchase_order_id' => 1]))->setRelation('purchaseOrder', $po);
        $dpBill = (new SupplierBill(['direct_purchase_id' => 1]))->setRelation('directPurchase', $dp);
        $receipt = (new GoodsReceipt(['direct_purchase_id' => 1]))->setRelation('directPurchase', $dp);

        $this->assertSame('PO-26110001', $poBill->sourceNumber());
        $this->assertSame('DPO-26110001', $dpBill->sourceNumber());
        $this->assertSame('DPO-26110001', $receipt->sourceNumber());
    }
}

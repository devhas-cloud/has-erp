<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nomor dokumen lama (YYMMNNNN) diberi prefix sesuai config/document_numbers.php,
 * mis. 26110001 -> PO-26110001, 26110002-L -> PO-26110002-L. Nomor yang sudah
 * ber-prefix dilewati, jadi aman dijalankan ulang.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'projects' => 'project_number',
        'goods_requests' => 'request_number',
        'purchase_orders' => 'po_number',
        'direct_purchases' => 'dp_number',
        'supplier_returns' => 'return_number',
        'goods_receipts' => 'receipt_number',
        'delivery_orders' => 'do_number',
        'stock_transfers' => 'transfer_number',
        'stock_entries' => 'entry_number',
        'stock_opnames' => 'opname_number',
        'invoices' => 'invoice_number',
        'customer_receipts' => 'receipt_number',
        'supplier_bills' => 'bill_number',
        'supplier_payments' => 'payment_number',
        'supplier_refunds' => 'refund_number',
    ];

    public function up(): void
    {
        $this->rewrite(fn (string $prefix, string $number) => preg_match('/^\d{8}/', $number) ? $prefix.$number : null);

        // Tagihan otomatis Direct Purchase memakai "DP <nomor>" sebagai no. invoice supplier.
        DB::table('supplier_bills')->whereNotNull('direct_purchase_id')->where('supplier_invoice_number', 'like', 'DP %')
            ->get(['id', 'supplier_invoice_number'])
            ->each(fn ($bill) => DB::table('supplier_bills')->where('id', $bill->id)->update([
                'supplier_invoice_number' => config('document_numbers.direct_purchases').'-'.substr($bill->supplier_invoice_number, 3),
            ]));
    }

    public function down(): void
    {
        $this->rewrite(fn (string $prefix, string $number) => str_starts_with($number, $prefix) ? substr($number, strlen($prefix)) : null);
    }

    private function rewrite(callable $map): void
    {
        foreach (self::COLUMNS as $table => $column) {
            $prefix = config('document_numbers.'.$table).'-';

            DB::table($table)->whereNotNull($column)->get(['id', $column])->each(function ($row) use ($table, $column, $prefix, $map) {
                $new = $map($prefix, (string) $row->{$column});
                if ($new !== null) {
                    DB::table($table)->where('id', $row->id)->update([$column => $new]);
                }
            });
        }
    }
};

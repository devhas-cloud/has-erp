<?php

/*
|--------------------------------------------------------------------------
| Prefix nomor dokumen
|--------------------------------------------------------------------------
| Format semua dokumen transaksi: PREFIX-YYMMNNNN (YYMM = tanggal dokumen,
| NNNN urut per jenis dokumen, reset tiap tahun). Prefix = singkatan nama
| dokumen standar dalam bahasa Inggris, unik, disimpan di nomor dokumen.
| Dipakai App\Support\NumberSequence (kunci = nama tabel).
|
| Tidak memakai pola ini: Quotation (format resmi perusahaan), dokumen HR,
| dan nomor milik pihak lain (invoice supplier, faktur pajak, PO customer).
*/

return [
    // Admin
    'projects' => 'PRJ',

    // Purchasing
    'goods_requests' => 'PR',      // Purchase Requisition (Permintaan Barang)
    'purchase_orders' => 'PO',     // Purchase Order (+ suffix -L / -C, revisi REV)
    'direct_purchases' => 'DPO',   // Direct Purchase Order
    'supplier_returns' => 'RTV',   // Return to Vendor

    // Inventory
    'goods_receipts' => 'GRN',     // Goods Receipt Note
    'delivery_orders' => 'DO',     // Delivery Order
    'stock_transfers' => 'ST',     // Stock Transfer
    'stock_entries' => 'SE',       // Stock Entry (masuk stok lain-lain)
    'stock_opnames' => 'SC',       // Stock Count (stock opname)

    // Finance
    'invoices' => 'INV',           // Sales Invoice
    'customer_receipts' => 'RV',   // Receipt Voucher (penerimaan customer)
    'supplier_bills' => 'BILL',    // Supplier Bill (reguler & uang muka)
    'supplier_payments' => 'PV',   // Payment Voucher (pembayaran supplier)
    'supplier_refunds' => 'RFD',   // Supplier Refund
];

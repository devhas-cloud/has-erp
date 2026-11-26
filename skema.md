# Skema Purchasing, Inventory & Finance

Snapshot skema database `has_erp` di branch `feature/stock-ledger-simple` (18 Nov 2026, setelah skema stok baru stocks + stock_logs, branch, produksi, dan packing list), diambil dari `information_schema` (kolom, PK, UNIQUE, FK).

**Cara pakai:** isi kolom **Nama usulan** bila nama tabel/kolom tidak sesuai, dan alasan di **Catatan**. Biarkan kosong bila nama sudah benar. Jangan ubah kolom lain — itu kondisi database saat ini.

Keterangan: `PK` primary key · `UQ` unique · `NULL` boleh kosong · **Relasi** = foreign key ke `tabel.kolom`.

## Daftar isi

- [Purchasing](#purchasing) — `suppliers`, `goods_requests`, `goods_request_items`, `goods_request_approvals`, `purchase_orders`, `purchase_order_items`, `direct_purchases`, `direct_purchase_items`, `project_po_supplier_approvals`
- [Inventory](#inventory) — `branches`, `warehouses`, `goods_receipts`, `goods_receipt_items`, `stock_entries`, `stock_entry_items`, `stock_transfers`, `stock_transfer_items`, `branch_transfers`, `branch_transfer_items`, `stock_opnames`, `stock_opname_items`, `packing_lists`, `packing_list_boxes`, `packing_list_items`, `delivery_orders`, `delivery_order_items`, `delivery_order_item_stocks`, `supplier_returns`, `supplier_return_items`, `stocks`, `stock_logs`
- [Produksi](#produksi) — `material_requests`, `material_request_items`, `material_handovers`, `material_handover_items`, `productions`, `production_items`, `material_returns`, `material_return_items`
- [Finance AP (hutang supplier)](#finance-ap-hutang-supplier) — `supplier_bills`, `supplier_bill_items`, `supplier_payments`, `supplier_payment_allocations`, `supplier_refunds`
- [Finance AR (piutang customer)](#finance-ar-piutang-customer) — `projects`, `project_billing_terms`, `invoices`, `invoice_items`, `customer_receipts`, `profit_estimates`, `profit_estimate_lines`
- [Master & pendukung](#master--pendukung) — `master_products`, `currencies`, `offices`, `documents`

## Purchasing

| Tabel saat ini | Fungsi | Nama usulan | Catatan |
|---|---|---|---|
| `suppliers` | Master supplier |  |  |
| `goods_requests` | Purchase Request (PR) — header |  |  |
| `goods_request_items` | Purchase Request — item |  |  |
| `goods_request_approvals` | Approval PR per user |  |  |
| `purchase_orders` | Purchase Order (PO) — header |  |  |
| `purchase_order_items` | Purchase Order — item |  |  |
| `direct_purchases` | Direct Purchase (DPO) — header |  |  |
| `direct_purchase_items` | Direct Purchase — item |  |  |
| `project_po_supplier_approvals` | Approval project untuk PO Supplier |  |  |

### `suppliers`

Master supplier.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `name` | `varchar(200)` |  |  |  |  |  |
| `address` | `text` |  | ya |  |  |  |
| `phone` | `varchar(50)` |  | ya |  |  |  |
| `fax` | `varchar(50)` |  | ya |  |  |  |
| `email` | `varchar(150)` |  | ya |  |  |  |
| `attn_name` | `varchar(150)` |  | ya |  |  |  |
| `payment_term_days` | `smallint(5) unsigned` |  |  |  |  |  |
| `max_advance_percent` | `decimal(5,2)` |  |  |  |  |  |
| `npwp` | `varchar(30)` |  | ya |  |  |  |
| `is_pkp` | `tinyint(1)` |  |  |  |  |  |
| `default_pph_percent` | `decimal(5,2)` |  | ya |  |  |  |
| `bank_name` | `varchar(100)` |  | ya |  |  |  |
| `bank_account_no` | `varchar(50)` |  | ya |  |  |  |
| `bank_account_name` | `varchar(150)` |  | ya |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `goods_requests`

Purchase Request (PR) — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `request_number` | `varchar(50)` | UQ | ya |  |  |  |
| `purpose` | `varchar(20)` |  |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `division_id` | `bigint(20) unsigned` |  | ya | → `divisions.id` |  |  |
| `status` | `varchar(50)` |  |  |  |  |  |
| `notes` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `final_checked_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `approval_note` | `text` |  | ya |  |  |  |
| `approved_at` | `timestamp` |  | ya |  |  |  |
| `rejected_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `goods_request_items`

Purchase Request — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `goods_request_id` | `bigint(20) unsigned` |  |  | → `goods_requests.id` |  |  |
| `quote_configuration_item_id` | `bigint(20) unsigned` |  | ya | → `quote_configuration_items.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  | ya | → `master_products.id` |  |  |
| `part_number` | `varchar(100)` |  | ya |  |  |  |
| `description` | `text` |  | ya |  |  |  |
| `qty` | `int(11)` |  | ya |  |  |  |
| `closed_qty` | `int(11)` |  |  |  |  |  |
| `closed_reason` | `varchar(255)` |  | ya |  |  |  |
| `closed_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `closed_at` | `timestamp` |  | ya |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `currency` | `varchar(10)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `goods_request_approvals`

Approval PR per user.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `goods_request_id` | `bigint(20) unsigned` |  |  | → `goods_requests.id` |  |  |
| `user_id` | `bigint(20) unsigned` |  |  | → `users.id` |  |  |
| `approved_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `purchase_orders`

Purchase Order (PO) — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `supplier_name` | `varchar(200)` |  |  |  |  |  |
| `supplier_id` | `bigint(20) unsigned` |  | ya | → `suppliers.id` |  |  |
| `supplier_address` | `text` |  | ya |  |  |  |
| `supplier_phone` | `varchar(50)` |  | ya |  |  |  |
| `supplier_fax` | `varchar(50)` |  | ya |  |  |  |
| `supplier_attn` | `varchar(150)` |  | ya |  |  |  |
| `po_number` | `varchar(50)` | UQ | ya |  |  |  |
| `po_type` | `varchar(20)` |  |  |  |  |  |
| `revision_of_id` | `bigint(20) unsigned` |  | ya | → `purchase_orders.id` |  |  |
| `date` | `date` |  | ya |  |  |  |
| `terms` | `varchar(100)` |  | ya |  |  |  |
| `payment_term_days` | `smallint(5) unsigned` |  | ya |  |  |  |
| `delivery` | `varchar(150)` |  | ya |  |  |  |
| `your_ref` | `varchar(100)` |  | ya |  |  |  |
| `discount_percent` | `decimal(5,2)` |  | ya |  |  |  |
| `ppn_percent` | `decimal(5,2)` |  | ya |  |  |  |
| `status` | `varchar(50)` |  |  |  |  |  |
| `notes` | `text` |  | ya |  |  |  |
| `request_by_name` | `varchar(150)` |  | ya |  |  |  |
| `finance_name` | `varchar(150)` |  | ya |  |  |  |
| `accounting_name` | `varchar(150)` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `final_checked_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `approval_note` | `text` |  | ya |  |  |  |
| `approved_at` | `timestamp` |  | ya |  |  |  |
| `rejected_at` | `timestamp` |  | ya |  |  |  |
| `cancel_reason` | `text` |  | ya |  |  |  |
| `cancelled_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `cancelled_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `purchase_order_items`

Purchase Order — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `purchase_order_id` | `bigint(20) unsigned` |  |  | → `purchase_orders.id` |  |  |
| `goods_request_item_id` | `bigint(20) unsigned` |  | ya | → `goods_request_items.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  | ya | → `master_products.id` |  |  |
| `part_number` | `varchar(100)` |  | ya |  |  |  |
| `description` | `text` |  | ya |  |  |  |
| `qty` | `int(11)` |  | ya |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `price` | `decimal(15,2)` |  | ya |  |  |  |
| `currency` | `varchar(10)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `direct_purchases`

Direct Purchase (DPO) — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `dp_number` | `varchar(50)` | UQ | ya |  |  |  |
| `date` | `date` |  | ya |  |  |  |
| `payment_term_days` | `smallint(5) unsigned` |  | ya |  |  |  |
| `supplier_id` | `bigint(20) unsigned` |  | ya | → `suppliers.id` |  |  |
| `supplier_name` | `varchar(200)` |  |  |  |  |  |
| `supplier_phone` | `varchar(50)` |  | ya |  |  |  |
| `purpose` | `varchar(20)` |  |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `ppn_percent` | `decimal(5,2)` |  | ya |  |  |  |
| `status` | `varchar(50)` |  |  |  |  |  |
| `notes` | `text` |  | ya |  |  |  |
| `request_by_name` | `varchar(150)` |  | ya |  |  |  |
| `finance_name` | `varchar(150)` |  | ya |  |  |  |
| `accounting_name` | `varchar(150)` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `final_checked_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `approval_note` | `text` |  | ya |  |  |  |
| `approved_at` | `timestamp` |  | ya |  |  |  |
| `rejected_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `direct_purchase_items`

Direct Purchase — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `direct_purchase_id` | `bigint(20) unsigned` |  |  | → `direct_purchases.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  | ya | → `master_products.id` |  |  |
| `part_number` | `varchar(100)` |  | ya |  |  |  |
| `description` | `text` |  | ya |  |  |  |
| `qty` | `int(11)` |  | ya |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `currency` | `varchar(10)` |  | ya |  |  |  |
| `price` | `decimal(15,2)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `project_po_supplier_approvals`

Approval project untuk PO Supplier.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  |  | → `projects.id` |  |  |
| `user_id` | `bigint(20) unsigned` |  |  | → `users.id` |  |  |
| `approved_at` | `timestamp` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

## Inventory

| Tabel saat ini | Fungsi | Nama usulan | Catatan |
|---|---|---|---|
| `branches` | Master branch (kantor cabang) — dikelola di Configuration |  |  |
| `warehouses` | Master gudang (milik satu branch) |  |  |
| `goods_receipts` | Penerimaan barang (GRN) — header |  |  |
| `goods_receipt_items` | Penerimaan barang — item |  |  |
| `stock_entries` | Masuk stok lain-lain — header |  |  |
| `stock_entry_items` | Masuk stok lain-lain — item |  |  |
| `stock_transfers` | Transfer stok — header |  |  |
| `stock_transfer_items` | Transfer stok — item |  |  |
| `branch_transfers` | Branch Transfer (BT) — pengiriman antar branch + surat jalan |  |  |
| `branch_transfer_items` | Branch Transfer — barang dikirim, hasil pemeriksaan penerima |  |  |
| `stock_opnames` | Stock opname — header |  |  |
| `stock_opname_items` | Stock opname — item |  |  |
| `packing_lists` | Packing List (PKL) — barang project dikemas, siap kirim |  |  |
| `packing_list_boxes` | Packing List — koli (deskripsi, qty, dimensi, berat) |  |  |
| `packing_list_items` | Packing List — isi koli (baris stok) |  |  |
| `delivery_orders` | Delivery Order (DO) — header (jalur Langsung / Dengan Packing List) |  |  |
| `delivery_order_items` | Delivery Order — baris dokumen |  |  |
| `delivery_order_item_stocks` | Delivery Order Langsung — isi stok per baris |  |  |
| `supplier_returns` | Retur ke supplier — header |  |  |
| `supplier_return_items` | Retur ke supplier — item |  |  |
| `stocks` | Posisi stok per baris — sumber saldo (material per penerimaan, barang ber-SN per unit) |  |  |
| `stock_logs` | Riwayat perubahan baris stok (hanya ditambah) — kartu stok & biaya project |  |  |

### `branches`

Master branch (kantor cabang) — dikelola di Configuration.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `code` | `varchar(20)` | UQ |  |  |  |  |
| `name` | `varchar(150)` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `warehouses`

Master gudang (milik satu branch).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `branch_id` | `bigint(20) unsigned` |  | ya | → `branches.id` |  |  |
| `code` | `varchar(50)` | UQ |  |  |  |  |
| `name` | `varchar(150)` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `goods_receipts`

Penerimaan barang (GRN) — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `receipt_number` | `varchar(50)` | UQ | ya |  |  |  |
| `purchase_order_id` | `bigint(20) unsigned` |  | ya | → `purchase_orders.id` |  |  |
| `direct_purchase_id` | `bigint(20) unsigned` |  | ya | → `direct_purchases.id` |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  | ya | → `warehouses.id` |  |  |
| `received_date` | `date` |  | ya |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `goods_receipt_items`

Penerimaan barang — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `goods_receipt_id` | `bigint(20) unsigned` |  |  | → `goods_receipts.id` |  |  |
| `purchase_order_item_id` | `bigint(20) unsigned` |  | ya | → `purchase_order_items.id` |  |  |
| `direct_purchase_item_id` | `bigint(20) unsigned` |  | ya | → `direct_purchase_items.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  | ya | → `master_products.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `currency` | `varchar(10)` |  | ya |  |  |  |
| `price` | `decimal(15,2)` |  | ya |  |  |  |
| `destination` | `varchar(20)` |  |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `batch_number` | `varchar(100)` |  | ya |  |  |  |
| `expiry_date` | `date` |  | ya |  |  |  |
| `warranty_until` | `date` |  | ya |  |  |  |
| `serial_numbers` | `longtext` |  | ya |  |  |  |
| `note` | `varchar(255)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `stock_entries`

Masuk stok lain-lain — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `entry_number` | `varchar(50)` | UQ |  |  |  |  |
| `entry_date` | `date` |  |  |  |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `reason` | `varchar(30)` |  |  |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `stock_entry_items`

Masuk stok lain-lain — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `stock_entry_id` | `bigint(20) unsigned` |  |  | → `stock_entries.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  |  | → `master_products.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `unit_cost` | `decimal(18,4)` |  | ya |  |  |  |
| `batch_number` | `varchar(100)` |  | ya |  |  |  |
| `expiry_date` | `date` |  | ya |  |  |  |
| `supplier_id` | `bigint(20) unsigned` |  | ya | → `suppliers.id` |  |  |
| `warranty_until` | `date` |  | ya |  |  |  |
| `serial_numbers` | `longtext` |  | ya |  |  |  |
| `note` | `varchar(255)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `stock_transfers`

Transfer stok — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `transfer_number` | `varchar(50)` | UQ |  |  |  |  |
| `transfer_date` | `date` |  |  |  |  |  |
| `from_warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `from_project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `to_warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `to_project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `stock_transfer_items`

Transfer stok — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `stock_transfer_id` | `bigint(20) unsigned` |  |  | → `stock_transfers.id` |  |  |
| `stock_id` | `bigint(20) unsigned` |  |  | → `stocks.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `branch_transfers`

Branch Transfer (BT) — pengiriman antar branch + surat jalan.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `transfer_number` | `varchar(50)` | UQ |  |  |  |  |
| `transfer_date` | `date` |  |  |  |  |  |
| `from_branch_id` | `bigint(20) unsigned` |  |  | → `branches.id` |  |  |
| `from_warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `to_branch_id` | `bigint(20) unsigned` |  |  | → `branches.id` |  |  |
| `to_warehouse_id` | `bigint(20) unsigned` |  | ya | → `warehouses.id` |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `courier` | `varchar(150)` |  | ya |  |  |  |
| `vehicle_no` | `varchar(50)` |  | ya |  |  |  |
| `driver_name` | `varchar(150)` |  | ya |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `receive_note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `shipped_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `shipped_at` | `timestamp` |  | ya |  |  |  |
| `received_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `received_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `branch_transfer_items`

Branch Transfer — barang dikirim, hasil pemeriksaan penerima.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `branch_transfer_id` | `bigint(20) unsigned` |  |  | → `branch_transfers.id` |  |  |
| `stock_id` | `bigint(20) unsigned` |  |  | → `stocks.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `received_qty` | `decimal(15,4)` |  | ya |  |  |  |
| `condition_ok` | `tinyint(1)` |  | ya |  |  |  |
| `receive_note` | `varchar(255)` |  | ya |  |  |  |
| `checked_at` | `timestamp` |  | ya |  |  |  |
| `received_stock_id` | `bigint(20) unsigned` |  | ya | → `stocks.id` |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `stock_opnames`

Stock opname — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `opname_number` | `varchar(50)` | UQ |  |  |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `opname_date` | `date` |  |  |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `stock_opname_items`

Stock opname — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `stock_opname_id` | `bigint(20) unsigned` |  |  | → `stock_opnames.id` |  |  |
| `stock_id` | `bigint(20) unsigned` |  | ya | → `stocks.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  |  | → `master_products.id` |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `batch_number` | `varchar(100)` |  | ya |  |  |  |
| `serial_number` | `varchar(100)` |  | ya |  |  |  |
| `expiry_date` | `date` |  | ya |  |  |  |
| `system_qty` | `decimal(15,4)` |  |  |  |  |  |
| `counted_qty` | `decimal(15,4)` |  | ya |  |  |  |
| `counted_at` | `timestamp` |  | ya |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `unit_cost` | `decimal(18,4)` |  | ya |  |  |  |
| `remark` | `varchar(255)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `packing_lists`

Packing List (PKL) — barang project dikemas, siap kirim.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `packing_number` | `varchar(50)` | UQ |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  |  | → `projects.id` |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `packing_date` | `date` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `delivery_order_id` | `bigint(20) unsigned` |  | ya | → `delivery_orders.id` |  |  |
| `recipient_name` | `varchar(150)` |  |  |  |  |  |
| `recipient_address` | `text` |  | ya |  |  |  |
| `recipient_phone` | `varchar(100)` |  | ya |  |  |  |
| `recipient_attn` | `varchar(150)` |  | ya |  |  |  |
| `customer_ref` | `varchar(100)` |  | ya |  |  |  |
| `customer_ref_date` | `date` |  | ya |  |  |  |
| `marking` | `varchar(150)` |  | ya |  |  |  |
| `signer_name` | `varchar(150)` |  | ya |  |  |  |
| `signer_position` | `varchar(150)` |  | ya |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `ready_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `ready_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `packing_list_boxes`

Packing List — koli (deskripsi, qty, dimensi, berat).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `packing_list_id` | `bigint(20) unsigned` |  |  | → `packing_lists.id` |  |  |
| `quotation_item_id` | `bigint(20) unsigned` |  | ya | → `quotation_items.id` |  |  |
| `description` | `text` |  |  |  |  |  |
| `qty_order` | `decimal(15,4)` |  | ya |  |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `length_cm` | `decimal(10,2)` |  | ya |  |  |  |
| `width_cm` | `decimal(10,2)` |  | ya |  |  |  |
| `height_cm` | `decimal(10,2)` |  | ya |  |  |  |
| `weight_kg` | `decimal(10,2)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `packing_list_items`

Packing List — isi koli (baris stok).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `packing_list_id` | `bigint(20) unsigned` |  |  | → `packing_lists.id` |  |  |
| `packing_list_box_id` | `bigint(20) unsigned` |  |  | → `packing_list_boxes.id` |  |  |
| `stock_id` | `bigint(20) unsigned` |  |  | → `stocks.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `remark` | `varchar(255)` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `delivery_orders`

Delivery Order (DO) — header (jalur Langsung / Dengan Packing List).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `do_number` | `varchar(50)` | UQ |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  |  | → `projects.id` |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `uses_packing_list` | `tinyint(1)` |  |  |  |  |  |
| `delivery_date` | `date` |  |  |  |  |  |
| `recipient_name` | `varchar(150)` |  |  |  |  |  |
| `recipient_phone` | `varchar(50)` |  | ya |  |  |  |
| `recipient_attn` | `varchar(150)` |  | ya |  |  |  |
| `shipping_address` | `text` |  |  |  |  |  |
| `customer_ref` | `varchar(100)` |  | ya |  |  |  |
| `customer_ref_date` | `date` |  | ya |  |  |  |
| `courier` | `varchar(150)` |  | ya |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `checked_by_name` | `varchar(150)` |  | ya |  |  |  |
| `acknowledged_by_name` | `varchar(255)` |  | ya |  |  |  |
| `sent_by_name` | `varchar(150)` |  | ya |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `delivery_order_items`

Delivery Order — baris dokumen.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `delivery_order_id` | `bigint(20) unsigned` |  |  | → `delivery_orders.id` |  |  |
| `quotation_item_id` | `bigint(20) unsigned` |  | ya | → `quotation_items.id` |  |  |
| `description` | `text` |  |  |  |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `reference` | `varchar(255)` |  | ya |  |  |  |
| `remark` | `varchar(255)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `delivery_order_item_stocks`

Delivery Order Langsung — isi stok per baris.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `delivery_order_item_id` | `bigint(20) unsigned` |  |  | → `delivery_order_items.id` |  |  |
| `stock_id` | `bigint(20) unsigned` |  |  | → `stocks.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `supplier_returns`

Retur ke supplier — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `return_number` | `varchar(50)` | UQ |  |  |  |  |
| `supplier_id` | `bigint(20) unsigned` |  |  | → `suppliers.id` |  |  |
| `purchase_order_id` | `bigint(20) unsigned` |  |  | → `purchase_orders.id` |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  | ya | → `warehouses.id` |  |  |
| `return_date` | `date` |  |  |  |  |  |
| `reason` | `text` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `supplier_return_items`

Retur ke supplier — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `supplier_return_id` | `bigint(20) unsigned` |  |  | → `supplier_returns.id` |  |  |
| `purchase_order_item_id` | `bigint(20) unsigned` |  |  | → `purchase_order_items.id` |  |  |
| `stock_id` | `bigint(20) unsigned` |  | ya | → `stocks.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  | ya | → `master_products.id` |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `destination` | `varchar(20)` |  |  |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `stocks`

Posisi stok per baris — sumber saldo (material per penerimaan, barang ber-SN per unit).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `master_product_id` | `bigint(20) unsigned` |  |  | → `master_products.id` |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `is_material` | `tinyint(1)` |  |  |  |  |  |
| `batch_number` | `varchar(100)` |  | ya |  |  |  |
| `serial_number` | `varchar(100)` |  | ya |  |  |  |
| `received_date` | `date` |  |  |  |  |  |
| `expiry_date` | `date` |  | ya |  |  |  |
| `supplier_id` | `bigint(20) unsigned` |  | ya | → `suppliers.id` |  |  |
| `warranty_until` | `date` |  | ya |  |  |  |
| `qty_in` | `decimal(15,4)` |  |  |  |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `unit_cost` | `decimal(18,4)` |  |  |  |  |  |
| `is_open` | `tinyint(1)` |  | ya | generated: qty > 0 |  |  |
| `parent_id` | `bigint(20) unsigned` |  | ya | → `stocks.id` |  |  |
| `source_type` | `varchar(40)` |  | ya | polimorfik: goods_receipt_item, stock_entry_item, stock_opname_item, production |  |  |
| `source_id` | `bigint(20) unsigned` |  | ya | polimorfik → id item dokumen asal (tanpa FK) |  |  |
| `note` | `varchar(255)` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `stock_logs`

Riwayat perubahan baris stok (hanya ditambah) — kartu stok & biaya project.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `stock_id` | `bigint(20) unsigned` |  |  | → `stocks.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  |  | → `master_products.id` |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `is_material` | `tinyint(1)` |  |  |  |  |  |
| `log_date` | `date` |  |  |  |  |  |
| `type` | `varchar(20)` |  |  |  |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `qty_after` | `decimal(15,4)` |  |  |  |  |  |
| `unit_cost` | `decimal(18,4)` |  |  |  |  |  |
| `ref_type` | `varchar(40)` |  | ya | polimorfik: goods_receipt_item, stock_entry_item, stock_opname_item, stock_transfer_item, supplier_return_item, goods_request_item, branch_transfer_item, material_handover_item, production, packing_list_item, delivery_order_item_stock |  |  |
| `ref_id` | `bigint(20) unsigned` |  | ya | polimorfik → id item dokumen penyebab (tanpa FK) |  |  |
| `goods_request_item_id` | `bigint(20) unsigned` |  | ya | → `goods_request_items.id` |  |  |
| `note` | `varchar(255)` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  |  |  |  |  |

## Produksi

| Tabel saat ini | Fungsi | Nama usulan | Catatan |
|---|---|---|---|
| `material_requests` | Material Request (MR) — kebutuhan material project per branch, 1 approver |  |  |
| `material_request_items` | Material Request — material diminta |  |  |
| `material_handovers` | Serah Terima Material (MH) — gudang ke produksi, stok langsung keluar |  |  |
| `material_handover_items` | Serah Terima — baris stok diserahkan = Material di Produksi |  |  |
| `productions` | Produksi (MO) — barang jadi dari Material di Produksi |  |  |
| `production_items` | Produksi — material dipakai |  |  |
| `material_returns` | Retur Material Produksi (RMP) — pengembalian material sisa ke gudang asal sebagai stok bebas |  |  |
| `material_return_items` | Retur Material — baris material dikembalikan |  |  |

### `material_requests`

Material Request (MR) — kebutuhan material project per branch, 1 approver.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `request_number` | `varchar(50)` | UQ |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  |  | → `projects.id` |  |  |
| `branch_id` | `bigint(20) unsigned` |  |  | → `branches.id` |  |  |
| `request_date` | `date` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `approved_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `approved_at` | `timestamp` |  | ya |  |  |  |
| `rejected_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `rejected_at` | `timestamp` |  | ya |  |  |  |
| `reject_reason` | `varchar(255)` |  | ya |  |  |  |
| `closed_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `closed_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `material_request_items`

Material Request — material diminta.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `material_request_id` | `bigint(20) unsigned` |  |  | → `material_requests.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  |  | → `master_products.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `note` | `varchar(255)` |  | ya |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `material_handovers`

Serah Terima Material (MH) — gudang ke produksi, stok langsung keluar.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `handover_number` | `varchar(50)` | UQ |  |  |  |  |
| `material_request_id` | `bigint(20) unsigned` |  |  | → `material_requests.id` |  |  |
| `handover_date` | `date` |  |  |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `material_handover_items`

Serah Terima — baris stok diserahkan = Material di Produksi.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `material_handover_id` | `bigint(20) unsigned` |  |  | → `material_handovers.id` |  |  |
| `material_request_item_id` | `bigint(20) unsigned` |  |  | → `material_request_items.id` |  |  |
| `stock_id` | `bigint(20) unsigned` |  |  | → `stocks.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `productions`

Produksi (MO) — barang jadi dari Material di Produksi.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `production_number` | `varchar(50)` | UQ |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  |  | → `projects.id` |  |  |
| `output_product_id` | `bigint(20) unsigned` |  |  | → `master_products.id` |  |  |
| `output_qty` | `decimal(15,4)` |  |  |  |  |  |
| `output_warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `production_date` | `date` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `cancelled_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `cancelled_at` | `timestamp` |  | ya |  |  |  |
| `cancel_reason` | `varchar(255)` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `production_items`

Produksi — material dipakai.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `production_id` | `bigint(20) unsigned` |  |  | → `productions.id` |  |  |
| `material_handover_item_id` | `bigint(20) unsigned` |  |  | → `material_handover_items.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `material_returns`

Retur Material Produksi (RMP) — produksi mengajukan pengembalian material sisa (belum terpakai) ke gudang asal sebagai stok bebas. Disimpan = menunggu approval (stok belum bergerak); approver menyetujui → stok masuk, atau menolak.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `return_number` | `varchar(50)` | UQ |  |  |  |  |
| `branch_id` | `bigint(20) unsigned` |  |  | → `branches.id` |  |  |
| `return_date` | `date` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  | `waiting_approval` / `approved` / `rejected` |
| `note` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `approved_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `approved_at` | `timestamp` |  | ya |  |  |  |
| `rejected_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `rejected_at` | `timestamp` |  | ya |  |  |  |
| `reject_reason` | `varchar(255)` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `material_return_items`

Retur Material — baris material yang dikembalikan. Gudang asal (`warehouse_id`) dan harga (`unit_cost`) dibekukan saat serah terima.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `material_return_id` | `bigint(20) unsigned` |  |  | → `material_returns.id` |  |  |
| `material_handover_item_id` | `bigint(20) unsigned` |  |  | → `material_handover_items.id` |  |  |
| `stock_id` | `bigint(20) unsigned` |  |  | → `stocks.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  |  | → `master_products.id` |  |  |
| `warehouse_id` | `bigint(20) unsigned` |  |  | → `warehouses.id` |  |  |
| `project_id` | `bigint(20) unsigned` |  |  | → `projects.id` |  |  |
| `qty` | `decimal(15,4)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `unit_cost` | `decimal(15,4)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

## Finance AP (hutang supplier)

| Tabel saat ini | Fungsi | Nama usulan | Catatan |
|---|---|---|---|
| `supplier_bills` | Tagihan supplier (BILL) — header |  |  |
| `supplier_bill_items` | Tagihan supplier — item |  |  |
| `supplier_payments` | Pembayaran supplier (PV) |  |  |
| `supplier_payment_allocations` | Alokasi pembayaran ke tagihan (M:N) |  |  |
| `supplier_refunds` | Refund uang muka dari supplier |  |  |

### `supplier_bills`

Tagihan supplier (BILL) — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `bill_number` | `varchar(50)` | UQ |  |  |  |  |
| `bill_type` | `varchar(20)` |  |  |  |  |  |
| `supplier_id` | `bigint(20) unsigned` |  |  | → `suppliers.id` |  |  |
| `purchase_order_id` | `bigint(20) unsigned` |  | ya | → `purchase_orders.id` |  |  |
| `direct_purchase_id` | `bigint(20) unsigned` |  | ya | → `direct_purchases.id` |  |  |
| `supplier_invoice_number` | `varchar(100)` |  |  |  |  |  |
| `tax_invoice_number` | `varchar(100)` |  | ya |  |  |  |
| `bill_date` | `date` |  |  |  |  |  |
| `due_date` | `date` |  |  |  |  |  |
| `currency` | `varchar(10)` |  |  |  |  |  |
| `exchange_rate` | `decimal(18,6)` |  |  |  |  |  |
| `rate_estimated` | `tinyint(1)` |  |  |  |  |  |
| `subtotal` | `decimal(18,2)` |  |  |  |  |  |
| `discount_percent` | `decimal(5,2)` |  |  |  |  |  |
| `discount_amount` | `decimal(18,2)` |  |  |  |  |  |
| `other_charges` | `decimal(18,2)` |  |  |  |  |  |
| `ppn_percent` | `decimal(5,2)` |  |  |  |  |  |
| `ppn_amount` | `decimal(18,2)` |  |  |  |  |  |
| `pph_label` | `varchar(50)` |  | ya |  |  |  |
| `pph_percent` | `decimal(5,2)` |  | ya |  |  |  |
| `pph_base` | `decimal(18,2)` |  |  |  |  |  |
| `pph_amount` | `decimal(18,2)` |  |  |  |  |  |
| `total` | `decimal(18,2)` |  |  |  |  |  |
| `advance_deduction` | `decimal(18,2)` |  |  |  |  |  |
| `pph_slip_number` | `varchar(100)` |  | ya |  |  |  |
| `pph_slip_date` | `date` |  | ya |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `notes` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `verified_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `verified_at` | `timestamp` |  | ya |  |  |  |
| `void_reason` | `text` |  | ya |  |  |  |
| `voided_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `voided_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `supplier_bill_items`

Tagihan supplier — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `supplier_bill_id` | `bigint(20) unsigned` |  |  | → `supplier_bills.id` |  |  |
| `line_type` | `varchar(20)` |  |  |  |  |  |
| `purchase_order_item_id` | `bigint(20) unsigned` |  | ya | → `purchase_order_items.id` |  |  |
| `direct_purchase_item_id` | `bigint(20) unsigned` |  | ya | → `direct_purchase_items.id` |  |  |
| `master_product_id` | `bigint(20) unsigned` |  | ya | → `master_products.id` |  |  |
| `project_id` | `bigint(20) unsigned` |  | ya | → `projects.id` |  |  |
| `part_number` | `varchar(100)` |  | ya |  |  |  |
| `description` | `text` |  | ya |  |  |  |
| `qty` | `int(11)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `price` | `decimal(18,2)` |  |  |  |  |  |
| `amount` | `decimal(18,2)` |  |  |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `supplier_payments`

Pembayaran supplier (PV).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `payment_number` | `varchar(50)` | UQ |  |  |  |  |
| `supplier_id` | `bigint(20) unsigned` |  |  | → `suppliers.id` |  |  |
| `payment_date` | `date` |  |  |  |  |  |
| `method` | `varchar(20)` |  |  |  |  |  |
| `source_account` | `varchar(150)` |  | ya |  |  |  |
| `bank_name` | `varchar(100)` |  | ya |  |  |  |
| `bank_account_no` | `varchar(50)` |  | ya |  |  |  |
| `bank_account_name` | `varchar(150)` |  | ya |  |  |  |
| `reference_number` | `varchar(100)` |  | ya |  |  |  |
| `currency` | `varchar(10)` |  |  |  |  |  |
| `amount` | `decimal(18,2)` |  |  |  |  |  |
| `exchange_rate` | `decimal(18,6)` |  |  |  |  |  |
| `amount_base` | `decimal(18,2)` |  |  |  |  |  |
| `bank_charge` | `decimal(18,2)` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `notes` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_at` | `timestamp` |  | ya |  |  |  |
| `void_reason` | `text` |  | ya |  |  |  |
| `voided_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `voided_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `supplier_payment_allocations`

Alokasi pembayaran ke tagihan (M:N).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `supplier_payment_id` | `bigint(20) unsigned` |  |  | → `supplier_payments.id` |  |  |
| `supplier_bill_id` | `bigint(20) unsigned` |  |  | → `supplier_bills.id` |  |  |
| `amount` | `decimal(18,2)` |  |  |  |  |  |
| `amount_base_book` | `decimal(18,2)` |  |  |  |  |  |
| `amount_base_paid` | `decimal(18,2)` |  |  |  |  |  |
| `fx_difference` | `decimal(18,2)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `supplier_refunds`

Refund uang muka dari supplier.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `refund_number` | `varchar(50)` | UQ |  |  |  |  |
| `supplier_id` | `bigint(20) unsigned` |  |  | → `suppliers.id` |  |  |
| `purchase_order_id` | `bigint(20) unsigned` |  |  | → `purchase_orders.id` |  |  |
| `refund_date` | `date` |  |  |  |  |  |
| `method` | `varchar(20)` |  |  |  |  |  |
| `destination_account` | `varchar(150)` |  | ya |  |  |  |
| `reference_number` | `varchar(100)` |  | ya |  |  |  |
| `currency` | `varchar(10)` |  |  |  |  |  |
| `amount` | `decimal(18,2)` |  |  |  |  |  |
| `exchange_rate` | `decimal(18,6)` |  |  |  |  |  |
| `amount_base` | `decimal(18,2)` |  |  |  |  |  |
| `amount_base_book` | `decimal(18,2)` |  |  |  |  |  |
| `fx_difference` | `decimal(18,2)` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `notes` | `text` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_at` | `timestamp` |  | ya |  |  |  |
| `void_reason` | `text` |  | ya |  |  |  |
| `voided_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `voided_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

## Finance AR (piutang customer)

| Tabel saat ini | Fungsi | Nama usulan | Catatan |
|---|---|---|---|
| `projects` | Project |  |  |
| `project_billing_terms` | Termin penagihan project |  |  |
| `invoices` | Invoice customer (INV) — header |  |  |
| `invoice_items` | Invoice customer — item |  |  |
| `customer_receipts` | Penerimaan customer (RV) |  |  |
| `profit_estimates` | Estimasi PL — header |  |  |
| `profit_estimate_lines` | Estimasi PL — baris |  |  |

### `projects`

Project.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `project_number` | `varchar(50)` | UQ |  |  |  |  |
| `project_name` | `varchar(255)` |  |  |  |  |  |
| `project_description` | `text` |  | ya |  |  |  |
| `quotation_id` | `bigint(20) unsigned` |  | ya | → `quotations.id` |  |  |
| `company_id` | `bigint(20) unsigned` |  |  | → `account_companies.id` |  |  |
| `po_number_company` | `varchar(100)` | UQ |  |  |  |  |
| `po_date` | `date` |  |  |  |  |  |
| `po_date_received` | `date` |  |  |  |  |  |
| `po_due_date` | `date` |  |  |  |  |  |
| `total_sale_amount` | `decimal(15,2)` |  |  |  |  |  |
| `net_sale_amount` | `decimal(15,2)` |  |  |  |  |  |
| `manual_subtotal` | `decimal(18,2)` |  | ya |  |  |  |
| `manual_discount_percent` | `decimal(7,4)` |  | ya |  |  |  |
| `manual_discount_amount` | `decimal(18,2)` |  | ya |  |  |  |
| `manual_ppn_percent` | `decimal(7,4)` |  | ya |  |  |  |
| `manual_ppn_amount` | `decimal(18,2)` |  | ya |  |  |  |
| `manual_referral_percent` | `decimal(7,4)` |  | ya |  |  |  |
| `is_retention` | `tinyint(1)` |  |  |  |  |  |
| `type_of_achievement` | `varchar(20)` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `po_document_path` | `varchar(255)` |  | ya |  |  |  |
| `po_document_name` | `varchar(255)` |  | ya |  |  |  |
| `po_uploaded_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `po_uploaded_at` | `timestamp` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `updated_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `project_billing_terms`

Termin penagihan project.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  |  | → `projects.id` |  |  |
| `sort_order` | `int(10) unsigned` |  |  |  |  |  |
| `name` | `varchar(100)` |  |  |  |  |  |
| `percent` | `decimal(5,2)` |  |  |  |  |  |
| `description` | `varchar(255)` |  | ya |  |  |  |
| `target_date` | `date` |  | ya |  |  |  |
| `unlocked_at` | `timestamp` |  | ya |  |  |  |
| `unlocked_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `invoices`

Invoice customer (INV) — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `project_id` | `bigint(20) unsigned` |  |  | → `projects.id` |  |  |
| `billing_term_id` | `bigint(20) unsigned` |  | ya | → `project_billing_terms.id` |  |  |
| `invoice_number` | `varchar(50)` | UQ |  |  |  |  |
| `invoice_date` | `date` |  |  |  |  |  |
| `ship_to` | `text` |  | ya |  |  |  |
| `date_shipped` | `date` |  | ya |  |  |  |
| `shipping_method` | `varchar(100)` |  | ya |  |  |  |
| `do_number` | `varchar(100)` |  | ya |  |  |  |
| `terms` | `varchar(100)` |  | ya |  |  |  |
| `due_date` | `date` |  | ya |  |  |  |
| `date_payment` | `date` |  | ya |  |  |  |
| `reference_number` | `varchar(100)` |  | ya |  |  |  |
| `tax_invoice_number` | `varchar(50)` |  | ya |  |  |  |
| `paid_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `cancel_reason` | `text` |  | ya |  |  |  |
| `cancelled_at` | `timestamp` |  | ya |  |  |  |
| `cancelled_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `subtotal` | `decimal(15,2)` |  |  |  |  |  |
| `discount_percent` | `decimal(5,2)` |  | ya |  |  |  |
| `discount_amount` | `decimal(15,2)` |  |  |  |  |  |
| `noted` | `varchar(255)` |  | ya |  |  |  |
| `non_term_reason` | `text` |  | ya |  |  |  |
| `signer_name` | `varchar(100)` |  | ya |  |  |  |
| `signer_position` | `varchar(100)` |  | ya |  |  |  |
| `percent_payment` | `decimal(5,2)` |  |  |  |  |  |
| `amount_payment` | `decimal(15,2)` |  |  |  |  |  |
| `ppn` | `decimal(5,2)` |  |  |  |  |  |
| `total_payment` | `decimal(15,2)` |  |  |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `approval_status` | `varchar(30)` |  |  |  |  |  |
| `final_checked_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `approval_note` | `text` |  | ya |  |  |  |
| `approved_at` | `timestamp` |  | ya |  |  |  |
| `rejected_at` | `timestamp` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `updated_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `invoice_items`

Invoice customer — item.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `invoice_id` | `bigint(20) unsigned` |  |  | → `invoices.id` |  |  |
| `product_id` | `bigint(20) unsigned` |  | ya | → `master_products.id` |  |  |
| `product_name` | `varchar(255)` |  |  |  |  |  |
| `description` | `text` |  | ya |  |  |  |
| `qty` | `decimal(15,2)` |  |  |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `unit_price` | `decimal(15,2)` |  |  |  |  |  |
| `total_price` | `decimal(15,2)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `customer_receipts`

Penerimaan customer (RV).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `receipt_number` | `varchar(20)` | UQ |  |  |  |  |
| `invoice_id` | `bigint(20) unsigned` |  |  | → `invoices.id` |  |  |
| `company_id` | `bigint(20) unsigned` |  | ya | → `account_companies.id` |  |  |
| `receipt_date` | `date` |  |  |  |  |  |
| `method` | `varchar(20)` |  |  |  |  |  |
| `deposit_account` | `varchar(150)` |  | ya |  |  |  |
| `reference_number` | `varchar(100)` |  | ya |  |  |  |
| `amount` | `decimal(15,2)` |  |  |  |  |  |
| `notes` | `text` |  | ya |  |  |  |
| `status` | `varchar(20)` |  |  |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `posted_at` | `timestamp` |  | ya |  |  |  |
| `void_reason` | `text` |  | ya |  |  |  |
| `voided_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `voided_at` | `timestamp` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `profit_estimates`

Estimasi PL — header.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `quotation_id` | `bigint(20) unsigned` | UQ |  | → `quotations.id` |  |  |
| `task_id` | `bigint(20) unsigned` |  | ya | → `tasks.id` |  |  |
| `opportunity_id` | `bigint(20) unsigned` |  | ya | → `opportunities.id` |  |  |
| `date` | `date` |  | ya |  |  |  |
| `project_name` | `varchar(255)` |  | ya |  |  |  |
| `rates` | `longtext` |  | ya |  |  |  |
| `nilai_awal` | `decimal(18,2)` |  |  |  |  |  |
| `ppn_amount` | `decimal(18,2)` |  |  |  |  |  |
| `discount_amount` | `decimal(18,2)` |  |  |  |  |  |
| `referral_percent` | `decimal(7,2)` |  |  |  |  |  |
| `investment_percent` | `decimal(7,2)` |  |  |  |  |  |
| `marketing_fee_percent` | `decimal(7,2)` |  |  |  |  |  |
| `pm_fee_percent` | `decimal(7,2)` |  |  |  |  |  |
| `subtotal_1` | `decimal(18,2)` |  |  |  |  |  |
| `subtotal_2` | `decimal(18,2)` |  |  |  |  |  |
| `referral_amount` | `decimal(18,2)` |  |  |  |  |  |
| `real_project_value` | `decimal(18,2)` |  |  |  |  |  |
| `investment_amount` | `decimal(18,2)` |  |  |  |  |  |
| `total_cost` | `decimal(18,2)` |  |  |  |  |  |
| `estimated_profit` | `decimal(18,2)` |  |  |  |  |  |
| `profit_percent` | `decimal(7,2)` |  |  |  |  |  |
| `marketing_fee_amount` | `decimal(18,2)` |  |  |  |  |  |
| `pm_fee_amount` | `decimal(18,2)` |  |  |  |  |  |
| `real_profit` | `decimal(18,2)` |  |  |  |  |  |
| `real_profit_percent` | `decimal(7,2)` |  |  |  |  |  |
| `notes` | `text` |  | ya |  |  |  |
| `sales_person_name` | `varchar(150)` |  | ya |  |  |  |
| `finance_name` | `varchar(150)` |  | ya |  |  |  |
| `accounting_name` | `varchar(150)` |  | ya |  |  |  |
| `is_outdated` | `tinyint(1)` |  |  |  |  |  |
| `outdated_at` | `timestamp` |  | ya |  |  |  |
| `created_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `updated_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `profit_estimate_lines`

Estimasi PL — baris.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `profit_estimate_id` | `bigint(20) unsigned` |  |  | → `profit_estimates.id` |  |  |
| `section` | `varchar(20)` |  |  |  |  |  |
| `label` | `varchar(255)` |  | ya |  |  |  |
| `qty` | `decimal(12,2)` |  | ya |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `vendor` | `varchar(150)` |  | ya |  |  |  |
| `currency` | `varchar(10)` |  | ya |  |  |  |
| `amount` | `decimal(18,4)` |  | ya |  |  |  |
| `amount_idr` | `decimal(18,2)` |  |  |  |  |  |
| `is_manual` | `tinyint(1)` |  |  |  |  |  |
| `percent` | `decimal(7,2)` |  | ya |  |  |  |
| `is_up` | `tinyint(1)` |  |  |  |  |  |
| `sort_order` | `int(11)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

## Master & pendukung

| Tabel saat ini | Fungsi | Nama usulan | Catatan |
|---|---|---|---|
| `master_products` | Master barang |  |  |
| `currencies` | Master mata uang |  |  |
| `offices` | Kantor (dipakai absensi) |  |  |
| `documents` | Lampiran dokumen (polimorfik) |  |  |

### `master_products`

Master barang.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `name` | `varchar(150)` | UQ | ya |  |  |  |
| `code` | `varchar(50)` | UQ |  |  |  |  |
| `brand` | `varchar(100)` |  | ya |  |  |  |
| `category` | `varchar(100)` |  | ya |  |  |  |
| `division_id` | `bigint(20) unsigned` |  | ya | → `divisions.id` |  |  |
| `description` | `text` |  | ya |  |  |  |
| `image` | `varchar(255)` |  | ya |  |  |  |
| `selling_price` | `decimal(15,2)` |  |  |  |  |  |
| `currency_id` | `bigint(20) unsigned` |  | ya | → `currencies.id` |  |  |
| `status` | `enum('Active','Inactive')` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |
| `type` | `varchar(50)` |  | ya |  |  |  |
| `unit` | `varchar(50)` |  | ya |  |  |  |
| `is_material` | `tinyint(1)` |  |  |  |  |  |
| `parameter` | `varchar(255)` |  | ya |  |  |  |
| `purchase_price` | `decimal(15,2)` |  | ya |  |  |  |

### `currencies`

Master mata uang.

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `name` | `varchar(10)` | UQ |  |  |  |  |
| `symbol` | `varchar(10)` |  | ya |  |  |  |
| `rate` | `decimal(15,4)` |  |  |  |  |  |
| `is_base` | `tinyint(1)` |  |  |  |  |  |
| `status` | `enum('Active','Inactive')` |  |  |  |  |  |
| `description` | `varchar(255)` |  | ya |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `offices`

Kantor (dipakai absensi).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `name` | `varchar(150)` |  |  |  |  |  |
| `address` | `text` |  | ya |  |  |  |
| `latitude` | `decimal(10,8)` |  |  |  |  |  |
| `longitude` | `decimal(11,8)` |  |  |  |  |  |
| `radius_meters` | `int(10) unsigned` |  |  |  |  |  |
| `is_active` | `tinyint(1)` |  |  |  |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

### `documents`

Lampiran dokumen (polimorfik).

| Kolom saat ini | Tipe | Kunci | NULL | Relasi | Nama usulan | Catatan |
|---|---|---|---|---|---|---|
| `id` | `bigint(20) unsigned` | PK |  |  |  |  |
| `documentable_type` | `varchar(255)` |  |  | polimorfik: class model pemilik |  |  |
| `documentable_id` | `bigint(20) unsigned` |  |  | polimorfik → id pemilik (tanpa FK) |  |  |
| `type` | `varchar(30)` |  |  |  |  |  |
| `file_path` | `varchar(255)` |  |  |  |  |  |
| `file_name` | `varchar(255)` |  |  |  |  |  |
| `mime` | `varchar(100)` |  | ya |  |  |  |
| `size` | `bigint(20) unsigned` |  |  |  |  |  |
| `notes` | `varchar(255)` |  | ya |  |  |  |
| `uploaded_by` | `bigint(20) unsigned` |  | ya | → `users.id` |  |  |
| `created_at` | `timestamp` |  | ya |  |  |  |
| `updated_at` | `timestamp` |  | ya |  |  |  |

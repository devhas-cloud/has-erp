# 07 — Desain Credit Note Supplier (P1-6)

Status: **desain**, belum diimplementasikan. Dokumen ini menjawab gap: retur supplier
mengurangi qty yang boleh ditagih, tetapi tagihan yang sudah `verified`/`paid` tidak
dikoreksi.

## 1. Masalah

Alur saat ini:

1. Barang diterima (`goods_receipts`) → qty diterima bertambah.
2. Supplier bill dibuat dan diverifikasi → hutang diakui.
3. Barang diretur (`supplier_returns`) → qty diterima berkurang, stok keluar.
4. Bill lama **tidak berubah**; `PayableReport` tetap menghitung hutang/biaya dari bill
   yang sudah terlanjur diverifikasi/dibayar.

Akibat:

- Barang yang sudah ditagih lalu diretur tetap menjadi hutang sampai dibayar.
- Biaya project di `PayableReport::projectCosts()` dan `projectBills()` tidak turun.
- Tidak ada dokumen audit yang menjelaskan koreksi tagihan.

## 2. Kebutuhan

- Mengurangi nilai tagihan supplier tanpa menghapus histori bill asli.
- Terhubung ke supplier, PO, bill asli, retur, dan project.
- Mendukung PPN, PPh, diskon/charge, valas, dan kurs buku.
- Memiliki approval workflow dan audit log.
- Dapat dialokasikan ke pembayaran yang belum lunas atau menjadi saldo kredit supplier.
- Laporan AP dan biaya project langsung menyesuaikan.
- Tidak mengubah bill asli yang sudah `paid` (histori tetap).

## 3. Opsi desain

| Opsi | Kelebihan | Kekurangan |
|---|---|---|
| A. Tabel `supplier_credit_notes` + item | Histori jelas, approval, alokasi, laporan rapi | Perlu model/controller/view baru |
| B. Bill negatif (`supplier_bills` total negatif) | Reuse tabel | Semantik 3-way match/status kacau; nomor dan PPN membingungkan |
| C. Edit/hapus bill lama | Cepat | Merusak audit, tidak boleh untuk bill paid |
| D. Penyesuaian manual di laporan | Tanpa skema | Tidak auditable, rawan salah |

**Rekomendasi: Opsi A** — credit note sebagai dokumen tersendiri.

## 4. Skema usulan

### `supplier_credit_notes`

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint | PK |
| `credit_note_number` | string(50) unique | Prefix baru `CN` |
| `supplier_id` | FK suppliers | Wajib |
| `purchase_order_id` | FK purchase_orders nullable | Null untuk DP |
| `direct_purchase_id` | FK direct_purchases nullable | Alternatif PO |
| `supplier_bill_id` | FK supplier_bills nullable | Bill yang dikoreksi |
| `supplier_return_id` | FK supplier_returns nullable | Sumber retur |
| `project_id` | FK projects nullable | Snapshot biaya project |
| `date` | date | Tanggal credit note |
| `currency` | string(10) | Mengikuti bill/PO |
| `exchange_rate` | decimal | Kurs buku |
| `subtotal` | decimal | Nilai barang yang dikreditkan |
| `discount_percent` / `discount_amount` | decimal | Proporsional bill |
| `ppn_percent` / `ppn_amount` | decimal | Koreksi PPN |
| `pph_percent` / `pph_amount` | decimal | Koreksi PPh |
| `total` | decimal | Subtotal − diskon + PPN |
| `status` | string(20) | `draft`, `verified`, `applied`, `void` |
| `reason` | text | Alasan wajib |
| `verified_by` / `verified_at` | FK/timestamp | Approval |
| `void_reason` / `voided_by` / `voided_at` | | Void |
| `created_by`, timestamps | | Audit |

### `supplier_credit_note_items`

| Kolom | Catatan |
|---|---|
| `supplier_credit_note_id` | FK |
| `supplier_bill_item_id` | Baris bill yang dikoreksi |
| `supplier_return_item_id` | Baris retur sumber |
| `master_product_id`, `project_id` | Snapshot |
| `qty`, `price`, `amount` | Nilai kredit |
| `line_type` | `item` / `charge` / `tax_adjustment` |
| `sort_order` | Urutan |

### `supplier_credit_note_allocations` (opsional, fase 2)

Untuk mengalokasikan kredit ke bill lain atau pembayaran berikutnya:

| Kolom | Catatan |
|---|---|
| `supplier_credit_note_id` | FK |
| `supplier_bill_id` | Bill yang dikurangi |
| `amount` | Nominal alokasi |

## 5. Workflow

```
draft → verified → applied
   ↘ rejected/void
```

- **draft**: dibuat manual dari bill/retur; bisa diedit/hapus.
- **verified**: approver Finance menyetujui; nilai terkunci.
- **applied**: kredit mengurangi outstanding bill atau menjadi saldo kredit supplier.
- **void**: hanya draft/verified; wajib alasan.

Aturan:

1. Total credit note ≤ nilai baris bill yang belum dibayar (atau ≤ nilai retur).
2. Satu retur dapat menghasilkan satu credit note; satu bill dapat punya banyak credit note.
3. Bill `paid` tetap tidak diubah; credit note menjadi saldo kredit/refund.
4. Credit note valas memakai kurs buku bill; selisih kurs dihitung seperti payment/refund.
5. PPN/PPh dihitung proporsional terhadap baris yang dikreditkan.

## 6. Dampak ke modul lain

| Area | Perubahan |
|---|---|
| `SupplierBill` | `outstanding()` dikurangi total credit note `applied`; status bisa kembali terbuka |
| `SupplierPayment` | Alokasi pembayaran mempertimbangkan credit note yang belum terpakai |
| `PayableReport::openBills` | Outstanding = bill outstanding − credit note applied |
| `PayableReport::projectBills` | Total biaya project = bill amountDue − credit note |
| `PayableReport::projectCosts` | `billed`/`paid` dikurangi porsi credit note |
| `ReceivableReport` | Tidak berubah (AR) |
| `SupplierReturn` | Setelah posted, tombol “Buat Credit Note” membuat draft |
| Audit | `Log::record('create/verify/apply/void_credit_note', ...)` |

## 7. Route dan UI usulan

```
GET    supplier-credit-note
GET    supplier-credit-note/data
GET    supplier-credit-note/create?supplier_bill_id=...
POST   supplier-credit-note
GET    supplier-credit-note/{id}
GET    supplier-credit-note/{id}/edit
PUT    supplier-credit-note/{id}
POST   supplier-credit-note/{id}/approve   -> can_approve
POST   supplier-credit-note/{id}/void      -> can_approve
```

Modul baru: `MOD_SUPPLIER_CREDIT_NOTE`, grup Finance, route base
`supplier-credit-note`; daftarkan di `ModulesTableSeeder`.

UI:

- Tab “Credit Note” di detail Supplier Bill.
- Tombol “Buat Credit Note” di detail Supplier Return.
- Halaman daftar + form + detail dengan PDF.
- Badge status dan alasan.

## 8. Migrasi dan backfill

- Migration baru membuat tiga tabel di atas.
- Tidak ada backfill otomatis; credit note dibuat manual untuk kasus lama.
- Index: `supplier_id`, `supplier_bill_id`, `supplier_return_id`, `status`, `date`.
- Nomor dokumen: tambah `'supplier_credit_notes' => 'CN'` di
  `config/document_numbers.php`.

## 9. Test yang diperlukan

1. Credit note dari retur mengurangi outstanding bill.
2. Credit note tidak boleh melebihi nilai baris bill.
3. Bill `paid` + credit note → saldo kredit/refund, bill tidak berubah.
4. PPN/PPh proporsional.
5. Valas: kurs buku, selisih kurs.
6. Approval/void dan permission `can_approve`.
7. `PayableReport::projectBills`/`projectCosts` turun sesuai kredit.
8. PDF dan halaman render.

## 10. Keputusan terbuka

1. **Alokasi kredit**: langsung ke bill, saldo kredit supplier, atau refund?
   Rekomendasi: langsung ke bill; sisa menjadi saldo kredit.
2. **Pajak**: credit note memakai nomor faktur pajak koreksi? Perlu keputusan Finance.
3. **Retur parsial**: satu credit note per baris retur atau per retur?
   Rekomendasi: per retur, item per baris.
4. **Bill paid**: kredit menjadi saldo kredit atau refund? Rekomendasi: saldo kredit,
   refund lewat modul Supplier Refund bila diminta.

## 11. Fase implementasi

| Fase | Isi |
|---|---|
| 1 | Skema, model, nomor dokumen, daftar/detail, create dari retur |
| 2 | Approval, void, PDF, audit log |
| 3 | Integrasi outstanding bill, PayableReport, payment allocation |
| 4 | Valas, PPN/PPh proporsional, saldo kredit supplier |

Sampai fase 1–3 selesai, prosedur manual: bill yang sudah terlanjur diverifikasi dan
barangnya diretur harus di-void (jika belum dibayar) atau dibuatkan refund/penyesuaian
manual oleh Finance, dengan catatan di `notes`.

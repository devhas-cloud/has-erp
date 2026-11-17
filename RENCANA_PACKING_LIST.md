# Rencana Packing List & Delivery Order

Status: **diimplementasi 18 Nov 2026 (belum di-commit).** Rencana disepakati 17 Nov 2026 (revisi setelah contoh dokumen). Branch kerja: `feature/stock-ledger-simple`.
Halaman rencana v1 (ERD + checklist, **sebagian sudah usang** — file ini yang berlaku): https://claude.ai/artifact/Aq1Bg8du7xhTrE7KbwTTa9

## Latar belakang

DO saat ini mengambil baris stok langsung dari gudang (`delivery_order_items.stock_id`). Bila barang yang dikirim banyak, dokumen ikut panjang. Contoh dokumen Packing List perusahaan menunjukkan yang dicetak adalah **koli (box)** dengan deskripsi ringkas, qty order, qty kirim, dimensi, dan berat — bukan daftar baris stok.

## Peran dokumen

| | Packing List (gudang) | Delivery Order (pengiriman project) |
|---|---|---|
| Dicetak | Per koli: deskripsi (dari item Quotation / bebas), qty order, qty kirim, box "x of N", dimensi, berat; total koli & berat | Baris dokumen, terisi otomatis dari koli PKL yang dimuat (bisa diubah) |
| Dicatat (tidak dicetak utama) | Isi fisik tiap koli: baris stok, SN / batch, qty — dasar stok keluar | — |
| Hubungan | 1 PKL hanya di 1 DO | 1 DO memuat 1 atau lebih PKL |

## Keputusan

| Topik | Keputusan |
|---|---|
| Packing List | Dibuat gudang per project + 1 gudang. Nomor `PKL-YYMMNNNN`. |
| Koli | **1 baris PKL = 1 koli.** Satu item butuh 2 koli → 2 baris. Satu koli berisi beberapa item Quotation → deskripsi ditulis gabungan. Box No. otomatis "x of N". |
| Isi koli | **Setiap koli wajib berisi ≥1 baris stok** milik project di gudang PKL (pemilih stok bersama; material boleh sebagian, unit ber-SN wajib ber-SN). Barang yang tidak tercatat di stok tidak bisa masuk koli. |
| Deskripsi koli | Dari `quotation_items` project (part number + deskripsi, qty order = qty Quotation) atau teks bebas. Info "sudah dikirim X dari Y" per item Quotation — hanya info, tidak memblokir. |
| Status PKL | Draft (boleh ubah / hapus) → Siap Kirim (terkunci, stok dipesan) → Terkirim (saat DO diposting). Siap Kirim boleh dibuka kembali ke Draft selama belum dimuat DO. |
| Kapan stok keluar | Saat **DO diposting**: semua baris stok di koli PKL yang dimuat (ref `packing_list_item`) atau di baris DO Langsung (ref `delivery_order_item_stock`) keluar dengan log `out`. |
| Reservasi | Barang di PKL Siap Kirim tidak bisa diambil modul lain (Transfer, Serah Terima, Opname, Branch Transfer, Retur, …). Dicek di satu tempat: `StockService` + `StockPicker` menampilkan sisa yang belum dipesan. |
| Delivery Order — dua jalur | Saat membuat DO dipilih salah satu, **tidak boleh campur**: **Langsung** (barang sedikit) atau **Dengan Packing List** (barang banyak). Draft boleh ubah / hapus. |
| DO Langsung | Baris DO (deskripsi dari `quotation_items` / bebas, qty, satuan) **masing-masing wajib berisi ≥1 baris stok** milik project di gudang DO. Tanpa reservasi; dicek ulang saat posting (seperti DO sekarang). Kolom Serial Number / Part Number = SN baris stok (kode produk bila tidak ber-SN). |
| DO Dengan Packing List | Memuat ≥1 PKL Siap Kirim dengan project & gudang yang sama (PKL lepas kembali bila DO draft dihapus). **Baris DO terisi otomatis dari koli** (deskripsi, qty kirim, satuan, `quotation_item_id`), masih bisa diubah / ditambah. Kolom Serial Number = rujukan koli, mis. "Box 1–2 · PKL-26110001"; rincian SN ada di PKL. |
| Header DO | To / alamat / telepon / **Attn**, DO Number (`DO-YYMMNNNN`, tetap standar) + Date (tanggal DO), PO/SPK No + Date (tanggal PO customer; default dari project, bisa diubah). |
| Footer DO | Nama **Cek By** (default pembuat), **Acknowledge**, **Send by** diisi per DO. Teks "Note: Good has been Purchased can not be Returned, Refund or Exchange", kotak "Received in good Condition", dan "PS: Please Fax to 021 - 4264624…" tetap di template PDF. |
| Header PKL | To / alamat / telepon / Attn: default dari customer project (seperti DO), bisa diubah. PO/SPK No = default `projects.po_number_company`; **Date di atas = tanggal PO customer** (default `projects.po_date`). Marking: default nama customer. |
| Footer PKL | **Date di bawah = tanggal pengiriman** (`packing_lists.packing_date`). Penandatangan shipper: nama + jabatan (default pembuat, bisa diubah). Customer / Receiver kosong untuk tanda tangan. Total Package = jumlah koli = total berat. |
| Cetak | PDF Packing List sesuai contoh. **Lampiran rincian SN / batch per koli opsional** (dicentang saat cetak). Surat Jalan mencetak baris DO. |
| DO lama | **Tidak ada data DO di produksi** → tanpa konversi. Tabel `delivery_order_items` diubah langsung (data lokal / test dibuang). |

## Alur & efek stok

```
A. DO Langsung (barang sedikit)
   DO Draft: baris dokumen → isi baris stok (SN) → posting → stok keluar

B. DO Dengan Packing List (barang banyak)
   PKL Draft (koli + isi stok) → PKL Siap Kirim (stok dipesan)
   → DO Draft (muat PKL; baris terisi dari koli) → posting
   → stok keluar per baris stok di koli, PKL Terkirim
```
Aturan sama untuk kedua jalur: setiap baris dokumen (baris DO Langsung atau koli PKL) wajib berisi ≥1 baris stok, sehingga yang tercetak selalu sama dengan yang keluar dari stok.

| Langkah | Efek ke stok | Log |
|---|---|---|
| PKL Draft | — | — |
| PKL Siap Kirim | Qty dipesan (cek ulang ketersediaan) | — |
| PKL dibuka kembali | Pesanan dilepas | — |
| DO diposting | Semua barang PKL yang dimuat keluar; PKL Terkirim | `out` (ref `packing_list_item`) |
| DO draft dihapus | PKL kembali Siap Kirim tanpa DO | — |

## Skema

| Tabel | Status | Isi |
|---|---|---|
| `packing_lists` | baru | `packing_number` (UQ, PKL-), `project_id`, `warehouse_id`, `packing_date` (tanggal pengiriman), `status` (draft / ready / shipped), `delivery_order_id` (null = belum dimuat), `recipient_name`, `recipient_address`, `recipient_phone`, `recipient_attn`, `customer_ref` (PO/SPK No), `customer_ref_date` (tanggal PO customer), `marking`, `signer_name`, `signer_position`, `note`, `created_by`, `ready_by`, `ready_at` |
| `packing_list_boxes` | baru | `packing_list_id`, `sort_order` (= nomor koli), `quotation_item_id` (nullable FK), `description` text, `qty_order` decimal (nullable), `qty` decimal (qty kirim), `unit`, `length_cm`, `width_cm`, `height_cm`, `weight_kg` (decimal, nullable) |
| `packing_list_items` | baru | `packing_list_box_id`, `stock_id`, `qty` decimal(15,4), `unit` (dari baris stok), `remark` |
| `delivery_order_items` | ubah | Menjadi baris dokumen: `quotation_item_id` (nullable FK), `description` text, `qty` decimal, `unit`, `remark`, `sort_order`. `stock_id` dihapus (tanpa konversi) |
| `delivery_order_item_stocks` | baru | Isi baris DO Langsung: `delivery_order_item_id`, `stock_id`, `qty` decimal(15,4), `unit` |
| `delivery_orders` | ubah | + `uses_packing_list` (boolean, jalur), `recipient_attn`, `customer_ref`, `customer_ref_date`, `checked_by_name`, `acknowledged_by_name`, `sent_by_name`. Gudang = gudang PKL yang dimuat / gudang baris stok |
| `stock_logs` | data | Ref baru `packing_list_item` dan `delivery_order_item_stock` (ref lama `delivery_order_item` tidak dipakai lagi) |
| modules / config | ubah | `MOD_PACKING_LIST` (grup Inventory, sebelum Delivery Order, akses disalin dari `MOD_DELIVERY_ORDER`), prefix `packing_lists => 'PKL'` |

## Kode yang terdampak (hasil cek 17 Nov)

- `app/Http/Controllers/DeliveryOrderController.php` — DO memuat PKL + baris dokumen; posting mengambil stok dari item PKL.
- `app/Models/DeliveryOrder.php`, `app/Models/DeliveryOrderItem.php`
- `app/Models/StockLog.php` — `REF_MAP` + konstanta `REF_PACKING_LIST_ITEM`.
- `app/Services/StockService.php`, `app/Support/StockPicker.php` — reservasi.
- `app/Http/Controllers/ProjectController.php` + `resources/views/project/show.blade.php` — kartu "Goods & Delivery" (daftar DO).
- `resources/views/delivery-order/{index,form,show,pdf}.blade.php`
- `tests/Feature/DeliveryOrderTest.php` — diperbarui.
- Sumber deskripsi koli: `Quotation::items()` → `quotation_items` (item_no, part_number, description HTML, qty, unit, parent_id bertingkat).
- Default penerima: `DeliveryOrder::defaultAddress($project->company)` dan data company project (dipakai juga di DO sekarang).

## Fase

- **P0** — Baseline test (perubahan Pemenuhan dari Stok sudah di-commit).
- **P1** — Migration `packing_lists`, `packing_list_boxes`, `packing_list_items`, `delivery_order_item_stocks`; ubah `delivery_order_items` (tanpa konversi) dan kolom baru `delivery_orders`; modul + prefix + seeder.
- **P2** — Model `PackingList`, `PackingListBox`, `PackingListItem`, `DeliveryOrderItem` baru, `DeliveryOrderItemStock`; reservasi di `StockService` (take / move / adjust menolak qty melebihi sisa belum dipesan, kecuali posting DO untuk PKL itu) dan `StockPicker`.
- **P3** — Modul Packing List: daftar (ListToolbar: project, gudang, status, tanggal, milik saya); form (project → gudang → header penerima/PO/marking/penandatangan → koli: deskripsi dari pop-up item Quotation atau bebas, qty order, qty kirim, dimensi, berat, lalu pilih baris stok isi koli); detail; Siap Kirim / buka kembali; PDF sesuai contoh + lampiran SN opsional.
- **P4** — Delivery Order baru: form (project → gudang → pilih jalur; **Langsung**: baris dokumen dari pop-up item Quotation / bebas + pilih baris stok per baris; **Dengan Packing List**: centang PKL Siap Kirim → baris terisi dari koli, bisa diubah / ditambah), header Attn + PO/SPK + tanggal PO, footer Cek By / Acknowledge / Send by; posting; detail; PDF sesuai contoh DO (kolom Serial Number / Part Number).
- **P5** — Integrasi: `StockLog` REF_MAP, halaman project, riwayat SN menunjuk PKL.
- **P6** — Test: alur PKL (2 koli) → Siap Kirim → DO (2 PKL) → posting; DO Langsung (baris + SN) → posting; DO campur jalur ditolak; koli / baris DO Langsung tanpa stok ditolak; reservasi menolak Transfer / Serah Terima; deskripsi dari Quotation + info terkirim; DO tanpa PKL ditolak; buka kembali PKL yang dimuat DO ditolak; hapus DO draft melepas PKL; PDF tampil. Suite harus sama dengan baseline.
- **P7** — `skema.md` dibuat ulang dari database; catatan keputusan.

## Setelah ini

[RENCANA_PROJECT_TRACKING.md](RENCANA_PROJECT_TRACKING.md) — dashboard tracking project, dikerjakan setelah Packing List.

## Catatan implementasi (18 Nov 2026)

- Migration: `2026_11_18_000001_create_packing_lists_and_rework_delivery_orders.php` (tabel PKL, `delivery_order_item_stocks`, perubahan `delivery_order_items` & `delivery_orders`, modul `MOD_PACKING_LIST`).
- Kolom tambahan `delivery_order_items.reference` untuk kolom Serial Number jalur Packing List (rujukan koli, terisi otomatis, bisa diubah).
- `packing_list_items.packing_list_id` disimpan juga (selain `packing_list_box_id`) agar query reservasi cukup satu join.
- Reservasi: `PackingListItem::reservedQty()` dicek di `StockService` (take / move / adjust minus) dan dikurangkan di `StockPicker` (kolom `available`).
- Kelas bersama `App\Support\ShipmentLines` (validasi isi stok baris/koli, item Quotation + qty terkirim, default penerima) dan partial JS `partials/shipment-lines`.
- Riwayat stok: log keluar dari isi koli / isi baris DO menunjuk ke Delivery Order-nya.
- Test: `tests/Feature/PackingListTest.php`, `tests/Feature/DeliveryOrderTest.php` (jalur Langsung).

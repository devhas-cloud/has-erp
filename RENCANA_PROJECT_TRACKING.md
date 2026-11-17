# Rencana Project Tracking Dashboard

Status: **konsep disepakati 17 Nov 2026, belum dieksekusi.** Dikerjakan **setelah** Packing List ([RENCANA_PACKING_LIST.md](RENCANA_PACKING_LIST.md)), supaya tahap 5–6 langsung akurat.
Halaman konsep (mockup): https://claude.ai/artifact/E1DxwmrkYR3prmU91GxVYf

## Tujuan

Monitoring dan ringkasan setiap project sebagai tracking proses, dari opportunity sampai penagihan. Status tiap tahap **dihitung otomatis dari dokumen yang sudah ada** — tanpa input manual, tanpa tabel baru.

## Keputusan

| Topik | Keputusan |
|---|---|
| Akses | Lewat akses modul. Yang punya akses melihat semua project (tanpa filter divisi). |
| Letak | **Keduanya**: modul sendiri `MOD_PROJECT_TRACKING` (daftar + ringkasan) dan tab "Tracking" di detail project, memakai satu kelas perhitungan. |
| Urutan kerja | Packing List dulu, lalu dashboard. |
| Ambang peringatan (N hari) | Belum dijawab — default saran: satu nilai di config, 14 hari. |
| Project Completed / Cancelled | Belum dijawab — default saran: hanya Active, dengan filter untuk melihat yang lain. |

## Tahap & sumber data

| Tahap | Selesai bila | Angka progres | Sumber data |
|---|---|---|---|
| 1 Project | Project dibuat dari quotation (selalu selesai) | Nilai kontrak, tgl PO customer, jatuh tempo | `projects`, `quotations`, `opportunities` |
| 2 Pembelian | PO Supplier Approval 2/2 dan semua item PR project sudah di-PO / DP | Approval x/2 · qty di-PO ÷ qty PR · komitmen vs estimasi biaya | `project_po_supplier_approvals`, `goods_requests`, `purchase_orders`, `direct_purchases`, `profit_estimates` |
| 3 Terima Barang | Semua qty PO / DP project sudah diterima | qty diterima ÷ qty dipesan | `goods_receipt_items` |
| 4 Produksi | Semua MR selesai dan Material di Produksi habis terpakai. "Tidak dipakai" bila project tanpa MR | Diserahkan ÷ diminta · jumlah produksi · sisa material | `material_requests`, `material_handover_items`, `productions` |
| 5 Packing List | Tidak ada PKL Draft / Siap Kirim tertinggal | PKL per status | `packing_lists` |
| 6 Delivery Order | Semua item Quotation terkirim | qty terkirim ÷ qty Quotation · sisa stok project | `delivery_orders`, `delivery_order_items` (baris dokumen, `quotation_item_id`), `stocks` |
| 7 Invoice | Total invoice = nilai kontrak dan semua lunas | % ditagih · % diterima · piutang berjalan | `project_billing_terms`, `invoices`, `customer_receipts` |

Status tahap: Selesai · Berjalan · Terlambat / tertahan · Belum mulai · Tidak dipakai.

## Tampilan

- **Daftar project** — ringkasan di atas (project aktif, menunggu pembelian, siap kirim, belum ditagih penuh, lewat jatuh tempo), lalu satu baris per project: 7 titik tahap, tahap saat ini, jatuh tempo (sisa / lewat hari), bar ditagih / diterima, peringatan. Filter: status project, customer, tahap saat ini, jatuh tempo, hanya yang bermasalah.
- **Detail tracking (tab di project)** — nilai kontrak, estimasi biaya, biaya aktual, ditagih, diterima; linimasa 7 tahap dengan nomor dokumen + tautan dan angka progres.

## Peringatan otomatis

| Peringatan | Aturan |
|---|---|
| Lewat jatuh tempo | Hari ini > `projects.po_due_date` dan tahap 6 belum selesai |
| Approval PO tertahan | PO Supplier Approval < 2 lebih dari N hari sejak project dibuat |
| Biaya melewati estimasi | Biaya aktual (`PayableReport::projectCosts()`) > `profit_estimates.total_cost` |
| Barang belum datang | PO / DP project disetujui lebih dari N hari dan belum diterima penuh |
| SN belum lengkap | Stok milik project dengan unit ber-SN tanpa SN |
| Barang siap tapi belum dikirim | Stok milik project ada, tanpa PKL / DO berjalan selama N hari |
| Piutang menunggak | Invoice lewat `due_date` dan belum lunas |

## Teknis

- Kelas `App\Support\ProjectTracker`: menghitung 7 tahap untuk banyak project sekaligus — satu query `GROUP BY project_id` per tahap, bukan per project.
- Biaya memakai `PayableReport::projectCosts()` agar angka sama dengan laporan.
- PO supplier tidak punya `project_id` langsung: hubungan lewat `purchase_order_items.goods_request_item_id` → `goods_requests.project_id` (ikuti cara `PayableReport`).
- Cache beberapa menit hanya bila nanti project sangat banyak.

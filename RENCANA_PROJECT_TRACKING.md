# Rencana Project Tracking Dashboard

Status: **revisi 9 tahap 24 Nov 2026 (belum di-commit)**; versi 7 tahap diimplementasi & di-commit 18 Nov 2026. Konsep disepakati 17 Nov 2026. Dikerjakan **setelah** Packing List ([RENCANA_PACKING_LIST.md](RENCANA_PACKING_LIST.md)), supaya tahap 5–6 langsung akurat.
Halaman konsep (mockup, masih versi 7 tahap): https://claude.ai/artifact/E1DxwmrkYR3prmU91GxVYf

## Tujuan

Monitoring dan ringkasan setiap project sebagai tracking proses, dari opportunity sampai penagihan. Status tiap tahap **dihitung otomatis dari dokumen dan task yang sudah ada** — tanpa input manual, tanpa tabel baru.

## Keputusan

| Topik | Keputusan |
|---|---|
| Akses | Lewat akses modul. Yang punya akses melihat semua project (tanpa filter divisi). |
| Letak | Modul sendiri `MOD_PROJECT_TRACKING`: daftar + ringkasan, dan **halaman detail tracking sendiri** (`project-tracking/{id}`). Tidak ditampilkan di detail project (revisi 18 Nov). |
| Urutan kerja | Packing List dulu, lalu dashboard. |
| Ambang peringatan (N hari) | 14 hari, `config/project_tracking.php` (`PROJECT_TRACKING_ALERT_DAYS`). |
| Project Completed / Cancelled | Default hanya Active; filter status untuk melihat yang lain. |
| Bahasa UI | Inggris (revisi 24 Nov). |
| Packing List | Digabung ke tahap Delivery (revisi 24 Nov). |
| Pengecekan & pemasangan | Dari task project kategori "Incoming Quality" dan "Installasi" (revisi 24 Nov). |

## Tahap & sumber data

| Tahap | Selesai bila | Angka progres | Tanggal (mulai → selesai) | Sumber data |
|---|---|---|---|---|
| 1 Project | Project dibuat dari quotation (selalu selesai) | Nilai kontrak, tgl PO customer, jatuh tempo | Tgl PO customer | `projects`, `quotations`, `opportunities` |
| 2 Purchasing | PO Supplier Approval 2/2 dan semua item PR project sudah di-PO / DP | Approval x/2 · qty di-PO ÷ qty PR · komitmen vs estimasi biaya | PR / DP pertama dibuat → approval PO / DP terakhir | `project_po_supplier_approvals`, `goods_requests`, `purchase_orders`, `direct_purchases`, `profit_estimates` |
| 3 Purchase Payment | Semua tagihan supplier project lunas **dan** nilai tagihan ≥ nilai barang diterima. Terlambat bila ada tagihan lewat jatuh tempo | Dibayar ÷ max(ditagih, nilai diterima), dalam mata uang dasar | Pembayaran pertama (atau tgl tagihan pertama) → pembayaran terakhir | `supplier_bill_items`, `supplier_bills`, alokasi `supplier_payments` |
| 4 Goods Receipt | Semua qty PO / DP project sudah diterima | qty diterima ÷ qty dipesan | GRN posted pertama → terakhir | `goods_receipt_items` |
| 5 Incoming Quality | Semua task kategori "Incoming Quality" selesai | task selesai ÷ total task | Task pertama dibuat → task terakhir selesai | `tasks` (`project_id`, kategori, status ≠ cancelled) |
| 6 Production | Semua MR selesai dan Material di Produksi habis terpakai. "Not used" bila project tanpa MR | Diserahkan ÷ diminta · jumlah produksi · sisa material | MR pertama → produksi terakhir | `material_requests`, `material_handover_items`, `productions` |
| 7 Delivery | (a) semua item Quotation ber-qty terkirim penuh (item anak dihitung untuk paketnya), **atau** (b) ada DO terposting, stok project habis, tidak ada PKL/DO draft tertinggal | qty terkirim ÷ qty Quotation · PKL per status · sisa stok project | PKL / DO pertama → DO posted terakhir | `packing_lists`, `delivery_orders`, `delivery_order_items` (`quotation_item_id`), `stocks` |
| 8 Installation | Semua task kategori "Installasi" selesai | task selesai ÷ total task | Task pertama dibuat → task terakhir selesai | `tasks` |
| 9 Invoice | Total invoice = nilai kontrak dan semua lunas | % ditagih · % diterima · piutang berjalan | Invoice pertama → invoice terakhir | `project_billing_terms`, `invoices`, `customer_receipts` |

Status tahap: Done · In progress · Delayed · Not started · Not used.

Aturan khusus:
- Tahap 2–4 = **Not used** bila project tanpa PR / DP project tetapi tahap berikutnya sudah berjalan (MR, PKL, DO, atau invoice) — mis. dikirim dari stok yang sudah ada. Tahap 3 = Not started bila belum ada tagihan.
- Tahap 5 tanpa task Incoming Quality = **Not started**, ditambah peringatan bila barang sudah diterima.
- Tahap 8 tanpa task Installasi = **Not used**.
- Tahap berbasis task: Done bila semua task selesai, Delayed bila ada task lewat tenggat, In progress bila ada task yang bukan To Do, selain itu Not started.

## Tampilan

- **Daftar project** — ringkasan di atas (project aktif, di pembelian tahap 2–5, di produksi & pengiriman tahap 6–8, belum ditagih penuh, lewat jatuh tempo), lalu satu baris per project: project, tahap saat ini (sejak tanggal, ringkasan, "N of M stages done"), jatuh tempo (sisa / lewat hari), bar ditagih / diterima, peringatan. Tanpa kolom stepper tahap (dihapus 24 Nov) — alur lengkap ada di detail. Filter: cari, status project, customer, tahap saat ini, hanya yang bermasalah.
- **Detail tracking (halaman sendiri, ada tombol Open Project)** — alur horizontal "Project Journey" 9 tahap bertanggal; ringkasan project, termin, KPI (nilai kontrak, estimasi biaya, biaya aktual, ditagih, diterima); "Stage Timeline" 9 tahap dengan penjelasan, rentang tanggal, nomor dokumen + tautan dan angka progres.

## Peringatan otomatis

| Peringatan | Aturan |
|---|---|
| Lewat jatuh tempo | Hari ini > `projects.po_due_date` dan tahap 7 Delivery belum selesai |
| Approval PO tertahan | PO Supplier Approval < 2 lebih dari N hari sejak project dibuat |
| Biaya melewati estimasi | Biaya aktual (`PayableReport::projectCosts()`) > `profit_estimates.total_cost` |
| Barang belum datang | PO / DP project disetujui lebih dari N hari dan belum diterima penuh |
| Tagihan supplier lewat jatuh tempo | Tagihan supplier project lewat `due_date` dan belum lunas |
| Belum ada task Incoming Quality | Barang sudah diterima tetapi project belum punya task kategori "Incoming Quality" |
| SN belum lengkap | Stok milik project dengan unit ber-SN tanpa SN |
| Barang siap tapi belum dikirim | Stok milik project ada, tanpa PKL / DO berjalan selama N hari |
| Piutang menunggak | Invoice lewat `due_date` dan belum lunas |

## Teknis

- Kelas `App\Support\ProjectTracker`: menghitung 9 tahap untuk banyak project sekaligus — satu query `GROUP BY project_id` per tahap, bukan per project.
- Biaya memakai `PayableReport::projectCosts()` agar angka sama dengan laporan.
- PO supplier tidak punya `project_id` langsung: hubungan lewat `purchase_order_items.goods_request_item_id` → `goods_requests.project_id` (ikuti cara `PayableReport`).
- Cache beberapa menit hanya bila nanti project sangat banyak.

## Catatan implementasi (18 Nov 2026)

- `App\Support\ProjectTracker` (perhitungan), `ProjectTrackingController` (daftar + data JSON), view `project-tracking/index` dan `project-tracking/show` (gaya CSS di masing-masing view, tanpa partial terpisah).
- Modul `MOD_PROJECT_TRACKING` grup Admin (migration `2026_11_18_000002`, akses baca disalin dari Project).
- Tambahan aturan: tahap 2 Pembelian & 3 Terima Barang = **Tidak dipakai** bila project tanpa PR / DP project tetapi tahap berikutnya sudah berjalan (MR, PKL, DO, atau invoice) — mis. dikirim dari stok yang sudah ada.
- Ditagih / diterima = `amount_payment` invoice Approved (tidak Cancelled) / Lunas, dibanding DPP (`billingBaseAmount`).
- Test: `tests/Feature/ProjectTrackingTest.php`.

## Revisi 9 tahap (24 Nov 2026)

- Tahap baru: 3 Purchase Payment, 5 Incoming Quality, 8 Installation. Packing List digabung ke 7 Delivery.
- `ProjectTracker::STAGES`, `STAGE_HINTS`, `TASK_STAGE_CATEGORIES` (5 → "Incoming Quality", 8 → "Installasi"); setiap tahap membawa `start` / `end`, dan data daftar membawa `current_since` serta `progress` (`overallProgress()`).
- Seluruh label, status, dan peringatan dalam bahasa Inggris.
- Test: `test_incoming_quality_and_installation_follow_project_tasks` ditambahkan.

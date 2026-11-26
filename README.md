# HAS-ERP

Web aplikasi ERP/CRM berbasis **Laravel 12** yang mengelola proses bisnis dari **Lead → Opportunity → Task Quote → Quote Configuration (per divisi) → Quotation → Project → Pembelian → Penerimaan Barang → Produksi → Packing List → Delivery Order → Invoice**, lengkap dengan manajemen akun & kontak, task planner dengan approval flow, notifikasi (polling 30 detik), kontrol akses per-modul, modul **Employee Relations** (HR), **Purchasing**, **Inventory** (stok per baris + SN, multi-branch), **Produksi**, **Finance** (hutang & piutang), **Project Tracking**, dan modul konfigurasi teknis ber-versi (Water, IMS, Enviro, IH, Gas).

## Teknologi

| Komponen | Teknologi |
|---|---|
| Framework | Laravel 12 (PHP ^8.2) |
| Database | Eloquent ORM — MySQL/MariaDB (`has_erp`); test memakai SQLite in-memory |
| Session / Queue / Cache | driver `database` |
| Frontend | Blade + Bootstrap 5 + jQuery + DataTables + Select2 + toastr + SweetAlert2; aset dibundel via Vite + Tailwind |
| PDF | barryvdh/laravel-dompdf |
| Spreadsheet | phpoffice/phpspreadsheet (import), sebagian export pakai builder OOXML manual |
| WhatsApp | gateway **waBaileys** (`config/services.php` → `wabaileys`) |
| Testing | PHPUnit 11 (via `php artisan test`) |

## Setup

```bash
composer install
cp .env.example .env   # lalu isi konfigurasi database & gateway waBaileys
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Alternatif satu perintah: `composer setup`.

Perintah pengembangan (`composer dev`) menjalankan server, queue listener, log pail, dan Vite sekaligus.

Konfigurasi:

- `config/er.php` — Employee Relations (rate BPJS, cutoff payroll, THR, multiplier lembur), bisa dioverride lewat env.
- `config/document_numbers.php` — prefix nomor dokumen (lihat [Nomor dokumen](#nomor-dokumen)).
- `config/project_tracking.php` — batas hari peringatan Project Tracking (`PROJECT_TRACKING_ALERT_DAYS`, default 14).

## Arsitektur

```
app/
├── Console/Commands/       # SendTaskAlerts, SendPayableDueReminders, SendReceivableDueReminders
├── Http/
│   ├── Controllers/        # 59 controller modul
│   └── Middleware/         # CheckAccessControl (ACL per-modul)
├── Models/                 # 121 model Eloquent
├── Observers/              # TaskObserver, TaskActivityObserver, ActivityObserver
├── Providers/              # AppServiceProvider (registrasi observer + view composer ACL)
├── Services/               # import/export XLSX, WhatsApp, alert, payroll, mention, StockService
└── Support/                # ModuleAccess, SidebarMenu, NumberSequence, Number, StockPicker, StockCost,
                            # ShipmentLines, PayableReport, ReceivableReport, ProjectTracker, ProductPricing, …
```

- **Autentikasi**: login berbasis session (`AuthController`) memakai **username**, password hash, regenerasi session saat login.
- **Otorisasi**: middleware `access.control` (`CheckAccessControl`) + view composer global — tiap user punya `UserAccessControl` per modul (`can_create/read/update/delete/approve`). Admin (role `Admin`) bypass semua cek.
- **Mapping method → permission** (otomatis, dari segmen terakhir nama route): `approve/reject/unlock/settle/pay/finalize/void` → `can_approve`; `revise` → `can_update`; `POST` → `can_create`; `PUT/PATCH` → `can_update`; `DELETE` → `can_delete`; sisanya → `can_read`.
- **Modul = segmen pertama route name** (potong di titik pertama), dipetakan ke tabel `modules` (56 modul ter-seed di `ModulesTableSeeder`). Modul baru ditambahkan lewat migration (menyalin akses dari modul terdekat) **dan** seeder.
- **Branch**: user boleh terikat satu branch (`users.branch_id`, kosong = semua branch); gudang milik satu branch (`warehouses.branch_id`).
- **Audit trail**: model `Log` + trait `Loggable` + helper `Log::record(action, description, moduleCode, loggable)`.
- **Notifikasi**: tabel `notifications`, polling 30 detik di frontend, dikelompokkan per entitas (task/lead/opportunity/quotation). Tipe: `task_assigned`, `task_status_changed`, `task_activity`, `task_approval_required`, `task_approved`, `task_opened`, `mention`, `visit_recorded`, `quotation_approval_required`, `quotation_approved`, `quotation_rejected`.
- **Roles**: `task_roles` (`hierarchy_level`, `is_global_delegator`) menentukan scope data yang bisa dilihat user; role Sales dibatasi ke data miliknya.
- **Scheduler** (`routes/console.php`):
  - `TaskAlertService::sendAlerts()` tiap hari 08:00 — reminder task (`php artisan task:send-alerts`).
  - `PayableDueNotifier::send()` tiap 5 menit — pengingat hutang supplier WhatsApp (`php artisan finance:send-due-reminders`).
  - `ReceivableDueNotifier::send()` tiap 5 menit — pengingat piutang customer WhatsApp (`php artisan finance:send-receivable-reminders`).

### Nomor dokumen

Format `PREFIX-YYMMNNNN` (YYMM = tanggal dokumen, NNNN urut per jenis dokumen, reset tiap tahun) lewat `App\Support\NumberSequence`; prefix di `config/document_numbers.php`:

| Grup | Dokumen → prefix |
|---|---|
| Admin | Project `PRJ` |
| Purchasing | Purchase Request `PR`, Purchase Order `PO` (+ suffix `-L`/`-C`, revisi `REV`), Direct Purchase `DPO`, Retur ke Supplier `RTV` |
| Inventory | Penerimaan Barang `GRN`, Packing List `PKL`, Delivery Order `DO`, Transfer Stok `ST`, Masuk Stok Lain-lain `SE`, Stock Opname `SC`, Branch Transfer `BT` |
| Produksi | Material Request `MR`, Serah Terima Material `MH`, Produksi `MO` |
| Finance | Invoice `INV`, Penerimaan Customer `RV`, Tagihan Supplier `BILL`, Pembayaran Supplier `PV`, Refund Supplier `RFD` |

Tidak memakai pola ini: **Quotation** (format resmi `{seq:03d}/HAS/QT-{inisial}/{bulan romawi}/{tahun}`, urut **reset tiap bulan**), dokumen HR, dan nomor milik pihak lain (invoice supplier, faktur pajak, PO customer).

### Format angka & tampilan

- Angka memakai gaya US (pemisah ribuan koma, desimal titik) lewat `App\Support\Number` (server) dan `window.Num` (browser); Rupiah tanpa desimal kecuali ada sen. Qty stok desimal ditampilkan tanpa nol di belakang (`StockCost::qty`).
- Daftar dokumen memakai toolbar bersama `partials/list-toolbar` (kotak cari + filter + reset).
- UI dimigrasikan ke bahasa Inggris bertahap per modul (Purchase Request & Direct Purchase sudah); modul inventory, produksi, dan tracking berbahasa Indonesia.

## Modul & Fungsi

### Sidebar

- Urutan menu **tidak** alfabet: grup diurutkan lewat `module_groups.sort_order`, modul di dalam grup lewat `modules.sort_order`.
- Urutan grup: Dashboard → CRM → Master Data → Admin → Purchasing → Inventory → Produksi → Finance → Employee Relations → Water → IMS → Enviro → IH → Gas. Grup yang belum terdaftar di `module_groups` tampil paling akhir.
- Grup `Admin`: Quotation → Estimasi PL → Project → Project Tracking → PO Supplier Approval.
- Grup `Inventory`: Penerimaan Barang → Pemenuhan dari Stok → Packing List → Delivery Order → Transfer Stok → Branch Transfer → Masuk Stok Lain-lain → Stock Opname → Lengkapi Data Stok → Laporan Stok.
- Logika pengambilan menu ada di `app/Support/SidebarMenu.php` (dipakai sidebar, halaman pertama setelah login, dan form User Management). Sidebar hanya menampilkan modul ber-`can_read` (Admin melihat semua).

### Master Data

1. **User Management** — `UserManagementController`
   - CRUD user (username, email, password, divisi, task role, **branch** opsional).
   - Assign ACL per-modul saat create/update: can_create/read/update/delete/approve.
   - Listing DataTables server-side dengan search & filter divisi.

2. **Configuration (Master Data generik)** — `ConfigurationController`
   - **CRUD generik berbasis konfigurasi** (`$config`) untuk tabel referensi: Divisi, Job Title, Source, Contact Method, Segmentation, Business Entity, Business Value, Role In Project, Interaction Level, Account Type, Types Account Company, Task Role, Task Category, Forecast, Loss Reason, Stage, WhatsApp Group, Division Handler, **Branch**.
   - Endpoint dinamis: `/configuration/{table}` → list/store/update/destroy.

3. **Product Management** — `ProductManagementController`
   - CRUD produk: nama, kode unik, brand, kategori, **type (teks bebas)**, **satuan**, divisi, harga, gambar, status.
   - **Jenis stok** (`is_material`): **Material** = dihitung per jumlah (boleh desimal, diambil sebagian); **Barang ber-SN** = satu baris stok per unit (SN boleh menyusul). Tidak bisa diubah selama produk masih punya stok.
   - Harga beli hanya bisa diisi/diubah Admin; harga jual hasil formulasi. IMS hanya melihat harga beli produk divisinya.
   - Export XLSX (builder OOXML manual), import CSV/XLSX (upsert by code, validasi per baris), template import + sheet referensi.

4. **Currency Management** — `CurrencyController`
   - CRUD mata uang + nilai kurs konversi.

5. **Supplier** — `SupplierController`
   - CRUD master supplier, menjadi sumber pilihan supplier pada Purchase Order / Direct Purchase.

6. **Gudang** — `WarehouseController`
   - Master gudang, masing-masing milik satu **branch** (tanpa tipe gudang transit).

7. **Notifikasi Finance** — `FinanceNotificationController`
   - Satu tempat mengatur pengingat WhatsApp **hutang supplier** & **piutang customer**: aktif/nonaktif, jam kirim, tujuan.
   - Endpoint `PATCH finance-notification/{type}` (simpan, `can_update`) dan `finance-notification/{type}/send`.

### CRM

8. **Account Management** — `AccountManagementController`
   - CRUD account/perusahaan: data kontak, alamat billing & shipping, segmentation, business entity/value, interaction level, tipe perusahaan, parent account (hierarki), flag end-user.
   - Import/Export XLSX. Search & sorting DataTables.

9. **Contact Management** — `ContactManagementController`
   - CRUD kontak personal yang terhubung ke account: salutation, email, phone/mobile, job title, source, role in project, contact method, divisi, owner.
   - Import/Export XLSX. Filter per account, search lintas kolom, buat lead/task dari kontak.

10. **Leads Management** — `LeadsManagementController`
    - Pipeline: **New → Approach → Qualified → Converted** / Unqualified.
    - Auto-create contact & company saat membuat lead; update ber-transaksi.
    - `markQualified`, `markUnqualified` (wajib alasan + tanggal closed), `markConverted` (membuat Opportunity terkait).
    - **Import lead dari CSV** (template XLSX 33 kolom via `LeadImportService`) + download template.
    - Aktivitas/komentar: reply thread, lampiran (maks 10), **@mention** (`MentionParser`) → notifikasi. Buat task dari lead.
    - Scope data khusus role Sales (hanya owner/assignee).

11. **Opportunity Management** — `OpportunityManagementController`
    - Pipeline deal: stage (`New → Proposal & Quote → In Review → Negotiation → Closed Won/Closed Lost`), forecast, probability, budget/authorize/timeline, loss reason, close date, next step, end user.
    - Auto-fill dari lead; link account & contact; division handler untuk penanganan.
    - Alur negosiasi & close-loss dengan approval; aktivitas + lampiran + mention; buat task (assignee otomatis ditambah roster handling group).
    - Stage `Closed Won` di-set otomatis oleh PO Supplier Approval (kuorum 2). Scope data: role Sales hanya melihat opportunity miliknya.

12. **Task Planner** — `TaskPlannerController`
    - CRUD task: kategori, WhatsApp group, due date/time, alert (email/WhatsApp), assignee (banyak), requires_approval, terhubung ke **project** aktif (opsional).
    - **Status workflow**: `todo → in_progress → waiting_approval → done`, plus approve/reject oleh creator; task bisa dibatalkan.
    - **Enforcement berbasis kategori**: kategori visit wajib rekam lokasi GPS sebelum done; kategori proposal wajib upload PDF proposal.
    - **Kunjungan (visit)**: rekam lokasi lat/lng + alamat, notifikasi `visit_recorded`.
    - **Proposal**: upload PDF ber-version (auto increment), streaming view.
    - Aktivitas: komentar + reply + lampiran + mention. Export XLSX + import XLSX/CSV dengan template & sheet referensi.
    - **Reminder otomatis**: `SendTaskAlerts` (artisan + scheduler) mengirim pesan WhatsApp H-1 dan hari-H via waBaileys, lalu menandai `is_alert_sent`.
    - **Dashboard Visit Planner** (`DashboardVisitPlannerController`): jadwal visit 7 hari kerja (task berkategori visit), satu baris per assignee salesperson, dikelompokkan per divisi dan terlihat lintas divisi; Sabtu/Minggu tetap dihitung tetapi tidak ditampilkan di grid.

### Konfigurasi Teknis (ber-versi, per divisi)

Format sama (approval workflow, versioning) untuk **5 divisi**: **Water**, **IMS**, **Enviro**, **IH**, **Gas** — controller: `WaterConfigurationController`, `ImsConfigurationController`, `EnviroConfigurationController`, `IhConfigurationController`, `GasConfigurationController`.

- Konfigurasi teknis (breakdown item produk) untuk task quotation divisi terkait, satu level di bawah Quotation.
- Header di-derive dari task/opportunity/lead (to_name, lokasi, PIC, sales).
- **Item hierarki parent–child**; harga & satuan item dari MasterProduct (satuan terkunci dari master).
- **Approval workflow**: draft → submit → waiting_approval → approve/reject; approve hanya oleh approver divisi yang sama (bukan creator), admin override.
- **Versioning**: `group_id` + `version` + `is_current`; approve/revise meng-archive versi lama. `unlock` (approver membuka kunci revisi) dan `revise` (salin header+item ke versi baru, remap parent_id).
- **Template**: konfigurasi versi terbaru tiap group bisa dipakai sebagai template prefill (DFS order) — tersedia CRUD template + PDF + duplikat.
- **PDF** A4 via DOMPDF; audit log di setiap transisi status.

> Perbedaan **IMS**: item membawa price/unit langsung dari input user (bukan lookup MasterProduct), qty wajib ≥ 1, `parameter_note` opsional. Divisi lain mengikuti pola Water.

### Admin / Penawaran

13. **Quotation** — `QuotationController`
    - Dokumen quotation final dari task: prefill dari **semua konfigurasi yang sudah approved** (semua divisi).
    - **3 tab item**: List Items (utama), List Configuration (snapshot item konfigurasi), Cost/Biaya (tidak mempengaruhi subtotal). Item hierarki parent–child; deskripsi disanitasi HTML (`<b><i><u><br>`).
    - **Mesin kalkulasi total** (`Quotation::calculateTotals`): subtotal = Σ(qty×price) → diskon (% / manual) → DPP → PPN (11%) → grand total.
    - **Nomor quotation otomatis**: `{seq:03d}/HAS/QT-{inisial}/{bulan romawi}/{tahun}`, urut reset tiap bulan.
    - **Sales**: maksimal 2 sales (`sales_ids`) diambil dari assignee task berdivisi Sales, ditambah pembuat.
    - Approval workflow submit/approve/reject/unlock/revise + versioning.
    - Gating: quotation hanya bisa dibuat jika semua konfigurasi task sudah approved; satu task satu quotation (current).
    - **Upload PO** customer dipindahkan ke modul **Project**; project menandai quotation `finish` (memicu PO Supplier Approval).
    - **PDF utama + PDF cost** terpisah; template item & template cost; T&C default, diskon/PPN fleksibel.

14. **Estimasi PL (Profit & Loss)** — `ProfitEstimateController`
    - Estimasi pendapatan & biaya per quotation (Nilai Awal = grand total, urutan PPN → Discount → Referral); `sync` mengambil ulang data sumber, penanda *outdated*, export PDF.

15. **Project** — `ProjectController`
    - Dibuat dari quotation `approved`/`finish` yang sudah punya Profit Estimate; satu quotation boleh menjadi beberapa project.
    - Nomor project otomatis `PRJ-YYMMNNNN`.
    - Menyimpan PO customer (nomor, tanggal, jatuh tempo, dokumen) + menandai quotation `finish`.
    - `total_sale_amount` read-only dari `quotation.grand_total`; `net_sale_amount` dari `profit_estimates.real_project_value`.
    - **Timeline penagihan (termin)**: total persen wajib = nilai kontrak (DPP = subtotal − diskon); termin terkunci sampai dibuka; invoice di luar timeline wajib alasan.
    - Sales dipilih di form project; net dibagi sama rata (sisa pembulatan ke sales terakhir). Rincian achievement per device dikelola terpisah di tab **Achievements**.
    - Halaman detail: barang milik project (stok per gudang & terkirim), Delivery Order, biaya pembelian vs Estimasi PL, garansi, task.
    - Project lama tanpa quotation bisa diinput manual.

16. **Project Tracking** — `ProjectTrackingController` + `App\Support\ProjectTracker`
    - Monitoring proses setiap project dalam **7 tahap**, dihitung otomatis dari dokumen yang ada (tanpa input manual, tanpa tabel baru): **1 Project · 2 Pembelian · 3 Terima Barang · 4 Produksi · 5 Packing List · 6 Delivery Order · 7 Invoice**.
    - Status tahap: Selesai, Berjalan, Terlambat, Belum mulai, Tidak dipakai (mis. project tanpa Material Request, atau tanpa pembelian tetapi sudah dikirim dari stok).
    - Tahap 6 selesai bila semua item Quotation ber-qty terkirim (item anak dihitung untuk paketnya) **atau** sudah ada DO terposting, stok project habis, dan tidak ada PKL/DO draft.
    - **Peringatan**: lewat jatuh tempo PO customer, approval PO Supplier tertahan, barang PO/DP belum datang, biaya aktual melewati estimasi, unit project belum ber-SN, barang siap tapi belum dikirim, invoice lewat jatuh tempo. Batas "tertahan" = `config/project_tracking.php`.
    - **Daftar** (default project Active): ringkasan, 7 titik tahap per project, tahap saat ini, jatuh tempo, % ditagih/diterima, filter status/customer/tahap/bermasalah.
    - **Halaman detail** (`project-tracking/{id}`): ringkasan project, termin penagihan, nilai kontrak vs estimasi vs biaya aktual, ditagih/diterima, linimasa 7 tahap dengan tautan dokumen.

16a. **IMS Project (sementara)** — `ImsProjectController`
    - Modul sementara grup IMS: input project hybrid tanpa quotation (nilai 0, tanpa sales) — simpan PO, dokumen, dan garansi; jatuh tempo wajib.
    - Gate akses via middleware `access.control` (route_name `ims-project`).

17. **PO Supplier Approval** — `PoSupplierApprovalController`
    - Approval per **project** (PO customer diupload saat project dibuat) agar purchasing boleh lanjut ke PO supplier untuk project.
    - **Kuorum 2 approver berbeda**; saat penuh, opportunity dari quotation project otomatis menjadi `Closed Won`.

18. **Dashboard Achievement** — `DashboardAchievementController`
    - Total achievement & brand terjual per divisi dari quotation stage Closed Won / Finish.

### Purchasing

19. **Purchase Request** (Permintaan Barang) — `GoodsRequestController`
    - Tujuan **project** (syarat: project aktif + PO Supplier Approval 2/2) atau **stok/internal**; item wajib master produk (dari configuration atau katalog), satuan dari master.
    - Approval cukup **1 approver** (`can_approve`, pembuat ber-can_approve boleh menyetujui sendiri); Tutup Sisa.
    - Dipenuhi lewat PO, atau dari stok bebas lewat **Pemenuhan dari Stok**.
20. **Purchase Order** — `PurchaseOrderController`: hanya dari item Purchase Request Approved, satu mata uang, termin bayar, approve di detail, Batalkan PO (`purchase-order.void`), Revisi PO Rejected (`…REV`), PDF.
21. **Direct Purchase** — `DirectPurchaseController`
    - Pembelian tanpa PO dengan tujuan **Stok** (stok bebas), **Project** (stok milik project), atau **Langsung Pakai**; 1 approver; saat Approved tagihan supplier dibuat otomatis.
    - Tombol **Add from Material Request** (tujuan Stok): acuan pembelian dari Material Request Disetujui — per branch, produk sama digabung, qty saran = sisa MR − stok tersedia − dalam pembelian. Hanya acuan, baris DP tidak terhubung ke MR.
22. **Retur ke Supplier** — `SupplierReturnController`: mengurangi qty diterima & stok (baris stok dipilih).

### Inventory

Skema stok: **`stocks`** (satu baris per penerimaan untuk Material, satu baris per unit untuk Barang ber-SN; berisi gudang, kepemilikan project / stok bebas, batch, SN, kedaluwarsa, garansi, harga modal) dan **`stock_logs`** (riwayat append-only, juga dasar biaya project dari log `allocate`/`transfer`). **Semua perubahan qty wajib lewat `App\Services\StockService`**; pemilihan baris stok di form memakai `App\Support\StockPicker` + partial `partials/stock-pick`. Unit ber-SN tanpa SN tidak bisa keluar ke customer, supplier, atau pemakaian.

23. **Penerimaan Barang** — `GoodsReceiptController`: dari PO / Direct Purchase; masuk gudang (stok bebas atau milik project) atau langsung pakai; SN boleh menyusul. Daftar dengan filter dokumen, gudang, supplier, tanggal, "SN belum lengkap".
24. **Pemenuhan dari Stok** — `StockOutController`: penuhi item Purchase Request Approved dari stok bebas — permintaan internal keluar stok, permintaan project dialokasikan ke project (biaya project).
25. **Packing List** — `PackingListController`
    - Gudang mengemas barang milik project dari satu gudang **per koli**: satu baris = satu koli (deskripsi dari item Quotation atau bebas, qty order, qty kirim, dimensi P×L×T, berat); isi koli = baris stok (wajib minimal satu).
    - Draft → **Siap Kirim** (barang dipesan: tidak bisa diambil Transfer, Serah Terima, Opname, dll.) → **Terkirim** (saat DO diposting). Siap Kirim bisa dibuka kembali selama belum dimuat DO.
    - PDF sesuai format perusahaan (total koli & berat, marking, tanggal PO customer & tanggal pengiriman), lampiran rincian SN/batch per koli opsional.
26. **Delivery Order** — `DeliveryOrderController`
    - Dua jalur (tidak boleh campur): **Langsung** — tiap baris dokumen (dari item Quotation atau bebas) berisi baris stok milik project, SN tercetak di kolom Serial Number; **Dengan Packing List** — memuat PKL Siap Kirim (project & gudang sama), baris dokumen terisi dari koli, kolom Serial Number berisi rujukan koli.
    - Posting oleh gudang (tanpa approval) mengeluarkan stok dari isi baris / isi koli; info "terkirim X dari Y" per item Quotation.
    - PDF sesuai format perusahaan (PO/SPK + tanggal PO, Cek By / Acknowledge / Send by, kotak tanda terima).
27. **Transfer Stok** — `StockTransferController`: pindah gudang dalam **satu branch** dan/atau kepemilikan (alokasi stok bebas ke project / lepas alokasi).
28. **Branch Transfer** — `BranchTransferController`: kirim antar branch dengan surat jalan — Draft → Kirim (stok dalam pengiriman) → branch tujuan memeriksa per barang (qty, kondisi, foto) dan memilih gudang → diterima hanya bila semua sesuai.
29. **Masuk Stok Lain-lain** — `StockEntryController`: saldo awal, retur, hibah/sampel.
30. **Stock Opname** — `StockOpnameController`: hitung fisik per baris stok, penyesuaian lewat `StockService::adjust`.
31. **Lengkapi Data Stok** — `StockDetailController`: isi/koreksi SN, batch / No. MFG, kedaluwarsa, garansi; riwayat per SN.
32. **Laporan Stok** — `StockReportController`: saldo per produk, gudang, branch, dan kepemilikan; nilai stok; barang dalam pengiriman antar branch; kartu stok.

### Produksi

33. **Material Request** — `MaterialRequestController`: kebutuhan material project untuk produksi di satu branch (project aktif). Tanpa draft: disimpan = Menunggu Approval (masih bisa diubah/dihapus) → Disetujui (1 approver) / Ditolak (bisa diperbaiki & diajukan ulang) → Selesai (otomatis saat semua diserahkan, atau ditutup manual).
34. **Serah Terima Material** — `MaterialHandoverController`: gudang menyerahkan baris stok untuk MR Disetujui dari gudang branch MR. Satu langkah, stok langsung keluar (tanpa gudang produksi), tidak bisa dibatalkan. Stok bebas dialokasikan ke project dulu (menjadi biaya project); stok milik project tidak dihitung dua kali. Unit ber-SN wajib ber-SN.
35. **Produksi** — `ProductionController`: memilih item + qty dari **Material di Produksi** milik project + branch (gabungan semua serah terima; material boleh sebagian, ber-SN per SN) menjadi **barang jadi** (tidak ber-SN) di gudang branch yang sama sebagai stok milik project. Produk barang jadi dari master (harus Material) atau dibuat baru di form. Harga modal = Σ material ÷ qty; biaya project tidak bertambah. Batal produksi (`can_approve`) selama barang jadi belum dipakai.

### Finance (Hutang Supplier & Piutang Customer)

36. **Invoice** — `InvoiceController`
    - Satu project bisa punya beberapa invoice (DP, termin, pelunasan), nomor `INV-YYMMNNNN`.
    - Item invoice memakai deskripsi wajib (mendukung line break/format paste, disanitasi server); baris bisa ditambah manual atau dari quote; `total_price = qty × unit_price` dihitung server.
    - **Ambil dari Quote** membuka modal pilihan item; hanya item tercentang yang masuk invoice. Part number quote dicocokkan ke `master_products.code`; jika tidak ada, item tetap bebas dengan `product_id` null.
    - `amount_payment = subtotal × percent_payment / 100`; `ppn` disimpan sebagai persen dan dihitung dari amount payment; `total_payment = amount_payment + nilai PPN`.
    - Approval terpisah dari status tagihan: `approval_status` = `draft` → `waiting_approval` → `approved`/`rejected`; approve/reject hanya untuk user dengan `can_approve`.
    - Edit/hapus hanya saat `draft`/`rejected`. Submit ulang setelah reject membersihkan metadata approval.
    - Status tagihan: `unpaid`, `paid`, `cancelled` (default `unpaid`).
    - PDF invoice bisa dicetak semua status; watermark status approval muncul sampai invoice `approved`.
37. **Penerimaan Customer** — `CustomerReceiptController`: penerimaan pembayaran piutang; approve/void oleh approver; upload dokumen; endpoint `customer-receipt.open-invoices` memilih invoice terbuka.
38. **Laporan Piutang** — `ReceivableReportController` + `App\Support\ReceivableReport`: per customer & per project (posisi termin: terkunci, siap ditagih, dalam proses, piutang, lunas), umur piutang, perkiraan penerimaan, export. Pengingat WhatsApp piutang diatur di Notifikasi Finance.
39. **Tagihan Supplier** — `SupplierBillController`: 3-way match (PO × Penerimaan × Tagihan), uang muka & potongan otomatis, biaya tambahan, PPh tarif bebas + bukti potong, faktur pajak wajib untuk PKP.
40. **Pembayaran Supplier** — `SupplierPaymentController`: sebagian / banyak tagihan sekaligus, kurs valas, posting & void oleh approver.
41. **Refund Supplier** — `SupplierRefundController`: pengembalian uang muka PO batal / barang kurang.
42. **Laporan Hutang** — `PayableReportController` + `App\Support\PayableReport`: per supplier, umur hutang, jadwal bayar, biaya per project (komitmen PO/DP + alokasi stok) vs Estimasi PL, export Excel, pengingat WhatsApp (`PayableDueNotifier`).

### Employee Relations (HR)

43. **Data Karyawan** — `EmployeeManagementController`
    - CRUD karyawan (data personal, kepegawaian, BPJS/pajak, gaji/bank), keluarga, terminate/reactivate (tanpa hard delete).
    - Tab Komponen Gaji: CRUD override `employee_salary_components` per karyawan.

44. **Absensi** — `AttendanceController`
    - Absensi manual, import device fingerprint (CSV/XLSX), approve/reject, peta mini + foto, timeline approval.
    - Master Office (geofence GPS) & Shift. Koreksi absensi (`attendance_corrections`) submit → approve/reject.

45. **Pengajuan Cuti/Izin** — `LeaveRequestController`
    - Jenis cuti (Tahunan/Izin/Sakit/Unpaid/Special), perhitungan hari kerja otomatis (Sen–Jum), saldo cuti, approve/reject/revise/cancel.
    - Approve → auto-create attendance (`leave_integration`).

46. **Pengajuan Lembur** — `OvertimeRequestController`
    - Jam lembur dihitung otomatis dari absensi (shift end → jam pulang); estimasi nilai `(base ÷ 173) × jam × multiplier` (rate di `config/er.php`).
    - Approval dengan koreksi multiplier; integrasi payroll (baris `payslip_components` type overtime).

47. **Peminjaman (Kas Bon)** — `LoanController`
    - Pinjaman + jadwal angsuran otomatis setelah approve; bayar manual per angsuran, settle early; potong otomatis di payroll.

48. **Penggajian / Payroll** — `PayrollController`
    - Periode cutoff **25 → 24** bulan berikut (anti-overlap), `is_thr` otomatis (bulan THR config).
    - Wizard 3 langkah: Compile → Draft (editor komponen) → Finalize/Lock.
    - Engine `PayrollService`: prorata gaji, komponen monthly/daily (basis attended_days), BPJS (employee + company share), PPH21 (ptk_status), potongan angsuran loan & unpaid leave.
    - Export Excel (phpspreadsheet) & PDF slip (dompdf), log periode (`payroll_logs`).

### Lainnya

49. **Notifications** — `NotificationController`
    - Bell notifikasi dengan badge unread (polling 30 dtk) dan dropdown ber-group per entitas; halaman `/notifications/all` dengan pagination 20/halaman.
    - `count()`, `index()`, `all()`, `markAsRead()`, `markAllRead()`.

50. **Profile** — `ProfileController`
    - Edit profil, ganti password, hapus foto.

## Alur Bisnis Utama

```
Lead (New → Approach → Qualified)
  → Converted → Opportunity (stage, forecast)
  → Task Quote dibuat (kategori Quote + handling group lintas divisi)
  → Quote Configuration per divisi dibuat & di-approve (Water/IMS/Enviro/IH/Gas)
  → Quotation dibuat dari konfigurasi approved → submit → approve → PDF
  → Estimasi PL (Profit & Loss)
  → Buat Project + upload PO customer + timeline penagihan → quotation finish
  → PO Supplier Approval (kuorum 2) → Opportunity otomatis Closed Won
  → Pembelian: Purchase Request → Purchase Order, atau Direct Purchase
  → Penerimaan Barang (stok bebas / milik project, SN)
  → (opsional) Produksi: Material Request → Serah Terima → Produksi barang jadi
  → (opsional) Packing List per koli → Siap Kirim
  → Delivery Order (Langsung / Dengan Packing List) → posting, stok keluar
  → Invoice per termin → Penerimaan Customer
  → Pantau seluruhnya di Project Tracking; Dashboard Achievement
```

Setiap tahap mencatat log audit dan mengirim notifikasi ke pihak terkait.

## Testing

```bash
composer test        # php artisan test (PHPUnit 11)
```

70 test feature + 2 test unit, antara lain: `QuotationTest`, `WaterConfigurationApprovalTest`, `ImsConfigurationApprovalTest`, `EnviroConfigurationApprovalTest`, `GasConfigurationApprovalTest`, `IhConfigurationApprovalTest`, `ProfitEstimateTest`, `ProjectTest`, `ProjectBillingTimelineTest`, `ProjectTrackingTest`, `PoSupplierApprovalTest`, `GoodsRequestTest`, `PurchaseOrderTest`, `PurchaseDocumentTest`, `PurchasingInventoryTest`, `PurchasingListFilterTest`, `StockInventoryTest`, `StockLedgerTest`, `StockOpnameTest`, `BranchTransferTest`, `ProductionFlowTest`, `PackingListTest`, `DeliveryOrderTest`, `DocumentNumberTest`, `InvoiceTest`, `CustomerReceiptTest`, `ReceivableReportTest`, `SupplierTest`, `SupplierBillTest`, `SupplierPaymentTest`, `SupplierAdvanceRefundTest`, `SupplierForeignCurrencyTest`, `SupplierTaxReturnTest`, `PayableReportTest`, `PayableDueReminderTest`, `FinanceNotificationTest`, `ProductManagementPageTest`, `ProductPriceAccessTest`, `CheckAccessControlTest`, `SidebarMenuTest`, `DashboardVisitScheduleTest`, `TaskPlannerProjectTest`; unit: `NumberFormatTest`.

## Dokumentasi Lain

- `docs/wiki/` — Code Wiki: arsitektur, tanggung jawab modul, data model, kelas/fungsi kunci, dependency, dan cara menjalankan project.
- `PANDUAN_MODUL.md` — aturan baku menambah modul baru (route naming, gate middleware, flag UI composer, approval workflow) supaya konsisten.
- `RENCANA_PACKING_LIST.md` — keputusan & rancangan Packing List dan Delivery Order dua jalur (sudah diimplementasi).
- `RENCANA_PROJECT_TRACKING.md` — keputusan & rancangan Project Tracking (sudah diimplementasi).
- `NOTIFICATION_SYSTEM.md` — dokumentasi teknis sistem notifikasi.
- `NOTIFICATION_QUICK_TEST.md` — panduan test cepat notifikasi.
- `employee-management.md` — acuan build modul Employee Relations (karyawan, absensi, cuti, lembur, pinjaman, payroll).
- `SIMULASI_ALUR.md` — SOP simulasi end-to-end (Lead → Quotation → PO Supplier Approval → Dashboard Achievement).
- `desaindatabases.md` — skema awal tabel inti CRM (34 tabel awal); modul lain didokumentasikan di dokumen masing-masing.

## License

Belum tersedia — tidak ada file `LICENSE` di repository.

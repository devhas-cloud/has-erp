# HAS-ERP

Web aplikasi ERP/CRM berbasis **Laravel 12** yang mengelola proses bisnis dari **Lead → Opportunity → Task Quote → Quote Configuration (per divisi) → Quotation → Purchase Order**, lengkap dengan manajemen akun & kontak, task planner dengan approval flow, notifikasi (polling 30 detik), kontrol akses per-modul, modul **Employee Relations** (HR), **Purchasing**, dan modul konfigurasi teknis ber-versi (Water, IMS, Enviro, IH, Gas).

## Teknologi

| Komponen | Teknologi |
|---|---|
| Framework | Laravel 12 (PHP ^8.2) |
| Database | Eloquent ORM — default `sqlite` di `.env.example`, mendukung MySQL |
| Session / Queue / Cache | driver `database` |
| Frontend | Blade + Bootstrap 5 + jQuery + DataTables (server-side) + Select2 + toastr + SweetAlert2; aset dibundel via Vite + Tailwind |
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

Konfigurasi Employee Relations (rate BPJS, cutoff payroll, THR, multiplier lembur) ada di `config/er.php` dan bisa dioverride lewat env.

## Arsitektur

```
app/
├── Console/Commands/       # SendTaskAlerts, SendPayableDueReminders, SendReceivableDueReminders
├── Http/
│   ├── Controllers/        # 52 controller modul
│   └── Middleware/         # CheckAccessControl (ACL per-modul)
├── Models/                 # 106 model Eloquent
├── Observers/              # TaskObserver, TaskActivityObserver, ActivityObserver
├── Providers/              # AppServiceProvider (registrasi observer + view composer ACL)
├── Services/               # 21 service: import/export XLSX, WhatsApp, alert, payroll, mention
├── Support/                # ModuleAccess, ConfigurationTemplateHandling, TaskWorkflowLogger
└── Traits/                 # Loggable, Notifiable
```

- **Autentikasi**: login berbasis session (`AuthController`) memakai **username**, password hash, regenerasi session saat login.
- **Otorisasi**: middleware `access.control` (`CheckAccessControl`) + view composer global — tiap user punya `UserAccessControl` per modul (`can_create/read/update/delete/approve`). Admin (role `Admin`) bypass semua cek.
- **Mapping method → permission** (otomatis, dari segmen terakhir nama route): `approve/reject/unlock/settle/pay/finalize/void` → `can_approve`; `revise` → `can_update`; `POST` → `can_create`; `PUT/PATCH` → `can_update`; `DELETE` → `can_delete`; sisanya → `can_read`.
- **Modul = segmen pertama route name** (potong di titik pertama), dipetakan ke tabel `modules` (30 modul ter-seed di `ModulesTableSeeder`).
- **Audit trail**: model `Log` + trait `Loggable` + helper `Log::record(action, description, moduleCode, loggable)`.
- **Notifikasi**: tabel `notifications`, polling 30 detik di frontend, dikelompokkan per entitas (task/lead/opportunity/quotation). Tipe: `task_assigned`, `task_status_changed`, `task_activity`, `task_approval_required`, `task_approved`, `task_opened`, `mention`, `visit_recorded`, `quotation_approval_required`, `quotation_approved`, `quotation_rejected`.
- **Roles**: `task_roles` (`hierarchy_level`, `is_global_delegator`) menentukan scope data yang bisa dilihat user; role Sales dibatasi ke data miliknya.
- **Scheduler** (`routes/console.php`):
  - `TaskAlertService::sendAlerts()` tiap hari 08:00 — reminder task (`php artisan task:send-alerts`).
  - `PayableDueNotifier::send()` tiap 5 menit — pengingat hutang supplier WhatsApp (`php artisan finance:send-due-reminders`).
  - `ReceivableDueNotifier::send()` tiap 5 menit — pengingat piutang customer WhatsApp (`php artisan finance:send-receivable-reminders`).

## Modul & Fungsi

### Master Data

1. **User Management** — `UserManagementController`
   - CRUD user (username, email, password, divisi, task role).
   - Assign ACL per-modul saat create/update: can_create/read/update/delete/approve.
   - Listing DataTables server-side dengan search & filter divisi.

2. **Configuration (Master Data generik)** — `ConfigurationController`
   - **CRUD generik berbasis konfigurasi** (`$config`) untuk ±18 tabel referensi: Divisi, Job Title, Source, Contact Method, Segmentation, Business Entity, Business Value, Role In Project, Interaction Level, Account Type, Types Account Company, Task Role, Task Category, Forecast, Loss Reason, Stage, WhatsApp Group, Division Handler.
   - Endpoint dinamis: `/configuration/{table}` → list/store/update/destroy.

### Sidebar

- Urutan menu **tidak** alfabet: grup diurutkan lewat `module_groups.sort_order`, modul di dalam grup lewat `modules.sort_order`.
- Grup yang belum terdaftar di `module_groups` tetap tampil, di urutan paling akhir.
- Grup `Admin`: Quotation → Estimasi PL → Project → Invoice → PO Supplier Approval.
- Logika pengambilan menu ada di `app/Support/SidebarMenu.php` (dipakai sidebar, halaman pertama setelah login, dan form User Management).

3. **Product Management** — `ProductManagementController`
   - CRUD produk: nama, kode unik, brand, kategori, divisi, harga, gambar, status.
   - Export XLSX (builder OOXML manual), import CSV (auto-detect delimiter, upsert by code, validasi per baris), template import + sheet referensi.

4. **Currency Management** — `CurrencyController`
   - CRUD mata uang + nilai kurs konversi.

5. **Supplier** — `SupplierController`
   - CRUD master supplier, menjadi sumber pilihan supplier pada Purchase Order.

5a. **Notifikasi Finance** — `FinanceNotificationController`
    - Satu tempat mengatur pengingat WhatsApp **hutang supplier** & **piutang customer**: aktif/nonaktif, jam kirim, tujuan.
    - Endpoint `PATCH finance-notification/{type}` (simpan, `can_update`) dan `finance-notification/{type}/send`.

### CRM

6. **Account Management** — `AccountManagementController`
   - CRUD account/perusahaan: data kontak, alamat billing & shipping, segmentation, business entity/value, interaction level, tipe perusahaan, parent account (hierarki), flag end-user.
   - Import/Export XLSX. Search & sorting DataTables.

7. **Contact Management** — `ContactManagementController`
   - CRUD kontak personal yang terhubung ke account: salutation, email, phone/mobile, job title, source, role in project, contact method, divisi, owner.
   - Import/Export XLSX. Filter per account, search lintas kolom, buat lead/task dari kontak.

8. **Leads Management** — `LeadsManagementController`
   - Pipeline: **New → Approach → Qualified → Converted** / Unqualified.
   - Auto-create contact & company saat membuat lead; update ber-transaksi.
   - `markQualified`, `markUnqualified` (wajib alasan + tanggal closed), `markConverted` (membuat Opportunity terkait).
   - **Import lead dari CSV** (template XLSX 33 kolom via `LeadImportService`) + download template.
   - Aktivitas/komentar: reply thread, lampiran (maks 10), **@mention** (`MentionParser`) → notifikasi. Buat task dari lead.
   - Scope data khusus role Sales (hanya owner/assignee).

9. **Opportunity Management** — `OpportunityManagementController`
   - Pipeline deal: stage (`New → Proposal & Quote → In Review → Negotiation → Closed Won/Closed Lost`), forecast, probability, budget/authorize/timeline, loss reason, close date, next step, end user.
   - Auto-fill dari lead; link account & contact; division handler untuk penanganan.
   - Alur negosiasi & close-loss dengan approval; aktivitas + lampiran + mention; buat task (assignee otomatis ditambah roster handling group).
   - Stage `Closed Won` di-set otomatis oleh PO Supplier Approval (kuorum 2). Scope data: role Sales hanya melihat opportunity miliknya.

10. **Task Planner** — `TaskPlannerController`, `DashboardTaskPlannerController`
    - CRUD task: kategori, WhatsApp group, due date/time, alert (email/WhatsApp), assignee (banyak), requires_approval.
    - **Status workflow**: `todo → in_progress → waiting_approval → done`, plus approve/reject oleh creator.
    - **Enforcement berbasis kategori**: kategori visit wajib rekam lokasi GPS sebelum done; kategori proposal wajib upload PDF proposal.
    - **Kunjungan (visit)**: rekam lokasi lat/lng + alamat, notifikasi `visit_recorded`.
    - **Proposal**: upload PDF ber-version (auto increment), streaming view.
    - Aktivitas: komentar + reply + lampiran + mention. Export XLSX + import XLSX/CSV dengan template & sheet referensi.
    - **Dashboard** (`dashboard-task-planner`): ringkasan total/todo/in-progress/done/overdue, 10 task terdekat, progress per kategori.
    - **Reminder otomatis**: `SendTaskAlerts` (artisan + scheduler) mengirim pesan WhatsApp H-1 dan hari-H via waBaileys, lalu menandai `is_alert_sent`.
    - **Dashboard Visit Planner** (`DashboardVisitPlannerController`): jadwal visit per hari kerja (Sen–Jum).

### Konfigurasi Teknis (ber-versi, per divisi)

Format sama (approval workflow, versioning) untuk **5 divisi**: **Water**, **IMS**, **Enviro**, **IH**, **Gas** — controller: `WaterConfigurationController`, `ImsConfigurationController`, `EnviroConfigurationController`, `IhConfigurationController`, `GasConfigurationController`.

- Konfigurasi teknis (breakdown item produk) untuk task quotation divisi terkait, satu level di bawah Quotation.
- Header di-derive dari task/opportunity/lead (to_name, lokasi, PIC, sales).
- **Item hierarki parent–child**; harga item dari MasterProduct.
- **Approval workflow**: draft → submit → waiting_approval → approve/reject; approve hanya oleh approver divisi yang sama (bukan creator), admin override.
- **Versioning**: `group_id` + `version` + `is_current`; approve/revise meng-archive versi lama. `unlock` (approver membuka kunci revisi) dan `revise` (salin header+item ke versi baru, remap parent_id).
- **Template**: konfigurasi versi terbaru tiap group bisa dipakai sebagai template prefill (DFS order) — tersedia CRUD template + PDF + duplikat.
- **PDF** A4 via DOMPDF; audit log di setiap transisi status.

> Perbedaan **IMS**: item membawa price/unit langsung dari input user (bukan lookup MasterProduct), qty wajib ≥ 1, `parameter_note` opsional. Divisi lain mengikuti pola Water.

### Admin / Penawaran

11. **Quotation** — `QuotationController`
    - Dokumen quotation final dari task: prefill dari **semua konfigurasi yang sudah approved** (semua divisi).
    - **3 tab item**: List Items (utama), List Configuration (snapshot item konfigurasi), Cost/Biaya (tidak mempengaruhi subtotal). Item hierarki parent–child; deskripsi disanitasi HTML (`<b><i><u><br>`).
    - **Mesin kalkulasi total** (`Quotation::calculateTotals`): subtotal = Σ(qty×price) → diskon (% / manual) → DPP → PPN (11%) → grand total.
    - **Nomor quotation otomatis**: `{seq:03d}/HAS/QT-{inisial}/{bulan romawi}/{tahun}`.
    - Approval workflow submit/approve/reject/unlock/revise + versioning.
    - Gating: quotation hanya bisa dibuat jika semua konfigurasi task sudah approved; satu task satu quotation (current).
    - **Upload PO** customer dipindahkan ke modul **Project**; project menandai quotation `finish` (memicu PO Supplier Approval).
    - **PDF utama + PDF cost** terpisah; template item & template cost; T&C default, diskon/PPN fleksibel.

12. **Project** — `ProjectController`
    - Dibuat dari quotation `approved`/`finish` yang sudah punya Profit Estimate; satu quotation boleh menjadi beberapa project.
    - Nomor project otomatis `YYMM` + 4 digit urut mulai `0001`, urut per tahun seperti invoice; contoh `26100001`.
    - Menyimpan PO customer + menandai quotation `finish` (pengganti upload PO di modul Quotation).
    - `total_sale_amount` read-only dari `quotation.grand_total`; `net_sale_amount` dari `profit_estimates.real_project_value`.
    - Sales dipilih di form project; net dibagi sama rata (sisa pembulatan ke sales terakhir). Rincian achievement per device dikelola terpisah di tab **Achievements**.
    - Project lama tanpa quotation bisa diinput manual.

12a. **IMS Project (sementara)** — `ImsProjectController`
    - Modul sementara grup IMS: input project hybrid tanpa quotation (nilai 0, tanpa sales) — simpan PO, dokumen, dan garansi.
    - Gate akses via middleware `access.control` (route_name `ims-project`).

13. **Invoice** — `InvoiceController`
    - Satu project bisa punya beberapa invoice (DP, termin, pelunasan).
    - Nomor invoice otomatis `YYMM` + 4 digit urut mulai `0001`, urut per tahun; contoh `26100001`.
    - Item invoice memakai deskripsi wajib (mendukung line break/format paste, disanitasi server); baris bisa ditambah manual atau dari quote; `total_price = qty × unit_price` dihitung server.
    - **Ambil dari Quote** membuka modal pilihan item; hanya item tercentang yang masuk invoice. Part number quote dicocokkan ke `master_products.code`; jika tidak ada, item tetap bebas dengan `product_id` null.
    - `amount_payment = subtotal × percent_payment / 100`; `ppn` disimpan sebagai persen dan dihitung dari amount payment; `total_payment = amount_payment + nilai PPN`.
    - Approval terpisah dari status tagihan: `approval_status` = `draft` → `waiting_approval` → `approved`/`rejected`; approve/reject hanya untuk user dengan `can_approve`.
    - Edit/hapus hanya saat `draft`/`rejected`. Submit ulang setelah reject membersihkan metadata approval.
    - Status tagihan: `unpaid`, `paid`, `cancelled` (default `unpaid`).
    - PDF invoice bisa dicetak semua status; watermark status approval muncul sampai invoice `approved`.

14. **Estimasi PL (Profit & Loss)** — `ProfitEstimateController`
    - Estimasi pendapatan & biaya (profit/loss) per quotation; `sync` mengambil ulang data sumber, export PDF.

15. **PO Supplier Approval** — `PoSupplierApprovalController`
    - Approval per **project** (PO customer diupload saat project dibuat) agar purchasing boleh lanjut ke PO supplier.
    - **Kuorum 2 approver berbeda**; saat penuh, opportunity dari quotation project otomatis menjadi `Closed Won`.

16. **Dashboard Achievement** — `DashboardAchievementController`
    - Total achievement & brand terjual per divisi dari quotation stage Closed Won / Finish.

### Purchasing & Inventory

Rincian lengkap (alur, aturan, hak akses, tabel): **`PANDUAN_PURCHASING_FINANCE.md`**.

17. **Permintaan Barang** — `GoodsRequestController`: tujuan project / stok-internal, item wajib master produk (dari configuration atau katalog), approval 2 approver, Tutup Sisa, nomor `YYMM`+4 digit.
18. **Purchase Order** — `PurchaseOrderController`: hanya dari item Permintaan Approved, satu mata uang, termin bayar, approve di detail, Batalkan PO (`purchase-order.void`), Revisi PO Rejected (`…REV`), PDF.
19. **Direct Purchase** — `DirectPurchaseController`: pembelian tanpa PO; saat Approved tagihan supplier dibuat otomatis.
20. **Penerimaan Barang / Retur ke Supplier** — `GoodsReceiptController`, `SupplierReturnController`: masuk gudang atau langsung pakai; retur mengurangi qty diterima & stok.
21. **Inventory** — Gudang, Pemenuhan dari Stok, Transfer Stok, Masuk Stok Lain-lain, Delivery Order, Stock Opname, Laporan & Kartu Stok; valuasi rata-rata bergerak (`StockMovement`, `StockValuation`).

### Finance (Hutang Supplier & Piutang Customer)

22. **Tagihan Supplier** — `SupplierBillController`: 3-way match (PO × Penerimaan × Tagihan), uang muka & potongan otomatis, biaya tambahan, PPh tarif bebas + bukti potong, faktur pajak wajib untuk PKP.
23. **Pembayaran Supplier** — `SupplierPaymentController`: sebagian / banyak tagihan sekaligus, kurs valas, posting & void oleh approver.
24. **Refund Supplier** — `SupplierRefundController`: pengembalian uang muka PO batal / barang kurang.
25. **Laporan Hutang** — `PayableReportController` + `App\Support\PayableReport`: per supplier, umur hutang, jadwal bayar, biaya per project vs Estimasi PL, export Excel, pengingat WhatsApp harian (`PayableDueNotifier`).

25a. **Penerimaan Customer** — `CustomerReceiptController`
    - Penerimaan pembayaran piutang customer; approve/void oleh approver; upload dokumen.
    - Endpoint `customer-receipt.open-invoices` memilih invoice terbuka yang akan dibayar.

25b. **Laporan Piutang** — `ReceivableReportController`
    - Per customer: umur piutang, sisa tagihan, export. Pengingat WhatsApp piutang diatur di modul Notifikasi Finance.

### Employee Relations (HR)

26. **Data Karyawan** — `EmployeeManagementController`
    - CRUD karyawan (data personal, kepegawaian, BPJS/pajak, gaji/bank), keluarga, terminate/reactivate (tanpa hard delete).
    - Tab Komponen Gaji: CRUD override `employee_salary_components` per karyawan.

27. **Absensi** — `AttendanceController`
    - Absensi manual, import device fingerprint (CSV/XLSX), approve/reject, peta mini + foto, timeline approval.
    - Master Office (geofence GPS) & Shift. Koreksi absensi (`attendance_corrections`) submit → approve/reject.

28. **Pengajuan Cuti/Izin** — `LeaveRequestController`
    - Jenis cuti (Tahunan/Izin/Sakit/Unpaid/Special), perhitungan hari kerja otomatis (Sen–Jum), saldo cuti, approve/reject/revise/cancel.
    - Approve → auto-create attendance (`leave_integration`).

29. **Pengajuan Lembur** — `OvertimeRequestController`
    - Jam lembur dihitung otomatis dari absensi (shift end → jam pulang); estimasi nilai `(base ÷ 173) × jam × multiplier` (rate di `config/er.php`).
    - Approval dengan koreksi multiplier; integrasi payroll (baris `payslip_components` type overtime).

30. **Peminjaman (Kas Bon)** — `LoanController`
    - Pinjaman + jadwal angsuran otomatis setelah approve; bayar manual per angsuran, settle early; potong otomatis di payroll.

31. **Penggajian / Payroll** — `PayrollController`
    - Periode cutoff **25 → 24** bulan berikut (anti-overlap), `is_thr` otomatis (bulan THR config).
    - Wizard 3 langkah: Compile → Draft (editor komponen) → Finalize/Lock.
    - Engine `PayrollService`: prorata gaji, komponen monthly/daily (basis attended_days), BPJS (employee + company share), PPH21 (ptk_status), potongan angsuran loan & unpaid leave.
    - Export Excel (phpspreadsheet) & PDF slip (dompdf), log periode (`payroll_logs`).

### Lainnya

32. **Notifications** — `NotificationController`
    - Bell notifikasi dengan badge unread (polling 30 dtk) dan dropdown ber-group per entitas; halaman `/notifications/all` dengan pagination 20/halaman.
    - `count()`, `index()`, `all()`, `markAsRead()`, `markAllRead()`.

33. **Profile** — `ProfileController`
    - Edit profil, ganti password, hapus foto.

## Alur Bisnis Utama

```
Lead (New → Approach → Qualified)
  → Converted → Opportunity (stage, forecast)
  → Task Quote dibuat (kategori Quote + handling group lintas divisi)
  → Quote Configuration per divisi dibuat & di-approve (Water/IMS/Enviro/IH/Gas)
  → Quotation dibuat dari konfigurasi approved → submit → approve → PDF
  → Estimasi PL (Profit & Loss)
  → Buat Project + upload PO customer → status finish
  → Buat Invoice project (DP/termin/pelunasan)
  → PO Supplier Approval (kuorum 2) → Opportunity otomatis Closed Won
  → Purchase Order ke supplier (dari item Permintaan Barang approved)
  → Dashboard Achievement
```

Setiap tahap mencatat log audit dan mengirim notifikasi ke pihak terkait.

## Testing

```bash
composer test        # php artisan test (PHPUnit 11)
```

60 test feature + 2 test unit (mis. `QuotationTest`, `WaterConfigurationApprovalTest`, `ImsConfigurationApprovalTest`, `EnviroConfigurationApprovalTest`, `GasConfigurationApprovalTest`, `IhConfigurationApprovalTest`, `PurchaseOrderTest`, `GoodsRequestTest`, `PoSupplierApprovalTest`, `ProfitEstimateTest`, `SupplierTest`, `SupplierBillTest`, `SupplierPaymentTest`, `SupplierAdvanceRefundTest`, `CustomerReceiptTest`, `PayableReportTest`, `ReceivableReportTest`, `FinanceNotificationTest`, `PayableDueReminderTest`, `CurrencyManagementTest`, `AccountManagementTest`, `ContactManagementTest`, `LeadsManagementTest`, `DashboardAchievementTest`, `TaskPlannerQuoteGateTest`, `ImsProjectTest`, `StockInventoryTest`, `DeliveryOrderTest`, `StockOpnameTest`, `CheckAccessControlTest`, `SidebarMenuTest`; unit: `NumberFormatTest`).

## Dokumentasi Lain

- `PANDUAN_PURCHASING_FINANCE.md` — acuan Purchasing, Inventory & Finance (hutang supplier): alur, aturan, hak akses, tabel, jadwal, test, dan **tahap lanjutan yang belum dikerjakan**.
- `PANDUAN_MODUL.md` — aturan baku menambah modul baru (route naming, gate middleware, flag UI composer, approval workflow) supaya konsisten.
- `NOTIFICATION_SYSTEM.md` — dokumentasi teknis sistem notifikasi.
- `employee-management.md` — acuan build modul Employee Relations (karyawan, absensi, cuti, lembur, pinjaman, payroll).
- `SIMULASI_ALUR.md` — SOP simulasi end-to-end (Lead → Quotation → PO Supplier Approval → Dashboard Achievement).
- `desaindatabases.md` — skema awal tabel inti CRM (34 tabel awal); modul ER/Quotation/Purchasing didokumentasikan di dokumen modul masing-masing.
- `NOTIFICATION_QUICK_TEST.md` — panduan test cepat notifikasi.

## License

Belum tersedia — tidak ada file `LICENSE` di repository.

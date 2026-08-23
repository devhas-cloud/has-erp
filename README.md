# HAS-ERP

Web aplikasi ERP/CRM berbasis **Laravel 12** yang mengelola proses bisnis dari **Lead → Opportunity → Task/Quote → Quotation**, lengkap dengan manajemen akun & kontak, task planner dengan approval flow, notifikasi real-time, kontrol akses per-modul, serta modul konfigurasi teknis (IMS & WATER) yang menghasilkan dokumen quotation ber-versi.

## Teknologi

| Komponen | Teknologi |
|---|---|
| Framework | Laravel 12 (PHP ^8.2) |
| Database | MySQL (via Eloquent ORM) |
| Frontend | Blade + Bootstrap, DataTables (server-side), vanilla JS |
| PDF | barryvdh/laravel-dompdf |
| Spreadsheet | phpoffice/phpspreadsheet (import), XLSX builder manual (export) |
| WhatsApp | Evolution API (HTTP client) |
| Testing | PHPUnit 11 + Pest plugin |

## Setup

```bash
composer install
cp .env.example .env   # lalu isi konfigurasi database & Evolution API
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Alternatif satu perintah: `composer setup`.

Perintah pengembangan (`composer dev`) menjalankan server, queue worker, log pail, dan Vite sekaligus.

## Arsitektur

```
app/
├── Console/Commands/       # SendTaskAlerts (reminder WhatsApp terjadwal)
├── Http/
│   ├── Controllers/        # 15 controller modul
│   └── Middleware/         # CheckAccessControl (ACL per-modul)
├── Models/                 # 42 model Eloquent
├── Observers/              # TaskObserver, TaskActivityObserver, ActivityObserver
├── Providers/              # AppServiceProvider (registrasi observer + view composer ACL)
├── Services/               # Import/Export XLSX, WhatsApp, Alert, Mention
└── Traits/                 # Loggable, Notifiable
```

- **Autentikasi**: login berbasis session (`AuthController`), password hash, regenerasi session saat login.
- **Otorisasi**: middleware `access.control` + view composer global — tiap user punya `UserAccessControl` per modul (`can_create/read/update/delete/approve`). Admin bypass semua cek. Mapping method HTTP → permission otomatis (GET=read, POST=create, PUT/PATCH=update, DELETE=delete, approve/reject/unlock=approve).
- **Audit trail**: model `Log` + trait `Loggable` + helper `Log::record(action, description, moduleCode, loggable)` — dipakai di Task, Lead, Opportunity, Quote Configuration, dan Quotation.
- **Notifikasi**: tabel `notifications`, polling 30 detik di frontend, dikelompokkan per entitas (task/lead/opportunity), dibuat via observer dan controller.
- **Roles**: `task_roles` dengan `hierarchy_level` dan `is_global_delegator` — menentukan scope data yang bisa dilihat user.

## Modul & Fungsi

### 1. User Management (Manajemen User)
Controller: `UserManagementController`

- CRUD user (username, email, password, divisi, task role).
- **Assign ACL per-modul** saat create/update: can_create, can_read, can_update, can_delete, can_approve.
- Listing DataTables server-side dengan search & filter divisi.

### 2. Configuration (Master Data)
Controller: `ConfigurationController`

- **CRUD generik berbasis konfigurasi** (`$config` array) untuk ±18 tabel referensi: Divisi, Job Title, Source, Contact Method, Segmentation, Business Entity, Business Value, Role In Project, Interaction Level, Account Type, Types Account Company, Task Role, Task Category, Forecast, Loss Reason, Stage, WhatsApp Group, dan Division Handler.
- Endpoint dinamis: `/configuration/{table}` → list/store/update/destroy.

### 3. Account Management (Manajemen Perusahaan)
Controller: `AccountManagementController`

- CRUD account/perusahaan: data kontak, alamat billing & shipping, segmentation, business entity/value, interaction level, tipe perusahaan, parent account (hierarki), flag end-user.
- Search & sorting DataTables.

### 4. Contact Management (Manajemen Kontak)
Controller: `ContactManagementController`

- CRUD kontak personal yang terhubung ke account: salutation, email, phone/mobile, job title, source, role in project, contact method, divisi, owner.
- Filter per account, search lintas kolom.

### 5. Leads Management (Manajemen Lead)
Controller: `LeadsManagementController`

- Pipeline: **New → Approach → Qualified → Converted** / Unqualified.
- Auto-create contact & company saat membuat lead; update ber-transaksi.
- `markQualified`, `markUnqualified` (wajib alasan + tanggal closed), `markConverted` (membuat Opportunity terkait, stage awal).
- **Import lead dari CSV** (template XLSX 33 kolom via `LeadImportService`) dan download template.
- Aktivitas/komentar: reply thread, lampiran (maks 10), **@mention** (`MentionParser`) → notifikasi.
- Buat task dari lead; scope data khusus role Sales (hanya owner/assignee).

### 6. Opportunity Management (Manajemen Opportunity)
Controller: `OpportunityManagementController`

- Pipeline deal: stage, forecast, probability, budget/authorize/timeline, loss reason, close date, next step, end user.
- Auto-fill dari lead; link account & contact; division handler untuk penanganan.
- Aktivitas + lampiran + mention; buat task (assignee otomatis ditambah roster division handler).
- Scope data: role Sales hanya melihat opportunity miliknya.

### 7. Task Planner
Controller: `TaskPlannerController`, `DashboardTaskPlannerController`

- CRUD task: kategori, WhatsApp group, due date/time, alert (email/WhatsApp), assignee (banyak), requires_approval.
- **Status workflow**: `todo → in_progress → waiting_approval → done`, plus approve/reject oleh creator.
- **Enforcement berbasis kategori**: kategori visit wajib rekam lokasi GPS sebelum done; kategori proposal wajib upload PDF proposal.
- **Approval flow**: assignee selesai → `waiting_approval` → creator approve → `done`. Auto-upgrade status lead terkait New→Approach saat task selesai.
- **Kunjungan (visit)**: rekam lokasi lat/lng + alamat, notifikasi `visit_recorded`.
- **Proposal**: upload PDF ber-version (auto increment), streaming view.
- Aktivitas: komentar + reply + lampiran + mention.
- **Export XLSX** (manual OOXML builder), **Import XLSX/CSV** dengan template + sheet referensi.
- **Dashboard**: ringkasan total/todo/in-progress/done/overdue, 10 task terdekat, progress per kategori.
- **Reminder otomatis**: `SendTaskAlerts` (artisan + scheduler) mengirim pesan WhatsApp H-1 dan hari-H via Evolution API, lalu menandai `is_alert_sent`.

### 8. Product Management (Master Produk)
Controller: `ProductManagementController`

- CRUD produk: nama, kode unik, brand, kategori, divisi, harga, gambar, status.
- **Export XLSX** (tanpa library spreadsheet — builder OOXML manual).
- **Import CSV** dengan auto-detect delimiter, upsert by code, validasi per baris.
- Template import XLSX + sheet referensi.

### 9. Water Configuration (Konfigurasi Teknis Air)
Controller: `WaterConfigurationController`

- Konfigurasi teknis (breakdown item produk) untuk task quotation divisi **WATER**, satu level di bawah Quotation.
- Header di-derive dari task/opportunity/lead (to_name, lokasi, PIC, sales).
- **Item hierarki parent–child**; harga item diambil dari MasterProduct.
- **Approval workflow**: draft → submit → waiting_approval → approve/reject; approve hanya oleh approver divisi yang sama (bukan creator), admin override.
- **Versioning**: `group_id` + `version` + `is_current`; approve/revise meng-archive versi lama.
- `unlock` (approver membuka kunci untuk revisi) dan `revise` (salin header+item ke versi baru, remap parent_id).
- **Template**: konfigurasi versi terbaru tiap group bisa dipakai sebagai template prefill (DFS order).
- **PDF** A4 via DOMPDF; audit log di setiap transisi status.

### 10. IMS Configuration (Konfigurasi Teknis IMS)
Controller: `ImsConfigurationController`

- Identik dengan Water Configuration untuk divisi **IMS** (model data sama: `QuoteConfiguration`).
- Perbedaan: item membawa price/unit langsung dari input user (bukan lookup MasterProduct), qty wajib ≥ 1, `parameter_note` opsional.
- Workflow submit/approve/reject/unlock/revise, versioning, template, PDF, dan audit log yang sama.

### 11. Quotation (Pembuatan Penawaran Harga)
Controller: `QuotationController`

- Dokumen quotation final dari task: prefill dari **semua konfigurasi yang sudah approved** (IMS + WATER).
- **3 tab item**: List Items (utama), List Configuration (snapshot item konfigurasi), Cost/Biaya (tidak mempengaruhi subtotal).
- Item hierarki parent–child di ketiga tab; deskripsi item disanitasi HTML (`<b><i><u><br>`).
- **Mesin kalkulasi total** (`Quotation::calculateTotals`): subtotal = Σ(qty×price) → diskon (% / manual) → DPP → PPN (11%) → grand total.
- **Nomor quotation otomatis**: `{seq:03d}/HAS/QT-{inisial}/{bulan romawi}/{tahun}`.
- Approval workflow submit/approve/reject/unlock/revise + versioning (sama seperti modul konfigurasi).
- Gating: quotation hanya bisa dibuat jika semua konfigurasi task sudah approved, dan satu task satu quotation (current).
- **PDF utama + PDF cost** terpisah; template item & template cost.
- Terms & Conditions default, diskon/PPN fleksibel (persen atau nilai manual).

### 12. Notifications (Notifikasi)
Controller: `NotificationController`

- Bell notifikasi dengan badge unread (polling 30 dtk) dan dropdown ber-group per entitas.
- Halaman `/notifications/all` dengan pagination 20/halaman.
- `count()`, `index()`, `markAsRead()`, `markAllRead()`.
- Tipe notifikasi: `task_assigned`, `task_status_changed`, `task_activity`, `task_approval_required`, `task_approved`, `mention`, `visit_recorded`.

## Alur Bisnis Utama

```
Lead (New → Approach → Qualified)
  → Converted → Opportunity (stage, forecast)
  → Task quotation dibuat (kategori proposal)
  → Water/IMS Configuration dibuat & di-approve
  → Quotation dibuat dari konfigurasi approved → submit → approve
  → PDF quotation & cost diberikan ke customer
```

Setiap tahap mencatat log audit dan mengirim notifikasi ke pihak terkait.

## Testing

```bash
composer test        # php artisan test
```

Test feature tersedia untuk: `WaterConfigurationApprovalTest`, `ImsConfigurationApprovalTest`, `QuotationTest`, `ProductManagementPageTest`, `ImsConfigurationApprovalTest`.

## Documentasi Lain

- `NOTIFICATION_SYSTEM.md` — dokumentasi teknis sistem notifikasi.
- `desaindatabases.md` — skema database lengkap (34+ tabel, relasi, dan log types).
- `NOTIFICATION_QUICK_TEST.md` — panduan test cepat notifikasi.

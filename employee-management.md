# ER Modules - Employee Relations (Acuan Build)

> Dokumen acuan pembangunan modul group **"Employee Relations"** (ER).
> Meng-cover modul HR: Data Karyawan, Absensi, Pengajuan Cuti/Izin, Pengajuan Lembur, Peminjaman (Kas Bon), Penggajian/Payrol.
> **Status: Milestone 1–6 + 3-c BUILD COMPLETE** (M1 fondasi · M2 Data Karyawan · M3 Absensi · **M3-c Koreksi: panel + modal pengajuan koreksi absensi (pending → approve/reject via `can_approve`; approve auto-update attendance record manual + late_minutes hitung ulang)** · M3-b Lembur · M4 Cuti/Izin · M5 Peminjaman · M6 Penggajian/Payrol). Tersisa fase Android API (Sanctum) + UI Configuration rate.

---

## 0. Konvensi Proyek (WAJIB diikuti)

- **Stack**: Laravel 12, PHP ^8.2, Blade + jQuery + Bootstrap 5 + DataTables + Select2 + toastr + SweetAlert2 (via CDN, layout `resources/views/layouts/app.blade.php`, `@extends('layouts.app')`).
- **Controller**: flat di `app/Http/Controllers/`, nama `<Feature>ManagementController.php` (contoh: `OpportunityManagementController.php`).
- **Model**: `app/Models/<Singular>.php` dengan `$fillable` + `$casts` wajib, relasi bertipe return (`BelongsTo`/`HasMany`), audit trail via trait `App\Traits\Loggable` (relasi `logs()`).
- **Migration**: anonymous class `return new class extends Migration;`, nama `YYYY_MM_DD_NNNNNN_create_<table>_table.php`, FK pakai `foreignId('x_id')->nullable()->constrained(...)->nullOnDelete()`; timestamps() selalu.
- **Route**: semua di `routes/web.php`, custom endpoint (`.../data`, `approve`, `reject`, dll) dideklarasikan **sebelum** `Route::resource(...)`, dalam group `['auth','access.control']`. Nama route segment pertama = `route_name` di tabel modules (mis. `employee-management`).
- **Registrasi Modul**: `database/seeders/ModulesTableSeeder.php` via `Module::firstOrCreate(['module_code' => ...], [...])`:
  ```php
  [
      'module_code' => 'MOD_ER_EMPLOYEE',
      'module_name' => 'Data Karyawan',
      'route_name'  => 'employee-management', // harus match segment pertama route name
      'icon'        => 'fa fa-users',
      'group'       => 'Employee Relations',
  ]
  ```
- **Middleware akses**: `app/Http/Middleware/CheckAccessControl.php` — GET→can_read, POST→can_create, PUT→can_update, DELETE→can_delete; verb approval-tier **`approve/reject/unlock/settle/pay/revise/cancel`** → `can_approve` (diproses via segmen terakhir nama route, jadi `loan.installments.pay` ikut `can_approve` juga). Setelah tambah modul, assign hak via `user_access_controls` (UI User Access).
- **View**: `resources/views/<kebab-module>/{index,show}.blade.php` (edit via modal, bukan halaman terpisah), DataTable index + modal form, confirm delete/lock pakai SweetAlert2, feedback pakai toastr, CSRF via meta tag.
- **API-readiness untuk Android**: semua endpoint data/detail/store/status controller me-return `response()->json()` konsisten, sehingga saat aplikasi Android dibangun cukup dimigrasi ke `routes/api.php` + auth token (Sanctum) tanpa rewrite logic.

---

## 1. Data Karyawan — `employee-management`

### Tabel `employees`
| Kolom | Ket. |
|---|---|
| `employee_no` | otomatis `EMP-0001`, unique |
| `user_id` | FK users, **nullable** (akun login nanti saja) |
| `name`, `gender` (L/P), `birth_date`, `birth_place`, `address`, `phone`, `email` | personal |
| `nik`, `npwp_no`, `ptkp_status` (TK/0 s/d K/3) | data pajak |
| `bpjs_kesehatan_no`, `bpjs_tk_no` | data BPJS |
| `division_id`, `job_title_id`, `manager_id` (FK employees, nullable) | struktur |
| `join_date`, `resign_date`, `status` (active/on_leave/terminated) | kepegawaian |
| `base_salary`, `bank_name`, `bank_account_no`, `bank_account_name` | data gaji |

### Tabel `employee_families`
`employee_id`, `name`, `relation` (spouse/child/parent), `birth_date`, `occupation`.

### UI/UX CRUD
- **Index**: DataTable (No. Pegawai, Nama, Division, Job Title, Join Date, Status badge). Tombol `[+ Tambah]` = modal wizard 4 langkah: Personal → Kepegawaian → BPJS/Pajak → Gaji/Bank.
- **Show** (tab-4):
  1. **Profile**: semua field + `[Edit]` (modal), `[Terminate/Reactive]` (SweetAlert confirm — **tidak ada hard delete**, cukup status `terminated` karena ada FK ke payslip/attendance/loan).
  2. **Keluarga**: CRUD `employee_families` via modal.
  3. **Absensi**: baca dari tabel attendance (read-only, filter tanggal).
  4. **Riwayat**: `logs()` audit + riwayat leave/loan/payslip.
- Tab master `division` & `job title` sudah ada → reuse.
- **Tab 5 — Komponen Gaji** (build M6): CRUD `employee_salary_components` via endpoint `GET/POST/PUT/DELETE employee-management/{id}/salary-components` — list semua komponen `is_globally_assigned` + tambah komponen spesifik (Select2 master, hint nilai default), **override amount** (input currency), `active_since`, hapus override; preview estimasi per kondisi basis (monthly/daily × attended) jika data absensi terpilih.

---

## 2. Absensi — `attendance` (fingerprint + GPS + face untuk Android)

### Tabel pendukung
- **`offices`** (master): `name`, `address`, `latitude`, `longitude`, `radius_meters` (default 100) — titik geofence absen GPS. CRUD dgn peta kecil (embed iframe, tanpa API key JS).
- **`shifts`**: `name` (Pagi/Sore/Malai/Flexi), `clock_in_time`, `clock_out_time`, `tolerance_minutes` (basis hitung `late_minutes`).
- **`attendances`**:
  | Kolom | Ket. |
  |---|---|
  | `employee_id`, `work_date` | unique `(employee_id, work_date)` |
  | `shift_id` | nullable |
  | `clock_in`, `clock_out`, `late_minutes` | auto dari shift |
  | `status` | pending/present/late/leave/sick/absent |
  | `check_in_method` | fingerprint/gps/face/manual/leave_integration |
  | `office_id` | nullable (untuk GPS) |
  | `check_in_lat`, `check_in_lng` | kordinat bila GPS/face |
  | `photo_path` | selfie (GPS/face) |
  | `face_matched` | boolean nullable |
  | `source` | web/device/api/auto |
  | `note` | |
- **`attendance_approvals`**: riwayat approve/reject (action, note, actor, timestamp).
- **`attendance_corrections`**: pengajuan koreksi (karyawan tidak bisa absen) — jam masuk/keluar yang diajukan + alasan; status pending → approved/rejected.

### Rule approval (simple)
- Entry via API Android (gps/face) & device fingerprint → status `pending` → di-approve Supervisor/HR (route `approve`/`reject` → middleware cek `can_approve`).
- Entry manual oleh HR/admin → langsung `approved`, method `manual`.
- Approved → mengunci dari edit manual (kecuali admin).

### UI/UX CRUD
- **Index**: DataTable (Tanggal, Karyawan, Shift, Masuk/Keluar, Telat mnt, Status badge, Metode — icon sidik/GPS/wajah/manual, Aksi). Filter: rentang tanggal, karyawan, metode, status. Tombol `[+ Absen Manual]` (modal: karyawan Select2, tanggal, jam, status).
- **Tombol bulk `[Import via Device]`**: upload CSV/Excel log fingerprint (phpspreadsheet; kolom NIP + datetime) → mapping employee.
- **Show**: detail + foto selfie + **peta mini** (iframe embed titik check-in vs office) + timeline approval; tombol `[Approve]`, `[Reject]` (modal alasan).
- **Master Office & Shift**: submenu sederhana (index + modal CRUD).

### Endpoint Android (fase API nanti)
- `POST attendance/check-in`, `POST attendance/check-out`
- Payload: `{ "method": "gps"|"face", "lat": ..., "lng": ..., "photo": <base64> }`
- Logika server:
  1. **GPS**: haversine distance kantor terdekat; valid bila <= `radius_meters`, else reject (`out_of_geofence`).
  2. **Face**: Android capture selfie + ML Kit face detection (client-side); server simpan foto, set flag `face_matched` (matching server-side bisa disempurnakan di fase berikutnya).
  3. **Fingerprint**: log dari device fingerprint dikirim (atau import CSV oleh HR).
- Foto disimpan `storage/app/public/attendance/...`, proses berat masuk queue.

---

## 3. Pengajuan Cuti/Izin — `leave-request`

### Tabel
- **`leave_types`** (master): `name` (Cuti Tahunan/Izin/Sakit/Unpaid/Special), `quota_days` (Tahunan = 12), `paid` bool, `is_proof_required` (sakit > 1 hari perlu surat).
- **`leave_requests`**: employee_id, leave_type_id, `request_no` (LR-2026-09-0001), `start_date`, `end_date`, `days` (auto, hari kerja Senin–Jumat, ter-exclude weekend), `reason`, `attachment_path`, `status` (draft/submitted/approved/rejected/revise/cancelled).
- **`leave_balances`**: employee_id, tahun, `entitlement`, `used`, `carried_over`.
- **`leave_request_logs`**: timeline approval (action, note, actor, timestamp).

### Rule
- Approve → auto-create attendance (status leave/sick, `method = leave_integration`, `source = auto`) dan dikunci dari edit manual.
- Karyawan masih boleh `cancel` selama belum approved — aksi pemohon (can_create) dgn cek kepemilikan; admin boleh juga.

### UI/UX CRUD
- **Index**: DataTable + tab filter [Menunggu Approval | Semua]; kolom: No. Pengajuan, Karyawan, Jenis, Periode, Jml Hari, Status, Aksi.
- **Show**: detail + timeline approval; tombol `[Approve] [Reject] [Revise]` (approver), `[Cancel]` (pemohon).
- **Modal buat**: karyawan (atau self-service), jenis cuti (live info sisa saldo), date-range (hitung hari live), upload lampiran, catatan.
- Widget **saldo cuti** (bar terpakai vs sisa) di show karyawan.

---

## 3-b. Pengajuan Lembur — `overtime-request` (modul terpisah)

### Tabel `overtime_requests`
| Kolom | Ket. |
|---|---|
| `request_no` | OT-2026-09-0001, unique |
| `employee_id` | FK employees |
| `work_date` | tanggal lembur; unique `(employee_id, work_date)` |
| `attendance_id` | FK attendances nullable — sumber jam kerja hari tsb |
| `shift_end_time` | snapshot jam pulang shift (mis. 17:00) untuk audit |
| `start_time`, `end_time` | dari absen (≥ shift_end), mis. 17:00–20:00 |
| `hours` | otomatis = `end_time − shift.clock_out_time` (floor per menit, desimal jam) |
| `multiplier` | default dari config (1.5), dapat disesuaikan admin saat approve |
| `estimated_amount` | `(base_salary ÷ 173) × hours × multiplier` — basis tarip per jam konvensi Indonesia |
| `reason` | alasan lembur |
| `status` | submitted/approved/rejected/cancelled |
| `submitted_by`, `approved_by`, `approved_at`, `decision_note` | approval trail |
| timestamps | |

### Rule hitung otomatis (server)
1. Saat submit: ambil attendance hari itu (wajib ada kecuali dibuat manual oleh admin); `start_time` = `clock_out` absen; `hours = end_time − shift_end` (contoh: shift 08:00–17:00, absen keluar 20:00 → **3 jam lembur**).
2. Est. nilai live di UI (JS): `(base_salary ÷ 173) × hours × multiplier`.
3. Approve admin → boleh mengoreksi `multiplier` & `end_time`, setelah itu dikunci.
4. Cancel hanya oleh pemohon selama `submitted` (can_create + ownership check, admin boleh).

### Integrasi
- **Payroll (Step Compile)**: semua `overtime_requests` approved dalam rentang periode 25–24 → baris `payslip_components` (type `overtime`, `source_type = overtime_request`, `source_id`), tampil sebagai "Upah Lembur" di slip.
- **Attendance**: menautkan `attendance_id` hari sama; validasi jam lembur ≥ `clock_out`.
- **Config**: default multiplier & pembagi 173/max_hours diset via env-`config/er.php` (`ER_OVERTIME_DEFAULT_MULTIPLIER/HOUR_DIVISOR/MAX_HOURS`) — bisa diubah tanpa ubah kode pemakaian UI Configuration&quot; (UI config datang bersama rate BPJS di M6). Audit: unique `(employee, work_date)` DB hanya menahan satu baris — gajat cancel socio/keen 3-c lebih bersih:520.
- **Shift malam**: `hoursBetween` menangani `end_time` di bawah tengah malam (+24h, mis. shift 22:00→01:00 di-hingkat).

### UI/UX CRUD
- **Index**: DataTable (No. Pengajuan, Karyawan, Tanggal, Shift End→Keluar, Lembur × multiplier, Est. Nilai, Status badge, Aksi); filter status/periode/karyawan.
- **Modal buat**: pilih karyawan + tanggal → fetch absen hari itu → hitung jam lembur live; admin bisa buat manual bila absen belum ada.
- **Show**: detail + timeline approval; tombol `[Approve] [Reject]` (approver wajib `can_approve`), `[Cancel]` (pemohon).

### API Android
- `GET overtime-request/data` (self), `POST overtime-request` (self-service; `submitted_by` otomatis).

---

## 4. Peminjaman (Kas Bon) — `loan`

### Tabel
- **`loans`**: employee_id, `loan_no` (LN-2026-0001), `amount`, `tenor_months`, `installment_amount` (auto `amount/tenor`, bisa di-override), `fee_amount`, `purpose`, `disburse_date`, `status` (pending/approved/active/paid_off/rejected/settled), `approved_by`.
- **`loan_installments`**: loan_id, `installment_no`, `due_date` (bulanan), `amount`, `status` (unpaid/paid), `paid_payslip_id` (nullable — trace ke payslip), `paid_at`.

### Rule
- Generate jadwal angsuran otomatis setelah loan **approved** (loop tenor bulanan).
- Loan baru ditolak (`+` disabled + toast) bila masih ada angsuran berjalan.
- Angsuran jatuh tempo dalam periode payroll → **potong otomatis** di payslip (link `paid_payslip_id`).
- `[Settle Early]` = lunas sisa sekaligus.

### UI/UX CRUD
- **Index**: DataTable (No. Pinjaman, Karyawan, Total, Tenor, Angsuran/bulan, Progress X/Y, Status badge, Aksi).
- **Show**: header info + progress bar pembayaran; tab **Angsuran** (tabel: No, Jatuh tempo, Nominal, Status, Sumber pembayaran) + tombol `[Bayar Manual]` (admin, SweetAlert confirm); tombol `[Approve] [Reject] [Settle Early]`.

---

## 5. Penggajian / Payrol — `payroll`

### Konfigurasi periode
- **Cutoff 25 s/d 24 bulan berikut** (mis. `2026-09-25 → 2026-10-24`, nama periode auto).
- Validasi: periode baru tidak boleh overlap periode sebelumnya.

### Tabel
- **`salary_components`** (master): `code`, `name`, `type` (allowance/deduction/bonus/overtime), `calculation`:
  `fixed` | `percent_base` | `fixed_daily` | `bpjs_jht_company` (3,67%) | `bpjs_jht_employee` (2%) | `bpjs_jp_company` (2%) | `bpjs_jp_employee` (1%) | `bpjs_kesehatan_company` (4%) | `bpjs_kesehatan_employee` (1%) | `bpjs_penalty`
  — plus `amount`/`percent`, `amount_cap` (mis. cap BPJS Kesehatan), `is_taxable`, `prorate_on_absence`, `is_globally_assigned`
  **+ kolom baru (M6):**
  | Kolom | Ket. |
  |---|---|
  | `frequency` | `monthly` (default) \| `daily` |
  | `prorate_basis` | `calendar_days` \| `working_days` \| **`attended_days`** (default `calendar_days`) |
  - Basis pay: komponen `daily` dibayar **per hari hadir saja** (present + late), alpa/cuti unpaid tidak dibayar — `attended_days` diambil dari ringkasan absensi payslip.
- **`employee_salary_components`**: employee_id, salary_component_id, `override_amount`, `active_since` (komponen spesifik per karyawan).
- **`payroll_periods`**: `name`, `start_date`, `end_date`, `status` (open/processing/closed), `processed_at`, `created_by`, **`is_thr`** (boolean — periode yang masuk pembayaran THR, bulan THR configurable di settings).
- **`payslips`**: employee_id, period_id, ringkasan absensi (hadir/late/sakit/izin/alpa/cuti + **`working_days`**, **`attended_days`**), `base_salary_prorata`, `total_gross`, `total_deduction`, `total_bpjs_company`, `total_pph21`, `total_net`, data bank, `locked_at`.
- **`payslip_components`**: payslip_id, salary_component_id nullable, `label`, `type`, `amount`, `sort_order`, `source_type/source_id` (trace: dari angsuran loan, unpaid leave, lembur, salary_component, dsb).
- **`payroll_logs`**: aksi per periode (user, action, timestamp).

### Seeder master komponen gaji (M6)
Rate default di master; **setiap karyawan dapat override** via tab Komponen Gaji (akhir §1).
| code | name | type | calculation | frequency | prorate_basis | taxable |
|---|---|---|---|---|---|---|
| TJ_JABATAN | Tunjangan Jabatan | allowance | fixed | monthly | working_days | ya |
| UANG_MAKAN | Uang Makan | allowance | fixed_daily | daily | attended_days | **tidak** |
| UANG_TRANSPORT | Uang Transportasi | allowance | fixed_daily | daily | attended_days | **tidak** |
| THR | Tunjangan Hari Raya | bonus | fixed (1×base) | monthly (1×/tahun) | calendar (periode is_thr) | ya |
- THR: `amount = base_salary`, hanya dibayar di periode yg `is_thr = true`; bulan THR (default Maret) diset via settings — tidak hardcode.
- Komponen BPJS benefit (company share) dicatat sebagai info bar `bpjs_*_company` di slip (bukan pendapatan tunjangan di gross).

### Formula payroll (Step Compile → Draft)
```
base_prorata     = base_salary × (working_days / calendar basis)
komponen monthly = amount_override ?? amount_master  × prorate_basis
komponen daily   = amount_override ?? amount_master  × attended_days
THR              = 1 × base_salary   (hanya periode is_thr=true)
gross            = base_prorata + Σ komponen monthly + Σ komponen daily + THR (+ upah lembur approved)
deduction        = BPJS karyawan (KS 1% + JHT 2% + JP 1%) + potongan angsuran loan
                   + unpaid leave (alpa/hari tanpa cuti paid) + PPH21 (ptkp_status)
net (THP)        = gross − deduction
```
- Daily komponen tampil per baris `payslip_components` (`source_type = salary_component`, `source_id = salary_component_id`) — traceable & editable di Step Draft.
- Preview estimasi live per komponen tersedia di Step Draft (JS re-calc).

### Rate BPJS (konsisten regulasi Indonesia)
- Rate **tidak hardcode** — disimpan sebagai setting/config (halaman Configuration): BPJS Kesehatan 4% + 1% (cap upah 12 jt), JHT 3,67% + 2%, JP 2% + 1%, dsb. Bisa berubah per periode tanpa ubah kode.
- PPH 21 pakai kolom `ptkp_status` karyawan + tabel tarif (menyesuaikan.

### UI/UX CRUD (Wizard 3 langkah per periode)
- **Index periode**: DataTable (Periode, Total Karyawan, Gross, Deduction, Net, Status badge, Aksi) + `[+ Buat Periode]` (auto-fill cutoff 25→24; anti-overlap).
- **Show Periode** (`open`):
  1. **Step Compile**: preview karyawan aktif + data absensi/cuti/instalment loan jatuh tempo pada periode → `[Generate Draft]`.
  2. **Step Draft**: tabel payslip draft (editor per bari: tambah/edit/hapus komponen semua, hitung gross/net via JS live); bisa exclude karyawan.
  3. **Finalize**: grand total → `[Process & Lock]` (SweetAlert; setelah lock period = `closed`, payslip read-only) + tombol `[Download Excel]` (phpspreadsheet), `[Print All] / [Print Selected]` PDF slip (dompdf).
- Status badge: `open` abu, `processing` kuning, `closed` hijau.
- **Show Payslip**: format slip gaji Indonesia — header karyawan + periode; tabel 2 kolom **Pendapatan** (Gaji Pokok prorata + Tunjangan Jabatan/Tunjangan + Uang Makan (per hari hadir) + Uang Transportasi (per hari hadir) + Upah Lembur + THR) | **Potongan** (BPJS KS 1%, JHT 2%, JP 1%, PPH21, Pinjaman, Unpaid/Alpa) + grand total dan Take Home Pay + rekap BPJS company share; tombol `[Cetak PDF]`.

---

## 6. Group sidebar & Module seeder

| module_code | route_name | menu |
|---|---|---|
| MOD_ER_EMPLOYEE | employee-management | Data Karyawan |
| MOD_ER_ATTENDANCE | attendance | Absensi Karyawan |
| MOD_ER_LEAVE | leave-request | Pengajuan (Cuti/Izin) |
| MOD_ER_OVERTIME | overtime-request | Pengajuan Lembur |
| MOD_ER_LOAN | loan | Peminjaman |
| MOD_ER_PAYROLL | payroll | Penggajian/Payrol |

Semua `group = 'Employee Relations'`, ikon fontawesome (fa fa-users, fa-fingerprint, fa-calendar-check, fa-user-clock, fa-hand-holding-usd, fa-file-invoice-dollar). Master internal (office/shift/leave type/salary component) akses dari dalam modul induk masing-masing (tidak perlu modul menu sendiri).

> Catatan Milestone 1: `MOD_ER_OVERTIME` sudah ter-seed bersama route skeleton `overtime-request` + controller stub (implementasi penuh di Milestone 3-b).

---

## 7. Antrean Fase Pembangunan (milestones)

| # | Milestone | Isi |
|---|---|---|
| 1 | Fondasi ✅ | 20 migration + 20 model + route skeleton (termasuk `overtime-request`) + 6 module seeder (group "Employee Relations"). *(nomor 000013 bolong — hasil renumbering: `loan_installments` dipindah ke 000018 agar FK `payslips` sudah ada.)* |
| 2 | Data Karyawan ✅ | CRUD employee + keluarga + audit log + wizard modal + terminate/reactivate. |
| 3 | Absensi ✅ (incl. correction 3-c) | Office/shift master, absen manual (late auto), import CSV/XLSX device (pending → approve), approve/reject, peta mini + foto, timeline approval, **kode correction 3-c: panel `attendance_corrections` (submit pending → approve → auto update attendance record manual & late_minutes recompute / reject note wajib; guard pending duplicate (employee, tanggal); data endpoint `attendance.corrections.*`)**. |
| 3-b | Lembur ✅ | `overtime_requests` build penuh: submit w/ hitung jam otomatis (shift_end→jam pulang; floor per menit), est. nominal live `(base ÷ 173) × jam × multiplier` — rate `config/er.php` (multiplier default / divisor / max_hours via env); approve dgn koreksi multiplier & end_time, reject (note wajib), cancel (hanya submitted); guard unique (employee, tanggal) & durasi 0/max; request_no `OT-YYYY-MM-0001`; UI index + show; Payroll integration via payslip_components (M6). Seeded `MOD_ER_OVERTIME`. |
| 4 | Cuti/Izin ✅ | Seeded leave type (kode `ANN/IZN/SCK/UNP/SPL`; Cuti Tahunan quota 12 hari, Izin, Sakit (WAJIB >1 hari, lampiran), Unpaid, Special); `request_no` LR-YYYY-MM-000x; hari kerja otomatis (Sen–Jum, excl. weekend); guard saldo per tahun `start_date`; approve → potong `leave_balances.used` (increment) + auto-create attendance `leave/sick` (method leave_integration, source auto — status via `leave_type.code = SCK`, skip weekend, manual entry dikunci), reject/revise (note wajib)/cancel; widget saldo & est. live modal; route `balances`. `LeaveTypesTableSeeder` baru. |
| 5 | Peminjaman ✅ | `loan_no` LN-YYYY-000x; guard karyawan masih punya angsuran berjalan (422 + pesan); installment_amount auto `amount ÷ tenor` (dapat override; update hanya recompute saat amount/tenor/installment berubah, selalu rounded); approve → generate jadwal angsuran bulanan (mulai disburse_date/bulan berikut), status `active`; reject (note wajib); **Bayar Manual** per angsuran (admin, `loan/installments/{id}/pay`, auto `paid_off` saat habis); **Settle Early** = lunas sisa sekaligus; progress bar X/Y + status badge; potongan payroll nanti mengikat `paid_payslip_id` (M6). |
| 6 | Penggajian ✅ | Master salary_components seeded: TJ_JABATAN (monthly, basis working days, prorate), UANG_MAKAN & UANG_TRANSPORT (daily × attended_days, non-taxable), THR (percent_base=100% base, hanya periode is_thr), BPJS 6 komponen (employee deductions 1/2/1% + company share benefit info 4/3.67/2%, salary cap via config). Engine App\Services\PayrollService: cutoff 25→24 auto & anti-overlap, is_thr auto (bulan THR config), compile preview (attendance total/loan due/overtime approved), generate draft (updateOrCreate payslips + recalc komponen: base prorata×working/calendar, komponen monthly prorata 22 hari kerja (config standard_working_days), daily×attended, PPH21 progressive (taxable − PTKP bulanan dari ptkp_status) × rate config, loan deduction jatuh tempo). Wizard views: Step Compile (checkbox karyawan), Step Draft (editor komponen via modal add/edit/delete + auto recalc), Finalize lock (payslip locked_at + period closed) + export Excel (phpspreadsheet) & PDF slip (dompdf). Tab 5 Komponen Gaji di employee show (CRUD override per komponen, master select2 default hint). Konfig: config/er.php payroll (BPJS rate, salary_cap 12jt, THR bulan Maret, PPH21 PTKP bulanan & tarif progresif). |
|
| (nanti) | API Android | `routes/api.php` + Sanctum, endpoint: employee data, attendance check-in/out, leave self-service, **overtime self-service**, loan status, payslip self. |

### Definition of Done tiap milestone
- Migration jalan (`php artisan migrate:fresh --seed`).
- Modul tampil di sidebar group Employee Relations untuk admin; non-admin patuh `user_access_controls` (termasuk tombol approve).
- CRUD index/show FIFA house style, modal + toastr + SweetAlert berfungsi.
- Approval flow mem-block user tanpa `can_approve` (403 JSON dgn header X-Requested-With, redirect-back dgn flash error sisi web).

---

## 8. Estimasi berkas per modul (contoh Data Karyawan)
```
database/migrations/2026_09_25_000001_create_employees_table.php
database/migrations/2026_09_25_000002_create_employee_families_table.php
app/Models/Employee.php
app/Models/EmployeeFamily.php
app/Http/Controllers/EmployeeManagementController.php
resources/views/employee-management/index.blade.php
resources/views/employee-management/show.blade.php
routes/web.php (tambah blok route)
database/seeders/ModulesTableSeeder.php (tambah entri)
```

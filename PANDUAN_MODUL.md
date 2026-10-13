# Panduan Penambahan Modul — HAS-ERP

Dokumen ini mematok **aturan baku** pembuatan modul baru (atau penambahan fitur dalam modul
yang sudah ada) supaya konsisten dengan arsitektur yang sudah ditegakkan. **Agent/AI yang
menulis kode wajib mengikuti dokumen ini.**

Sumber kebenaran pola yang sudah jalan:

- Middleware: `app/Http/Middleware/CheckAccessControl.php`
- View composer: `app/Providers/AppServiceProvider.php`
- Helper non-gate: `app/Support/ModuleAccess.php`
- Registrasi modul: `database/seeders/ModulesTableSeeder.php`
- Contoh modul lengkap: `GoodsRequest`, `PurchaseOrder`, `Quotation`, `WaterConfiguration`

---

## 1. Prinsip Inti (jangan dilanggar)

1. **Satu gerbang akses: middleware `access.control`.** Semua route bisnis berada di dalam
   group `Route::middleware(['auth', 'access.control'])` di `routes/web.php`.
2. **Controller TIDAK BOLEH menolak request berdasarkan UAC** (tidak ada query
   `UserAccessControl`/`Module` untuk gate, tidak ada helper `isApprover()`/`canManage()`).
   Larangan ini berupa *gate*; kebutuhan *data* (mis. daftar approver untuk notifikasi) lewat
   helper, lihat §5.4.
3. **Flag UI (tombol/kolom) satu sumber: view composer global**, bukan hasil hitung
   controller. Composer menyuntik `$canCreate`, `$canRead`, `$canUpdate`, `$canDelete`,
   `$canApprove` ke semua view untuk user login.
4. **Module = route_name satu segmen tanpa titik**, terdaftar di tabel `modules`
   (seeder). Route bertingkat tetap dipetakan ke modul itu lewat potongan di **titik
   pertama** (sama di middleware dan composer).
5. **Pola yang sudah ada tidak ditulis ulang.** Modul konfigurasi teknis (Water/IMS/Ih/Gas/
   Enviro) itu 5 salinan nyaris identik — jangan dijadikan contoh kode baru; jangan menambah
   salinan ke-6.

---

## 2. Checklist Ringkas (9 langkah)

| # | Langkah | File |
|---|---------|------|
| 1 | Migration (schema) | `database/migrations/YYYY_MM_DD_HHMMSS_nama.php` |
| 2 | Model + relasi + status const | `app/Models/*.php` |
| 3 | Routes + naming | `routes/web.php` |
| 4 | Registrasi modul di seeder | `database/seeders/ModulesTableSeeder.php` |
| 5 | Controller (tanpa UAC) | `app/Http/Controllers/*.php` |
| 6 | Views (flag dari composer) | `resources/views/**` |
| 7 | Audit log + notifikasi | trait `Loggable`/`Notifiable`, model `Log`/`Notification` |
| 8 | Test permission + alur | `tests/Feature/*.php` |
| 9 | Validasi (lihat §10) | CLI |

Urutan migrasi: buat table dulu, lalu seeder/controller/view.

---

## 3. Aturan Route & Gate Middleware

### 3.1 Pemetaan verb → permission (sudah otomatis, jangan duplikasi)

Di `CheckAccessControl`, field permission ditentukan dari **segmen terakhir** route name:

| Kondisi | Field permission |
|---|---|
| Segmen terakhir ∈ `[approve, reject, unlock, settle, pay, revise, finalize]` | `can_approve` |
| Action = `upload-po` (khusus Quotation) | `can_update` |
| Method `POST` | `can_create` |
| Method `PUT`/`PATCH` | `can_update` |
| Method `DELETE` | `can_delete` |
| selain itu (GET dsb.) | `can_read` |

> Route approval APAPUN harus berakhiran salah satu verb di daftar. Admin di-bypass otomatis
> oleh middleware.

### 3.2 Naming route (PENTING — ada jebakan)

Modul = segmen **pertama** route name (potong di **titik pertama**):

```
route('module.segment')        → modul module
route('module.anak.tindakan')   → modul module  (anak.tindakan = action)
```

**Jebakan yang sudah pernah terjadi:** action `approve-negotiation` (pakai *hyphen* di segmen
terakhir) tidak dikenali sebagai verb approval → middleware menilainya `can_create` → user
tanpa `can_approve` bisa approve. Jangan pernah menaruh verb approval di tengah nama dengan
hyphen. Benar: `opportunity-management.negotiation.approve` (segmen terakhir `approve`).
Contoh benar lain: `loan.installments.pay`, `attendance.corrections.approve`.

Aturan baku route:

- Resource utama: `Route::resource('module', XxxController::class);` → route `module.index` …
- Endpoint DataTables/JSON: `module/data` → `module.data` (GET, `can_read`).
- Aksi workflow PATCH/POST ber-verb non-approval: `module/{id}/submit` → `module.submit`
  (jarang diberi permission khusus; default `can_create` untuk POST).
- Aksi approval: `module/{id}/approve` → `module.approve` → `can_approve`. Reject/unlock/
  revise sejenis.
- Route bertingkat tetap namai `module.anak.aksi` (tiga segmen diperbolehkan).

### 3.3 Jalankan pengecekan

```bash
php artisan route:list --name=module
```

Pastikan tiap route approval berakhiran verb approval.

---

## 4. Registrasi Modul (Seeder)

Tambah 1 array di `run()` `ModulesTableSeeder`:

```php
[
    'module_code' => 'MOD_NAMA_MODUL',          // UPPERCASE penuh (bukan 'MOD_Water_...')
    'module_name' => 'Nama Modul',
    'description' => 'Sebaris penjelasan fungsi modul.',
    'route_name'  => 'nama-modul',              // 1 segmen, tanpa titik, sama dengan route resource
    'icon'        => 'fa fa-...',
    'group'       => 'Kelompok Menu',
],
```

Catatan: `route_name` harus identik dengan base route. Tanpa baris ini, middleware
**meloloskan semua request** modul tersebut (tidak ada UAC). Modul yang memang boleh diakses
semua user login (mis. `profile`, `notifications`) **tidak** diberi baris modul — itu
disengaja.

---

## 5. Controller

### 5.1 Larangan

```php
// ✗ JANGAN (gate di controller)
UserAccessControl::where(...)->where('can_approve', true)->exists();
Module::where('route_name', ...)->first();
$cek = Auth::user()->role === 'Admin' && ...;  // deny manual
// ✗ JANGAN (flag UI dihitung controller & dikirim ke view/data endpoint)
return view('x.show', ['canApprove' => ...]);
return response()->json(['can_approve' => $bool, ...]);
```

### 5.2 Yang diperbolehkan

- `Log::record(...)` dan pembuatan `Notification` (data/history).
- `ModuleAccess::approverUserIds(self::MODULE_CODE, exceptUserId)` untuk **target
  notifikasi** — bukan gate.
- `ModuleAccess::crossModuleCanRead('MOD_LAIN')` untuk tombol lintas-modul (lihat §5.4).

### 5.3 Flag UI: dari composer, bukan dari controller

Controller cukup `return view('module.show', [...data bisnis...])`. View composer otomatis
menyediakan `$canCreate`, `$canRead`, `$canUpdate`, `$canDelete`, `$canApprove` untuk modul
route berjalan (Admin → semuanya `true`; user tanpa UAC → `false`).

Contoh di blade view:

```blade
@if ($quotation->status === 'waiting_approval' && $canApprove)
    <button ...>Approve</button>
@endif
```

**DataTables (server-side JSON): jangan kirim `can_approve` per baris.** Kirim hanya data
bisnis + `status`. Flag dikunci di halaman saat render:

```js
const grCanApprove = {{ $canApprove ? 'true' : 'false' }};
...
if (grCanApprove && row.status === 'waiting_approval') { ... }
```

### 5.4 Butuh flag/approver lintas modul

View composer hanya menghitung flag modul **route yang sedang dirender**. Untuk tombol yang
menunjuk modul lain (contoh: tombol "Estimasi PL" di halaman Quotation):

```php
// di controller, teruskan ke view sebagai data
$canViewX = ModuleAccess::crossModuleCanRead('MOD_X');
```

Dan untuk notifikasi "menunggu approval" ke semua approver modul:

```php
$approverIds = ModuleAccess::approverUserIds(self::MODULE_CODE, Auth::id());
```

(jika modul punya aturan scoping tambahan seperti "approver satu divisi", filter hasil helper
itu di controller — filter data boleh, gate tidak.)

### 5.5 Pola status workflow approval (standar)

Status & warna didefinisikan di model sebagai const public + label arrays:

```php
public const STATUS_DRAFT = 'draft';
public const STATUS_WAITING_APPROVAL = 'waiting_approval';
public const STATUS_APPROVED = 'approved';

public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }
public function statusBadgeHtml(): string { ... }  // span dengan style color/background
```

Transisi standar dokumen ber-approval:

```
draft            (creator, boleh edit/hapus)
  → submit       → waiting_approval
  → approve      → approved        (hanya approver: can_approve / Admin; bukan creator)
  → reject       → rejected        (creator boleh revise → draft/waiting baru)
  → unlock/revise → salinan versi baru (lihat §6.2)
```

Di controller: validasi state (`status !== X` → 422), tulis `Log::record`, kirim
`Notification`, semua dalalm `DB::transaction`. **Jangan** cek permission di sini — middleware
sudah menutup route.

---

## 6. Model & Migration

### 6.1 Pola kolom konsisten

```php
$table->id();
$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // relasi
$table->softDeletes();
$table->timestamps();
```

- Kolom status memakai string + const di model (`self::STATUS_*`), bukan ENUM DB.
- Uang: integer/string sesuai satuan modul lain; hindari float. Format tampilan lewat
  helper model (`formatMoney`, `grandTotal()`).
- Setiap model: `protected $fillable`, `protected $casts` (tanggal → `immutable_datetime`),
  dan relasi dideklarasikan lengkap.

### 6.2 Versioning dokumen (group_id + version + is_current)

Untuk dokumen ber-revisi (config teknis, quotation) tetap pakai pola yang ada:

- `group_id` (induk sementara), `version` (integer auto-increment per group),
  `is_current` (boolean, cuma 1 true per group).
- `revise`: salin header + item (remap parent_id), versi +1, arsip versi lama
  (`is_current` lama → false).
- Query "versi terakhir": `selectRaw('MAX(id) ...')->groupBy('group_id')`.
- Perubahan schema: buat **migration baru** (jangan edit migration lama setelah release).

---

## 7. View Composer (otomatis — jangan ditulis ulang)

`AppServiceProvider::boot()` mendaftarkan composer `*` yang menyuntik flag UAC memakai
potongan **titik pertama** route name (sama seperti middleware). Artinya:

- `module.index`, `module.show`, `module.template-edit`, `module.anak.detail` → semuanya
  dapat flag modul `module` dengan benar.
- Tidak perlu (dan dilarang) menghitung ulang flag di view atau controller.

---

## 8. Audit Log & Notifikasi

- Trait `Loggable` + `Notifiable` (di model) menghindari boilerplate.
- Catat setiap transisi state:
  `Log::record('approve', '…pesan…', self::MODULE_CODE, $model);`
- Notifikasi: tabel `notifications`, tipe string konsisten (`X_approval_required`,
  `X_approved`, `mention`, `task_status_changed`, …). Frontend polling 30 detik tidak
  perlu diubah.

---

## 9. Testing Wajib

Tiap modul baru wajib punya feature test (PHPUnit, `RefreshDatabase`):

1. **Gate permission** — user `can_create` tanpa `can_approve` TIDAK boleh approve (403);
   approver boleh. (Lihat `CheckAccessControlTest::test_negotiation_approve_requires_can_approve_not_can_create`.)
2. **Alur status** — draft → submit → approve → done (dan reject/revise bila ada).
3. **Halaman render** — index/show bisa dirender user berhak; tombol approval tidak
   muncul untuk non-approver.
4. **JSON data endpoint** — 403 JSON bila tak punya `can_read`.
5. Enterprise rules (scoping divisi, gating antar-modul) tetap diuji.

Jangan menulis test yang sekadar menyalin perilaku yang dihapus (mis. mengharap field
`can_approve` di payload DataTables — itu sudah dihapus dari pola ini).

---

## 10. Checklist Akhir (sebelum commit/PR)

```bash
php artisan migrate --seed          # schema + seeder modul
php artisan route:list --name=module # route approval berakhiran verb approval
php artisan test                     # seluruh suite hijau
./vendor/bin/pint <file yang diubah> # style konsisten
```

- Semua route di dalam group `['auth', 'access.control']`.
- Module registered di seeder (kecuali modul yang sengaja terbuka).
- Controller **nol** query UAC untuk gate.
- View flag dari composer; DataTables pakai const dari composer.
- Tak ada `tmp_*.php`, `dd()`, atau kode mati di commit.
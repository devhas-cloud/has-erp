# SOP SIMULASI END-TO-END HAS-ERP

Dokumen ini adalah **Standard Operating Procedure (SOP)** untuk menguji alur bisnis utama HAS-ERP secara menyeluruh melalui **browser agent**. Tujuan akhir: terbukti satu deal tuntas dari **Lead → Opportunity → Task Quote → Config (Water + IMS) → Quotation → PL → PO Supplier Approval → Dashboard Achievement**.

Browser agent WAJIB membaca, memahami, dan mengeksekusi setiap langkah **secara berurutan**. Tanda `{TS}` = timestamp unik (format `YYYYMMDDHHmm`, contoh `202610061430`) agar data mudah dicari dan tidak bentrok dengan data lama.

---

## 1. TUJUAN

Memverifikasi seluruh alur kerja HAS-ERP dari nol sampai output **Dashboard Achievement**, dengan memperhatikan:
- Setiap transisi status yang benar sesuai gating aplikasi.
- Approver yang tepat untuk tiap dokumen.
- Data uji unik (tidak mencemari data lama).

## 2. LINGKUP & PRASYARAT

| # | Prasyarat | Status |
|---|---|---|
| 1 | Aplikasi berjalan, halaman `/login` bisa diakses | Wajib |
| 2 | Database = data lama (sudah ada 365 lead, 841 opportunity). Simulasi **HANYA membuat data baru**, tidak mengubah/menghapus data existing | Wajib |
| 3 | Akun-akun simulasi sudah ada; password semua `password` | Wajib |
| 4 | Modul sudah ter-seed (28 modul di tabel `modules`) | Wajib |

## 3. GLOSARIUM

| Istilah | Arti |
|---|---|
| Lead stage | `New → Approach → Qualified → Converted`; cabang `Unqualified` = dead-end. Transisi `New → Approach` **hanya** terjadi otomatis lewat task transition ke `done` (bukan lewat jalur approval) |
| Opportunity stage | ID stage: `1 New → 2 Proposal & Quote → 3 In Review → 4 Negotiation → 5 Closed Won` / `6 Closed Lost`. Stage `5 Closed Won` **hanya** di-set otomatis oleh PO Supplier Approval (kuorum 2), tidak bisa dipilih manual |
| Task Quote | Task kategori `Quote` (`use_division_handler = true`), memicu config lintas divisi |
| Handling Group | Roster divisi penanganan (`Water + IMS + Admin`, 8 user) — otomatis jadi assignee task quote |
| Quote Configuration (Config) | Dokumen teknis per divisi. Status: `draft → waiting_approval → approved` |
| Quotation `approved` | Dokumen penawaran final, disetujui approver |
| Quotation `finish` | PO customer sudah diupload (`upload-po`) |
| `is_current` | Versi terbaru aktif dari satu dokumen (config/quotation) |
| PO Supplier Approval | Kuorum **2 approver berbeda**; saat penuh, opportunity otomatis menjadi `Closed Won` |

## 4. PEMETAAN AKUN, DIVISI & TANGGUNG JAWAB

Semua password = `password`. Login via `/login`, form memakai **username**.

| Tahap | Username | Divisi | Tanggung jawab |
|---|---|---|---|
| Setup ACL & verifikasi akhir | `superadmin` | Admin (bypass ACL) | Set akses modul, cek Dashboard Achievement |
| Sales: lead → qualified → converted → opportunity → task quote | `Rima` | Sales | Sumber deal simulasi |
| Config maker Water | `riki` | Water (id 2) | Buat config Water, submit |
| Approver Water | `isandi` | Water (id 2) | Approve config Water (bukan pembuat) |
| Config maker IMS | `abdul` | IMS (id 3) | Buat config IMS, submit |
| Approver IMS | `chika` | IMS (id 3) | Approve config IMS (bukan pembuat) |
| Jalankan task quote | `riki` | Water | Ubah status task `todo → in_progress` |
| Quotation creator | `Rima` | Sales | Buat quotation, do submit |
| Quotation approver | `maya` | Admin (id 1) | Approve quotation |
| Upload PO & buat PL | `maya` | Admin | Upload PO customer → `finish`; buat Estimasi PL |
| PO Supplier Approval #1 | `maya` | Admin | Approval pertama (kuorum 1/2) |
| PO Supplier Approval #2 | `frida` | Admin | Approval kedua (kuorum 2/2 → Closed Won) |

> Catatan roster handling group `Water + IMS + Admin`: `riki, isandi, maidin` (Water), `abdul, chika, arlina` (IMS), `maya, frida` (Admin). Saat task quote dibuat dengan handling group ini, kedelapan user otomatis terdaftar sebagai assignee.

## 5. ATURAN & GATING WAJIB (BACA SEBELUM EKSEKUSI)

Agent harus mematuhi aturan berikut — melanggar satu saja membuat alur buntu:

1. **Task Quote wajib berstatus `in_progress`** saat config & quotation dibuat. JANGAN pindahkan ke `done` sebelum quotation selesai — config terkunci & task hilang dari daftar quotation.
2. **Approver config harus satu divisi dengan pembuat dan bukan pembuat itu sendiri.** Contoh: `riki` membuat → `riki` TIDAK bisa approve; `isandi` yang approve. Respone error bila salah: `403 ... tidak bisa approve configuration ini`.
3. **Quotation hanya bisa dibuat** jika **semua** config versi terakhir (`is_current`) task sudah `approved`, task masih `in_progress`, dan task belum punya quotation. Kalau satu config belum approved, task tidak muncul di daftar.
4. **PO Supplier Approval hanya menampilkan quotation `finish`** (PO customer sudah diupload). Urutan wajib: quotation `approved` → upload PO → baru approval supplier.
5. **Butuh 2 approver berbeda** di PO Supplier Approval. Setelah approver kedua, system otomatis mengganti opportunity terkait menjadi stage `Closed Won` + mengisi `close_won_date`.
6. **Dashboard Achievement** hanya menghitung quotation `finish` + opportunity `Closed Won`. Jadi tidak akan ada angka sebelum kedua kondisi itu terpenuhi.
7. **Estimasi PL**: satu quotation hanya boleh punya satu PL; tiap item wajib punya vendor (validasi menolak item tanpa vendor).
8. **Lead `New → Approach`** hanya naik otomatis lewat **task transition** ke `done` (pembuat/assignee menekan tombol transition). Task yang harus di-approve lalu diselesaikan lewat **Approve** pembuat **tidak** menaikkan lead. Hindari kategori `Visit`/`Proposal`/`Quote` untuk task pemicu ini (masing-masing punya gating khusus: check-in lokasi / PDF / quotation approved).
9. **`Closed Won` tidak bisa dipilih manual**: hanya PO Supplier Approval (kuorum 2 approver) yang men-set stage 5 + `close_won_date`.

## 6. PROSEDUR

Konvensi input uji: gunakan `{TS}` = `YYYYMMDDHHmm` saat eksekusi (contoh `202610061430`).

---

### A. SETUP AKSES (superadmin)

**Langkah A.1 — Login superadmin**
- Login sebagai: `superadmin`
- Bagikan: `/login` → username `superadmin`, password `password`
- Verifikasi: dialihkan ke halaman modul yang bisa dibaca (bukan halaman error).
- Exit criteria: berhasil masuk, menu samping (sidebar) tampil.

**Langkah A.2 — Pastikan UAC modul aktor**
- Bagikan: menu `User Management` (`/user-management`)
- Aksi: buka salah satu aktor (`riki`, `isandi`, `abdul`, `chika`, `maya`, `frida`, `Rima`) → buka modal/Edit → pastikan kolom akses **Read**, **Create**, **Approve** tercentang untuk modul yang dia pegang:
  - `riki`/`isandi`: Water Configuration
  - `abdul`/`chika`: IMS Configuration
  - `Rima`: Leads Management, Opportunity Management, Quotation, Task Planner
  - `maya`/`frida`: Quotation, Estimasi PL, PO Supplier Approval
- Catatan: seed default memberi semua user akses semua modul (`can_* = true`). Langkah ini bersifat verifikasi. Jika ada yang mati, centang lalu simpan.
- Exit criteria: akses tersimpan, tombol simpan memberi pesan sukses.

---

### B. LEAD & QUALIFICATION (Rima)

**Langkah B.1 — Buat Lead**
- Login sebagai: `Rima`
- Bagikan: menu `Leads Management` (`/leads-management`) → tombol **Add Lead**
- Isi form. Field **wajib** (bertanda `*`): `lead_title`, `Salutation`, `Full Name`, `Email`, `Mobile`, `Job Title`, `Department` (divisions_id), `Lead Source`, `Follow Up Date`.
  - `lead_title`: `Simulasi E2E {TS}`
  - `Full Name`: `Kontak Simulasi {TS}`
  - `Email`/`Mobile`: nilai unik (mobile & email wajib unik di `account_contacts`)
  - Buat **company baru** lewat field `Company` (ketik nama → opsi `(new)`):
    - company name: `PT Simulasi {TS}`
  - `Lead Source` (`source_id`): pilih salah satu yang tersedia (misal `Event`)
- Catatan: **tidak ada field `lead_owner`** di form — owner otomatis = user login (`Rima`). `Assign To` opsional (dan hanya tampil bila user punya akses update & bukan sales non-manager).
- Simpan. Verifikasi: response sukses.
- Exit criteria: lead muncul di tabel dengan status **`New`**. Cari via search `Simulasi E2E {TS}`.

**Langkah B.2 — (Tunjuk bukti branching) Lead New → Approach**
- Buka detail lead yang barusan dibuat (nama `Simulasi E2E {TS}`).
- Tab **Task** → **Add Task**: pilih kategori netral seperti **`Training`** (atau `Seminar`). **JANGAN** pakai `Visit` (kategori `Visit` butuh check-in lokasi `TaskVisit` sebelum bisa `done`), dan jangan pakai `Proposal`/`Quote`.
- Assignee: **hanya `Rima`** (diri sendiri), atau biarkan kosong (otomatis assign ke pembuat). Jangan menambah assignee lain — bila ada assignee selain pembuat, `requires_approval=true` dan task masuk `waiting_approval` lebih dulu.
- Di Task Planner, buka task → jalankan **transition** `todo → in_progress` lalu `in_progress → done` sebagai `Rima`.
- Kembali ke lead. **Verifikasi: status lead berubah `New → Approach`** (mekanisme otomatis saat task terkait selesai lewat transition).
- Peringatan: perubahan lead **hanya** dipicu oleh `transition()` ke `done`. Bila task harus di-approve (ada assignee lain) lalu diselesaikan lewat tombol **Approve** pembuat, lead **tidak** naik.
- Exit criteria: badge lead = `Approach`.

**Langkah B.3 — Mark Qualified**
- Bagikan: detail lead `Simulasi E2E {TS}` → tombol **Mark Qualified** (hanya muncul saat status = `Approach`)
- Isi modal (wajib): `Company`, `Field Type` (types_accounts_companies), `Segmentation`, `Business Entity`, `Business Value`, `Address Street`, `City`, `Province`, `Zip`, `Country`.
  - `interaction_levels` **opsional** (field disembunyikan di UI).
- Simpan. Verifikasi: status lead = **`Qualified`**, company terkait menjadi `Active`.
- Exit criteria: badge lead = `Qualified`.

**Langkah B.4 — Mark Converted**
- Bagikan: detail lead → tombol **Converted** (hanya muncul saat status = `Qualified`)
- Isi modal: **hanya** `Division` (wajib) — pilih divisi internal pemilik opportunity. Modal ini **tidak** meminta `probability`/`forecast`.
- Simpan. Verifikasi: response sukses dan muncul **Opportunity baru** tersambung.
- Hasil otomatis: opportunity stage **`New`**, `probability = 0`, `forecast` kosong, `close_date` = hari ini + 3 bulan, owner = `Rima`.
- Exit criteria: opportunity muncul dengan stage **`New`**, nama `Simulasi E2E {TS}`, owner `Rima`.

---

### C. TASK QUOTE (Rima + riki)

**Langkah C.1 — Buat Task Quote di Opportunity**
- Login sebagai: `Rima`
- Bagikan: menu `Opportunity Management` (`/opportunity-management`) → buka opportunity `Simulasi E2E {TS}` → tab **Task** → tombol **Add Task**
- Isi:
  - `title`: `Quote Simulasi {TS}`
  - `category_id`: **`Quote`** (satu-satunya kategori dengan `use_division_handler = true`)
  - `handling_group_id`: **`Water + IMS + Admin`** (field `Penanganan` hanya muncul untuk kategori ber-flag handler, yaitu `Quote`)
  - `due_date`: **otomatis** 3 hari kerja dari hari dibuat (field terkunci, tidak bisa diisi manual) saat kategori `Quote` dipilih
  - assignee: biarkan kosong (tidak wajib) — roster handling group otomatis ditambahkan saat `Penanganan` dipilih
- Simpan. Verifikasi: task dibuat, assignee otomatis = 8 user handling group.
- Exit criteria: task muncul di daftar task opportunity, status **`todo`**.

**Langkah C.2 — Jalankan task (todo → in_progress)**
- Login sebagai: `riki` (salah satu assignee)
- Bagikan: menu `Task Planner` (`/task-planner`)
- Buka task `Quote Simulasi {TS}` → **transition** status ke **`in_progress`**.
- Exit criteria: status task = `in_progress`.

---

### D. CONFIG Water & IMS

**Langkah D.1 — Buat Config Water (riki)**
- Login sebagai: `riki` (divisi Water)
- Bagikan: menu `Water Configuration` (`/water-configuration`) → tombol **Tambah/New**
- Form memilih task: pilih `Quote Simulasi {TS}` (muncul karena task `in_progress` + kategori Quote + `riki` belum punya config Water di task ini).
- Tambah item (lihat kolom `search-products`, `/water-configuration/search-products`): pilih 2–3 produk dari **MasterProduct** (contoh kode produk yang ada di seed), isi `qty` ≥ 1. Header (to_name, lokasi, PIC, sales) ter-derive otomatis dari task.
- Simpan → status **`Draft`**. Verifikasi: config tersimpan.
- **Submit**. Exit criteria: status = **`waiting_approval`**.

**Langkah D.2 — Approve Config Water (isandi)**
- Login sebagai: `isandi` (Water, bukan pembuat = wajib).
- Bagikan: `Water Configuration` → buka dokumen `waiting_approval` yang dibuat riki → tombol **Approve**.
- Verifikasi: status = **`approved`**.
- (Bukti gating opsional: login `riki` → tombol approve pada dokumennya sendiri akan error `403 ... tidak bisa approve configuration ini`.)
- Exit criteria: badge config Water = `approved`.

**Langkah D.3 — Buat Config IMS (abdul)**
- Login sebagai: `abdul` (divisi IMS)
- Bagikan: menu `IMS Configuration` (`/ims-configuration`) → **Tambah/New**
- Pilih task `Quote Simulasi {TS}`.
- Tambah item: beda dari Water — **harga/unit diinput manual**, `qty` wajib ≥ 1, `parameter_note` opsional. Tambahkan 2–3 item material dengan nama unik `Material IMS {TS}-1` dst.
- Simpan → `Draft` → **Submit**.
- Exit criteria: status = **`waiting_approval`**.

**Langkah D.4 — Approve Config IMS (chika)**
- Login sebagai: `chika` (IMS, bukan pembuat).
- Bagikan: `IMS Configuration` → dokumen `waiting_approval` milik abdul → **Approve**.
- Exit criteria: badge config IMS = **`approved`**.

**Langkah D.5 — Cek prasyarat quotation**
- Login sebagai: `Rima`
- Bagikan: menu `Quotation` (`/quotation`) → form baru
- Verifikasi: task `Quote Simulasi {TS}` muncul di dropdown task (hanya muncul jika **semua config approved** + task `in_progress` + belum ada quotation).

---

### E. QUOTATION (Rima + maya)

**Langkah E.1 — Buat Quotation**
- Login sebagai: `Rima`
- Bagikan: `Quotation` → **Tambah/New** → pilih task `Quote Simulasi {TS}`
- Item ter-prefill dari snapshot config IMS + Water (tab **List Items**). Pastikan ada item dari kedua divisi. Harga sudah mengikuti kurs.
- Cek tab **Cost/Biaya** (opsional isi biaya; tidak mempengaruhi subtotal).
- Pertimbangkan diskon (% atau manual) dan PPN (default 11%) — biarkan default bila tidak ingin ubah.
- Simpan. Verifikasi: status **`Draft`**, nomor quotation otomatis terbentuk `{seq:03d}/HAS/QT-{inisial}/{bulan romawi}/{tahun}`.
- **Submit**. Exit criteria: status = **`waiting_approval`**.

**Langkah E.2 — Approve Quotation (maya)**
- Login sebagai: `maya` (Admin)
- Bagikan: `Quotation` → buka dokumen `waiting_approval` → **Approve**.
- Verifikasi: status = **`approved`**.
- Exit criteria: badge quotation = `approved`; PDF quotation & PDF cost dapat diunduh/stream.

---

### F. PL & PO SUPPLIER APPROVAL (maya + frida)

**Langkah F.1 — Buat Estimasi PL (Profit & Loss)**
- Login sebagai: `maya`
- Bagikan: menu `Estimasi PL` (`/profit-estimate`) → **Tambah/New**
- Pilih quotation `.../HAS/QT-...` hasil E.2 (muncul karena status bukan rejected/archived & belum ada PL).
- Form ter-prefill dari quotation. **Wajib**: setiap item **products** punya `vendor` terisi (validasi menolak bila kosong). Isi koreksi biaya/persentase sesuai keinginan.
- Simpan. Verifikasi: PL muncul, status badge `Up to date`, `real_profit` terhitung.
- Exit criteria: PL tersimpan, satu-quotation-satu-PL (ulangi simpan → ditolak "sudah memiliki Estimasi PL").

**Langkah F.2 — Upload PO customer → quotation FINISH**
- Login sebagai: `maya`
- Bagikan: `Quotation` → buka quotation yang sudah `approved` → tombol **Upload PO**
- Unggah file PDF/JPG/PNG (≤ 10 MB) apa pun sebagai bukti PO customer.
- Verifikasi: status quotation = **`FINISH`**, tombol lihat PO tersedia (`/quotation/{id}/view-po`).
- Exit criteria: badge quotation = `finish`.

**Langkah F.3 — PO Supplier Approval #1 (maya)**
- Login sebagai: `maya`
- Bagikan: menu `PO Supplier Approval` (`/po-supplier-approval`)
- Bukti: quotation `finish` dari F.2 muncul di tabel (hanya quotation `finish` yang tampil).
- Aksi: tombol **Approve**.
- Verifikasi: `approval_count = 1`, pesan "Menunggu approver kedua", `ready_for_supplier_po` belum Ya.
- Exit criteria: approval pertama tercatat.

**Langkah F.4 — PO Supplier Approval #2 (frida) → Closed Won**
- Login sebagai: `frida` (approver kedua, berbeda dari maya — wajib).
- Bagikan: menu `PO Supplier Approval`
- Aksi: tombol **Approve** pada quotation yang sama.
- Verifikasi:
  - `approval_count = 2`, `ready_for_supplier_po = Ya`, pesan "Purchasing boleh melanjutkan PO barang ke supplier".
  - **Opportunity terkait otomatis menjadi `Closed Won`** (stage `Closed Won`, `close_won_date` & `close_date` terisi).
- Exit criteria: kuorum 2/2 terpenuhi + opportunity Closed Won.

---

### G. OUTPUT: DASHBOARD ACHIEVEMENT

**Langkah G.1 — Verifikasi Dashboard Achievement**
- Login sebagai: `superadmin`
- Bagikan: menu `Dashboard Achievement` (`/dashboard-achievement`)
- Verifikasi muncul:
  1. **Total Achievement** bertambah sejumlah `grand_total` quotation simulasi.
  2. Baris **achievement per divisi** (divisi dari `handling_groups.division_id` yang dipakai task quote) — nilai sesuai quotation `finish`.
  3. **Brand terjual per divisi** — bila produk yang dipakai punya `brand` di MasterProduct.
  4. **Pipeline** opportunity (stage New/Proposal & Quote/Negotiation) terhitung.
- Exit criteria: semua angka mencerminkan deal simulasi (FINISH + Closed Won).

---

## 7. PROSEDUR SKENARIO CABANG (OPSIONAL)

Untuk membuktikan dead-end, agent boleh (setelah G selesai):

**Langkah H.1 — Lead Unqualified**
- Login `Rima` → Leads Management → buat lead baru `Simulasi Buntu {TS}` → buka detail → **Mark Unqualified**
- Isi wajib: `unqualified_reason` (misal `Tidak ada budget`).
- Verifikasi: status = **`Unqualified`**, `closed_date` terisi.
- Exit criteria: lead tidak bisa lanjut ke opportunity (end state).

## 8. CHECKLIST BUKTI KEBERHASILAN

| # | Bukti | Kondisi | Checklist |
|---|---|---|---|
| 1 | Lead `Simulasi E2E {TS}` | status `Converted` | ☐ |
| 2 | Task Quote `Quote Simulasi {TS}` | kategori Quote, status `in_progress`, 8 assignee | ☐ |
| 3 | Config Water | `approved` | ☐ |
| 4 | Config IMS | `approved` | ☐ |
| 5 | Quotation | `finish` (PO terupload) | ☐ |
| 6 | Estimasi PL | `Up to date`, 1 per quotation | ☐ |
| 7 | PO Supplier Approval | 2 approver berbeda = `ready_for_supplier_po` | ☐ |
| 8 | Opportunity | stage `Closed Won` + `close_won_date` | ☐ |
| 9 | Dashboard Achievement | total & per-divisi naik | ☐ |

## 9. PENANGANAN GAGAL (TROUBLESHOOTING)

| Gejala | Kemungkinan penyebab | Tindakan |
|---|---|---|
| Task tidak muncul di form config | Task belum `in_progress`, atau user sudah punya config divisi tsb di task | Pastikan C.2 selesai; cek filter divisi config |
| Lead tidak naik `New → Approach` | Task diselesaikan lewat jalur Approve, atau kategori `Visit` terblokir check-in | Selesaikan lewat transition `done`, atau ganti ke kategori `Training`/`Seminar` |
| Task tidak muncul di daftar quotation | Ada config `is_current` belum `approved`, atau task sudah punya quotation | Cek semua config status `approved`; cek belum ada quotation |
| Approve config error `403` | Approver = pembuat atau divisi beda | Gunakan user satu divisi, bukan pembuat |
| Quotation PO tidak bisa upload | Status bukan `approved`/`finish` | Pastikan E.2 approve dulu |
| Quotation tidak muncul di PO Supplier Approval | Status belum `finish` | Selesaikan F.2 (upload PO) dulu |
| Opportunity tidak Closed Won | Kuorum belum 2 approver berbeda | Selesaikan F.4 |
| Dashboard Achievement kosong | Belum `finish` atau belum `Closed Won` | Penuhi G step sebelumnya |
| PL tidak bisa dibuat | Quotation `rejected`/`archived`, atau sudah ada PL | Gunakan quotation `approved`/`finish` tanpa PL |
| PL simpan gagal | Ada item products tanpa vendor | Isi `vendor` semua baris produk |

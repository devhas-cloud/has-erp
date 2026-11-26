# Plan — Modul Akuntansi (Double-Entry, terintegrasi ke dokumen Finance)

## Ringkasan

Bangun modul akuntansi baru: Chart of Accounts (COA), jurnal double-entry, pemetaan akun default per produk/kategori, auto-posting dari dokumen Finance yang sudah ada, jurnal pembuka (saldo awal), serta laporan Trial Balance, General Ledger, Neraca, Laba Rugi, dan rekap PPN/PPh.

Keputusan yang sudah disepakati:

1. Mesin **double-entry penuh**.
2. Auto-posting: **AR, AP, Kas/Bank** (termasuk PPN, PPh, uang muka, selisih kurs pembayaran). **Inventori/HPP tidak diposting** di fase ini.
3. Data lama: **tidak di-backfill**; saldo awal lewat jurnal pembuka manual.

## Analisa: Single-entry vs Double-entry terhadap skema saat ini

### Kondisi skema sekarang

- Tidak ada tabel akun, jurnal, atau ledger sama sekali. `AccountCompany`/`AccountType` adalah CRM, bukan akun buku besar.
- Angka keuangan tersebar di dokumen: `invoices` (`amount_payment`, `ppn`, `total_payment`), `customer_receipts.amount`, `supplier_bills` (`subtotal`, `discount_amount`, `other_charges`, `ppn_amount`, `pph_amount`, `advance_deduction`, `total`), `supplier_payments` (`amount`, `exchange_rate`, `amount_base`, `bank_charge`), `supplier_payment_allocations` (`amount_base_book`, `amount_base_paid`, `fx_difference`), `supplier_refunds` (`amount_base`, `amount_base_book`, `fx_difference`).
- Laporan (Piutang/Hutang) dihitung ulang dari dokumen. Tidak ada kontrol bahwa total debit = total kredit.
- `invoices` tidak punya kolom mata uang → invoice diasumsikan Rupiah (base). Pembelian/pembayaran/refund multi-currency sudah punya kurs + nilai base.

### Dampak single-entry (hanya menambah kolom akun di dokumen)

- Cepat, tetapi tidak bisa menghasilkan Trial Balance/Neraca; tidak ada deteksi jurnal tidak seimbang atau transaksi terlewat.
- Sulit merekonsiliasi AR/AP/PPN/PPh antar dokumen; koreksi hanya menimpa angka.
- Tidak memenuhi syarat "sesuai prinsip akuntansi profesional".

### Dampak double-entry

- Butuh tabel baru + mesin posting + reversal + period lock, dan menyentuh 6 controller Finance.
- **Tidak mengubah tabel dokumen yang ada** (aditif): laporan lama tetap jalan, ledger menjadi lapisan baru.
- Risiko utama: posting ganda saat approve/void. Dimitigasi dengan `source_key` unik + reversal eksplisit, bukan edit jurnal posted.
- Keuntungan: setiap transaksi terbukti balance, ada audit trail, bisa Trial Balance/Neraca/Laba Rugi, PPN & PPh terkontrol.

### Rekomendasi

**Double-entry.** Skema saat ini sudah memuat semua nilai yang dibutuhkan (DPP, PPN, PPh, uang muka, kurs), sehingga double-entry bisa dibangun sebagai lapisan posting tanpa migrasi data dokumen. Single-entry tidak akan pernah bisa menghasilkan Trial Balance yang bisa dipertanggungjawabkan.

## Current State Analysis (titik integrasi)

| Dokumen | File | Aksi | Perubahan akuntansi |
|---|---|---|---|
| Invoice | `app/Http/Controllers/InvoiceController.php` (`approve` L434, `cancel` L504) | approve | Dr AR, Cr Pendapatan per produk, Cr PPN Keluaran |
| Penerimaan Customer | `app/Http/Controllers/CustomerReceiptController.php` (`approve` ~L206, `void`) | posted / void | Dr Kas/Bank, Cr AR / reversal |
| Tagihan Supplier | `app/Http/Controllers/SupplierBillController.php` (`approve` L293, `void`) | verified / void | Dr Beban per produk + PPN Masukan + Uang Muka, Cr AP, Cr PPh |
| Pembayaran Supplier | `app/Http/Controllers/SupplierPaymentController.php` (`approve` L249, `void`) | posted / void | Dr AP, Dr Biaya Bank + selisih kurs, Cr Kas/Bank |
| Refund Supplier | `app/Http/Controllers/SupplierRefundController.php` (`approve` L223, `void` L241) | posted / void | Dr Kas/Bank, Cr Uang Muka Supplier (+ selisih kurs) |

Catatan model:

- `Invoice::getNetSubtotalAttribute()` = `subtotal - discount_amount`; `total_payment = amount_payment + PPN dari amount_payment`. `amount_payment` adalah porsi termin (`percent_payment`), bukan selalu subtotal penuh → pendapatan per produk harus dialokasikan pro-rata dari `amount_payment`.
- `SupplierBill::amountDue()` = `total - advance_deduction - pph_amount`; `taxBase()` = `subtotal - discount_amount + other_charges`.
- `SupplierPaymentAllocation` menyimpan `amount_base_book`, `amount_base_paid`, `fx_difference`.
- `MasterProduct` hanya punya `category` (string bebas), tanpa tabel kategori → pemetaan kategori memakai string `category`.
- ACL: middleware `CheckAccessControl` memetakan segmen pertama route ke `modules.route_name`; verb/segmen `approve|void|finalize|...` → `can_approve`. Maka posting jurnal memakai segmen route `approve`, reversal memakai `void`.

## Proposed Changes

### 1. Migrasi (prefix `2026_11_24_000004` dst.)

`database/migrations/2026_11_24_000004_create_accounting_accounts_table.php`

- `accounting_accounts`: `id`, `code` (unik), `name`, `type` enum(`asset`,`liability`,`equity`,`revenue`,`expense`), `normal_balance` enum(`debit`,`credit`), `parent_id` (self FK, nullOnDelete), `is_header` bool, `is_active` bool, `description` nullable, `sort_order`, timestamps.
- Index (`type`,`code`). Tanpa soft delete: akun dinonaktifkan lewat `is_active`.

`..._000005_create_accounting_settings_table.php`

- `accounting_settings`: `id` (selalu 1), `default_receivable_account_id`, `default_payable_account_id`, `default_ppn_output_account_id`, `default_ppn_input_account_id`, `default_pph_payable_account_id`, `default_advance_supplier_account_id`, `default_cash_account_id`, `default_bank_account_id`, `default_revenue_account_id`, `default_expense_account_id`, `default_other_charges_account_id`, `default_bank_charge_account_id`, `default_fx_gain_account_id`, `default_fx_loss_account_id`, `books_locked_through` date nullable, `updated_by`, timestamps. Semua FK `restrictOnDelete`.

`..._000006_create_accounting_product_accounts_table.php`

- `accounting_product_accounts`: `master_product_id` unik (cascade), `revenue_account_id`, `expense_account_id` (restrict), timestamps.
- `accounting_category_accounts`: `category` unik, `revenue_account_id`, `expense_account_id`, timestamps.

`..._000007_create_accounting_journals_table.php`

- `accounting_journals`: `journal_number` unik, `journal_date`, `journal_type` enum(`opening`,`manual`,`invoice`,`invoice_void`,`receipt`,`receipt_void`,`bill`,`bill_void`,`payment`,`payment_void`,`refund`,`refund_void`), `source_key` string unik nullable, `reversal_of_id` nullable unik FK self, `description`, `currency` default base, `exchange_rate` decimal(15,4) default 1, `total_debit`, `total_credit` decimal(18,2), `posted_by`, `posted_at`, `created_by`, timestamps.
- `accounting_journal_lines`: `journal_id` (cascade), `account_id` (restrict), `description` nullable, `debit`, `credit` decimal(18,2) default 0, `project_id` nullable (nullOnDelete), `sort_order`, timestamps; index (`account_id`,`journal_id`).

### 2. Model (`app/Models/`)

- `AccountingAccount.php` — relasi `parent/children`, scope `active`, `isDebitNormal()`.
- `AccountingSetting.php` — singleton helper `current()`, relasi ke tiap akun default.
- `AccountingProductAccount.php`, `AccountingCategoryAccount.php`.
- `AccountingJournal.php` — relasi `lines`, `reversal`, `original`, `poster`; scope posted.
- `AccountingJournalLine.php` — relasi `journal`, `account`, `project`.

### 3. Service (`app/Services/Accounting/`)

`JournalService.php`

- `post(string $sourceKey, string $type, CarbonInterface $date, array $lines, array $meta = []): AccountingJournal`
  - Bulatkan 2 desimal, buang baris nol, minimal 2 baris.
  - Tolak jika `|total_debit - total_credit| > 0.005` → `RuntimeException`.
  - Tolak akun nonaktif/header.
  - Tolak `journal_date <= books_locked_through`.
  - `DB::transaction`; jika `source_key` sudah ada → `RuntimeException` (cegah double posting).
  - Nomor jurnal via `NumberSequence::make('accounting_journals','journal_number',$date)`.
- `reverse(AccountingJournal $journal, string $reason): AccountingJournal` — mirror debit/kredit, `source_key = original:void`, `reversal_of_id` unik; reversal tetap boleh saat periode terkunci (dengan tanggal hari ini).
- `postOpening(CarbonInterface $date, array $lines)` — `source_key = opening:{tahun}`.

`AccountResolver.php`

- `revenueFor(InvoiceItem|SupplierBillItem $item)`: produk → kategori → `default_revenue_account_id`; jika kosong → `RuntimeException` dengan pesan akun mana yang belum dipetakan.
- `expenseFor(...)`: produk → kategori → `default_expense_account_id`.
- `cashFor(string $method, ?string $label)`: `cash` → akun kas, selain itu akun bank. Label rekening hanya masuk deskripsi baris (tidak ada COA per rekening di fase ini).

`Posting/` (satu class per dokumen, semua memakai `JournalService`):

- `InvoicePosting::post(Invoice)`, `::reverse(Invoice)`
- `ReceiptPosting::post(CustomerReceipt)`, `::reverse(...)`
- `BillPosting::post(SupplierBill)`, `::reverse(...)`
- `PaymentPosting::post(SupplierPayment)`, `::reverse(...)`
- `RefundPosting::post(SupplierRefund)`, `::reverse(...)`

### 4. Aturan posting (exact)

**Invoice approve** (`invoice:{id}`)

- Total pendapatan = `amount_payment`; alokasikan pro-rata per item berdasarkan `total_price / subtotal`, sisa pembulatan ke baris terbesar.
- Dr AR `total_payment`
- Cr Pendapatan (per produk, hasil alokasi)
- Cr PPN Keluaran `total_payment - amount_payment`
- Diskon tidak dibuat baris terpisah: pendapatan sudah neto karena berbasis `amount_payment`.
- `InvoiceController@cancel` setelah approved → `InvoicePosting::reverse()`.

**Penerimaan Customer posted** (`receipt:{id}`)

- Dr Kas/Bank (`amount`)
- Cr AR (`amount`)

**Tagihan Supplier verified** (`bill:{id}`)

- Reguler:
  - Dr Beban per item (`line_type=item`, `amount`, akun per produk)
  - Dr Beban biaya lain (`line_type=charge`, `amount`, `default_other_charges_account_id`)
  - Dr PPN Masukan (`ppn_amount`)
  - Dr AP `advance_deduction` (offset uang muka)
  - Cr Uang Muka Supplier `advance_deduction`
  - Cr AP `total`
  - Dr AP `pph_amount`; Cr PPh Payable `pph_amount`
  - Efek neto AP = `total - advance_deduction - pph_amount` = `amountDue()`.
- Tipe `advance`:
  - Dr Uang Muka Supplier (`subtotal - discount + other_charges`)
  - Dr PPN Masukan (`ppn_amount`)
  - Cr AP `total`
- Validasi: total debit harus sama dengan total kredit; jika tidak, approve dibatalkan dan transaksi dokumen di-rollback.

**Pembayaran Supplier posted** (`payment:{id}`)

- Dr AP = Σ `amount_base_book` alokasi.
- Dr Biaya Bank = `bank_charge`.
- Plug selisih kurs = (`amount_base` + `bank_charge`) − (Dr AP + Dr Biaya Bank); positif → Dr Rugi Kurs, negatif → Cr Laba Kurs (gunakan `default_fx_loss_account_id` / `default_fx_gain_account_id`). Nilai `fx_difference` tersimpan tetap dipakai untuk laporan dokumen, bukan untuk jurnal.
- Cr Kas/Bank = `amount_base + bank_charge`.

**Refund Supplier posted** (`refund:{id}`)

- Dr Kas/Bank `amount_base`
- Cr Uang Muka Supplier `amount_base_book`
- Plug selisih ke Laba/Rugi Kurs seperti di atas.

**Void** semua dokumen di atas → `reverse()` pada jurnal sumber (tidak menghapus baris).

### 5. Controller + route (`routes/web.php`, grup auth yang sudah ada)

- `app/Http/Controllers/AccountingAccountController.php` — index/data/store/update/deactivate (COA tree).
- `app/Http/Controllers/AccountingJournalController.php` — index/data/show/create/store (manual), `posting` via `POST accounting-journal/{id}/approve`, `void` untuk reversal, halaman `opening`.
- `app/Http/Controllers/AccountingSettingController.php` — form akun default + lock date.
- `app/Http/Controllers/AccountingProductAccountController.php` — mapping produk + kategori (data table + modal).
- `app/Http/Controllers/AccountingReportController.php` — trial-balance, general-ledger, balance-sheet, income-statement, tax (PPN/PPh), export Excel.

Route:

```php
Route::get('accounting-account/data', ...)->name('accounting-account.data');
Route::resource('accounting-account', AccountingAccountController::class);
Route::get('accounting-journal/data', ...)->name('accounting-journal.data');
Route::post('accounting-journal/{id}/approve', ...)->name('accounting-journal.approve');
Route::post('accounting-journal/{id}/void', ...)->name('accounting-journal.void');
Route::get('accounting-journal/opening', ...)->name('accounting-journal.opening');
Route::resource('accounting-journal', AccountingJournalController::class);
Route::get('accounting-setting', ...)->name('accounting-setting.index');
Route::put('accounting-setting', ...)->name('accounting-setting.update');
Route::get('accounting-product-account/data', ...)->name('accounting-product-account.data');
Route::resource('accounting-product-account', AccountingProductAccountController::class)->only(['index','store','update']);
Route::get('accounting-report/trial-balance', ...)->name('accounting-report.trial-balance');
Route::get('accounting-report/general-ledger', ...)->name('accounting-report.general-ledger');
Route::get('accounting-report/balance-sheet', ...)->name('accounting-report.balance-sheet');
Route::get('accounting-report/income-statement', ...)->name('accounting-report.income-statement');
Route::get('accounting-report/tax', ...)->name('accounting-report.tax');
Route::get('accounting-report/export', ...)->name('accounting-report.export');
```

### 6. View (`resources/views/accounting/`)

Ikuti pola Finance yang ada (toolbar `ListToolbar`, DataTables server-side, modal form, badge status, class `text-center` untuk tanggal dan angka rata kanan):

- `accounts/index.blade.php` (+ modal form)
- `journals/index.blade.php`, `journals/show.blade.php`, `journals/create.blade.php`, `journals/opening.blade.php`
- `settings/index.blade.php`
- `products/index.blade.php`
- `reports/trial-balance.blade.php`, `general-ledger.blade.php`, `balance-sheet.blade.php`, `income-statement.blade.php`, `tax.blade.php`

### 7. Laporan (`app/Support/AccountingReport.php`)

- Trial Balance: saldo awal + mutasi debit/kredit + saldo akhir per akun; total debit = total kredit (assert).
- General Ledger: per akun, saldo berjalan per tanggal.
- Neraca: `asset` vs `liability + equity + laba tahun berjalan`.
- Laba Rugi: `revenue - expense` per periode.
- Rekap Pajak: PPN Keluaran vs PPN Masukan per periode; daftar PPh Payable + bukti potong dari `supplier_bills`.
- Export Excel mengikuti pola export laporan Finance yang sudah ada.

### 8. Seeder & modul

- `database/seeders/AccountingSeeder.php`: COA default (Kas, Bank, AR, Uang Muka Supplier, AP, PPN Keluaran, PPN Masukan, PPh Payable, Pendapatan Penjualan, Beban Pembelian, Beban Biaya Lain, Biaya Bank, Laba/Rugi Kurs, Modal, Laba Ditahan) memakai `firstOrCreate` per `code` agar tidak menimpa editan user; buat baris `accounting_settings` id=1; **tanpa** memetakan produk otomatis.
- Daftarkan di `database/seeders/DatabaseSeeder.php`.
- `database/seeders/ModulesTableSeeder.php`: tambah modul group `Finance` — `MOD_ACCOUNTING_COA`, `MOD_ACCOUNTING_JOURNAL`, `MOD_ACCOUNTING_PRODUCT_ACCOUNT`, `MOD_ACCOUNTING_REPORT`, `MOD_ACCOUNTING_SETTING`; masukkan ke `GROUP_MODULE_ORDER['Finance']` setelah `MOD_PAYABLE_REPORT`.

### 9. Integrasi controller yang ada

Sisipkan pemanggilan posting **di dalam `DB::transaction` yang sudah ada**, setelah status dokumen berubah:

- `InvoiceController@approve` / `@cancel`
- `CustomerReceiptController@approve` / `@void`
- `SupplierBillController@approve` / `@void`
- `SupplierPaymentController@approve` / `@void`
- `SupplierRefundController@approve` / `@void`

Jika `JournalService` melempar exception (akun belum dipetakan / tidak balance), seluruh aksi dokumen ikut batal. Pesan error ditampilkan di UI.

### 10. Test

`tests/Feature/Accounting/`:

- `JournalServiceTest` — tolak jurnal tidak balance, tolak `source_key` ganda, reversal mirror dan unik, period lock.
- `InvoicePostingTest` — approve invoice membuat jurnal balance, pendapatan teralokasi per produk, PPN benar; cancel membuat reversal.
- `ReceiptPostingTest` — posted Dr Kas/Bank Cr AR; void reversal.
- `BillPostingTest` — reguler (PPN, PPh, advance), advance bill, void.
- `PaymentPostingTest` — alokasi multi-bill, biaya bank, selisih kurs balance.
- `RefundPostingTest` — Dr Kas/Bank Cr Uang Muka.
- `AccountingReportTest` — trial balance seimbang, neraca seimbang, laba rugi = revenue − expense.
- `AccountingProductAccountTest` — resolusi produk → kategori → default; error jika tidak ada.

## Assumptions & Decisions

1. Semua nominal jurnal dalam **mata uang base**; dokumen asing dikonversi memakai `exchange_rate` dokumen.
2. `invoices` tanpa kolom currency → dianggap base.
3. Pendapatan invoice memakai `amount_payment` (porsi termin), bukan `subtotal`, agar sesuai dokumen yang benar-benar ditagihkan.
4. Jurnal posted **immutable**; koreksi hanya via reversal.
5. Satu jurnal sumber per dokumen (`source_key` unik); satu reversal per jurnal (`reversal_of_id` unik).
6. Rekening bank tidak dipecah per nomor rekening di fase ini; label rekening hanya deskripsi.
7. Uang muka supplier diperlakukan sebagai **aset** (Uang Muka Supplier), bukan beban, agar `advance_deduction` dan refund tetap balance.
8. Inventori/HPP, revaluasi kurs akhir periode, dan credit note **di luar scope** fase ini.
9. Data lama tidak diposting ulang; rekonsiliasi lewat jurnal pembuka + checklist.
10. Tidak memakai `AccountCompany`/`AccountType` (CRM) sebagai COA; semua nama class baru berprefix `Accounting`.

## Verification

1. `env -u LD_LIBRARY_PATH -u LD_PRELOAD php artisan migrate`
2. `env -u LD_LIBRARY_PATH -u LD_PRELOAD php artisan db:seed --class=AccountingSeeder`
3. `env -u LD_LIBRARY_PATH -u LD_PRELOAD php artisan db:seed --class=ModulesTableSeeder`
4. `env -u LD_LIBRARY_PATH -u LD_PRELOAD php artisan test tests/Feature/Accounting`
5. `env -u LD_LIBRARY_PATH -u LD_PRELOAD php artisan test tests/Feature/ReceivableReportTest tests/Feature/PayableReportTest` (pastikan laporan lama tidak rusak)
6. `env -u LD_LIBRARY_PATH -u LD_PRELOAD ./vendor/bin/pint app database tests`
7. Manual: approve invoice → cek Trial Balance seimbang; posted receipt → saldo AR turun; bill verified → AP = `amountDue()`; payment posted → AP lunas & kas turun; void → reversal seimbang.
8. Rekonsiliasi go-live: total AR ledger = total sisa piutang `ReceivableReport`; total AP ledger = total outstanding `PayableReport`; saldo kas/bank = mutasi `customer_receipts` + `supplier_payments` + `supplier_refunds` sejak tanggal opening.

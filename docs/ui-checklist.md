# Checklist UI/UX HAS ERP

Standar untuk halaman baru maupun perbaikan halaman ERP (Blade + Bootstrap 5 + jQuery/DataTables), di atas `layouts.app`.
Disusun dari pola yang sudah dipakai di modul inventory, purchasing, dan approval, ditambah prinsip aksesibilitas dasar.

Cara pakai: jalani bagian **A** untuk setiap halaman, lalu bagian sesuai jenis halaman (**B** daftar, **C** form, **D** detail). Bagian **E** wajib sebelum selesai.

---

## A. Wajib di semua halaman

### Bahasa & istilah
- [ ] Bahasa UI mengikuti modulnya. Modul yang sudah dialihkan ke Inggris (mis. Purchase Request) tetap Inggris; modul lain bahasa Indonesia. Jangan campur dua bahasa dalam satu halaman.
- [ ] Istilah stok konsisten:
  - **Material** = dihitung per jumlah, 1 saldo per produk + gudang + kepemilikan, harga rata-rata tertimbang.
  - **Barang ber-SN** = 1 baris stok per unit.
  - **Stok bebas** vs **Milik project** untuk kepemilikan.
  - **Langsung Pakai** = tidak masuk stok.
- [ ] Label tombol berupa kata kerja yang jelas hasilnya: "Kirim Barang", "Selesaikan Penerimaan", "Terima & Posting" — bukan "OK" / "Submit".
- [ ] Subjudul halaman (`page-header-sub`) menjelaskan dalam satu-dua kalimat apa yang terjadi pada data (mis. kapan stok berkurang).

### Angka & uang
- [ ] Format gaya US: `10,000.50`. PHP lewat `App\Support\Number` / `StockCost::format()` / `StockCost::qty()`; JS lewat `Num.format()`.
- [ ] Input angka memakai `data-number="money|qty|percent|rate|decimal"` dan dibaca dengan `Num.parse()` / `Num.parseOrNull()`.
- [ ] Jangan pakai `number_format(..., ',', '.')` atau `toLocaleString('id-ID')`.
- [ ] Rupiah tanpa desimal kecuali ada sen; valas 2 desimal; qty material maksimal 4 desimal.
- [ ] Kolom angka rata kanan dengan `font-variant-numeric: tabular-nums`; satuan ditulis di sebelah qty dengan warna redup.

### Data yang jujur
- [ ] Jangan menampilkan angka, contoh, atau status yang tidak berasal dari data. Kosong ditulis `—`, bukan `0` palsu.
- [ ] Harga/nilai yang sudah terjadi (keluar ke project, retur, kiriman antar branch) diambil dari `stock_logs` (harga saat itu), bukan harga baris stok saat ini.
- [ ] Teks bebas dari pengguna selalu di-escape (`{{ }}` di Blade, helper `esc()` di JS sebelum disisipkan ke HTML).

### Warna & gaya
- [ ] Warna lewat variabel CSS di `layouts.app` (`--accent`, `--text-muted`, `--card-border`, `--danger`, dll.); hindari warna acak.
- [ ] Palet label status yang sudah dipakai:
  - Material: latar `#e0f2fe`, teks `#075985`
  - Ber-SN / peringatan ringan: `#fef3c7` / `#92400e`
  - Sesuai / siap: `#d1fae5` atau `#dcfce7` / `#065f46`
  - Langsung pakai: `#ede9fe` / `#5b21b6`
  - Hover dan terpilih di pemilih (skin IH): hover `#d1fae5`, terpilih `#ecfdf5`
- [ ] Jangan `transition: all`; animasikan hanya `background-color`, `border-color`, `opacity`, `transform`.
- [ ] Hindari kartu di dalam kartu dan emoji sebagai ikon (pakai Font Awesome).

### Aksesibilitas dasar
- [ ] Setiap input punya `<label for>` atau `aria-label`.
- [ ] Elemen yang bisa diklik adalah `<button>` atau `<a>` (bukan `<div onclick>`), dan fokus keyboard terlihat.
- [ ] Warna tidak menjadi satu-satunya penanda: status merah/hijau selalu disertai ikon atau teks ("Sesuai", "Tidak sesuai").

### Layout & responsif
- [ ] Tidak ada scroll horizontal halaman; tabel lebar dibungkus `.table-responsive`.
- [ ] Toolbar dan grid filter turun menjadi 1–2 kolom di bawah 992px.
- [ ] Modal diletakkan lewat `@push('modals')`, bukan di dalam kartu (menghindari masalah `transform` pada `.fade-in`).
- [ ] Script yang dipakai bersama diletakkan lewat `@push('page-scripts')`; skrip halaman memanggilnya di `$(document).ready`.

---

## B. Halaman daftar (index)

- [ ] Toolbar filter memakai `@include('partials.list-toolbar')` + `ListToolbar('#xx-toolbar', () => table)`; pencarian bawaan DataTables diganti `ListToolbar.layout`.
- [ ] Filter standar bila relevan: kotak cari, Status, entitas utama (supplier / project / customer / branch / gudang), rentang tanggal, "Dibuat oleh saya", tombol Reset dengan jumlah filter aktif.
- [ ] Select panjang memakai `data-searchable` (select2); filter yang tidak relevan disembunyikan (`data-hide-when`).
- [ ] Filter tersimpan per pengguna (stateSave / localStorage dengan try/catch) bila daftar sering dipakai ulang.
- [ ] Kartu ringkasan di atas tabel bila ada pembagian penting (mis. "Perlu approval saya", "Stok cukup / sebagian / tanpa stok"); setiap kartu berfungsi sebagai filter cepat (klik lagi untuk membatalkan) dan menandai kartu aktif.
- [ ] Jumlah per status ikut menyesuaikan cari & filter lain.
- [ ] Kolom pertama berisi nomor dokumen sebagai link ke detail, dengan info kedua (pembuat / tanggal) di bawahnya dalam teks kecil.
- [ ] Gabungkan informasi berpasangan dalam satu kolom: "Dari → Ke", "Customer · No. PO · Tanggal", "Gudang · Branch".
- [ ] Komposisi isi dokumen ditampilkan sebagai label kecil (mis. "3 material · 2 unit SN · 1 langsung pakai"); peringatan (SN kosong, kedaluwarsa) berupa label oranye yang bisa diklik.
- [ ] Urutan bawaan menaruh yang perlu tindakan lebih dulu (mis. belum diapprove sebelum yang sudah siap).
- [ ] Pesan kosong dibedakan: "Belum ada data" vs "Tidak ada yang cocok dengan pencarian / filter".
- [ ] Aksi baris: ikon Detail selalu ada; aksi utama yang sering dipakai berupa tombol kecil berlabel (mis. "Approve"), aksi berbahaya hanya untuk baris yang boleh (draft) dan memakai konfirmasi.

## C. Halaman form (buat / ubah)

- [ ] Bagian diberi nomor langkah sesuai urutan kerja (1 Rute, 2 Barang, …); nomor berubah menjadi ✓ bila lengkap.
- [ ] Field yang jarang dipakai dilipat (collapse) dengan penanda "terisi" bila sudah ada isinya.
- [ ] Pemilihan produk/stok memakai popup pemilih (pola `material-request/partials/items-editor` atau pemilih stok per halaman), bukan dropdown per baris:
  - klik di mana saja pada baris untuk memilih, Shift+klik untuk rentang, centang semua per halaman (dengan status setengah/indeterminate);
  - pilihan tetap tersimpan saat mencari / pindah halaman; chip pilihan bisa dibatalkan;
  - baris yang sudah ditambahkan ditandai "Sudah ditambahkan" dan tidak bisa dipilih lagi;
  - tombol terapkan menyebut jumlah ("Tambahkan 5 Produk").
- [ ] Material: qty boleh desimal, ada tombol "Maks"; barang ber-SN: qty bulat / "1 unit"; qty dibatasi stok tersedia.
- [ ] Field yang ditentukan master (satuan, divisi untuk IMS) terkunci di form dan juga ditimpa di server.
- [ ] Isian yang tidak berlaku untuk jenis barang disembunyikan, bukan sekadar diabaikan (mis. batch/kedaluwarsa untuk material).
- [ ] Ringkasan (jumlah baris, material vs SN, qty kosong) selalu terlihat: sidebar menempel atau bilah aksi menempel di bawah (`position: sticky; bottom: 0`) untuk form dengan tabel lebar.
- [ ] Validasi di tempat: field wajib yang kosong diberi `is-invalid` dan difokuskan, baris qty kosong ditandai merah — toast hanya pelengkap.
- [ ] Tombol simpan dikunci saat proses (spinner + teks "Menyimpan..."), dan tidak bisa dikirim dua kali.
- [ ] Peringatan saat meninggalkan halaman dengan perubahan belum disimpan (`beforeunload`) untuk form panjang.
- [ ] Pintasan bila berguna: Enter di qty pindah ke baris berikutnya, `/` ke pencarian, Ctrl+S simpan.
- [ ] Aksi yang tidak bisa dibatalkan (posting, kirim, terima) memakai konfirmasi SweetAlert yang menjelaskan akibatnya pada stok/dokumen.

## D. Halaman detail (show)

- [ ] Header: nomor dokumen + badge status, satu baris konteks (rute / supplier · tanggal), lalu tombol: Kembali, dokumen cetak, dan **satu** aksi utama (primary).
- [ ] Aksi sekunder/berbahaya (Edit draft, Hapus draft) dikelompokkan dalam menu "Lainnya", tidak bersebelahan langsung dengan aksi utama.
- [ ] Alur bertahap ditampilkan sebagai garis langkah (mis. Draft → Dikirim → Diterima) dengan siapa & kapan.
- [ ] Info header dipadatkan menjadi satu strip; detail tambahan dilipat ("Detail pengiriman ▾").
- [ ] Pemberitahuan kontekstual sesuai status dan peran pengguna (mis. hanya penerima yang melihat instruksi pemeriksaan).
- [ ] Untuk proses pemeriksaan/approval: status per baris berubah langsung saat diisi (hijau sesuai / merah tidak sesuai), penghitung di bilah aksi menempel, dan tombol penyelesaian baru tampil kuat bila semua syarat terpenuhi.
- [ ] Hubungkan ke data turunan: kartu stok, dokumen sumber (PO/DP), unit SN, dokumen lanjutan.
- [ ] Legenda singkat di bawah tabel bila halaman memakai label jenis (Material / Ber-SN / Langsung Pakai).

## E. Sebelum selesai

- [ ] `php artisan view:cache` berhasil (lalu `view:clear`).
- [ ] Script halaman lolos `node --check` (ekstrak isi `<script>`, ganti `{{ }}` / `@json`).
- [ ] Query baru dicoba ke MySQL lokal, bukan hanya test SQLite — terutama agregat (`SUM`, `COUNT`) yang digabung dengan kolom biasa, dan alias yang bisa bentrok dengan accessor model (`value`, `qty`).
- [ ] Test feature untuk filter / endpoint baru, dan test lama yang meng-assert teks UI disesuaikan.
- [ ] Bandingkan daftar test yang gagal dengan baseline `main` (bukan hanya jumlahnya).
- [ ] Dicoba di browser pada lebar desktop dan ±400px; cek konsol bebas error.

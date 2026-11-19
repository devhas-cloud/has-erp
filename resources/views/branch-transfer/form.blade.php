@extends('layouts.app')

@php $isEdit = (bool) $transfer; @endphp

@section('title', $isEdit ? 'Edit Branch Transfer' : 'Buat Branch Transfer')
@section('page-title', $isEdit ? 'Edit Branch Transfer' : 'Buat Branch Transfer')

@section('styles')
<style>
    /* Langkah bernomor */
    .bt-step { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 11.5px; font-weight: 700; margin-right: 8px; flex-shrink: 0; }
    .bt-step.is-done { background: #065f46; }
    .bt-card-title { display: flex; align-items: center; font-weight: 600; }
    .bt-card-hint { font-size: 12px; color: var(--text-muted); font-weight: 400; }

    /* Rute: Gudang Asal -> Branch Tujuan */
    .bt-route-grid { display: grid; grid-template-columns: minmax(0, 1fr) 44px minmax(0, 1fr) 190px; gap: 12px; align-items: start; }
    .bt-route-arrow { display: flex; align-items: center; justify-content: center; height: 38px; margin-top: 27px; color: var(--accent); font-size: 18px; }
    @media (max-width: 991.98px) {
        .bt-route-grid { grid-template-columns: 1fr; }
        .bt-route-arrow { margin-top: 0; height: auto; transform: rotate(90deg); }
    }
    .bt-field-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
    .bt-wh-meta { font-size: 11.5px; color: #065f46; margin-top: 4px; }
    .bt-docinfo-toggle { font-size: 12.5px; text-decoration: none; }
    .bt-docinfo-toggle .fa-chevron-down { transition: transform .2s; }
    .bt-docinfo-toggle.collapsed .fa-chevron-down { transform: rotate(-90deg); }
    .bt-docinfo-filled { font-size: 11px; background: #d1fae5; color: #065f46; border-radius: 999px; padding: 1px 8px; margin-left: 6px; }

    /* Toolbar & tabel barang */
    .bt-pick-toolbar { display: grid; grid-template-columns: auto minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr); gap: 8px; padding: 2px 2px 10px; align-items: center; }
    @media (max-width: 991.98px) { .bt-pick-toolbar { grid-template-columns: 1fr 1fr; } }
    .bt-pick-search { position: relative; }
    .bt-pick-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px; }
    .bt-pick-search input { padding-left: 30px; }
    .bt-pick-search kbd { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 10px; padding: 1px 5px; background: var(--bg-soft, #f3f4f6); color: var(--text-muted); border: 1px solid var(--card-border, #e5e7eb); }
    .bt-view { white-space: nowrap; }
    .bt-view .btn { font-size: 12.5px; }
    .bt-view .btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
    .bt-view .badge { background: rgba(0,0,0,.12); color: inherit; font-size: 10px; }
    .bt-pick-wrap { border: 1px solid var(--card-border, #e5e7eb); border-radius: 8px; max-height: 58vh; overflow: auto; }
    .bt-pick-table thead th { position: sticky; top: 0; z-index: 1; background: var(--card, #fff); font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; box-shadow: inset 0 -1px 0 var(--card-border, #e5e7eb); }
    .bt-pick-table td { font-size: 12.5px; }
    #bt-pick-body tr[data-id] { cursor: pointer; transition: background-color .15s ease; user-select: none; }
    #bt-pick-body tr[data-id]:hover td { background-color: #d1fae5 !important; }
    #bt-pick-body tr.is-picked td { background-color: #ecfdf5 !important; }
    #bt-pick-body tr.is-picked td:first-child { box-shadow: inset 3px 0 0 var(--accent); }
    .bt-pick-table .bt-code { font-weight: 600; font-variant-numeric: tabular-nums; }
    .bt-pick-table .bt-sub { font-size: 11.5px; color: var(--text-muted); }
    .bt-own { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
    .bt-own-free { background: #f3f4f6; color: #4b5563; }
    .bt-own-project { background: #fef3c7; color: #92400e; }
    .bt-kind { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 999px; }
    .bt-kind-material { background: #e0f2fe; color: #075985; }
    .bt-kind-serial { background: #fef3c7; color: #92400e; }
    .bt-qty-group { max-width: 150px; margin-left: auto; }
    .bt-qty-group .form-control { text-align: right; font-variant-numeric: tabular-nums; }
    .bt-qty-group .btn { font-size: 11px; }
    .bt-pick-empty { text-align: center; padding: 40px 10px !important; color: var(--text-muted); font-size: 13px; }
    .bt-pick-empty .bt-empty-icon { font-size: 28px; color: var(--accent); opacity: .6; display: block; margin-bottom: 8px; }
    .bt-pick-foot { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 8px 4px 0; font-size: 12px; color: var(--text-muted); }

    /* Bilah aksi menempel di bawah */
    .bt-actionbar { position: sticky; bottom: 0; z-index: 20; margin-top: 16px; background: var(--card, #fff); border: 1px solid var(--card-border, #e5e7eb); border-radius: 10px; box-shadow: 0 -6px 18px rgba(15, 23, 42, .08); padding: 10px 14px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bt-ab-route { font-size: 12.5px; min-width: 0; flex: 1 1 240px; }
    .bt-ab-route .bt-ab-label { font-size: 11px; color: var(--text-muted); }
    .bt-ab-route .bt-ab-path { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bt-ab-stats { display: flex; gap: 18px; }
    .bt-ab-stat { text-align: center; cursor: pointer; }
    .bt-ab-stat .n { font-size: 17px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .bt-ab-stat .l { font-size: 11px; color: var(--text-muted); }
    .bt-ab-actions { display: flex; gap: 8px; margin-left: auto; align-items: center; }
    .bt-ab-dirty { font-size: 11.5px; color: #b45309; display: none; }
    .is-invalid + .select2-container .select2-selection, select.is-invalid { border-color: var(--danger, #dc3545) !important; }
</style>
@endsection

@section('content')
@php $docFilled = (bool) ($transfer?->courier || $transfer?->vehicle_no || $transfer?->driver_name || $transfer?->note); @endphp
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit '.$transfer->transfer_number : 'Buat Pengiriman Antar Branch' }}</h1>
        <p class="page-header-sub">Tentukan rute, pilih barang, lalu simpan sebagai draft. Stok baru keluar saat dokumen <strong>Dikirim</strong> dari halaman detail.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('branch-transfer.show', $transfer->id) : route('branch-transfer.index') }}" class="btn btn-secondary btn-sm bt-leave">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

{{-- 1. Rute --}}
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span class="bt-card-title"><span class="bt-step" id="bt-step-1">1</span>Rute Pengiriman</span>
        <span class="bt-card-hint">Gudang tujuan dipilih penerima saat barang tiba</span>
    </div>
    <div class="card-body-custom">
        <div class="bt-route-grid">
            <div>
                <label class="form-label" for="bt-from-warehouse">Gudang Asal <span class="text-danger">*</span></label>
                <select id="bt-from-warehouse" class="form-select">
                    <option value="">— Pilih gudang asal —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" data-branch="{{ $w->branch_id }}" data-branch-label="{{ $w->branch_label }}" @selected(($transfer->from_warehouse_id ?? null) === $w->id)>{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                    @endforeach
                </select>
                <div class="bt-field-hint" id="bt-from-hint">Hanya gudang yang terhubung ke branch{{ auth()->user()->branch_id ? ' Anda' : '' }}.</div>
            </div>
            <div class="bt-route-arrow"><i class="fa-solid fa-arrow-right-long"></i></div>
            <div>
                <label class="form-label" for="bt-to-branch">Branch Tujuan <span class="text-danger">*</span></label>
                <select id="bt-to-branch" class="form-select">
                    <option value="">— Pilih branch tujuan —</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected(($transfer->to_branch_id ?? null) === $b->id)>{{ $b->code }} — {{ $b->name }}</option>
                    @endforeach
                </select>
                <div class="bt-field-hint">Branch gudang asal tidak bisa dipilih.</div>
            </div>
            <div>
                <label class="form-label" for="bt-date">Tanggal Kirim <span class="text-danger">*</span></label>
                <input type="date" id="bt-date" class="form-control" value="{{ $transfer?->transfer_date?->toDateString() ?? now()->toDateString() }}">
            </div>
        </div>

        <div class="mt-3 pt-2" style="border-top:1px dashed var(--card-border,#e5e7eb)">
            <a class="bt-docinfo-toggle {{ $docFilled ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#bt-docinfo" role="button" aria-expanded="{{ $docFilled ? 'true' : 'false' }}">
                <i class="fa fa-chevron-down me-1"></i>Info surat jalan <span style="color:var(--text-muted)">(opsional — ekspedisi, kendaraan, pengemudi, catatan)</span>
                <span class="bt-docinfo-filled" id="bt-docinfo-filled" style="{{ $docFilled ? '' : 'display:none' }}">terisi</span>
            </a>
            <div class="collapse {{ $docFilled ? 'show' : '' }}" id="bt-docinfo">
                <div class="row g-3 pt-2">
                    <div class="col-md-4">
                        <label class="form-label" for="bt-courier">Ekspedisi</label>
                        <input type="text" id="bt-courier" class="form-control bt-docfield" maxlength="150" value="{{ $transfer->courier ?? '' }}" placeholder="mis. Armada sendiri / JNE Trucking">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="bt-vehicle">No. Kendaraan</label>
                        <input type="text" id="bt-vehicle" class="form-control bt-docfield" maxlength="50" value="{{ $transfer->vehicle_no ?? '' }}" placeholder="B 1234 XYZ">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="bt-driver">Pengemudi / Pembawa</label>
                        <input type="text" id="bt-driver" class="form-control bt-docfield" maxlength="150" value="{{ $transfer->driver_name ?? '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="bt-note">Catatan</label>
                        <input type="text" id="bt-note" class="form-control bt-docfield" maxlength="2000" value="{{ $transfer->note ?? '' }}" placeholder="Tampil di surat jalan">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 2. Barang --}}
<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span class="bt-card-title"><span class="bt-step" id="bt-step-2">2</span>Pilih Barang Dikirim</span>
        <span class="bt-card-hint" id="bt-wh-label">Pilih gudang asal dulu</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="bt-pick-toolbar">
            <div class="btn-group btn-group-sm bt-view" role="group" aria-label="Tampilan">
                <button type="button" class="btn btn-outline-secondary active" data-view="all" disabled><i class="fa fa-warehouse me-1"></i>Stok Gudang <span class="badge rounded-pill ms-1" id="bt-view-all-count">0</span></button>
                <button type="button" class="btn btn-outline-secondary" data-view="picked" disabled><i class="fa fa-check me-1"></i>Dipilih <span class="badge rounded-pill ms-1" id="bt-view-count">0</span></button>
            </div>
            <div class="bt-pick-search">
                <i class="fa fa-magnifying-glass"></i>
                <input type="search" id="bt-q" class="form-control form-control-sm" placeholder="Cari kode, nama, SN, batch, catatan…" autocomplete="off" disabled>
                <kbd title="Tekan / untuk mencari">/</kbd>
            </div>
            <select id="bt-f-kind" class="form-select form-select-sm" disabled aria-label="Jenis">
                <option value="">Semua jenis</option>
                <option value="material">Material (per qty)</option>
                <option value="serial">Barang ber-SN</option>
            </select>
            <select id="bt-f-owner" class="form-select form-select-sm" disabled aria-label="Kepemilikan">
                <option value="">Semua kepemilikan</option>
                <option value="free">Stok bebas</option>
                <option value="project">Milik project</option>
            </select>
        </div>
        <div class="bt-pick-wrap">
            <table class="table table-custom align-middle mb-0 bt-pick-table">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center"><input type="checkbox" class="form-check-input" id="bt-check-all" title="Pilih semua yang tampil" disabled></th>
                        <th style="width:150px">Code</th>
                        <th>Nama Barang</th>
                        <th style="width:200px">Posisi</th>
                        <th style="width:140px">Masuk / Kedaluwarsa</th>
                        <th style="width:120px" class="text-end">Stok</th>
                        <th style="width:170px" class="text-end">Ambil</th>
                    </tr>
                </thead>
                <tbody id="bt-pick-body"></tbody>
            </table>
        </div>
        <div class="bt-pick-foot">
            <span><i class="fa fa-circle-info me-1"></i>Klik baris untuk memilih · Shift+klik untuk rentang · Enter di qty pindah ke baris berikutnya · Material boleh sebagian, barang ber-SN per unit</span>
            <span id="bt-pick-info"></span>
        </div>
    </div>
</div>

{{-- Bilah aksi --}}
<div class="bt-actionbar" id="bt-actionbar">
    <div class="bt-ab-route">
        <div class="bt-ab-label">Rute</div>
        <div class="bt-ab-path" id="bt-ab-route">—</div>
    </div>
    <div class="bt-ab-stats" title="Klik untuk melihat barang dipilih">
        <div class="bt-ab-stat" data-goto="picked"><div class="n" id="bt-sum-lines">0</div><div class="l">Baris stok</div></div>
        <div class="bt-ab-stat" data-goto="picked"><div class="n" id="bt-sum-products">0</div><div class="l">Jenis barang</div></div>
        <div class="bt-ab-stat" data-goto="picked"><div class="n" id="bt-sum-serial">0</div><div class="l">Unit ber-SN</div></div>
    </div>
    <div class="bt-ab-actions">
        <span class="bt-ab-dirty" id="bt-dirty"><i class="fa fa-circle me-1" style="font-size:7px"></i>Belum disimpan</span>
        <button type="button" class="btn btn-link btn-sm" id="bt-clear" style="display:none">Batalkan pilihan</button>
        <a href="{{ $isEdit ? route('branch-transfer.show', $transfer->id) : route('branch-transfer.index') }}" class="btn btn-outline-secondary btn-sm bt-leave">Batal</a>
        <button type="button" class="btn btn-primary btn-sm" id="bt-save" onclick="btSave()" title="Ctrl+S">
            <i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
        </button>
    </div>
</div>
@endsection

@section('scripts')
<script>
const btOptionsUrl = '{{ route("branch-transfer.stock-options") }}';
const btSaveUrl = '{{ $isEdit ? route("branch-transfer.update", $transfer->id) : route("branch-transfer.store") }}';
const btSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const btShowUrl = '{{ route("branch-transfer.show", "__ID__") }}';
const btLimit = 2000;
let btSaveLabel = '';
let btRows = [];                                   // baris stok gudang asal (dimuat sekali)
let btSel = @json((object) $selected->all());      // {stock_id: qty} pilihan
let btView = 'all';
let btLastIdx = null;
let btVisible = [];                                // index btRows yang sedang tampil
let btXhr = null;
let btDirty = false;
let btSaving = false;

function btEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function btNum(v) { return Num.format(v, 4); }
function btIsPicked(id) { return btSel[id] != null; }

function btSetToolbarEnabled(on) {
    $('#bt-q, #bt-f-kind, #bt-f-owner, #bt-check-all, .bt-view .btn').prop('disabled', !on);
}

function btEmptyState(icon, html) {
    return '<tr><td colspan="7" class="bt-pick-empty"><i class="fa-solid ' + icon + ' bt-empty-icon"></i>' + html + '</td></tr>';
}

function btLoad() {
    var wh = $('#bt-from-warehouse').val();
    btRows = []; btLastIdx = null;
    $('#bt-wh-label').text(wh ? 'Stok di ' + $('#bt-from-warehouse option:selected').text() : 'Pilih gudang asal dulu');
    $('#bt-view-all-count').text(0);
    if (!wh) {
        btSetToolbarEnabled(false);
        $('#bt-pick-body').html(btEmptyState('fa-warehouse', 'Pilih <strong>Gudang Asal</strong> di langkah 1 untuk menampilkan stok yang bisa dikirim.'));
        $('#bt-pick-info').text('');
        btSummary();
        return;
    }
    if (btXhr) btXhr.abort();
    btSetToolbarEnabled(false);
    $('#bt-pick-body').html('<tr><td colspan="7" class="bt-pick-empty"><span class="spinner-border spinner-border-sm me-1"></span>Memuat stok gudang…</td></tr>');
    btXhr = $.getJSON(btOptionsUrl, { warehouse_id: wh }).done(function(res) {
        btRows = res.data || [];
        // Pilihan lama yang qty-nya melebihi stok sekarang disesuaikan.
        var byId = {};
        btRows.forEach(function(r) { byId[r.id] = r; });
        Object.keys(btSel).forEach(function(id) {
            if (byId[id] && btSel[id] > byId[id].qty) btSel[id] = byId[id].qty;
        });
        btSetToolbarEnabled(true);
        $('#bt-view-all-count').text(btRows.length);
        $('#bt-pick-info').text(btRows.length >= btLimit ? 'Menampilkan ' + btLimit + ' baris pertama — gunakan pencarian.' : btRows.length + ' baris stok di gudang ini');
        btRender();
    }).fail(function(xhr) {
        if (xhr.statusText === 'abort') return;
        $('#bt-pick-body').html('<tr><td colspan="7" class="bt-pick-empty text-danger">' + btEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat stok.') + '</td></tr>');
    });
}

function btMatches(r) {
    var terms = String($('#bt-q').val() || '').toLowerCase().split(/\s+/).filter(Boolean);
    var kind = $('#bt-f-kind').val(), owner = $('#bt-f-owner').val();
    if (btView === 'picked' && !btIsPicked(r.id)) return false;
    if (kind === 'material' && r.serialized) return false;
    if (kind === 'serial' && !r.serialized) return false;
    if (owner === 'free' && r.project_id) return false;
    if (owner === 'project' && !r.project_id) return false;
    if (!terms.length) return true;
    var hay = [r.product_code, r.product_name, r.serial_number, r.batch_number, r.identity, r.note, r.project].join(' ').toLowerCase();
    return terms.every(function(t) { return hay.indexOf(t) !== -1; });
}

function btRowHtml(r, idx) {
    var picked = btIsPicked(r.id);
    var qty = picked ? btSel[r.id] : '';
    var ident = r.serialized
        ? (r.serial_number ? 'SN ' + btEsc(r.serial_number) : '<span style="color:#b45309">SN belum diisi</span>')
        : (r.batch_number ? 'Batch ' + btEsc(r.batch_number) : '');
    var take = r.serialized
        ? '<span class="bt-sub">' + (picked ? '<strong style="color:#065f46">1 unit</strong>' : '1 unit') + '</span>'
        : '<div class="input-group input-group-sm bt-qty-group">'
            + '<input type="text" inputmode="decimal" class="form-control bt-qty" placeholder="0" value="' + (picked ? btEsc(qty) : '') + '">'
            + '<button type="button" class="btn btn-outline-secondary bt-max" title="Ambil semua (' + btEsc(r.qty_label) + ')">Maks</button></div>';
    return '<tr data-id="' + r.id + '" data-idx="' + idx + '"' + (picked ? ' class="is-picked"' : '') + '>'
        + '<td class="text-center"><input type="checkbox" class="form-check-input bt-check"' + (picked ? ' checked' : '') + '></td>'
        + '<td><div class="bt-code">' + btEsc(r.product_code || '—') + '</div><span class="bt-kind ' + (r.serialized ? 'bt-kind-serial">Ber-SN' : 'bt-kind-material">Material') + '</span></td>'
        + '<td><div style="font-weight:600">' + btEsc(r.product_name || '—') + '</div>'
            + (ident ? '<div class="bt-sub">' + ident + '</div>' : '')
            + (r.note ? '<div class="bt-sub"><i class="fa fa-note-sticky me-1"></i>' + btEsc(r.note) + '</div>' : '') + '</td>'
        + '<td>' + (r.project
            ? '<span class="bt-own bt-own-project">Project</span><div class="bt-sub">' + btEsc(r.project) + '</div>'
            : '<span class="bt-own bt-own-free">Stok bebas</span>')
            + '<div class="bt-sub">' + btEsc(r.warehouse || '') + '</div></td>'
        + '<td>' + btEsc(r.received_date || '—') + (r.expiry_date ? '<div class="bt-sub">exp ' + btEsc(r.expiry_date) + '</div>' : '') + '</td>'
        + '<td class="text-end"><strong>' + btEsc(r.qty_label) + '</strong> <span class="bt-sub">' + btEsc(r.unit || '') + '</span></td>'
        + '<td class="text-end">' + take + '</td>'
        + '</tr>';
}

function btRender() {
    if (!$('#bt-from-warehouse').val()) return;
    btVisible = [];
    var html = '';
    btRows.forEach(function(r, idx) {
        if (!btMatches(r)) return;
        btVisible.push(idx);
        html += btRowHtml(r, idx);
    });
    if (!btRows.length) {
        html = btEmptyState('fa-inbox', 'Tidak ada stok yang bisa dikirim di gudang ini.');
    } else if (!btVisible.length) {
        html = btView === 'picked'
            ? btEmptyState('fa-hand-pointer', 'Belum ada barang yang dipilih. Buka tab <strong>Stok Gudang</strong> lalu klik baris barang.')
            : btEmptyState('fa-filter-circle-xmark', 'Tidak ada stok yang cocok dengan pencarian / filter.');
    }
    $('#bt-pick-body').html(html);
    btLastIdx = null;
    btSummary();
}

function btSetPicked(idx, on, qty) {
    var r = btRows[idx];
    if (!r) return;
    if (on) {
        var q = qty != null ? qty : (btSel[r.id] != null ? btSel[r.id] : (r.serialized ? 1 : r.qty));
        btSel[r.id] = Math.min(q, r.qty);
    } else {
        delete btSel[r.id];
    }
    var $tr = $('#bt-pick-body tr[data-idx="' + idx + '"]');
    if ($tr.length) {
        $tr.toggleClass('is-picked', on).find('.bt-check').prop('checked', on);
        if (!r.serialized) {
            var $q = $tr.find('.bt-qty');
            if (!$q.is(':focus')) $q.val(on ? btSel[r.id] : '');
        } else {
            $tr.find('td:last').html('<span class="bt-sub">' + (on ? '<strong style="color:#065f46">1 unit</strong>' : '1 unit') + '</span>');
        }
    }
}

function btSummary() {
    var ids = Object.keys(btSel);
    var byId = {};
    btRows.forEach(function(r) { byId[r.id] = r; });
    var products = {}, serial = 0;
    ids.forEach(function(id) {
        var r = byId[id];
        products[r ? r.master_product_id : 'x' + id] = true;
        if (r && r.serialized) serial++;
    });
    $('#bt-sum-lines').text(ids.length);
    $('#bt-sum-products').text(Object.keys(products).length);
    $('#bt-sum-serial').text(serial);
    $('#bt-view-count').text(ids.length);
    $('#bt-clear').toggle(ids.length > 0);

    // Header checkbox: semua / sebagian baris tampil terpilih.
    var vis = btVisible.length, pickedVis = btVisible.filter(function(i) { return btIsPicked(btRows[i].id); }).length;
    $('#bt-check-all').prop('checked', vis > 0 && pickedVis === vis).prop('indeterminate', pickedVis > 0 && pickedVis < vis);
    $('#bt-step-2').toggleClass('is-done', ids.length > 0).html(ids.length > 0 ? '<i class="fa fa-check" style="font-size:10px"></i>' : '2');
    btRouteSummary();
}

function btRouteSummary() {
    var from = $('#bt-from-warehouse option:selected'), to = $('#bt-to-branch option:selected');
    var fromTxt = from.val() ? from.data('branch-label') + ' · ' + from.text().split(' (')[0] : '—';
    var toTxt = to.val() ? to.text().split(' — ').pop() : '—';
    $('#bt-ab-route').html(btEsc(fromTxt) + ' <i class="fa fa-arrow-right mx-1" style="color:var(--accent)"></i> ' + btEsc(toTxt));
    var done = !!(from.val() && to.val() && $('#bt-date').val());
    $('#bt-step-1').toggleClass('is-done', done).html(done ? '<i class="fa fa-check" style="font-size:10px"></i>' : '1');
    $('#bt-from-hint').html(from.val() ? '<span class="bt-wh-meta"><i class="fa fa-building me-1"></i>Branch asal: ' + btEsc(from.data('branch-label')) + '</span>' : $('#bt-from-hint').data('default'));
}

function btMarkDirty() {
    btDirty = true;
    $('#bt-dirty').show();
}

function btSetView(view) {
    btView = view;
    $('.bt-view .btn').removeClass('active').filter('[data-view="' + view + '"]').addClass('active');
    btRender();
}

// Klik baris = pilih/batal; Shift+klik = rentang. Klik di input qty tidak men-toggle.
$(document).on('click', '#bt-pick-body tr[data-id]', function(e) {
    if ($(e.target).closest('.bt-qty, .bt-max').length) return;
    var idx = parseInt($(this).data('idx'), 10);
    var on = $(e.target).is('.bt-check') ? e.target.checked : !btIsPicked(btRows[idx].id);
    if (e.shiftKey && btLastIdx !== null) {
        var a = btVisible.indexOf(btLastIdx), b = btVisible.indexOf(idx);
        if (a > b) { var t = a; a = b; b = t; }
        for (var i = a; i <= b; i++) btSetPicked(btVisible[i], on);
        if (window.getSelection) window.getSelection().removeAllRanges();
    } else {
        btSetPicked(idx, on);
    }
    btLastIdx = idx;
    btMarkDirty();
    btSummary();
});

$(document).on('input', '#bt-pick-body .bt-qty', function() {
    this.value = this.value.replace(/[^\d.]/g, '');
    var idx = parseInt($(this).closest('tr').data('idx'), 10), r = btRows[idx];
    var v = Num.parseOrNull(this.value) || 0;
    if (v > r.qty) { v = r.qty; this.value = r.qty; }
    btSetPicked(idx, v > 0, v);
    btMarkDirty();
    btSummary();
});
$(document).on('keydown', '#bt-pick-body .bt-qty', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var $next = $(this).closest('tr').nextAll('tr').find('.bt-qty').first();
    if ($next.length) $next.trigger('focus').trigger('select');
});
$(document).on('click', '#bt-pick-body .bt-max', function() {
    var idx = parseInt($(this).closest('tr').data('idx'), 10);
    btSetPicked(idx, true, btRows[idx].qty);
    $(this).closest('tr').find('.bt-qty').val(btRows[idx].qty);
    btMarkDirty();
    btSummary();
});

$(document).on('change', '#bt-check-all', function() {
    var on = this.checked;
    btVisible.forEach(function(i) { btSetPicked(i, on); });
    btMarkDirty();
    btSummary();
});
$(document).on('click', '#bt-clear', function() {
    if (!confirm('Batalkan semua ' + Object.keys(btSel).length + ' barang yang dipilih?')) return;
    btSel = {};
    btMarkDirty();
    btRender();
});
$(document).on('click', '.bt-view .btn', function() { btSetView($(this).data('view')); });
$(document).on('click', '.bt-ab-stat[data-goto]', function() {
    if (!btRows.length) return;
    btSetView('picked');
    $('html, body').animate({ scrollTop: $('#bt-pick-body').closest('.card-custom').offset().top - 80 }, 200);
});
let btTimer = null;
$(document).on('input', '#bt-q', function() { clearTimeout(btTimer); btTimer = setTimeout(btRender, 200); });
$(document).on('change', '#bt-f-kind, #bt-f-owner', btRender);

// Branch tujuan tidak boleh sama dengan branch gudang asal.
function btSyncBranches() {
    var branch = String($('#bt-from-warehouse option:selected').data('branch') || '');
    $('#bt-to-branch option[value!=""]').each(function() { $(this).prop('disabled', !!branch && this.value === branch); });
    if ($('#bt-to-branch option:selected').prop('disabled')) $('#bt-to-branch').val('');
}

let btPrevWarehouse = null;
$(document).on('focus', '#bt-from-warehouse', function() { btPrevWarehouse = this.value; });
$(document).on('change', '#bt-from-warehouse', function() {
    if (Object.keys(btSel).length && !confirm('Ganti gudang asal? ' + Object.keys(btSel).length + ' barang yang sudah dipilih akan dikosongkan.')) {
        $(this).val(btPrevWarehouse);
        return;
    }
    btPrevWarehouse = this.value;
    btSel = {};
    $(this).removeClass('is-invalid');
    btSyncBranches();
    btMarkDirty();
    btSetView('all');
    btLoad();
});
$(document).on('change', '#bt-to-branch, #bt-date', function() { $(this).removeClass('is-invalid'); btMarkDirty(); btRouteSummary(); });
$(document).on('input', '.bt-docfield', function() {
    btMarkDirty();
    $('#bt-docinfo-filled').toggle($('.bt-docfield').filter(function() { return this.value.trim() !== ''; }).length > 0);
});

// Pintasan: "/" fokus ke pencarian, Ctrl/Cmd+S simpan.
$(document).on('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); btSave(); return; }
    if (e.key === '/' && !$(e.target).is('input, textarea, select') && !$('#bt-q').prop('disabled')) { e.preventDefault(); $('#bt-q').trigger('focus'); }
});

// Peringatan saat meninggalkan halaman dengan perubahan belum disimpan.
window.addEventListener('beforeunload', function(e) {
    if (btDirty && !btSaving) { e.preventDefault(); e.returnValue = ''; }
});

function btSave() {
    if (btSaving) return;
    var missing = [];
    ['#bt-from-warehouse', '#bt-to-branch', '#bt-date'].forEach(function(sel) {
        var empty = !$(sel).val();
        $(sel).toggleClass('is-invalid', empty);
        if (empty) missing.push(sel);
    });
    if (missing.length) {
        toastr.error('Lengkapi rute pengiriman: gudang asal, branch tujuan, dan tanggal kirim.');
        $(missing[0]).trigger('focus');
        return;
    }
    var items = Object.keys(btSel).filter(function(id) { return btSel[id] > 0; })
        .map(function(id) { return { stock_id: parseInt(id, 10), qty: btSel[id] }; });
    if (!items.length) {
        toastr.error('Pilih minimal satu barang yang dikirim.');
        btSetView('all');
        return;
    }
    btSaving = true;

    $('#bt-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: btSaveUrl, method: btSaveMethod, contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify({
            from_warehouse_id: $('#bt-from-warehouse').val(),
            to_branch_id: $('#bt-to-branch').val(),
            transfer_date: $('#bt-date').val(),
            courier: $('#bt-courier').val(),
            vehicle_no: $('#bt-vehicle').val(),
            driver_name: $('#bt-driver').val(),
            note: $('#bt-note').val(),
            items: items
        })
    }).done(function(res) {
        btDirty = false;
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = btShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        btSaving = false;
        $('#bt-save').prop('disabled', false).html(btSaveLabel);
    });
}

$(document).ready(function() {
    btSaveLabel = $('#bt-save').html();
    $('#bt-from-hint').data('default', $('#bt-from-hint').html());
    // Qty pilihan awal (mode edit) sebagai angka.
    Object.keys(btSel).forEach(function(id) { btSel[id] = parseFloat(btSel[id]); });
    btSyncBranches();
    btRouteSummary();
    if ($('#bt-from-warehouse').val()) btLoad(); else { btLoad(); }
});
</script>
@endsection

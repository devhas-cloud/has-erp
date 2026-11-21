@extends('layouts.app')

@php $fmtQty = fn ($v) => \App\Support\StockCost::qty($v); @endphp

@section('title', 'Serah Terima Material')
@section('page-title', 'Serah Terima Material')

@section('styles')
<style>
    .mh-table td, .mh-table th { vertical-align: middle; }
    .mh-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .mh-unit { color: var(--text-muted); font-size: 12px; }
    .mh-qty { width: 110px; text-align: right; }
    .mh-sub { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
    .mh-sn { display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 0 6px; margin: 2px 2px 0 0; border-radius: 4px; background: #f1f5f9; color: var(--text-secondary); }
    .mh-tag { display: inline-block; font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 999px; white-space: nowrap; }
    .mh-tag.ok { background: #d1fae5; color: #065f46; }
    .mh-tag.part { background: #fef3c7; color: #92400e; }
    .mh-tag.none { background: #f1f5f9; color: var(--text-muted); }
    .mh-tag.empty { background: var(--danger-soft); color: #b42318; }
    .mh-info td { padding: 3px 0; font-size: 13px; vertical-align: top; }
    .mh-info td:first-child { width: 110px; color: var(--text-muted); }
    #mhSnModal .mh-sn-row { cursor: pointer; }
    #mhSnModal .mh-sn-row.is-selected td { background-color: #ecfdf5 !important; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $mr ? 'Serah Terima '.$mr->request_number : 'Serah Terima Material' }}</h1>
        <p class="page-header-sub">
            @if($mr)
                Isi qty yang diserahkan. Barang langsung keluar dari stok dan tidak bisa dibatalkan.
            @else
                Pilih Material Request yang sudah disetujui.
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $mr ? route('material-handover.create') : route('material-handover.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

@if(! $mr)
<div class="card-custom fade-in">
    <div class="card-header-custom"><span>Material Request Menunggu Diserahkan</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. MR</th>
                        <th>Project</th>
                        <th>Branch</th>
                        <th>Peminta</th>
                        <th class="text-center">Material belum lengkap</th>
                        <th style="width:110px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $row)
                        <tr>
                            <td><strong style="color:var(--text-primary)">{{ $row->request_number }}</strong><div class="mh-sub">{{ $row->request_date?->format('d/m/Y') }}</div></td>
                            <td>{{ $row->project?->project_number }} — {{ $row->project?->project_name }}</td>
                            <td>{{ $row->branch?->name }}</td>
                            <td>{{ $row->creator?->display_name ?? '—' }}</td>
                            <td class="text-center">{{ $row->items->filter(fn ($i) => $i->remainingQty() > 0)->count() }} / {{ $row->items->count() }}</td>
                            <td class="text-end"><a href="{{ route('material-handover.create', ['material_request_id' => $row->id]) }}" class="btn btn-sm btn-primary">Serahkan</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted)">Tidak ada Material Request yang menunggu diserahkan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@else
<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span>Permintaan</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <table class="mh-info" style="width:100%">
                            <tr><td>Project</td><td>{{ $mr->project?->project_number }} — {{ $mr->project?->project_name }}</td></tr>
                            <tr><td>Branch</td><td>{{ $mr->branch?->name }}</td></tr>
                            <tr><td>Peminta</td><td>{{ $mr->creator?->display_name ?? '—' }} <span class="mh-unit">&middot; {{ $mr->request_date?->format('d/m/Y') }}</span></td></tr>
                            @if($mr->note)<tr><td>Catatan MR</td><td>{{ $mr->note }}</td></tr>@endif
                        </table>
                    </div>
                    <div class="col-lg-6">
                        <div class="row g-2">
                            <div class="col-sm-5">
                                <label class="form-label" for="mh-date">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" id="mh-date" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                            <div class="col-sm-7">
                                <label class="form-label" for="mh-receiver">Diterima oleh <span class="text-danger">*</span></label>
                                <input type="text" id="mh-receiver" class="form-control" maxlength="150" placeholder="Nama penerima dari produksi">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="mh-note">Catatan</label>
                                <input type="text" id="mh-note" class="form-control" maxlength="2000" placeholder="Opsional">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Material</span>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="mhFillAll()">Isi semua sesuai sisa</button>
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom mh-table mb-0">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th class="mh-num">Diminta</th>
                                <th class="mh-num">Sudah</th>
                                <th class="mh-num">Sisa</th>
                                <th class="mh-num">Stok ada</th>
                                <th class="mh-num" style="width:150px">Serahkan</th>
                                <th style="width:110px">Status</th>
                            </tr>
                        </thead>
                        <tbody id="mh-rows"></tbody>
                    </table>
                </div>
                <div class="px-2 pt-2 mh-sub">Stok diambil otomatis dari gudang branch {{ $mr->branch?->name }}: milik project dulu, lalu yang paling lama masuk. Unit ber-SN dipilih otomatis dan bisa diganti. Sisa yang belum diserahkan tetap menunggu di MR.</div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Material diserahkan</td><td class="text-end fw-semibold" id="mh-sum-lines">0</td></tr>
                    <tr><td>Masih kurang setelah ini</td><td class="text-end fw-semibold" id="mh-sum-short">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="mh-save" onclick="mhSave()">Serahkan Barang</button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Stok langsung keluar dan tidak bisa dibatalkan. Bukti serah terima bisa dicetak setelah disimpan.</div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@if($mr)
@push('modals')
<div class="modal fade" id="mhSnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="mh-sn-title">Pilih SN</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-2">
                <table class="table table-sm mb-0">
                    <thead><tr><th style="width:36px"></th><th>SN</th><th>Gudang</th></tr></thead>
                    <tbody id="mh-sn-body"></tbody>
                </table>
            </div>
            <div class="modal-footer justify-content-between">
                <small class="mh-sub" id="mh-sn-count"></small>
                <button type="button" class="btn btn-primary btn-sm" onclick="mhApplySn()">Pakai SN Ini</button>
            </div>
        </div>
    </div>
</div>
@endpush
@endif

@section('scripts')
@if($mr)
<script>
const mhShowUrl = '{{ route("material-handover.show", "__ID__") }}';
const mhSaveLabel = $('#mh-save').html();
const mhLines = @json($lines);
const EPS = 0.00005;

function mhEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function mhFmt(v) { return Num.format(v, 4); }

mhLines.forEach(function(l) {
    l.available = l.stocks.reduce(function(s, r) { return s + r.qty; }, 0);
    l.max = Math.max(0, Math.min(l.remaining, l.available));
    if (l.serialized) l.max = Math.floor(l.max + EPS);
    l.qty = 0;
    l.sn = [];
});

// Stok per gudang: "G1 30 · G2 15".
function mhStockText(l) {
    var per = {};
    l.stocks.forEach(function(r) { per[r.warehouse] = (per[r.warehouse] || 0) + r.qty; });
    return Object.keys(per).map(function(w) { return mhEsc(w) + ' ' + mhFmt(per[w]); }).join(' &middot; ');
}

// Material: ambil berurutan dari baris stok (milik project dulu, lalu FIFO).
function mhMaterialPicks(l) {
    var left = l.qty, picks = [];
    l.stocks.forEach(function(r) {
        if (left <= EPS) return;
        var take = Math.min(r.qty, left);
        picks.push({ stock_id: r.id, qty: Math.round(take * 10000) / 10000, warehouse: r.warehouse });
        left -= take;
    });
    return picks;
}

// Barang ber-SN: pilihan otomatis FIFO, pilihan manual dipertahankan bila masih cocok.
function mhSyncSn(l) {
    var want = Math.round(l.qty);
    l.sn = l.sn.filter(function(id) { return l.stocks.some(function(r) { return r.id === id; }); }).slice(0, want);
    l.stocks.forEach(function(r) { if (l.sn.length < want && l.sn.indexOf(r.id) === -1) l.sn.push(r.id); });
}

function mhRow(l, i) {
    var html = '<tr data-idx="' + i + '">';
    html += '<td><div class="fw-semibold" style="color:var(--text-primary)">' + mhEsc(l.code) + '</div><div class="mh-sub">' + mhEsc(l.name || '') + (l.note ? ' &middot; ' + mhEsc(l.note) : '') + '</div></td>';
    html += '<td class="mh-num">' + mhFmt(l.requested) + ' <span class="mh-unit">' + mhEsc(l.unit || '') + '</span></td>';
    html += '<td class="mh-num">' + mhFmt(l.handed) + '</td>';
    html += '<td class="mh-num fw-semibold">' + mhFmt(l.remaining) + '</td>';
    html += '<td class="mh-num">' + (l.available > EPS ? mhFmt(l.available) + '<div class="mh-sub">' + mhStockText(l) + '</div>' : '<span class="mh-unit">—</span>')
        + (l.missing_sn ? '<div class="mh-sub" style="color:#92400e">' + l.missing_sn + ' unit belum ber-SN</div>' : '') + '</td>';
    html += '<td class="mh-num">';
    if (l.remaining <= EPS) {
        html += '<span class="mh-unit">—</span>';
    } else if (l.max <= EPS) {
        html += '<span class="mh-unit">stok kosong</span>';
    } else {
        html += '<input type="text" class="form-control form-control-sm mh-qty d-inline-block" ' + (l.serialized ? 'inputmode="numeric"' : 'data-number="qty"')
            + ' aria-label="Qty diserahkan ' + mhEsc(l.code) + '" value="' + (l.qty > EPS ? l.qty : '') + '" placeholder="0">'
            + '<div class="mh-sub">maks ' + mhFmt(l.max) + '</div>';
    }
    html += '<div class="mh-pick"></div></td>';
    html += '<td class="mh-status"></td></tr>';
    return html;
}

function mhRender() {
    $('#mh-rows').html(mhLines.map(mhRow).join('') || '<tr><td colspan="7" class="text-center py-4" style="color:var(--text-muted)">Semua material sudah diserahkan.</td></tr>');
    mhLines.forEach(function(l, i) { mhRefresh(i); });
}

function mhRefresh(i) {
    var l = mhLines[i], $tr = $('#mh-rows tr[data-idx="' + i + '"]');
    var pick = '';
    if (l.qty > EPS) {
        if (l.serialized) {
            mhSyncSn(l);
            pick = l.sn.map(function(id) { var r = l.stocks.find(function(s) { return s.id === id; }); return '<span class="mh-sn">' + mhEsc(r.serial) + '</span>'; }).join('')
                + ' <button type="button" class="btn btn-link btn-sm p-0 align-baseline" onclick="mhOpenSn(' + i + ')">Ganti SN</button>';
        } else {
            pick = mhMaterialPicks(l).map(function(p) { return mhEsc(p.warehouse) + ' ' + mhFmt(p.qty); }).join(' &middot; ');
        }
    }
    $tr.find('.mh-pick').html(pick ? '<div class="mh-sub">' + pick + '</div>' : '');

    var after = l.remaining - l.qty, status;
    if (l.remaining <= EPS) status = '<span class="mh-tag ok">Lengkap</span>';
    else if (l.max <= EPS) status = '<span class="mh-tag empty">Stok kosong</span>';
    else if (l.qty <= EPS) status = '<span class="mh-tag none">Belum diisi</span>';
    else if (after <= EPS) status = '<span class="mh-tag ok">Lengkap</span>';
    else status = '<span class="mh-tag part">Kurang ' + mhFmt(after) + '</span>';
    $tr.find('.mh-status').html(status);
    mhSummary();
}

function mhSummary() {
    var lines = 0, short = 0;
    mhLines.forEach(function(l) {
        if (l.qty > EPS) lines++;
        if (l.remaining - l.qty > EPS) short++;
    });
    $('#mh-sum-lines').text(lines);
    $('#mh-sum-short').text(short);
}

$(document).on('input', '.mh-qty', function() {
    var i = $(this).closest('tr').data('idx'), l = mhLines[i];
    if (l.serialized) this.value = this.value.replace(/\D/g, '');
    var v = Num.parseOrNull(this.value) || 0;
    if (v > l.max + EPS) { v = l.max; this.value = l.serialized ? v : Num.format(v, 4); }
    l.qty = v;
    $(this).removeClass('is-invalid');
    mhRefresh(i);
});

// Enter di qty: pindah ke qty berikutnya.
$(document).on('keydown', '.mh-qty', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var $q = $('.mh-qty'), $next = $q.eq($q.index(this) + 1);
    if ($next.length) $next.focus().select();
});

function mhFillAll() {
    mhLines.forEach(function(l, i) {
        if (l.max <= EPS) return;
        l.qty = l.max;
        $('#mh-rows tr[data-idx="' + i + '"] .mh-qty').val(l.serialized ? l.max : Num.format(l.max, 4));
        mhRefresh(i);
    });
}

// ── Ganti SN ──
let mhSnIdx = null, mhSnModal = null;
function mhOpenSn(i) {
    var l = mhLines[i];
    mhSnIdx = i;
    $('#mh-sn-title').text('Pilih SN ' + l.code + ' (' + Math.round(l.qty) + ' unit)');
    $('#mh-sn-body').html(l.stocks.map(function(r) {
        var on = l.sn.indexOf(r.id) !== -1;
        return '<tr class="mh-sn-row' + (on ? ' is-selected' : '') + '" data-id="' + r.id + '">'
            + '<td><input type="checkbox" class="form-check-input mh-sn-check" aria-label="SN ' + mhEsc(r.serial) + '"' + (on ? ' checked' : '') + '></td>'
            + '<td><span class="mh-sn">' + mhEsc(r.serial) + '</span>' + (r.own ? ' <span class="mh-unit">milik project</span>' : '') + '</td>'
            + '<td>' + mhEsc(r.warehouse) + '</td></tr>';
    }).join(''));
    mhSnCount();
    if (!mhSnModal) mhSnModal = new bootstrap.Modal(document.getElementById('mhSnModal'));
    mhSnModal.show();
}
function mhSnCount() {
    var n = $('#mh-sn-body .mh-sn-check:checked').length, want = Math.round(mhLines[mhSnIdx].qty);
    $('#mh-sn-count').text(n + ' dari ' + want + ' unit dipilih');
}
$(document).on('click', '#mh-sn-body .mh-sn-row', function(e) {
    var $c = $(this).find('.mh-sn-check');
    if (!$(e.target).is('input')) $c.prop('checked', !$c.prop('checked'));
    $(this).toggleClass('is-selected', $c.prop('checked'));
    mhSnCount();
});
function mhApplySn() {
    var l = mhLines[mhSnIdx], ids = $('#mh-sn-body .mh-sn-check:checked').map(function() { return $(this).closest('tr').data('id'); }).get();
    if (ids.length !== Math.round(l.qty)) { toastr.error('Pilih tepat ' + Math.round(l.qty) + ' SN.'); return; }
    l.sn = ids;
    mhSnModal.hide();
    mhRefresh(mhSnIdx);
}

function mhSave() {
    var $receiver = $('#mh-receiver');
    if (!$.trim($receiver.val())) { toastr.error('Isi nama penerima barang.'); $receiver.addClass('is-invalid').focus(); return; }
    var items = [], count = 0;
    mhLines.forEach(function(l) {
        if (l.qty <= EPS) return;
        count++;
        if (l.serialized) l.sn.forEach(function(id) { items.push({ material_request_item_id: l.id, stock_id: id, qty: 1 }); });
        else mhMaterialPicks(l).forEach(function(p) { items.push({ material_request_item_id: l.id, stock_id: p.stock_id, qty: p.qty }); });
    });
    if (!items.length) { toastr.error('Isi qty minimal satu material.'); $('.mh-qty').first().addClass('is-invalid').focus(); return; }

    Swal.fire({ title: 'Serahkan barang?', text: count + ' material langsung keluar dari stok dan tidak bisa dibatalkan.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Serahkan', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $('#mh-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
            $.ajax({
                url: '{{ route("material-handover.store") }}', method: 'POST', contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                data: JSON.stringify({ material_request_id: {{ $mr->id }}, handover_date: $('#mh-date').val(), received_by_name: $.trim($receiver.val()), note: $('#mh-note').val(), items: items })
            }).done(function(res) {
                toastr.success(res.message || 'Tersimpan.');
                setTimeout(function() { window.location.href = mhShowUrl.replace('__ID__', res.id); }, 600);
            }).fail(function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.';
                if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
                toastr.error(msg);
                $('#mh-save').prop('disabled', false).html(mhSaveLabel);
            });
        });
}
$(document).on('input', '#mh-receiver', function() { $(this).removeClass('is-invalid'); });

$(document).ready(mhRender);
</script>
@endif
@endsection

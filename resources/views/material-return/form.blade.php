@extends('layouts.app')

@section('title', 'Catat Retur Material')
@section('page-title', 'Catat Retur Material')

@section('styles')
<style>
    .mr-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 4px 8px 10px; }
    .mr-toolbar .mr-search { flex: 1 1 220px; max-width: 320px; }
    .mr-toolbar select { width: auto; max-width: 220px; font-size: 12.5px; }
    .mr-toolbar .mr-count { font-size: 12.5px; color: var(--text-muted); margin-left: auto; white-space: nowrap; }
    @media (max-width: 991.98px) { .mr-toolbar select, .mr-toolbar .mr-search { max-width: none; flex: 1 1 45%; } .mr-toolbar .mr-count { margin-left: 0; } }
    .mr-pool td, .mr-pool th { font-size: 12.5px; vertical-align: middle; }
    .mr-pool .mr-qty { width: 96px; }
    .mr-pool .mr-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .mr-sn { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
    .mr-kind { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 0 7px; border-radius: 999px; }
    .mr-kind.material { background: #e0f2fe; color: #075985; }
    .mr-kind.serial { background: #fef3c7; color: #92400e; }
    .mr-pool tbody tr[data-id] { cursor: pointer; user-select: none; }
    .mr-pool tbody tr[data-id] td { transition: background-color .12s ease; }
    .mr-pool tbody tr[data-id]:hover td { background-color: #d1fae5 !important; color: #065f46; }
    .mr-pool tbody tr.is-selected td { background-color: #ecfdf5 !important; }
    .mr-pool .mr-check, #mr-check-all { width: 16px; height: 16px; cursor: pointer; vertical-align: middle; }
    .mr-pool .mr-qty { cursor: text; user-select: text; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Catat Retur Material</h1>
        <p class="page-header-sub">Pilih branch dan material sisa produksi yang dikembalikan ke gudang asal sebagai stok bebas. Retur menunggu approval sebelum stok masuk.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('material-return.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span>1. Branch</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label" for="mr-branch">Branch <span class="text-danger">*</span></label>
                        <select id="mr-branch" class="form-select">
                            <option value="">— Pilih Branch —</option>
                            @foreach($groups as $g)
                                <option value="{{ $g['branch_id'] }}">{{ $g['label'] }} ({{ $g['lines'] }} baris)</option>
                            @endforeach
                        </select>
                        @if($groups->isEmpty())
                            <small style="color:var(--text-muted)">Belum ada Material di Produksi yang bisa diretur. Material masuk lewat Serah Terima Material.</small>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="mr-date">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" id="mr-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span>2. Material Diretur</span></div>
            <div class="card-body-custom p-2">
                <div class="mr-toolbar">
                    <input type="search" id="mr-filter" class="form-control form-control-sm mr-search" placeholder="Cari kode, nama, SN, serah terima…" autocomplete="off" aria-label="Cari material">
                    <select id="mr-f-project" class="form-select form-select-sm" aria-label="Project asal"><option value="">Semua project asal</option></select>
                    <select id="mr-f-kind" class="form-select form-select-sm" aria-label="Jenis barang">
                        <option value="">Semua jenis</option>
                        <option value="material">Material</option>
                        <option value="serial">Barang ber-SN</option>
                    </select>
                    <span class="mr-count"><span id="mr-count">0</span> dipilih <button type="button" class="btn btn-link btn-sm p-0 ms-1" id="mr-clear" style="display:none">Hapus pilihan</button></span>
                </div>
                <div id="mr-pool"><div class="text-center py-4" style="color:var(--text-muted)">Pilih branch terlebih dahulu.</div></div>
                <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)">Klik baris untuk memilih. Material boleh diretur sebagian; barang ber-SN diretur utuh. Material kembali ke gudang asal sebagai stok bebas dengan harga modal saat diserahkan.</div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Material</td><td class="text-end fw-semibold" id="mr-sum-lines">0</td></tr>
                    <tr><td>Total nilai</td><td class="text-end fw-semibold" id="mr-sum-cost">0</td></tr>
                </table>
                <div class="mt-3">
                    <label class="form-label" for="mr-note">Catatan</label>
                    <input type="text" id="mr-note" class="form-control" maxlength="2000" placeholder="Opsional">
                </div>
                <button type="button" class="btn btn-primary w-100 mt-3" id="mr-save" onclick="mrSave()">Ajukan Retur</button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Nilai dari harga modal material saat diserahkan. Retur tidak bisa dibatalkan.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const mrPoolUrl = '{{ route("material-return.pool") }}';
const mrShowUrl = '{{ route("material-return.show", "__ID__") }}';
const mrSaveLabel = $('#mr-save').html();
let mrRows = [];

function mrEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function mrRow(id) { return mrRows.find(function(r) { return r.id === id; }); }

function mrPicks() {
    return mrRows.filter(function(r) { return r.checked && r.qty > 0; })
        .map(function(r) { return { material_handover_item_id: r.id, qty: r.qty, cost: r.qty * r.unit_cost }; });
}

function mrSummary() {
    var picks = mrPicks();
    var total = picks.reduce(function(s, p) { return s + p.cost; }, 0);
    var selected = mrRows.filter(function(r) { return r.checked; }).length;
    $('#mr-sum-lines').text(picks.length);
    $('#mr-sum-cost').text(Num.format(total, 0));
    $('#mr-count').text(selected);
    $('#mr-clear').toggle(selected > 0);
}

function mrFillOptions($select, label, values) {
    var current = $select.val();
    var html = '<option value="">' + label + '</option>';
    values.forEach(function(v) { html += '<option value="' + mrEsc(v[0]) + '">' + mrEsc(v[1]) + '</option>'; });
    $select.html(html).val(current && values.some(function(v) { return String(v[0]) === current; }) ? current : '');
}

function mrVisibleRows() {
    var q = ($('#mr-filter').val() || '').trim().toLowerCase();
    var project = $('#mr-f-project').val(), kind = $('#mr-f-kind').val();
    return mrRows.filter(function(r) {
        if (project && String(r.project_id) !== project) return false;
        if (kind && (kind === 'serial') !== !!r.serialized) return false;
        if (!q) return true;
        return [r.product_code, r.product_name, r.identity, r.handover_number, r.project_label, r.warehouse].some(function(v) {
            return (v || '').toString().toLowerCase().indexOf(q) !== -1;
        });
    });
}

function mrRenderPool() {
    if (!mrRows.length) return;
    var rows = mrVisibleRows();
    if (!rows.length) {
        $('#mr-pool').html('<div class="text-center py-3" style="color:var(--text-muted)">Tidak ada material yang cocok dengan pencarian / filter.</div>');
        return;
    }

    var html = '<div class="table-responsive" style="max-height:380px;overflow:auto"><table class="table table-sm mr-pool mb-0"><thead><tr>'
        + '<th style="width:34px" class="text-center"><input type="checkbox" id="mr-check-all" aria-label="Pilih semua yang tampil"></th>'
        + '<th>Material</th><th>Serah Terima</th><th>Project Asal</th><th>Gudang Asal</th><th class="mr-num">Sisa</th><th class="mr-num">Retur</th></tr></thead><tbody>';
    rows.forEach(function(r) {
        html += '<tr data-id="' + r.id + '"' + (r.checked ? ' class="is-selected"' : '') + '>';
        html += '<td class="text-center"><input type="checkbox" class="form-check-input mr-check" aria-label="Pilih ' + mrEsc(r.product_code) + '"' + (r.checked ? ' checked' : '') + '></td>';
        html += '<td><div class="fw-semibold">' + mrEsc(r.product_code) + ' <span style="font-weight:400;color:var(--text-muted)">' + mrEsc(r.product_name) + '</span></div>'
            + '<span class="mr-kind ' + (r.serialized ? 'serial' : 'material') + '">' + (r.serialized ? 'Ber-SN' : 'Material') + '</span> '
            + (r.serialized ? '<span class="mr-sn">' + mrEsc(r.identity) + '</span>' : '') + '</td>';
        html += '<td style="color:var(--text-muted)">' + mrEsc(r.handover_number) + '</td>';
        html += '<td style="color:var(--text-muted);font-size:12px">' + mrEsc(r.project_label) + '</td>';
        html += '<td style="color:var(--text-muted);font-size:12px">' + mrEsc(r.warehouse) + '</td>';
        html += '<td class="mr-num">' + mrEsc(r.remaining_label) + ' <span style="color:var(--text-muted)">' + mrEsc(r.unit || '') + '</span></td>';
        html += '<td class="mr-num">' + (r.serialized
            ? '<span style="color:var(--text-muted)">1 unit</span>'
            : '<input type="text" inputmode="decimal" class="form-control form-control-sm text-end mr-qty d-inline-block" aria-label="Qty retur ' + mrEsc(r.product_code) + '" value="' + r.qty + '">') + '</td>';
        html += '</tr>';
    });
    $('#mr-pool').html(html + '</tbody></table></div>');
    mrSyncCheckAll();
}

function mrSyncCheckAll() {
    var rows = mrVisibleRows(), on = rows.filter(function(r) { return r.checked; }).length;
    $('#mr-check-all').prop('checked', rows.length > 0 && on === rows.length).prop('indeterminate', on > 0 && on < rows.length);
}

function mrSetChecked(r, checked) {
    r.checked = checked;
    if (checked && !(r.qty > 0)) r.qty = r.remaining;
    $('#mr-pool tr[data-id="' + r.id + '"]').toggleClass('is-selected', checked).find('.mr-check').prop('checked', checked);
}

function mrLoadPool() {
    var branchId = $('#mr-branch').val();
    mrRows = [];
    $('#mr-filter').val('');
    $('#mr-f-project, #mr-f-kind').val('');
    if (!branchId) {
        $('#mr-pool').html('<div class="text-center py-4" style="color:var(--text-muted)">Pilih branch terlebih dahulu.</div>');
        mrSummary();
        return;
    }
    $('#mr-pool').html('<div class="text-center py-3" style="color:var(--text-muted)"><span class="spinner-border spinner-border-sm me-1"></span>Memuat material...</div>');
    $.get(mrPoolUrl, { branch_id: branchId }).done(function(res) {
        mrRows = (res.data || []).map(function(r) { r.qty = r.remaining; r.checked = false; return r; });

        var projects = {};
        mrRows.forEach(function(r) { if (r.project_id) projects[r.project_id] = r.project_label; });
        mrFillOptions($('#mr-f-project'), 'Semua project asal', Object.keys(projects).map(function(k) { return [k, projects[k]]; }).sort(function(a, b) { return a[1].localeCompare(b[1]); }));

        if (!mrRows.length) {
            $('#mr-pool').html('<div class="text-center py-3" style="color:var(--text-muted)">Tidak ada sisa material di branch ini.</div>');
        } else {
            mrRenderPool();
        }
        mrSummary();
    }).fail(function(xhr) {
        $('#mr-pool').html('<div class="text-danger py-2" style="font-size:13px">' + mrEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat material.') + '</div>');
    });
}

$(document).on('change', '#mr-branch', mrLoadPool);
$(document).on('input', '#mr-filter', function() { mrRenderPool(); });
$(document).on('change', '#mr-f-project, #mr-f-kind', function() { mrRenderPool(); });

$(document).on('click', '.mr-pool tbody tr[data-id]', function(e) {
    if ($(e.target).is('.mr-qty')) return;
    var r = mrRow($(this).data('id'));
    if (!r) return;
    var checked = $(e.target).is('.mr-check') ? $(e.target).prop('checked') : !r.checked;
    mrSetChecked(r, checked);
    mrSyncCheckAll();
    mrSummary();
});
$(document).on('change', '#mr-check-all', function() {
    var checked = this.checked;
    mrVisibleRows().forEach(function(r) { mrSetChecked(r, checked); });
    mrRenderPool();
    mrSummary();
});
$(document).on('click', '#mr-clear', function() {
    mrRows.forEach(function(r) { r.checked = false; });
    mrRenderPool();
    mrSummary();
});
$(document).on('input', '.mr-qty', function() {
    this.value = this.value.replace(/[^\d.]/g, '');
    var r = mrRow($(this).closest('tr[data-id]').data('id'));
    if (!r) return;
    var val = Num.parseOrNull(this.value) || 0;
    if (val > r.remaining) { val = r.remaining; this.value = r.remaining; }
    r.qty = val;
    mrSetChecked(r, val > 0);
    mrSyncCheckAll();
    mrSummary();
});

function mrSave() {
    var branchId = $('#mr-branch').val();
    if (!branchId) { toastr.error('Pilih branch.'); return; }
    var picks = mrPicks();
    if (!picks.length) { toastr.error('Pilih minimal satu material yang diretur.'); return; }

    var payload = {
        branch_id: branchId,
        return_date: $('#mr-date').val(),
        note: $('#mr-note').val(),
        items: picks.map(function(p) { return { material_handover_item_id: p.material_handover_item_id, qty: p.qty }; })
    };

    $('#mr-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: '{{ route("material-return.store") }}', method: 'POST', contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify(payload)
    }).done(function(res) {
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = mrShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#mr-save').prop('disabled', false).html(mrSaveLabel);
    });
}
</script>
@endsection

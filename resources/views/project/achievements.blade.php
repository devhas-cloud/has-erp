@extends('layouts.app')

@section('title', 'Rincian Achievement Device')
@section('page-title', 'Rincian Achievement Device')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Rincian Achievement per Device</h1>
        <p class="page-header-sub">
            {{ $project->project_number }} &middot; {{ $project->project_name }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('project.show', $project->id) }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <div class="row g-3">
            <div class="col-md-4">
                <div style="font-size:12px;color:var(--text-muted)">Net Sale Amount</div>
                <div style="font-size:18px;font-weight:700;color:var(--accent)">{{ \App\Models\Project::formatMoney($project->net_sale_amount) }}</div>
            </div>
            <div class="col-md-4">
                <div style="font-size:12px;color:var(--text-muted)">Total Rincian</div>
                <div style="font-size:18px;font-weight:700" id="pj-ach-total">Rp 0</div>
            </div>
            <div class="col-md-4">
                <div style="font-size:12px;color:var(--text-muted)">Status</div>
                <div id="pj-ach-status" style="font-size:13px">—</div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>Device</span>
        <button type="button" class="btn btn-secondary btn-sm" onclick="pjAddRow()">
            <i class="fa fa-plus me-1"></i> Tambah Baris
        </button>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px">#</th>
                        <th>Nama Device</th>
                        <th style="width:9%">Qty</th>
                        <th style="width:12%">Satuan</th>
                        <th style="width:17%">Amount</th>
                        <th style="width:16%">Divisi</th>
                        <th class="text-center" style="width:60px">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pj-ach-body"></tbody>
            </table>
        </div>
        <div class="config-card-empty" id="pj-ach-empty">
            <i class="fa-solid fa-inbox"></i> Belum ada rincian. Total amount harus sama dengan Net Sale Amount.
        </div>
    </div>
</div>

<div class="d-flex justify-content-end mb-4">
    @if($canUpdate ?? false)
    <button type="button" class="btn btn-primary" onclick="pjSave()">
        <i class="fa fa-save me-1"></i> Simpan Rincian
    </button>
    @endif
</div>
@endsection

@section('scripts')
<script>
@include('project.partials._number-format')
const pjProjectId = {{ $project->id }};
const pjNetCents = {{ (int) round((float) $project->net_sale_amount * 100) }};
const pjSaveUrl = '{{ route("project.achievements.update", $project->id) }}';
const pjShowUrl = '{{ route("project.show", $project->id) }}';
const pjDivisions = @json($divisions->map(fn ($d) => ['id' => $d->id, 'name' => $d->division_name])->values());
const pjInitialRows = @json($achievements);
const pjDeviceNames = @json($deviceNames);

function pjEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function pjDeviceOptions(selected) {
    var names = (pjDeviceNames || []).slice();
    var value = selected == null ? '' : String(selected);

    if (value !== '' && names.indexOf(value) === -1) {
        names.push(value);
    }

    var opts = '<option value=""></option>';
    names.forEach(function(name) {
        var isSelected = String(name) === value ? ' selected' : '';
        opts += '<option value="' + pjEsc(name) + '"' + isSelected + '>' + pjEsc(name) + '</option>';
    });

    return opts;
}

function pjInitDevice($el) {
    if ($el.hasClass('select2-hidden-accessible')) return;

    $el.select2({
        theme: 'bootstrap-5',
        width: '100%',
        tags: true,
        allowClear: true,
        placeholder: 'Ketik atau pilih nama device',
        createTag: function(params) {
            var term = $.trim(params.term || '');
            if (term === '') return null;

            return { id: term, text: term, newTag: true };
        }
    });
}

function pjRowHtml(row) {
    row = row || {};
    var opts = '<option value="">— Pilih Divisi —</option>';
    (pjDivisions || []).forEach(function(d) {
        var selected = String(row.division_id || '') === String(d.id) ? ' selected' : '';
        opts += '<option value="' + d.id + '"' + selected + '>' + pjEsc(d.name) + '</option>';
    });

    var html = '<tr>';
    html += '<td class="text-center pj-ach-num"></td>';
    html += '<td><select class="form-select form-select-sm pj-device">' + pjDeviceOptions(row.device_name) + '</select></td>';
    html += '<td><input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm pj-number pj-qty text-end" value="' + pjFormatStored(row.qty) + '"></td>';
    html += '<td><input type="text" class="form-control form-control-sm pj-unit" value="' + pjEsc(row.unit) + '" maxlength="50"></td>';
    html += '<td><input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm pj-number pj-amount text-end" value="' + pjFormatStored(row.amount) + '"></td>';
    html += '<td><select class="form-select form-select-sm pj-division">' + opts + '</select></td>';
    html += '<td class="text-center"><button type="button" class="btn-icon text-danger" title="Hapus" onclick="pjRemoveRow(this)"><i class="fa fa-trash"></i></button></td>';
    html += '</tr>';
    return html;
}

function pjAddRow(row) {
    var $row = $(pjRowHtml(row));
    $('#pj-ach-body').append($row);
    pjInitDevice($row.find('.pj-device'));
    pjRenumber();
    pjRecalc();
}

function pjRemoveRow(btn) {
    $(btn).closest('tr').remove();
    pjRenumber();
    pjRecalc();
}

function pjRenumber() {
    $('#pj-ach-body tr').each(function(i) { $(this).find('.pj-ach-num').text(i + 1); });
    $('#pj-ach-empty').toggle($('#pj-ach-body tr').length === 0);
}

function pjCollectRows() {
    var rows = [];
    $('#pj-ach-body tr').each(function() {
        rows.push({
            device_name: $(this).find('.pj-device').val() || '',
            qty: pjToRaw($(this).find('.pj-qty').val()),
            unit: $(this).find('.pj-unit').val(),
            amount: pjToRaw($(this).find('.pj-amount').val()),
            division_id: $(this).find('.pj-division').val()
        });
    });
    return rows;
}

function pjRecalc() {
    var totalCents = 0;
    $('#pj-ach-body .pj-amount').each(function() { totalCents += pjCents($(this).val()); });

    $('#pj-ach-total').text(pjFormatMoney(totalCents / 100));

    var status = $('#pj-ach-status');
    if (totalCents === pjNetCents) {
        status.text('Cocok dengan Net Sale Amount').css('color', '#166534');
    } else {
        status.text('Selisih ' + pjFormatMoney((totalCents - pjNetCents) / 100)).css('color', '#b91c1c');
    }
}

function pjValidate() {
    var rows = pjCollectRows();
    if (rows.length === 0) { toastr.error('Tambahkan minimal satu rincian device.'); return false; }

    for (var i = 0; i < rows.length; i++) {
        if (!$.trim(rows[i].device_name)) { toastr.error('Nama device baris ' + (i + 1) + ' wajib diisi.'); return false; }
        if (rows[i].amount === '' || rows[i].amount === null) { toastr.error('Amount baris ' + (i + 1) + ' wajib diisi.'); return false; }
        if (!rows[i].division_id) { toastr.error('Divisi baris ' + (i + 1) + ' wajib dipilih.'); return false; }
    }

    var totalCents = 0;
    rows.forEach(function(row) { totalCents += pjCents(row.amount); });
    if (totalCents !== pjNetCents) {
        toastr.error('Total rincian harus sama dengan Net Sale Amount.');
        return false;
    }

    return true;
}

function pjSave() {
    if (!pjValidate()) return;

    $.ajax({
        url: pjSaveUrl,
        method: 'PUT',
        data: {
            _token: '{{ csrf_token() }}',
            achievements: pjCollectRows()
        }
    }).done(function(res) {
        toastr.success(res.message || 'Rincian achievement tersimpan.');
        setTimeout(function() { window.location.href = pjShowUrl; }, 700);
    }).fail(function(xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan rincian.';
        toastr.error(message);
    });
}

$(document).ready(function() {
    (pjInitialRows || []).forEach(function(row) { pjAddRow(row); });
    pjRenumber();
    pjFormatAllStored();
    pjRecalc();

    $(document).on('input', '.pj-amount', pjRecalc);
});
</script>
@endsection

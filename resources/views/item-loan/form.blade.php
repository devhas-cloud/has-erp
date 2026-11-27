@extends('layouts.app')

@section('title', 'New Item Loan')
@section('page-title', 'New Item Loan')

@section('styles')
<style>
    .il-step { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 11.5px; font-weight: 700; margin-right: 8px; flex-shrink: 0; }
    .il-step.is-done { background: #065f46; }
    .il-card-title { display: inline-flex; align-items: center; font-weight: 600; }
    .il-hint { font-size: 12px; color: var(--text-muted); }
    .il-type { display: inline-flex; border: 1px solid var(--card-border); border-radius: var(--radius-sm); overflow: hidden; }
    .il-type label { margin: 0; padding: 6px 14px; font-size: 12.5px; cursor: pointer; transition: background-color .12s ease; }
    .il-type input { display: none; }
    .il-type input:checked + span { color: #fff; }
    .il-type label:has(input:checked) { background: var(--accent); color: #fff; }
    .il-units th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .il-units tbody tr[data-id] { cursor: pointer; }
    .il-units tbody tr[data-id]:hover td { background-color: #d1fae5; }
    .il-units tbody tr.is-picked td { background-color: #ecfdf5; }
    .il-name { font-weight: 600; font-size: 13px; color: var(--text-primary); }
    .il-sub { font-size: 11.5px; color: var(--text-muted); }
    .il-sn { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
    .il-own { font-size: 11px; font-weight: 600; padding: 1px 7px; border-radius: 99px; background: #fef3c7; color: #92400e; }
    .il-empty { text-align: center; padding: 30px 10px !important; color: var(--text-muted); font-size: 13px; }
    .il-actionbar { position: sticky; bottom: 0; z-index: 20; margin-top: 16px; background: var(--card, #fff); border: 1px solid var(--card-border); border-radius: 10px; box-shadow: 0 -6px 18px rgba(15, 23, 42, .08); padding: 10px 14px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .il-actionbar .sum { flex: 1 1 260px; font-size: 12.5px; }
    .il-actionbar .sum strong { font-size: 15px; }
    .is-invalid + .select2-container .select2-selection { border-color: var(--danger, #dc3545) !important; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">New Item Loan</h1>
        <p class="page-header-sub">Record who borrows which serial-numbered units and when they must be returned. Saving puts the units on loan immediately: they stay in stock but cannot be used by other documents until returned.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('item-loan.index') }}" class="btn btn-secondary btn-sm">Back</a>
    </div>
</div>

<form id="il-form" autocomplete="off" novalidate>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span class="il-card-title"><span class="il-step" id="il-step-1">1</span>Borrower</span></div>
            <div class="card-body-custom">
                <div class="il-type mb-3" role="radiogroup" aria-label="Borrower type">
                    <label><input type="radio" name="borrower_type" value="internal" checked><span>Internal staff</span></label>
                    <label><input type="radio" name="borrower_type" value="external"><span>External party</span></label>
                </div>
                <div class="il-internal">
                    <label class="form-label" for="il-user">Staff <span class="text-danger">*</span></label>
                    <select id="il-user" class="form-select" style="width:100%">
                        <option value="">— Select staff —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" data-phone="{{ $u->phone_number }}">{{ $u->display_name }}</option>
                        @endforeach
                    </select>
                    <div class="il-hint mt-1" id="il-user-phone">WhatsApp reminders use the staff's phone number from their profile.</div>
                </div>
                <div class="il-external row g-3" style="display:none">
                    <div class="col-md-6">
                        <label class="form-label" for="il-ext-name">Name <span class="text-danger">*</span></label>
                        <input type="text" id="il-ext-name" class="form-control" maxlength="150" placeholder="Borrower name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="il-ext-company">Company / Institution</label>
                        <input type="text" id="il-ext-company" class="form-control" maxlength="150" placeholder="Optional">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="il-ext-phone">Phone (WhatsApp)</label>
                        <input type="text" id="il-ext-phone" class="form-control" maxlength="30" placeholder="e.g. 0812…">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span class="il-card-title"><span class="il-step" id="il-step-2">2</span>Loan Details</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="il-loan-date">Loan Date <span class="text-danger">*</span></label>
                        <input type="date" id="il-loan-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="il-due-date">Return By <span class="text-danger">*</span></label>
                        <input type="date" id="il-due-date" class="form-control" value="{{ now()->addDays(7)->toDateString() }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="il-purpose">Purpose <span class="text-danger">*</span></label>
                        <input type="text" id="il-purpose" class="form-control" maxlength="255" placeholder="e.g. Demo at customer site, field calibration">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="il-project">Project <small class="text-muted">(optional)</small></label>
                        <select id="il-project" class="form-select" style="width:100%">
                            <option value="">— None —</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->project_number }} — {{ $p->project_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="il-note">Note</label>
                        <input type="text" id="il-note" class="form-control" maxlength="2000" placeholder="Optional">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mt-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="il-card-title"><span class="il-step" id="il-step-3">3</span>Units to Lend</span>
        <span class="il-hint">Only serial-numbered units that are available (not reserved or already on loan) are listed.</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="row g-2 px-1 pb-2">
            <div class="col-md-4">
                <label class="form-label" for="il-warehouse">Warehouse <span class="text-danger">*</span></label>
                <select id="il-warehouse" class="form-select" style="width:100%">
                    <option value="">— Select warehouse —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}{{ $w->branch ? ' ('.$w->branch->name.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="il-search">Find unit</label>
                <input type="search" id="il-search" class="form-control" placeholder="Product code / name, SN…" disabled>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="il-only-picked" disabled>
                    <label class="form-check-label" for="il-only-picked" style="font-size:12.5px">Show selected only</label>
                </div>
            </div>
        </div>
        <div class="table-responsive" style="max-height:460px;overflow-y:auto">
            <table class="table table-custom mb-0 il-units">
                <thead>
                    <tr>
                        <th style="width:36px"></th>
                        <th>Product</th>
                        <th>Serial Number</th>
                        <th>Owner</th>
                        <th style="width:280px">Condition when lent</th>
                    </tr>
                </thead>
                <tbody id="il-units-body">
                    <tr><td colspan="5" class="il-empty">Select a warehouse to list its available units.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="il-actionbar">
    <div class="sum"><strong id="il-picked-count">0</strong> unit(s) selected <span class="il-sub" id="il-picked-to"></span></div>
    <a href="{{ route('item-loan.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
    <button type="button" class="btn btn-primary btn-sm" id="il-save" title="Ctrl+S" disabled>Lend Units</button>
</div>
</form>
@endsection

@section('scripts')
<script>
const ilOptionsUrl = '{{ route("item-loan.stock-options") }}';
const ilStoreUrl = '{{ route("item-loan.store") }}';
const ilShowUrl = '{{ route("item-loan.show", "__ID__") }}';
let ilUnits = [];
let ilPicked = {};   // stock_id -> condition text
let ilDirty = false;
let ilSaving = false;

function ilEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function ilType() { return $('input[name="borrower_type"]:checked').val(); }

function ilRender() {
    var q = $('#il-search').val().trim().toLowerCase();
    var only = $('#il-only-picked').is(':checked');
    var rows = ilUnits.filter(function(u) {
        if (only && !(u.id in ilPicked)) return false;
        if (!q) return true;
        return [u.product_code, u.product_name, u.serial_number].join(' ').toLowerCase().indexOf(q) !== -1;
    });
    if (!ilUnits.length) {
        $('#il-units-body').html('<tr><td colspan="5" class="il-empty">No available serial-numbered units in this warehouse.</td></tr>');
    } else if (!rows.length) {
        $('#il-units-body').html('<tr><td colspan="5" class="il-empty">No units match the search.</td></tr>');
    } else {
        $('#il-units-body').html(rows.slice(0, 500).map(function(u) {
            var picked = u.id in ilPicked;
            return '<tr data-id="' + u.id + '" class="' + (picked ? 'is-picked' : '') + '">'
                + '<td><input type="checkbox" class="form-check-input il-pick" aria-label="Lend ' + ilEsc(u.product_code + ' ' + u.serial_number) + '"' + (picked ? ' checked' : '') + '></td>'
                + '<td><div class="il-name">' + ilEsc(u.product_name || u.product_code) + '</div><div class="il-sub">' + ilEsc(u.product_code) + '</div></td>'
                + '<td class="il-sn">' + ilEsc(u.serial_number) + '</td>'
                + '<td>' + (u.project ? '<span class="il-own">' + ilEsc(u.project) + '</span>' : '<span class="il-sub">Free stock</span>') + '</td>'
                + '<td>' + (picked ? '<input type="text" class="form-control form-control-sm il-cond" maxlength="255" placeholder="e.g. Good, complete with case" value="' + ilEsc(ilPicked[u.id]) + '" aria-label="Condition">' : '') + '</td>'
                + '</tr>';
        }).join('') + (rows.length > 500 ? '<tr><td colspan="5" class="il-empty">Showing 500 of ' + rows.length + ' — refine the search.</td></tr>' : ''));
    }
    ilSummary();
}

function ilSummary() {
    var n = Object.keys(ilPicked).length;
    $('#il-picked-count').text(n);
    var who = ilType() === 'external' ? $('#il-ext-name').val().trim() : ($('#il-user').val() ? $('#il-user option:selected').text().trim() : '');
    $('#il-picked-to').text(who ? '→ ' + who + ($('#il-due-date').val() ? ', return by ' + $('#il-due-date').val().split('-').reverse().join('/') : '') : '');
    $('#il-step-1').toggleClass('is-done', !!who);
    $('#il-step-2').toggleClass('is-done', !!($('#il-loan-date').val() && $('#il-due-date').val() && $('#il-purpose').val().trim()));
    $('#il-step-3').toggleClass('is-done', n > 0);
    $('#il-save').prop('disabled', ilSaving || n === 0);
}

function ilLoadUnits() {
    var wh = $('#il-warehouse').val();
    ilUnits = []; ilPicked = {};
    $('#il-search, #il-only-picked').prop('disabled', !wh);
    if (!wh) { $('#il-units-body').html('<tr><td colspan="5" class="il-empty">Select a warehouse to list its available units.</td></tr>'); ilSummary(); return; }
    $('#il-units-body').html('<tr><td colspan="5" class="il-empty"><span class="spinner-border spinner-border-sm me-1"></span>Loading units…</td></tr>');
    $.get(ilOptionsUrl, { warehouse_id: wh }).done(function(res) {
        ilUnits = res.data || [];
        ilRender();
    }).fail(function(xhr) {
        $('#il-units-body').html('<tr><td colspan="5" class="il-empty">' + ilEsc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to load units.') + '</td></tr>');
    });
}

function ilTogglePick(id) {
    if (id in ilPicked) delete ilPicked[id]; else ilPicked[id] = '';
    ilDirty = true;
    ilRender();
    if (id in ilPicked) $('#il-units-body tr[data-id="' + id + '"] .il-cond').trigger('focus');
}

function ilFail(message, $field) {
    toastr.error(message);
    if (!$field || !$field.length) return;
    $field.addClass('is-invalid');
    if ($field.is('select')) $field.select2('open'); else $field.trigger('focus');
}

function ilSave() {
    if (ilSaving) return;
    var external = ilType() === 'external';
    if (!external && !$('#il-user').val()) return ilFail('Select the borrower.', $('#il-user'));
    if (external && !$('#il-ext-name').val().trim()) return ilFail('Enter the borrower name.', $('#il-ext-name'));
    if (!$('#il-loan-date').val()) return ilFail('Enter the loan date.', $('#il-loan-date'));
    if (!$('#il-due-date').val() || $('#il-due-date').val() < $('#il-loan-date').val()) return ilFail('Return date cannot be before the loan date.', $('#il-due-date'));
    if (!$('#il-purpose').val().trim()) return ilFail('Enter the purpose of the loan.', $('#il-purpose'));
    var ids = Object.keys(ilPicked);
    if (!ids.length) return ilFail('Select at least one unit.');

    var who = external ? $('#il-ext-name').val().trim() : $('#il-user option:selected').text().trim();
    Swal.fire({
        title: 'Lend ' + ids.length + ' unit(s)?',
        html: 'The units will be on loan to <strong>' + ilEsc(who) + '</strong> until ' + ilEsc($('#il-due-date').val().split('-').reverse().join('/'))
            + '.<br>They stay in stock but cannot be used by other documents until their return is recorded.',
        icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, lend', cancelButtonText: 'Cancel'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        ilSaving = true;
        $('#il-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
        $.ajax({
            url: ilStoreUrl, method: 'POST', contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            data: JSON.stringify({
                borrower_type: ilType(),
                borrower_user_id: external ? null : $('#il-user').val(),
                borrower_name: external ? $('#il-ext-name').val().trim() : null,
                borrower_company: external ? $('#il-ext-company').val().trim() : null,
                borrower_phone: external ? $('#il-ext-phone').val().trim() : null,
                warehouse_id: $('#il-warehouse').val(),
                project_id: $('#il-project').val() || null,
                purpose: $('#il-purpose').val().trim(),
                loan_date: $('#il-loan-date').val(),
                due_date: $('#il-due-date').val(),
                note: $('#il-note').val().trim() || null,
                items: ids.map(function(id) { return { stock_id: Number(id), condition_out: ilPicked[id] || null }; })
            })
        }).done(function(res) {
            ilDirty = false;
            toastr.success(res.message || 'Saved.');
            setTimeout(function() { window.location.href = ilShowUrl.replace('__ID__', res.id); }, 600);
        }).fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to save.';
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            ilSaving = false;
            $('#il-save').text('Lend Units');
            ilSummary();
        });
    });
}

$(document).ready(function() {
    $('#il-user').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Select staff —', allowClear: true })
        .on('change', function() {
            $(this).removeClass('is-invalid');
            var phone = $(this).find('option:selected').data('phone');
            $('#il-user-phone').text($(this).val() ? (phone ? 'WhatsApp: ' + phone : 'This staff has no phone number — no personal reminder will be sent.') : 'WhatsApp reminders use the staff\'s phone number from their profile.');
            ilDirty = true; ilSummary();
        });
    $('#il-project').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— None —', allowClear: true });
    $('#il-warehouse').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Select warehouse —' })
        .on('change', function() {
            if (Object.keys(ilPicked).length && !confirm('Changing the warehouse clears the selected units. Continue?')) return;
            ilLoadUnits();
        });
    $('input[name="borrower_type"]').on('change', function() {
        var ext = ilType() === 'external';
        $('.il-internal').toggle(!ext); $('.il-external').toggle(ext);
        ilSummary();
    });
    $(document).on('input change', '#il-form input, #il-form textarea', function() { $(this).removeClass('is-invalid'); ilDirty = true; ilSummary(); });
    $('#il-search, #il-only-picked').on('input change', ilRender);
    $(document).on('click', '#il-units-body tr[data-id]', function(e) {
        if ($(e.target).is('input.il-cond')) return;
        ilTogglePick(Number($(this).data('id')));
    });
    $(document).on('input', '.il-cond', function() { ilPicked[$(this).closest('tr').data('id')] = $(this).val(); });
    $('#il-save').on('click', ilSave);
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') { e.preventDefault(); if (!$('#il-save').prop('disabled')) ilSave(); }
    });
    window.addEventListener('beforeunload', function(e) { if (ilDirty && !ilSaving) { e.preventDefault(); e.returnValue = ''; } });
    ilSummary();
});
</script>
@endsection

@extends('layouts.app')

@php $isEdit = (bool) $receipt; @endphp

@section('title', $isEdit ? 'Edit Penerimaan Customer' : 'Catat Penerimaan Customer')
@section('page-title', $isEdit ? 'Edit Penerimaan Customer' : 'Catat Penerimaan Customer')

@section('styles')
<style>
    .cr-hint { font-size: 12px; color: var(--text-muted); }
    .cr-invoice { background: var(--accent-subtle); border: 1px dashed var(--card-border); border-radius: var(--radius-sm); padding: 12px 14px; font-size: 13px; }
    .cr-invoice dl { display: grid; grid-template-columns: 110px 1fr; gap: 6px 10px; margin: 0; }
    .cr-invoice dt { color: var(--text-muted); font-weight: 500; font-size: 12px; }
    .cr-invoice dd { margin: 0; word-break: break-word; }
    .cr-total { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; padding: 12px 14px; background: var(--accent-soft); border-radius: var(--radius-sm); font-weight: 700; }
    .cr-total .val { color: var(--accent); font-size: 18px; font-variant-numeric: tabular-nums; }
    .cr-summary-sticky { position: sticky; top: 84px; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit Penerimaan '.$receipt->receipt_number : 'Catat Penerimaan Customer' }}</h1>
        <p class="page-header-sub">Pilih invoice yang dibayar customer. Nominal otomatis sama dengan total invoice.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('customer-receipt.show', $receipt->id) : route('customer-receipt.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--accent)"></i>Invoice yang Dibayar</span></div>
            <div class="card-body-custom">
                <label class="form-label" for="cr-invoice">Invoice <span class="text-danger">*</span></label>
                <select id="cr-invoice" class="form-select" style="width:100%" @disabled($isEdit)></select>
                <small class="cr-hint d-block mb-3">Hanya invoice Approved yang belum dibayar dan belum dicatat di penerimaan lain. Cari no. invoice, customer, atau project.</small>
                <div class="cr-invoice" id="cr-invoice-info">Pilih invoice untuk melihat rinciannya.</div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-building-columns me-2" style="color:var(--accent)"></i>Informasi Penerimaan</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label" for="cr-date">Tanggal Terima <span class="text-danger">*</span></label>
                        <input type="date" id="cr-date" class="form-control" max="{{ now()->toDateString() }}" value="{{ $receipt?->receipt_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label" for="cr-method">Metode <span class="text-danger">*</span></label>
                        <select id="cr-method" class="form-select">
                            @foreach(\App\Models\CustomerReceipt::METHOD_LABELS as $key => $label)
                                <option value="{{ $key }}" @selected(($receipt->method ?? 'transfer') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cr-reference">No. Referensi <span class="text-danger" id="cr-ref-required">*</span></label>
                        <input type="text" id="cr-reference" class="form-control" maxlength="100" value="{{ $receipt->reference_number ?? '' }}" placeholder="No. transfer / giro dari mutasi bank">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cr-account">Rekening Penerimaan</label>
                        <input type="text" id="cr-account" class="form-control" maxlength="150" list="cr-account-list" value="{{ $receipt->deposit_account ?? '' }}" placeholder="Contoh: BCA Operasional">
                        <datalist id="cr-account-list">
                            @foreach($depositAccounts as $account)
                                <option value="{{ $account }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cr-notes">Catatan</label>
                        <input type="text" id="cr-notes" class="form-control" maxlength="2000" value="{{ $receipt->notes ?? '' }}" placeholder="Opsional">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card-custom fade-in cr-summary-sticky">
            <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <div class="cr-total"><span>Nominal Diterima</span><span class="val" id="cr-total">Rp 0</span></div>
                <p class="cr-hint mt-2 mb-3">Customer membayar pas sesuai invoice. Bila uang yang masuk berbeda, jangan dicatat dulu — konfirmasi ke customer.</p>
                <button type="button" class="btn btn-primary w-100" id="cr-save" onclick="crSave()"><i class="fa fa-save me-1"></i> Simpan Draft</button>
                <p class="cr-hint mt-2 mb-0">Setelah disimpan, lampirkan bukti transfer, lalu approver Finance memposting penerimaan ini.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const crIsEdit = {{ $isEdit ? 'true' : 'false' }};
const crSaveUrl = '{{ $isEdit ? route("customer-receipt.update", $receipt->id) : route("customer-receipt.store") }}';
const crSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const crShowUrl = '{{ route("customer-receipt.show", "__ID__") }}';
let crPreselect = @json($preselectInvoice);

function crEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function crFail(message, $field) {
    toastr.error(message);
    if ($field) $field.addClass('is-invalid').trigger('focus');
}

function crRenderInvoice(inv) {
    if (!inv) {
        $('#cr-invoice-info').text('Pilih invoice untuk melihat rinciannya.');
        $('#cr-total').text('Rp 0');
        return;
    }
    var html = '<dl>';
    html += '<dt>Customer</dt><dd class="fw-semibold">' + crEsc(inv.company_name || '—') + '</dd>';
    html += '<dt>Project</dt><dd>' + crEsc(inv.project_label || '—') + '</dd>';
    html += '<dt>Termin</dt><dd>' + crEsc(inv.term_label) + (inv.outside_timeline ? ' <span class="status-badge ms-1" style="background:#fef3c7;color:#92400e;">Di luar timeline</span>' : '') + '</dd>';
    html += '<dt>Tanggal Invoice</dt><dd>' + crEsc(inv.invoice_date) + '</dd>';
    html += '<dt>Jatuh Tempo</dt><dd>' + crEsc(inv.due_date || '—') + (inv.is_overdue ? ' <span class="text-danger fw-semibold">· lewat jatuh tempo</span>' : '') + '</dd>';
    html += '<dt>Total Invoice</dt><dd class="fw-semibold">' + crEsc(inv.total_label) + '</dd>';
    html += '</dl>';
    $('#cr-invoice-info').html(html);
    $('#cr-total').text(inv.total_label);
}

function crToggleReference() {
    $('#cr-ref-required').toggle($('#cr-method').val() !== 'cash');
}

function crSave() {
    $('.is-invalid').removeClass('is-invalid');
    if (!$('#cr-invoice').val()) { crFail('Pilih invoice yang dibayar.'); return; }
    if (!$('#cr-date').val()) { crFail('Tanggal terima wajib diisi.', $('#cr-date')); return; }
    if ($('#cr-method').val() !== 'cash' && !$('#cr-reference').val().trim()) { crFail('No. referensi wajib diisi untuk transfer / giro.', $('#cr-reference')); return; }

    $('#cr-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
    $.ajax({
        url: crSaveUrl,
        method: crSaveMethod,
        contentType: 'application/json',
        data: JSON.stringify({
            invoice_id: $('#cr-invoice').val(),
            receipt_date: $('#cr-date').val(),
            method: $('#cr-method').val(),
            reference_number: $('#cr-reference').val().trim(),
            deposit_account: $('#cr-account').val().trim(),
            notes: $('#cr-notes').val()
        }),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function(res) {
        toastr.success(res.message);
        setTimeout(function() { location.href = crShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var res = xhr.responseJSON || {};
        var first = res.errors ? Object.values(res.errors)[0][0] : null;
        toastr.error(first || res.message || 'Gagal menyimpan penerimaan.');
        $('#cr-save').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan Draft');
    });
}

$(document).ready(function() {
    var $invoice = $('#cr-invoice');
    $invoice.select2({
        theme: 'bootstrap-5',
        placeholder: '— Pilih Invoice —',
        allowClear: !crIsEdit,
        ajax: {
            url: '{{ route("customer-receipt.open-invoices") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term || '' }; },
            processResults: function(data) { return data; }
        }
    });

    if (crPreselect) {
        $invoice.append(new Option(crPreselect.text, crPreselect.id, true, true)).trigger('change');
        crRenderInvoice(crPreselect);
    }

    $invoice.on('select2:select', function(e) { crRenderInvoice(e.params.data); });
    $invoice.on('select2:clear', function() { crRenderInvoice(null); });
    $('#cr-method').on('change', crToggleReference);
    $(document).on('input change', '.is-invalid', function() { $(this).removeClass('is-invalid'); });
    crToggleReference();
});
</script>
@endsection

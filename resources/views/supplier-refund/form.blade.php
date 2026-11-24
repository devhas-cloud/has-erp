@extends('layouts.app')

@php
    $isEdit = isset($refund);
    $selectedPo = $isEdit ? $refund->purchaseOrder : null;
@endphp

@section('title', $isEdit ? 'Edit Refund Supplier' : 'Catat Refund Supplier')
@section('page-title', $isEdit ? 'Edit Refund Supplier' : 'Catat Refund Supplier')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit Refund Supplier' : 'Catat Refund Supplier' }}</h1>
        <p class="page-header-sub">Catat uang muka yang dikembalikan supplier. Saldo uang muka PO baru berkurang setelah refund diposting approver Finance.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('supplier-refund.show', $refund->id) : route('supplier-refund.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-rotate-left me-2" style="color:var(--accent)"></i>Informasi Refund</span></div>
            <div class="card-body-custom">
                @if(! $isEdit && $pending->isEmpty())
                    <div class="text-center py-3" style="color:var(--text-muted)">Tidak ada PO yang menunggu refund.</div>
                @else
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="sr-po">PO Menunggu Refund <span class="text-danger">*</span></label>
                        @if($isEdit)
                            <input type="text" id="sr-po-display" class="form-control" disabled
                                   value="{{ $selectedPo?->po_number }} — {{ $selectedPo?->supplier?->name ?? $selectedPo?->supplier_name }} · maks. refund {{ $selectedPo?->currencyCode() }} {{ \App\Models\SupplierBill::formatMoney((float) $maxRefund) }}">
                        @else
                            <select id="sr-po" class="form-select">
                                @foreach($pending as $po)
                                    <option value="{{ $po->id }}" data-due="{{ $po->refundDue() }}" data-currency="{{ $po->currencyCode() }}" @selected($preselectPoId === $po->id)>{{ $po->po_number }} — {{ $po->supplier?->name ?? $po->supplier_name }} · harus kembali {{ $po->currencyCode() }} {{ \App\Models\SupplierBill::formatMoney($po->refundDue()) }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sr-date">Tanggal Diterima <span class="text-danger">*</span></label>
                        <input type="date" id="sr-date" class="form-control" max="{{ now()->toDateString() }}" value="{{ $isEdit ? $refund->refund_date?->format('Y-m-d') : now()->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sr-method">Metode <span class="text-danger">*</span></label>
                        <select id="sr-method" class="form-select">
                            @foreach(\App\Models\SupplierPayment::METHOD_LABELS as $key => $label)
                                <option value="{{ $key }}" @selected($isEdit && $refund->method === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sr-reference">No. Referensi <span class="text-danger" id="sr-ref-req">*</span></label>
                        <input type="text" id="sr-reference" class="form-control" maxlength="100" placeholder="No. transfer masuk" value="{{ $isEdit ? $refund->reference_number : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sr-amount">Nominal Refund <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text" id="sr-cur">—</span><input type="text" inputmode="decimal" id="sr-amount" class="form-control text-end" value="{{ $isEdit ? \App\Support\Number::format((float) $refund->amount, 2) : '' }}"></div>
                        <small style="color:var(--text-muted)" id="sr-due-hint"></small>
                    </div>
                    <div class="col-md-6" id="sr-rate-wrap" style="{{ $isEdit && $refund->currency !== $baseCurrency ? '' : 'display:none' }}">
                        <label class="form-label" for="sr-rate">Kurs ke {{ $baseCurrency }} <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" id="sr-rate" class="form-control text-end" value="{{ $isEdit ? \App\Support\Number::format((float) $refund->exchange_rate, 6) : '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sr-account">Rekening Penerima</label>
                        <input type="text" id="sr-account" class="form-control" maxlength="150" placeholder="Contoh: BCA Operasional" value="{{ $isEdit ? $refund->destination_account : '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="sr-notes">Catatan</label>
                        <input type="text" id="sr-notes" class="form-control" maxlength="2000" placeholder="Opsional" value="{{ $isEdit ? $refund->notes : '' }}">
                    </div>
                </div>
                <button type="button" class="btn btn-primary mt-3" id="sr-save" onclick="srSave()"><i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}</button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const srBase = @json($baseCurrency);
const srRates = @json($rates);
const srIsEdit = @json($isEdit);
const srSaveUrl = @json($isEdit ? route('supplier-refund.update', $refund->id) : route('supplier-refund.store'));
const srMaxRefund = @json($isEdit ? (float) $maxRefund : null);
const srEditCurrency = @json($isEdit ? $refund->currency : null);
// Format angka satu standar (gaya US) — lihat partials/number-input.
function srFmt(n) { return Num.format(n, 2); }
function srParse(v) { return Num.parse(v); }

function srSync() {
    if (srIsEdit) {
        $('#sr-cur').text(srEditCurrency);
        $('#sr-due-hint').text('Maks. refund: ' + srEditCurrency + ' ' + srFmt(srMaxRefund));
        $('#sr-rate-wrap').toggle(srEditCurrency !== srBase);
        return;
    }

    var $o = $('#sr-po option:selected');
    var cur = $o.data('currency') || srBase;
    $('#sr-cur').text(cur);
    $('#sr-amount').val(srFmt($o.data('due')));
    $('#sr-due-hint').text('Harus dikembalikan: ' + cur + ' ' + srFmt($o.data('due')));
    $('#sr-rate-wrap').toggle(cur !== srBase);
    if (cur !== srBase && !$('#sr-rate').val() && srRates[cur]) $('#sr-rate').val(srFmt(srRates[cur]));
}

$(document).on('change', '#sr-po', srSync);
$(document).on('change', '#sr-method', function() { $('#sr-ref-req').toggle($(this).val() !== 'cash'); });
$(document).on('input', '#sr-amount, #sr-rate', function() { this.value = this.value.replace(/[^\d.,]/g, ''); });
$(document).on('blur', '#sr-amount, #sr-rate', function() { if ($(this).val() !== '') $(this).val(srFmt(srParse($(this).val()))); });

function srSave() {
    var max = srIsEdit ? srMaxRefund : parseFloat($('#sr-po option:selected').data('due'));
    var amount = srParse($('#sr-amount').val());
    if (amount <= 0) { toastr.error('Nominal refund wajib diisi.'); return; }
    if (amount > max + 0.005) { toastr.error('Nominal melebihi yang harus dikembalikan.'); return; }
    if ($('#sr-method').val() !== 'cash' && !$('#sr-reference').val().trim()) { toastr.error('No. referensi wajib diisi.'); return; }

    var payload = {
        refund_date: $('#sr-date').val(), method: $('#sr-method').val(),
        reference_number: $('#sr-reference').val().trim(), destination_account: $('#sr-account').val().trim(),
        amount: amount, exchange_rate: $('#sr-rate-wrap').is(':visible') ? srParse($('#sr-rate').val()) : null, notes: $('#sr-notes').val()
    };
    if (!srIsEdit) payload.purchase_order_id = $('#sr-po').val();

    $('#sr-save').prop('disabled', true);
    $.ajax({
        url: srSaveUrl, method: srIsEdit ? 'PUT' : 'POST', contentType: 'application/json',
        data: JSON.stringify(payload),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function(res) {
        toastr.success(res.message);
        setTimeout(function() { location.href = '{{ route("supplier-refund.show", "__ID__") }}'.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#sr-save').prop('disabled', false);
    });
}

$(document).ready(function() {
    if ($('#sr-po').length || srIsEdit) srSync();
    $('#sr-ref-req').toggle($('#sr-method').val() !== 'cash');
});
</script>
@endsection

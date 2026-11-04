@extends('layouts.app')

@section('title', 'Catat Refund Supplier')
@section('page-title', 'Catat Refund Supplier')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Catat Refund Supplier</h1>
        <p class="page-header-sub">Catat uang muka yang dikembalikan supplier. Saldo uang muka PO baru berkurang setelah refund diposting approver Finance.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('supplier-refund.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-rotate-left me-2" style="color:var(--accent)"></i>Informasi Refund</span></div>
            <div class="card-body-custom">
                @if($pending->isEmpty())
                    <div class="text-center py-3" style="color:var(--text-muted)">Tidak ada PO yang menunggu refund.</div>
                @else
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="sr-po">PO Menunggu Refund <span class="text-danger">*</span></label>
                        <select id="sr-po" class="form-select">
                            @foreach($pending as $po)
                                <option value="{{ $po->id }}" data-due="{{ $po->refundDue() }}" data-currency="{{ $po->currencyCode() }}" @selected($preselectPoId === $po->id)>{{ $po->po_number }} — {{ $po->supplier?->name ?? $po->supplier_name }} · harus kembali {{ $po->currencyCode() }} {{ \App\Models\SupplierBill::formatMoney($po->refundDue()) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sr-date">Tanggal Diterima <span class="text-danger">*</span></label>
                        <input type="date" id="sr-date" class="form-control" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sr-method">Metode <span class="text-danger">*</span></label>
                        <select id="sr-method" class="form-select">
                            @foreach(\App\Models\SupplierPayment::METHOD_LABELS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="sr-reference">No. Referensi <span class="text-danger" id="sr-ref-req">*</span></label>
                        <input type="text" id="sr-reference" class="form-control" maxlength="100" placeholder="No. transfer masuk">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sr-amount">Nominal Refund <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text" id="sr-cur">—</span><input type="text" inputmode="decimal" id="sr-amount" class="form-control text-end"></div>
                        <small style="color:var(--text-muted)" id="sr-due-hint"></small>
                    </div>
                    <div class="col-md-6" id="sr-rate-wrap" style="display:none">
                        <label class="form-label" for="sr-rate">Kurs ke {{ $baseCurrency }} <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" id="sr-rate" class="form-control text-end">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sr-account">Rekening Penerima</label>
                        <input type="text" id="sr-account" class="form-control" maxlength="150" placeholder="Contoh: BCA Operasional">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="sr-notes">Catatan</label>
                        <input type="text" id="sr-notes" class="form-control" maxlength="2000" placeholder="Opsional">
                    </div>
                </div>
                <button type="button" class="btn btn-primary mt-3" id="sr-save" onclick="srSave()"><i class="fa fa-save me-1"></i> Simpan Draft</button>
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
function srFmt(n) { return (Number(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }); }
function srParse(v) { v = String(v || '').trim(); if (!v) return 0; var n = parseFloat(v.replace(/\./g, '').replace(',', '.')); return isNaN(n) ? 0 : n; }

function srSync() {
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
    var $o = $('#sr-po option:selected');
    var amount = srParse($('#sr-amount').val());
    if (amount <= 0) { toastr.error('Nominal refund wajib diisi.'); return; }
    if (amount > parseFloat($o.data('due')) + 0.005) { toastr.error('Nominal melebihi yang harus dikembalikan.'); return; }
    if ($('#sr-method').val() !== 'cash' && !$('#sr-reference').val().trim()) { toastr.error('No. referensi wajib diisi.'); return; }

    $('#sr-save').prop('disabled', true);
    $.ajax({
        url: '{{ route("supplier-refund.store") }}', method: 'POST', contentType: 'application/json',
        data: JSON.stringify({
            purchase_order_id: $('#sr-po').val(), refund_date: $('#sr-date').val(), method: $('#sr-method').val(),
            reference_number: $('#sr-reference').val().trim(), destination_account: $('#sr-account').val().trim(),
            amount: amount, exchange_rate: $('#sr-rate-wrap').is(':visible') ? srParse($('#sr-rate').val()) : null, notes: $('#sr-notes').val()
        }),
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

$(document).ready(function() { if ($('#sr-po').length) srSync(); });
</script>
@endsection

@extends('layouts.app')

@php $isEdit = (bool) $transfer; @endphp

@section('title', $isEdit ? 'Edit Branch Transfer' : 'Buat Branch Transfer')
@section('page-title', $isEdit ? 'Edit Branch Transfer' : 'Buat Branch Transfer')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $isEdit ? 'Edit '.$transfer->transfer_number : 'Buat Pengiriman Antar Branch' }}</h1>
        <p class="page-header-sub">Pilih gudang asal, branch tujuan, dan barang yang dikirim. Disimpan sebagai draft; stok baru keluar saat dokumen dikirim.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $isEdit ? route('branch-transfer.show', $transfer->id) : route('branch-transfer.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-route me-2" style="color:var(--accent)"></i>Asal &amp; Tujuan</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="bt-from-warehouse">Gudang Asal <span class="text-danger">*</span></label>
                        <select id="bt-from-warehouse" class="form-select">
                            <option value="">— Pilih Gudang —</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" data-branch="{{ $w->branch_id }}" @selected(($transfer->from_warehouse_id ?? null) === $w->id)>{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Hanya gudang yang sudah terhubung ke branch{{ auth()->user()->branch_id ? ' Anda' : '' }}.</small>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="bt-to-branch">Branch Tujuan <span class="text-danger">*</span></label>
                        <select id="bt-to-branch" class="form-select">
                            <option value="">— Pilih Branch —</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" @selected(($transfer->to_branch_id ?? null) === $b->id)>{{ $b->code }} — {{ $b->name }}</option>
                            @endforeach
                        </select>
                        <small style="color:var(--text-muted)">Gudang tujuan dipilih oleh penerima saat barang tiba.</small>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="bt-date">Tanggal Kirim <span class="text-danger">*</span></label>
                        <input type="date" id="bt-date" class="form-control" value="{{ $transfer?->transfer_date?->toDateString() ?? now()->toDateString() }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="bt-courier">Ekspedisi</label>
                        <input type="text" id="bt-courier" class="form-control" maxlength="150" value="{{ $transfer->courier ?? '' }}" placeholder="Opsional">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="bt-vehicle">No. Kendaraan</label>
                        <input type="text" id="bt-vehicle" class="form-control" maxlength="50" value="{{ $transfer->vehicle_no ?? '' }}" placeholder="Contoh: B 1234 XYZ">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="bt-driver">Pengemudi / Pembawa</label>
                        <input type="text" id="bt-driver" class="form-control" maxlength="150" value="{{ $transfer->driver_name ?? '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="bt-note">Catatan</label>
                        <input type="text" id="bt-note" class="form-control" maxlength="2000" value="{{ $transfer->note ?? '' }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>Barang Dikirim</span>
                <input type="search" id="bt-q" class="form-control form-control-sm" style="max-width:260px" placeholder="Cari kode, nama, SN, batch">
            </div>
            <div class="card-body-custom p-2">
                <div id="bt-picker"><div class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih gudang asal terlebih dahulu.</div></div>
                <div class="px-2 pt-2" style="font-size:12px;color:var(--text-muted)"><i class="fa fa-circle-info me-1"></i>Material boleh dikirim sebagian. Barang ber-SN dikirim per unit.</div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Baris stok</td><td class="text-end fw-semibold" id="bt-sum-lines">0</td></tr>
                    <tr><td>Total qty</td><td class="text-end fw-semibold" id="bt-sum-qty">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="bt-save" onclick="btSave()">
                    <i class="fa fa-save me-1"></i> {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
                </button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Setelah disimpan, klik <strong>Kirim</strong> di halaman detail saat barang berangkat, lalu cetak surat jalan.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@include('partials.stock-pick')
<script>
const btOptionsUrl = '{{ route("branch-transfer.stock-options") }}';
const btSaveUrl = '{{ $isEdit ? route("branch-transfer.update", $transfer->id) : route("branch-transfer.store") }}';
const btSaveMethod = '{{ $isEdit ? "PUT" : "POST" }}';
const btShowUrl = '{{ route("branch-transfer.show", "__ID__") }}';
const btSaveLabel = $('#bt-save').html();
let btSelected = @json((object) $selected->all());

function btRemember() {
    $('#bt-picker tr[data-stock-id]').each(function() { delete btSelected[$(this).data('stock-id')]; });
    StockPick.picks($('#bt-picker')).forEach(function(p) { btSelected[p.stock_id] = p.qty; });
}

// Pilihan yang tampil + pilihan sebelumnya yang tersembunyi oleh pencarian.
function btCollect() {
    var picks = StockPick.picks($('#bt-picker'));
    var visible = {};
    $('#bt-picker tr[data-stock-id]').each(function() { visible[$(this).data('stock-id')] = true; });
    Object.keys(btSelected).forEach(function(id) { if (!visible[id]) picks.push({ stock_id: parseInt(id, 10), qty: btSelected[id] }); });
    return picks;
}

function btSummary() {
    var picks = btCollect();
    $('#bt-sum-lines').text(picks.length);
    $('#bt-sum-qty').text(Num.format(picks.reduce(function(s, p) { return s + p.qty; }, 0), 4));
}

function btLoad() {
    var wh = $('#bt-from-warehouse').val();
    if (!wh) {
        $('#bt-picker').html('<div class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Pilih gudang asal terlebih dahulu.</div>');
        btSummary();
        return;
    }
    StockPick.load($('#bt-picker'), btOptionsUrl, { warehouse_id: wh, q: $('#bt-q').val() }, { requireSerial: false, selected: btSelected, onChange: btSummary });
}

// Branch tujuan tidak boleh sama dengan branch gudang asal.
function btSyncBranches() {
    var branch = String($('#bt-from-warehouse option:selected').data('branch') || '');
    $('#bt-to-branch option[value!=""]').each(function() { $(this).prop('disabled', !!branch && this.value === branch); });
    if ($('#bt-to-branch option:selected').prop('disabled')) $('#bt-to-branch').val('');
}

$(document).on('change', '#bt-from-warehouse', function() { btSelected = {}; btSyncBranches(); btLoad(); });
let btTimer = null;
$(document).on('input', '#bt-q', function() { btRemember(); clearTimeout(btTimer); btTimer = setTimeout(btLoad, 350); });

function btSave() {
    if (!$('#bt-from-warehouse').val()) { toastr.error('Pilih gudang asal.'); return; }
    if (!$('#bt-to-branch').val()) { toastr.error('Pilih branch tujuan.'); return; }
    var items = btCollect();
    if (!items.length) { toastr.error('Pilih minimal satu barang yang dikirim.'); return; }

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
        toastr.success(res.message || 'Tersimpan.');
        setTimeout(function() { window.location.href = btShowUrl.replace('__ID__', res.id); }, 600);
    }).fail(function(xhr) {
        var msg = 'Gagal menyimpan.';
        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
        toastr.error(msg);
        $('#bt-save').prop('disabled', false).html(btSaveLabel);
    });
}

$(document).ready(function() {
    btSyncBranches();
    if ($('#bt-from-warehouse').val()) btLoad();
});
</script>
@endsection

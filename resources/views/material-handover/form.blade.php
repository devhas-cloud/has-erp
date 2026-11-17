@extends('layouts.app')

@php $fmtQty = fn ($v) => \App\Support\StockCost::qty($v); @endphp

@section('title', 'Serah Terima Material')
@section('page-title', 'Serah Terima Material')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $mr ? 'Serah Terima '.$mr->request_number : 'Serah Terima Material' }}</h1>
        <p class="page-header-sub">
            @if($mr)
                {{ $mr->project?->project_number }} — {{ $mr->project?->project_name }} &middot; {{ $mr->branch?->name }}
            @else
                Pilih Material Request yang sudah disetujui.
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ $mr ? route('material-handover.create') : route('material-handover.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

@if(! $mr)
<div class="card-custom fade-in">
    <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-list me-2" style="color:var(--accent)"></i>Material Request Menunggu Diserahkan</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. MR</th>
                        <th>Project</th>
                        <th>Branch</th>
                        <th class="text-center">Material belum lengkap</th>
                        <th style="width:110px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $row)
                        <tr>
                            <td><strong style="color:var(--text-primary)">{{ $row->request_number }}</strong><div style="font-size:11.5px;color:var(--text-muted)">{{ $row->request_date?->format('d/m/Y') }}</div></td>
                            <td>{{ $row->project?->project_number }} — {{ $row->project?->project_name }}</td>
                            <td>{{ $row->branch?->name }}</td>
                            <td class="text-center">{{ $row->items->filter(fn ($i) => $i->remainingQty() > 0)->count() }} / {{ $row->items->count() }}</td>
                            <td class="text-end"><a href="{{ route('material-handover.create', ['material_request_id' => $row->id]) }}" class="btn btn-sm btn-primary">Pilih <i class="fa fa-arrow-right ms-1"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>Tidak ada Material Request yang menunggu diserahkan.</td></tr>
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
            <div class="card-header-custom"><span><i class="fa-solid fa-file-lines me-2" style="color:var(--accent)"></i>Dokumen</span></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="form-label" for="mh-date">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" id="mh-date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-lg-9">
                        <label class="form-label" for="mh-note">Catatan</label>
                        <input type="text" id="mh-note" class="form-control" maxlength="2000" placeholder="Opsional">
                    </div>
                </div>
            </div>
        </div>

        @if($warehouses->isEmpty())
            <div class="alert alert-warning py-2" style="font-size:13px"><i class="fa fa-triangle-exclamation me-1"></i>Belum ada gudang aktif di branch {{ $mr->branch?->name }}.</div>
        @endif

        @foreach($mr->items as $item)
            @php $remaining = $item->remainingQty(); @endphp
            @continue($remaining <= 0)
            <div class="card-custom fade-in mb-3 mh-item" data-item-id="{{ $item->id }}" data-remaining="{{ $remaining }}" data-code="{{ $item->masterProduct?->code }}">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>
                        <strong>{{ $item->masterProduct?->code }}</strong> <span style="color:var(--text-muted)">{{ $item->masterProduct?->name }}</span>
                        <span class="badge ms-1" style="background:#f1f5f9;color:var(--text-secondary);font-size:10.5px">Sisa {{ $fmtQty($remaining) }} {{ $item->unit }}</span>
                    </span>
                    <select class="form-select form-select-sm mh-warehouse" style="max-width:260px" aria-label="Gudang">
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="card-body-custom p-2"><div class="mh-picker"></div></div>
            </div>
        @endforeach
    </div>

    <div class="col-xl-3">
        <div class="card-custom fade-in" style="position:sticky;top:80px">
            <div class="card-header-custom"><span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Baris stok</td><td class="text-end fw-semibold" id="mh-sum-lines">0</td></tr>
                </table>
                <button type="button" class="btn btn-primary w-100 mt-3" id="mh-save" onclick="mhSave()">
                    <i class="fa fa-people-carry-box me-1"></i> Serahkan
                </button>
                <div style="font-size:11px;color:var(--text-muted);margin-top:8px">Stok langsung keluar dan tidak bisa dibatalkan. Barang ber-SN wajib sudah ber-SN.</div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
@if($mr)
@include('partials.stock-pick')
<script>
const mhOptionsUrl = '{{ route("material-handover.stock-options") }}';
const mhShowUrl = '{{ route("material-handover.show", "__ID__") }}';
const mhSaveLabel = $('#mh-save').html();

function mhSummary() {
    var n = 0;
    $('.mh-item').each(function() { n += StockPick.picks($(this).find('.mh-picker')).length; });
    $('#mh-sum-lines').text(n);
}

function mhLoad($card) {
    var wh = $card.find('.mh-warehouse').val();
    if (!wh) return;
    StockPick.load($card.find('.mh-picker'), mhOptionsUrl, { material_request_item_id: $card.data('item-id'), warehouse_id: wh },
        { requireSerial: true, selected: {}, onChange: mhSummary });
}

$(document).on('change', '.mh-warehouse', function() { mhLoad($(this).closest('.mh-item')); });

function mhSave() {
    var items = [], error = null;
    $('.mh-item').each(function() {
        var $card = $(this), total = 0;
        StockPick.picks($card.find('.mh-picker')).forEach(function(p) {
            total += p.qty;
            items.push({ material_request_item_id: $card.data('item-id'), stock_id: p.stock_id, qty: p.qty });
        });
        if (total - parseFloat($card.data('remaining')) > 0.00005) error = 'Qty ' + $card.data('code') + ' melebihi sisa permintaan.';
    });
    if (error) { toastr.error(error); return; }
    if (!items.length) { toastr.error('Pilih minimal satu baris stok yang diserahkan.'); return; }

    Swal.fire({ title: 'Serahkan ke produksi?', text: items.length + ' baris stok langsung keluar dari gudang dan tidak bisa dibatalkan.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Serahkan', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $('#mh-save').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');
            $.ajax({
                url: '{{ route("material-handover.store") }}', method: 'POST', contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                data: JSON.stringify({ material_request_id: {{ $mr->id }}, handover_date: $('#mh-date').val(), note: $('#mh-note').val(), items: items })
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

$(document).ready(function() { $('.mh-item').each(function() { mhLoad($(this)); }); });
</script>
@endif
@endsection

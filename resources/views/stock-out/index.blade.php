@extends('layouts.app')

@section('title', 'Pemenuhan dari Stok')
@section('page-title', 'Pemenuhan dari Stok')

@php
    $pendingMap = [];
    foreach ($pending as $p) {
        $pendingMap[$p['goods_request_item_id']] = $p;
    }
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Pemenuhan dari Stok</h1>
        <p class="page-header-sub">Penuhi item Permintaan Barang dari stok bebas — pilih baris stok yang dipakai, partial diperbolehkan. Permintaan internal: barang keluar. Permintaan project: stok menjadi milik project (boleh tetap di gudang yang sama).</p>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-right-from-bracket me-2" style="color:var(--accent)"></i>Item Permintaan dengan Sisa Kebutuhan</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Barang</th>
                        <th>Permintaan</th>
                        <th class="text-center" style="width:80px">Qty</th>
                        <th class="text-center" style="width:80px">Sisa</th>
                        <th>Stok Tersedia</th>
                        <th class="text-center" style="width:120px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pending as $i => $p)
                        @php
                            $totalStock = array_sum(array_column($p['stock'], 'balance'));
                            $productLabel = $p['product_name'] ?: $p['description'];
                        @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="font-size:13px">{{ $productLabel ?: '—' }}</div>
                                @if($p['part_number'])<div style="font-size:11px;color:var(--text-muted)">{{ $p['part_number'] }}</div>@endif
                            </td>
                            <td>
                                <div style="font-size:13px">
                                    <span class="badge" style="{{ $p['for_project'] ? 'background:#fef3c7;color:#92400e' : 'background:var(--info-soft);color:#1e40af' }};font-size:10px">{{ $p['for_project'] ? 'Project' : 'Internal' }}</span>
                                    {{ $p['project_name'] ?? '—' }}
                                </div>
                                <div style="font-size:11px;color:var(--text-muted)">Permintaan {{ $p['request_number'] }}</div>
                                @if($p['division_name'])<div style="font-size:11px;color:var(--text-muted)">{{ $p['division_name'] }}</div>@endif
                            </td>
                            <td class="text-center">{{ $p['requested_qty'] }}</td>
                            <td class="text-center"><strong>{{ $p['remaining_qty'] }}</strong></td>
                            <td>
                                @if($totalStock > 0)
                                    @foreach($p['stock'] as $w)
                                        <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:10px;margin:1px 2px 1px 0">
                                            {{ $w['code'] }}: {{ $w['balance_label'] }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-muted" style="font-size:12px">Tidak ada stok bebas</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($totalStock > 0 && $canCreate)
                                    <button type="button" class="btn btn-primary btn-sm" data-item-id="{{ $p['goods_request_item_id'] }}" onclick="openIssueModal(this)">
                                        <i class="fa {{ $p['for_project'] ? 'fa-arrow-right-arrow-left' : 'fa-right-from-bracket' }} me-1"></i> {{ $p['for_project'] ? 'Alokasikan' : 'Keluarkan' }}
                                    </button>
                                @else
                                    <span class="text-muted" style="font-size:12px">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4" style="color:var(--text-muted)">
                                <i class="fa-solid fa-inbox me-1"></i> Tidak ada item Permintaan Barang Approved dengan sisa kebutuhan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="issueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i><span id="issue-title">Penuhi dari Stok</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="issue-product" class="mb-3 fw-semibold" style="font-size:14px"></div>
                <div class="mb-3">
                    <label class="form-label">Gudang Asal (stok bebas) <span class="text-danger">*</span></label>
                    <select id="issue-warehouse" class="form-select"></select>
                </div>
                <div class="mb-3" id="issue-destination-wrap">
                    <label class="form-label">Gudang Tujuan Alokasi</label>
                    <select id="issue-destination" class="form-select">
                        <option value="">Sama dengan gudang asal</option>
                        @foreach($allWarehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }} ({{ $w->branch_label }})</option>
                        @endforeach
                    </select>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Barang menjadi stok milik project di gudang ini sampai dikirim lewat Delivery Order.</div>
                </div>
                <label class="form-label">Pilih Baris Stok <span class="text-danger">*</span></label>
                <div id="issue-picker" style="border:1px solid var(--border-color, #e5e7eb);border-radius:8px;padding:8px">
                    <div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Pilih gudang asal dulu.</div>
                </div>
                <div id="issue-available" style="font-size:12px;color:var(--text-muted);margin-top:4px"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="issue-submit" onclick="submitIssue()">
                    <i class="fa fa-check me-1"></i> Proses
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
@include('partials.stock-pick')
<script>
const pendingMap = @json($pendingMap);
const stockOutStoreUrl = '{{ route("stock-out.store") }}';
const stockOutOptionsUrl = '{{ route("stock-out.stock-options") }}';
let issueModalInstance = null;
let issueItemId = null;

function openIssueModal(btn) {
    issueItemId = $(btn).data('item-id');
    var item = pendingMap[issueItemId];
    if (!item) return;

    $('#issue-product').text((item.product_name || item.description || '') + (item.part_number ? ' (' + item.part_number + ')' : '') + ' — sisa ' + item.remaining_qty + ' ' + (item.unit || ''));

    var opts = '<option value="">— Pilih Gudang —</option>';
    item.stock.forEach(function(w) {
        opts += '<option value="' + w.id + '">' + w.code + ' — ' + w.name + ' (stok ' + w.balance_label + ')</option>';
    });
    $('#issue-warehouse').html(opts);
    $('#issue-picker').html('<div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Pilih gudang asal dulu.</div>');
    $('#issue-available').text('Sisa kebutuhan: ' + item.remaining_qty + ' ' + (item.unit || ''));
    $('#issue-title').text(item.for_project ? 'Alokasikan Stok ke Project' : 'Keluarkan Stok (Internal)');
    $('#issue-destination-wrap').toggle(!!item.for_project);
    $('#issue-destination').val('');

    if (!issueModalInstance) issueModalInstance = new bootstrap.Modal(document.getElementById('issueModal'));
    issueModalInstance.show();
}

$(document).on('change', '#issue-warehouse', function() {
    var item = pendingMap[issueItemId] || {};
    if (!$(this).val()) return;
    // Internal = barang keluar: unit ber-SN wajib sudah ber-SN. Alokasi project boleh tanpa SN.
    StockPick.load($('#issue-picker'), stockOutOptionsUrl, { goods_request_item_id: issueItemId, warehouse_id: $(this).val() }, {
        requireSerial: !item.for_project,
        onChange: function(total) {
            $('#issue-available').html('Dipilih ' + Num.format(total, 4) + ' dari sisa kebutuhan ' + item.remaining_qty + ' ' + (item.unit || '')
                + (total > item.remaining_qty ? ' <span class="text-danger">— melebihi sisa</span>' : ''));
        }
    });
});

function submitIssue() {
    var warehouseId = $('#issue-warehouse').val();
    var picks = StockPick.picks($('#issue-picker'));
    var qty = StockPick.total($('#issue-picker'));

    var item = pendingMap[issueItemId] || {};
    var destinationId = $('#issue-destination').val();
    if (!warehouseId) { toastr.error('Pilih gudang asal.'); return; }
    if (!picks.length) { toastr.error('Pilih minimal satu baris stok.'); return; }
    if (qty > item.remaining_qty) { toastr.error('Qty melebihi sisa kebutuhan (' + item.remaining_qty + ').'); return; }

    $('#issue-submit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Proses...');

    $.ajax({
        url: stockOutStoreUrl,
        method: 'POST',
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify({
            goods_request_item_id: issueItemId,
            warehouse_id: warehouseId,
            destination_warehouse_id: item.for_project && destinationId ? destinationId : null,
            picks: picks
        })
    }).done(function(res) {
        toastr.success(res.message || 'Berhasil.');
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memproses.');
    }).always(function() {
        $('#issue-submit').prop('disabled', false).html('<i class="fa fa-check me-1"></i> Proses');
    });
}
</script>
@endsection

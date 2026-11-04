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
        <p class="page-header-sub">Penuhi item Permintaan Barang dari stok bebas — partial diperbolehkan. Permintaan internal: keluar stok. Permintaan project: dialokasikan ke gudang tujuan (umumnya transit) sebagai stok project.</p>
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
                                            {{ $w['code'] }}: {{ $w['balance'] }}
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
    <div class="modal-dialog">
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
                    <label class="form-label">Gudang Tujuan Alokasi <span class="text-danger">*</span></label>
                    <select id="issue-destination" class="form-select">
                        <option value="">— Pilih Gudang Tujuan —</option>
                        @foreach($allWarehouses as $w)
                            <option value="{{ $w->id }}" data-type="{{ $w->type }}">{{ $w->code }} — {{ $w->name }} ({{ $w->type_label }})</option>
                        @endforeach
                    </select>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Barang menjadi stok milik project di gudang ini sampai dikirim lewat Delivery Order.{{ $transitWarehouses->isEmpty() ? ' Belum ada gudang transit — buat di menu Gudang bila perlu.' : '' }}</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Qty <span class="text-danger">*</span></label>
                    <input type="number" id="issue-qty" class="form-control" min="1">
                    <div id="issue-available" style="font-size:12px;color:var(--text-muted);margin-top:4px"></div>
                </div>
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
<script>
const pendingMap = @json($pendingMap);
const stockOutStoreUrl = '{{ route("stock-out.store") }}';
let issueModalInstance = null;
let issueItemId = null;

function openIssueModal(btn) {
    issueItemId = $(btn).data('item-id');
    var item = pendingMap[issueItemId];
    if (!item) return;

    $('#issue-product').text((item.product_name || item.description || '') + (item.part_number ? ' (' + item.part_number + ')' : '') + ' — sisa ' + item.remaining_qty + ' ' + (item.unit || ''));

    var opts = '<option value="">— Pilih Gudang —</option>';
    item.stock.forEach(function(w) {
        opts += '<option value="' + w.id + '" data-balance="' + w.balance + '">' + w.code + ' — ' + w.name + ' (stok ' + w.balance + ')</option>';
    });
    $('#issue-warehouse').html(opts);
    $('#issue-qty').val(item.remaining_qty);
    $('#issue-available').text('');
    $('#issue-title').text(item.for_project ? 'Alokasikan Stok ke Project' : 'Keluarkan Stok (Internal)');
    $('#issue-destination-wrap').toggle(!!item.for_project);
    var $transit = $('#issue-destination option[data-type="transit"]').first();
    $('#issue-destination').val($transit.length ? $transit.val() : '');

    if (!issueModalInstance) issueModalInstance = new bootstrap.Modal(document.getElementById('issueModal'));
    issueModalInstance.show();
}

$(document).on('change', '#issue-warehouse', function() {
    var bal = $('#issue-warehouse option:selected').data('balance') || 0;
    $('#issue-available').text('Stok tersedia: ' + bal);
    var max = bal;
    $('#issue-qty').attr('max', max);
    if (parseInt($('#issue-qty').val(), 10) > max) $('#issue-qty').val(max);
});

function submitIssue() {
    var warehouseId = $('#issue-warehouse').val();
    var qty = parseInt($('#issue-qty').val(), 10);

    var item = pendingMap[issueItemId] || {};
    var destinationId = $('#issue-destination').val();
    if (!warehouseId) { toastr.error('Pilih gudang asal.'); return; }
    if (item.for_project && !destinationId) { toastr.error('Pilih gudang tujuan alokasi.'); return; }
    if (!qty || qty < 1) { toastr.error('Qty harus lebih dari 0.'); return; }
    if (qty > item.remaining_qty) { toastr.error('Qty melebihi sisa kebutuhan (' + item.remaining_qty + ').'); return; }

    $('#issue-submit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Proses...');

    $.ajax({
        url: stockOutStoreUrl,
        method: 'POST',
        data: {
            goods_request_item_id: issueItemId,
            warehouse_id: warehouseId,
            destination_warehouse_id: item.for_project ? destinationId : '',
            qty: qty,
            _token: '{{ csrf_token() }}'
        }
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

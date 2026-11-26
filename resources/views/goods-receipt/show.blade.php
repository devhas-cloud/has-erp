@extends('layouts.app')

@section('title', 'Goods Receipt Details')
@section('page-title', 'Goods Receipt Details')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $sourceUrl = $receipt->purchase_order_id
        ? route('purchase-order.show', $receipt->purchase_order_id)
        : ($receipt->direct_purchase_id ? route('direct-purchase.show', $receipt->direct_purchase_id) : null);
    $toStock = $receipt->items->where('destination', '!=', 'direct');
    $direct = $receipt->items->where('destination', 'direct');
    $missingTotal = $toStock->sum(fn ($item) => $item->stocks->filter(fn ($st) => ! $st->canLeave())->count());
    $cardUrl = fn ($item) => route('stock-report.card', $item->master_product_id).'?'.http_build_query(['warehouse_id' => $receipt->warehouse_id, 'ownership' => $item->project_id ?: 'free']);
    $stockValue = $inLogs->flatten()->sum(fn ($log) => $log->qty * $log->unit_cost);
    $materialLines = $toStock->filter(fn ($i) => $i->masterProduct?->isMaterial())->count();
    $serialUnits = (int) $toStock->filter(fn ($i) => $i->masterProduct && ! $i->masterProduct->isMaterial())->sum('qty');
@endphp

@section('styles')
@include('partials.image-compress')
<style>
    .gr-muted { color: var(--text-muted); font-size: 12px; }
    .gr-hero { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 14px; }
    .gr-hero-top { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .gr-number { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 4px 0 2px; font-variant-numeric: tabular-nums; }
    .gr-context a { color: inherit; font-weight: 600; text-decoration: none; }
    .gr-context a:hover { color: var(--accent); }
    .gr-total { text-align: right; }
    .gr-total .val { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
    @media (max-width: 575.98px) { .gr-total { text-align: left; } }

    /* Strip fakta: gudang, pencatat, ringkasan isi */
    .gr-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px 18px; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--card-border); }
    .gr-facts .k { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; }
    .gr-facts .v { font-size: 13px; color: var(--text-primary); font-weight: 600; overflow-wrap: anywhere; }
    .gr-facts .v small { font-weight: 400; color: var(--text-muted); font-size: 12px; }
    .gr-note { margin-top: 10px; font-size: 12.5px; color: var(--text-secondary); }

    /* Pemberitahuan langkah berikutnya */
    .gr-next { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border: 1px solid; border-radius: var(--radius); padding: 10px 16px; margin-bottom: 14px; font-size: 13px; }
    .gr-next .txt { flex: 1; min-width: 220px; }
    .gr-next.warn { background: #fffbeb; border-color: #fde68a; color: #78350f; }

    /* Tabel barang */
    .gr-items th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .gr-items td { vertical-align: top; }
    .gr-name { font-weight: 600; color: var(--text-primary); font-size: 13px; }
    .gr-sub { font-size: 11.5px; color: var(--text-muted); }
    .gr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .gr-kind { font-size: 10px; font-weight: 600; padding: 1px 7px; border-radius: 999px; white-space: nowrap; }
    .gr-kind-material { background: #e0f2fe; color: #075985; }
    .gr-kind-serial { background: #fef3c7; color: #92400e; }
    .gr-kind-direct { background: #ede9fe; color: #5b21b6; }
    .gr-own-project { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; display: inline-block; }
    .gr-detail-row td { border-top: 0 !important; padding-top: 0 !important; background: #fafbfc; }
    .gr-detail { display: grid; gap: 8px; font-size: 12px; color: var(--text-secondary); padding: 8px 0 4px; }
    .gr-detail .k { font-size: 11px; color: var(--text-muted); margin-right: 4px; }
    .gr-meta span { margin-right: 14px; }
    .gr-sn { display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; margin: 2px 2px 0 0; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
    .gr-warn { color: #b45309; }

    /* Foto barang ber-SN */
    .gr-photos { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 2px; }
    .gr-photo { position: relative; width: 64px; height: 64px; border-radius: 6px; overflow: hidden; border: 1px solid var(--card-border); background: #f8fafc; }
    .gr-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .gr-photo-del { position: absolute; top: 2px; right: 2px; width: 18px; height: 18px; border: 0; border-radius: 50%; background: rgba(15, 23, 42, .65); color: #fff; font-size: 10px; line-height: 1; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .gr-photo-del:hover { background: #b42318; }
    .gr-photo-add { height: 64px; font-size: 12px; }
    .gr-photo-empty { font-size: 12px; color: var(--text-muted); }
    .gr-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12px; color: var(--text-muted); padding: 10px 6px 2px; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Goods Receipt Details</h1>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('goods-receipt.index') }}" class="btn btn-secondary btn-sm">Back</a>
    </div>
</div>

{{-- Ringkas: nomor, sumber, nilai, fakta utama --}}
<div class="gr-hero fade-in">
    <div class="gr-hero-top">
        <div>
            {!! $receipt->statusBadgeHtml() !!}
            <div class="gr-number">{{ $receipt->receipt_number ?: 'Receipt #'.$receipt->id }}</div>
            <div class="gr-muted gr-context">
                @if($sourceUrl)<a href="{{ $sourceUrl }}">{{ $receipt->sourceNumber() }}</a>@else{{ $receipt->sourceNumber() }}@endif
                &middot; {{ $receipt->supplierName() ?? '—' }}
                &middot; Received {{ $receipt->received_date?->format('d/m/Y') ?? '—' }}
            </div>
        </div>
        @if($stockValue > 0)
        <div class="gr-total">
            <div class="gr-muted">Value into stock</div>
            <div class="val">{{ \App\Models\Currency::baseName() }} {{ \App\Support\StockCost::format($stockValue) }}</div>
        </div>
        @endif
    </div>
    <div class="gr-facts">
        <div>
            <div class="k">Warehouse</div>
            <div class="v">
                @if($receipt->warehouse)
                    {{ $receipt->warehouse->code }} — {{ $receipt->warehouse->name }} <small>· {{ $receipt->warehouse->branch_label }}</small>
                @else
                    <small>All for direct use</small>
                @endif
            </div>
        </div>
        <div>
            <div class="k">To Warehouse</div>
            <div class="v">{{ $toStock->count() }} {{ \Illuminate\Support\Str::plural('line', $toStock->count()) }} <small>· {{ $materialLines }} material · {{ $serialUnits }} SN units</small></div>
        </div>
        <div>
            <div class="k">Direct Use</div>
            <div class="v">{{ $direct->count() }} {{ \Illuminate\Support\Str::plural('line', $direct->count()) }}</div>
        </div>
        <div>
            <div class="k">Recorded By</div>
            <div class="v">{{ $receipt->creator?->display_name ?? '—' }} <small>· {{ $receipt->created_at?->format('d/m/Y H:i') }}</small></div>
        </div>
    </div>
    @if($receipt->note)
        <div class="gr-note"><span class="gr-muted">Note:</span> {{ $receipt->note }}</div>
    @endif
</div>

@if($missingTotal > 0)
    <div class="gr-next warn">
        <div class="txt"><strong>{{ $missingTotal }} SN {{ \Illuminate\Support\Str::plural('unit', $missingTotal) }} not filled in.</strong> These units cannot leave stock until their serial numbers are recorded.</div>
        <a href="{{ route('stock-detail.index', ['goods_receipt_id' => $receipt->id]) }}" class="btn btn-sm btn-warning">Complete SN</a>
    </div>
@endif

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">Received Items <span style="color:var(--text-muted);font-weight:400">({{ $receipt->items->count() }})</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom mb-0 gr-items">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center">#</th>
                        <th>Item</th>
                        <th style="width:200px">For</th>
                        <th class="text-end" style="width:110px">Qty</th>
                        <th class="text-end" style="width:150px">Unit Cost</th>
                        <th style="width:210px">Destination</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipt->items as $i => $item)
                        @php
                            $isStock = $item->destination !== 'direct';
                            $serials = $isStock ? $item->stocks->pluck('serial_number')->filter() : collect();
                            $missing = $isStock ? $item->stocks->filter(fn ($st) => ! $st->canLeave())->count() : 0;
                            $photos = $photoLists->get($item->id);
                            $hasMeta = $item->batch_number || $item->expiry_date || $item->warranty_until || $item->note;
                            $hasDetail = $hasMeta || $serials->isNotEmpty() || $missing || $photos !== null;
                            $isMaterial = (bool) $item->masterProduct?->isMaterial();
                            $logs = $inLogs->get($item->id, collect());
                            $cost = $logs->isNotEmpty() ? $logs->sum(fn ($l) => $l->qty * $l->unit_cost) / max($logs->sum('qty'), 0.0001) : null;
                        @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="gr-name">{{ $item->masterProduct?->name ?: '—' }}</div>
                                <div class="gr-sub">{{ $item->masterProduct?->code ?: '—' }}
                                    @if(! $isStock)
                                        <span class="gr-kind gr-kind-direct ms-1">Direct Use</span>
                                    @elseif($item->masterProduct)
                                        <span class="gr-kind {{ $isMaterial ? 'gr-kind-material' : 'gr-kind-serial' }} ms-1">{{ $isMaterial ? 'Material' : 'Serial' }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($item->project)
                                    <span class="gr-own-project">{{ $item->project->project_number }} — {{ $item->project->project_name }}</span>
                                @else
                                    <span class="gr-sub">Free stock</span>
                                @endif
                            </td>
                            <td class="gr-num"><strong>{{ $fmtQty($item->qty) }}</strong> <span class="gr-sub">{{ $item->unit }}</span></td>
                            <td class="gr-num">{{ $cost !== null ? \App\Support\StockCost::format($cost) : '—' }}
                                @if($item->price !== null && ! \App\Models\Currency::isBaseCode($item->currency))
                                    <div class="gr-sub" title="Document price × exchange rate used at receipt">
                                        {{ strtoupper($item->currency) }} {{ \App\Support\Number::format($item->price, 2) }}{{ $item->exchange_rate ? ' × '.\App\Support\Number::format($item->exchange_rate, 2) : ' · rate not recorded' }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($isStock)
                                    <div style="font-size:12.5px">To warehouse</div>
                                    @if($isMaterial && $logs->isNotEmpty())
                                        <div class="gr-sub">Balance after: {{ $fmtQty($logs->last()->qty_after) }} {{ $item->unit }}</div>
                                    @elseif(! $isMaterial)
                                        <div class="gr-sub">{{ $item->stocks->count() }} stock rows (one per unit)</div>
                                    @endif
                                    @if($item->master_product_id && $receipt->warehouse_id)
                                        <a href="{{ $cardUrl($item) }}" style="font-size:11.5px">Stock card</a>
                                    @endif
                                @else
                                    <div style="font-size:12.5px">Direct use</div>
                                    <div class="gr-sub">Not added to stock</div>
                                @endif
                            </td>
                        </tr>
                        @if($hasDetail)
                            <tr class="gr-detail-row">
                                <td></td>
                                <td colspan="5">
                                    <div class="gr-detail">
                                        @if($hasMeta)
                                            <div class="gr-meta">
                                                @if($item->batch_number)<span><span class="k">Batch</span>{{ $item->batch_number }}</span>@endif
                                                @if($item->expiry_date)<span><span class="k">Expiry</span>{{ $item->expiry_date->format('d/m/Y') }}</span>@endif
                                                @if($item->warranty_until)<span><span class="k">Warranty until</span>{{ $item->warranty_until->format('d/m/Y') }}</span>@endif
                                                @if($item->note)<span><span class="k">Note</span>{{ $item->note }}</span>@endif
                                            </div>
                                        @endif
                                        @if($serials->isNotEmpty() || $missing > 0)
                                            <div>
                                                <span class="k">Serial numbers ({{ $serials->count() }}/{{ $serials->count() + $missing }})</span>
                                                @if($missing > 0)<span class="gr-warn">· {{ $missing }} not filled in</span>@endif
                                                @if($serials->isNotEmpty())
                                                    <div>@foreach($serials as $sn)<span class="gr-sn">{{ $sn }}</span>@endforeach</div>
                                                @endif
                                            </div>
                                        @endif
                                        @if($photos !== null)
                                            <div>
                                                <span class="k">Photos</span>
                                                <div class="gr-photos" data-item="{{ $item->id }}"
                                                     data-upload-url="{{ route('goods-receipt.photos.store', [$receipt->id, $item->id]) }}"
                                                     data-photos='@json($photos)'
                                                     data-can-upload="{{ $canCreate ? 1 : 0 }}"></div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4" style="color:var(--text-muted)">No items yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="gr-legend">
            <span><span class="gr-kind gr-kind-material">Material</span> added to the product balance</span>
            <span><span class="gr-kind gr-kind-serial">Serial</span> one stock row per unit</span>
            <span><span class="gr-kind gr-kind-direct">Direct Use</span> not added to stock</span>
        </div>
    </div>
</div>

@include('partials.purchase-documents', [
    'documents' => $documents,
    'types' => $receipt->documentTypes(),
    'uploadUrl' => route('goods-receipt.documents.store', $receipt->id),
    'canUpload' => $canCreate,
    'hint' => 'Optional — supplier delivery note / invoice.',
])
@endsection

@push('page-scripts')
<script>
// Foto barang ber-SN: galeri per item + unggah susulan (dikompres dulu di browser).
const grPhotoMax = {{ \App\Models\Document::MAX_FILES }};
const grPhotoFileMax = {{ \Illuminate\Http\UploadedFile::getMaxFilesize() }};

function grPhotoEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function grPhotoRender($wrap, photos) {
    $wrap.data('photos', photos);
    var canUpload = String($wrap.data('can-upload')) === '1';
    var html = photos.map(function(p, idx) {
        return '<div class="gr-photo"><a href="' + p.url + '" target="_blank" rel="noopener" title="' + grPhotoEsc(p.title) + '"><img src="' + p.url + '" alt="Photo ' + (idx + 1) + '" loading="lazy"></a>'
            + (p.delete_url ? '<button type="button" class="gr-photo-del" data-url="' + p.delete_url + '" title="Delete photo" aria-label="Delete photo ' + (idx + 1) + '"><i class="fa fa-xmark"></i></button>' : '')
            + '</div>';
    }).join('');
    if (!photos.length && !canUpload) html = '<span class="gr-photo-empty">No photos.</span>';
    if (canUpload && photos.length < grPhotoMax) {
        html += '<button type="button" class="btn btn-outline-secondary btn-sm gr-photo-add">' + (photos.length ? 'Add more photos' : 'Add photos') + '</button>'
            + '<input type="file" class="d-none gr-photo-input" accept="image/jpeg,image/png,image/webp" multiple aria-label="Add photos">';
    }
    $wrap.html(html);
}

$(document).ready(function() {
    $('.gr-photos[data-item]').each(function() { grPhotoRender($(this), $(this).data('photos') || []); });

    $(document).on('click', '.gr-photos .gr-photo-add', function() { $(this).siblings('.gr-photo-input').trigger('click'); });

    $(document).on('change', '.gr-photos .gr-photo-input', function() {
        var $wrap = $(this).closest('.gr-photos');
        var room = grPhotoMax - ($wrap.data('photos') || []).length;
        var files = Array.prototype.slice.call(this.files || []);
        this.value = '';
        if (files.length > room) toastr.warning('Up to ' + grPhotoMax + ' photos per item; ' + (files.length - room) + ' photo(s) skipped.');
        files = files.slice(0, Math.max(room, 0));
        if (!files.length) return;
        var $btn = $wrap.find('.gr-photo-add').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Uploading…');
        Promise.all(files.map(function(f) { return window.compressImage(f); })).then(function(result) {
            var fd = new FormData();
            var skipped = 0;
            result.forEach(function(f) { if (f.size > grPhotoFileMax) { skipped++; return; } fd.append('files[]', f, f.name); });
            if (skipped) toastr.error(skipped + ' photo(s) too large for the server were skipped.');
            if (!fd.has('files[]')) { grPhotoRender($wrap, $wrap.data('photos') || []); return; }
            fd.append('_token', '{{ csrf_token() }}');
            $.ajax({ url: $wrap.data('upload-url'), method: 'POST', data: fd, processData: false, contentType: false })
                .done(function(res) { toastr.success(res.message || 'Uploaded.'); grPhotoRender($wrap, res.photos || []); })
                .fail(function(xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to upload photos.';
                    if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
                    toastr.error(msg);
                    grPhotoRender($wrap, $wrap.data('photos') || []);
                });
        });
    });

    $(document).on('click', '.gr-photos .gr-photo-del', function() {
        var $wrap = $(this).closest('.gr-photos');
        var url = $(this).data('url');
        Swal.fire({
            title: 'Delete this photo?',
            text: 'The photo is removed permanently.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc2626'
        }).then(function(r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: url, method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                .done(function(res) { toastr.success(res.message || 'Deleted.'); grPhotoRender($wrap, res.photos || []); })
                .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete photo.'); });
        });
    });
});
</script>
@endpush

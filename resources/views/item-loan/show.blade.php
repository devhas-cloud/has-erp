@extends('layouts.app')

@section('title', 'Item Loan Details')
@section('page-title', 'Item Loan Details')

@php
    $onLoan = $loan->isOnLoan();
    $overdue = $loan->isOverdue();
    $outItems = $loan->items->whereNull('return_status');
    $returnedCount = $loan->items->count() - $outItems->count();
    $days = $onLoan ? $loan->daysToDue() : null;
    $returnLabels = \App\Models\ItemLoanItem::RETURN_LABELS;
    $returnColors = ['good' => ['#dcfce7', '#065f46'], 'damaged' => ['#fef3c7', '#92400e'], 'lost' => ['#fee2e2', '#b42318']];
    $canCancel = $onLoan && $returnedCount === 0 && $canDelete;
@endphp

@section('styles')
@include('partials.image-compress')
<style>
    .il-muted { color: var(--text-muted); font-size: 12px; }
    .il-hero { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 14px; }
    .il-hero-top { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .il-number { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 4px 0 2px; font-variant-numeric: tabular-nums; }
    .il-due { text-align: right; }
    .il-due .val { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; }
    .il-due .late { color: #b42318; }
    @media (max-width: 575.98px) { .il-due { text-align: left; } }
    .il-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px 18px; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--card-border); }
    .il-facts .k { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; }
    .il-facts .v { font-size: 13px; color: var(--text-primary); font-weight: 600; overflow-wrap: anywhere; }
    .il-facts .v small { font-weight: 400; color: var(--text-muted); font-size: 12px; }
    .il-next { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border: 1px solid; border-radius: var(--radius); padding: 12px 16px; margin-bottom: 14px; font-size: 13px; }
    .il-next .txt { flex: 1; min-width: 220px; }
    .il-next .txt strong { display: block; font-size: 14px; }
    .il-next .acts { display: flex; gap: 8px; flex-wrap: wrap; }
    .il-next.info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .il-next.danger { background: #fef2f2; border-color: #fecaca; color: #7f1d1d; }
    .il-next.ok { background: #f0fdf4; border-color: #bbf7d0; color: #14532d; }
    .il-next.muted { background: #f9fafb; border-color: var(--card-border); color: #374151; }
    .il-units th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .il-units td { vertical-align: top; font-size: 12.5px; }
    .il-name { font-weight: 600; font-size: 13px; color: var(--text-primary); }
    .il-sub { font-size: 11.5px; color: var(--text-muted); }
    .il-sn { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
    .il-badge { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 1px 8px; border-radius: 99px; white-space: nowrap; }
    .il-detail-row td { border-top: 0 !important; padding-top: 0 !important; background: #fafbfc; }
    .gr-photos { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 4px 0 6px; }
    .gr-photo { position: relative; width: 56px; height: 56px; border-radius: 6px; overflow: hidden; border: 1px solid var(--card-border); background: #f8fafc; }
    .gr-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .gr-photo-del { position: absolute; top: 2px; right: 2px; width: 18px; height: 18px; border: 0; border-radius: 50%; background: rgba(15, 23, 42, .65); color: #fff; font-size: 10px; line-height: 1; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .gr-photo-del:hover { background: #b42318; }
    .gr-photo-add { height: 56px; font-size: 12px; }
    .gr-photo-empty { font-size: 12px; color: var(--text-muted); }
    .il-ret-row td { vertical-align: middle; font-size: 12.5px; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div><h1 class="page-header-title">Item Loan Details</h1></div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('item-loan.pdf', $loan->id) }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Print PDF</a>
        <a href="{{ route('item-loan.index') }}" class="btn btn-secondary btn-sm">Back</a>
        @if($canCancel)
        <div class="dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">More</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#il-cancel-modal">Cancel Loan</button></li>
            </ul>
        </div>
        @endif
    </div>
</div>

<div class="il-hero fade-in">
    <div class="il-hero-top">
        <div>
            {!! $loan->statusBadgeHtml() !!}
            <div class="il-number">{{ $loan->loan_number }}</div>
            <div class="il-muted">{{ $loan->borrowerLabel() }}{{ $loan->isExternal() ? ' (external)' : '' }} &middot; {{ $loan->purpose }}</div>
        </div>
        <div class="il-due">
            <div class="il-muted">Return by</div>
            <div class="val {{ $overdue ? 'late' : '' }}">{{ $loan->due_date->format('d/m/Y') }}</div>
            @if($onLoan)
                <div class="il-muted {{ $overdue ? 'text-danger fw-semibold' : '' }}">{{ $days < 0 ? abs($days).' day(s) overdue' : ($days === 0 ? 'Due today' : 'in '.$days.' day(s)') }}</div>
            @endif
        </div>
    </div>
    <div class="il-facts">
        <div>
            <div class="k">Borrower</div>
            <div class="v">{{ $loan->borrowerLabel() }}
                <small>· {{ $loan->isExternal() ? ($loan->borrower_company ?: 'External party') : ($loan->borrowerUser?->division?->division_name ?? 'Internal staff') }}</small>
                @if($loan->borrowerPhone())<div><small>{{ $loan->borrowerPhone() }}</small></div>@endif
            </div>
        </div>
        <div>
            <div class="k">Warehouse</div>
            <div class="v">{{ $loan->warehouse?->code }} — {{ $loan->warehouse?->name }} <small>· {{ $loan->warehouse?->branch?->name }}</small></div>
        </div>
        <div>
            <div class="k">Loan date</div>
            <div class="v">{{ $loan->loan_date->format('d/m/Y') }} <small>· {{ $loan->items->count() }} unit(s)</small></div>
        </div>
        <div>
            <div class="k">Recorded by</div>
            <div class="v">{{ $loan->creator?->display_name ?? '—' }} <small>· {{ $loan->created_at?->format('d/m/Y H:i') }}</small></div>
        </div>
        @if($loan->project)
        <div>
            <div class="k">Project</div>
            <div class="v"><a href="{{ route('project.show', $loan->project_id) }}" class="text-decoration-none">{{ $loan->project->project_number }}</a> <small>· {{ $loan->project->project_name }}</small></div>
        </div>
        @endif
    </div>
    @if($loan->note)<div class="mt-2" style="font-size:12.5px"><span class="il-muted">Note:</span> {{ $loan->note }}</div>@endif
</div>

@if($onLoan)
    <div class="il-next {{ $overdue ? 'danger' : 'info' }}">
        <div class="txt">
            <strong>{{ $overdue ? 'Overdue — follow up with the borrower' : $outItems->count().' unit(s) on loan' }}</strong>
            {{ $returnedCount ? $returnedCount.' of '.$loan->items->count().' unit(s) already returned. ' : '' }}Record returns per unit as they come back; the loan closes when every unit is settled.
        </div>
        @if($canCreate)
        <div class="acts">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#il-extend-modal">Extend</button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#il-return-modal">Record Return</button>
        </div>
        @endif
    </div>
@elseif($loan->status === \App\Models\ItemLoan::STATUS_RETURNED)
    <div class="il-next ok"><div class="txt"><strong>All units settled</strong>Closed {{ $loan->closed_at?->format('d/m/Y H:i') }}.</div></div>
@else
    <div class="il-next muted"><div class="txt"><strong>Cancelled{{ $loan->canceller ? ' by '.$loan->canceller->display_name : '' }} · {{ $loan->cancelled_at?->format('d/m/Y H:i') }}</strong>{{ $loan->cancel_reason }}</div></div>
@endif

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">Units <span style="color:var(--text-muted);font-weight:400">({{ $loan->items->count() }})</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom mb-0 il-units">
                <thead>
                    <tr>
                        <th style="width:36px" class="text-center">#</th>
                        <th>Product</th>
                        <th>Serial Number</th>
                        <th>Condition when lent</th>
                        <th>Return</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loan->items as $i => $item)
                        @php $photos = $photoLists->get($item->id, []); @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="il-name">{{ $item->masterProduct?->display_name ?? $item->masterProduct?->code }}</div>
                                <div class="il-sub">{{ $item->masterProduct?->code }}</div>
                            </td>
                            <td class="il-sn">{{ $item->stock?->serial_number ?? '—' }}</td>
                            <td>{{ $item->condition_out ?: '—' }}</td>
                            <td>
                                @if($item->isOut())
                                    @if($loan->status === \App\Models\ItemLoan::STATUS_CANCELLED)
                                        <span class="il-sub">Loan cancelled</span>
                                    @else
                                        <span class="il-badge" style="background:#fef3c7;color:#92400e">On loan</span>
                                    @endif
                                @else
                                    @php [$bg, $fg] = $returnColors[$item->return_status]; @endphp
                                    <span class="il-badge" style="background:{{ $bg }};color:{{ $fg }}">{{ $returnLabels[$item->return_status] }}</span>
                                    <div class="il-sub">{{ $item->returned_at?->format('d/m/Y') }} · {{ $item->returner?->display_name ?? '—' }}</div>
                                    @if($item->return_note)<div style="font-size:12px">{{ $item->return_note }}</div>@endif
                                @endif
                            </td>
                        </tr>
                        <tr class="il-detail-row">
                            <td></td>
                            <td colspan="4">
                                <span class="il-sub">Condition photos</span>
                                <div class="gr-photos" data-item="{{ $item->id }}"
                                     data-upload-url="{{ route('item-loan.photos.store', [$loan->id, $item->id]) }}"
                                     data-photos='@json($photos)'
                                     data-can-upload="{{ $canCreate && $loan->status !== \App\Models\ItemLoan::STATUS_CANCELLED ? 1 : 0 }}"></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($loan->extensions->isNotEmpty())
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">Extensions</div>
    <div class="card-body-custom p-2">
        <table class="table table-sm mb-0" style="font-size:12.5px">
            <thead><tr><th>Date</th><th>Return date</th><th>Reason</th><th>By</th></tr></thead>
            <tbody>
                @foreach($loan->extensions as $ext)
                    <tr>
                        <td>{{ $ext->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $ext->old_due_date->format('d/m/Y') }} → <strong>{{ $ext->new_due_date->format('d/m/Y') }}</strong></td>
                        <td>{{ $ext->reason }}</td>
                        <td>{{ $ext->creator?->display_name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@push('modals')
@if($onLoan && $canCreate)
<div class="modal fade" id="il-return-modal" tabindex="-1" aria-labelledby="il-return-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="il-return-title">Record Return · {{ $loan->loan_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-4">
                        <label class="form-label" for="il-ret-date">Returned on</label>
                        <input type="date" class="form-control" id="il-ret-date" value="{{ now()->toDateString() }}" min="{{ $loan->loan_date->toDateString() }}">
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="il-sub">Tick the units that came back and set their condition. <strong>Lost</strong> units are removed from stock under the borrower's name. Damaged or lost units need a note.</div>
                    </div>
                </div>
                <table class="table table-sm mb-0">
                    <thead><tr><th style="width:34px"><input type="checkbox" class="form-check-input" id="il-ret-all" aria-label="Select all"></th><th>Unit</th><th style="width:150px">Condition</th><th>Note</th></tr></thead>
                    <tbody>
                        @foreach($outItems as $item)
                            <tr class="il-ret-row" data-id="{{ $item->id }}">
                                <td><input type="checkbox" class="form-check-input il-ret-pick" aria-label="Return {{ $item->stock?->serial_number }}"></td>
                                <td><div class="il-name" style="font-size:12.5px">{{ $item->masterProduct?->code }}</div><div class="il-sn">SN {{ $item->stock?->serial_number }}</div></td>
                                <td>
                                    <select class="form-select form-select-sm il-ret-status" aria-label="Condition" disabled>
                                        @foreach($returnLabels as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" class="form-control form-control-sm il-ret-note" maxlength="255" placeholder="Optional for Good" aria-label="Note" disabled></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <span class="me-auto il-sub" id="il-ret-count">0 unit(s) selected</span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="il-ret-save" disabled>Record Return</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="il-extend-modal" tabindex="-1" aria-labelledby="il-extend-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="il-extend-title">Extend Return Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="il-ext-date">New return date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="il-ext-date" min="{{ $loan->due_date->copy()->addDay()->toDateString() }}" value="{{ $loan->due_date->copy()->addDays(7)->toDateString() }}">
                    <div class="form-text">Current: {{ $loan->due_date->format('d/m/Y') }}.</div>
                </div>
                <label class="form-label" for="il-ext-reason">Reason <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="il-ext-reason" maxlength="255" placeholder="e.g. Demo extended by customer">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="il-ext-save">Extend</button>
            </div>
        </div>
    </div>
</div>
@endif

@if($canCancel)
<div class="modal fade" id="il-cancel-modal" tabindex="-1" aria-labelledby="il-cancel-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="il-cancel-title">Cancel Loan {{ $loan->loan_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:13px">Use this only for a loan recorded by mistake. All units become available again.</p>
                <label class="form-label" for="il-cancel-reason">Reason <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="il-cancel-reason" maxlength="255">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Back</button>
                <button type="button" class="btn btn-danger btn-sm" id="il-cancel-save">Cancel Loan</button>
            </div>
        </div>
    </div>
</div>
@endif
@endpush

@section('scripts')
<script>
const ilReturnUrl = '{{ route("item-loan.return", $loan->id) }}';
const ilExtendUrl = '{{ route("item-loan.extend", $loan->id) }}';
const ilCancelUrl = '{{ route("item-loan.cancel", $loan->id) }}';

function ilPost(url, payload, $btn) {
    $btn.prop('disabled', true);
    $.ajax({ url: url, method: 'POST', contentType: 'application/json', data: JSON.stringify(payload),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .done(function(res) { toastr.success(res.message || 'Saved.'); setTimeout(function() { location.reload(); }, 600); })
        .fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed.';
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
            $btn.prop('disabled', false);
        });
}

function ilRetSync() {
    var n = 0;
    $('.il-ret-row').each(function() {
        var on = $(this).find('.il-ret-pick').is(':checked');
        $(this).find('.il-ret-status, .il-ret-note').prop('disabled', !on);
        var needNote = on && $(this).find('.il-ret-status').val() !== 'good';
        $(this).find('.il-ret-note').attr('placeholder', needNote ? 'Required — what happened?' : 'Optional for Good');
        if (on) n++;
    });
    $('#il-ret-count').text(n + ' unit(s) selected');
    $('#il-ret-save').prop('disabled', n === 0);
    var total = $('.il-ret-row').length;
    $('#il-ret-all').prop('checked', n > 0 && n === total).prop('indeterminate', n > 0 && n < total);
}

$(document).ready(function() {
    $(document).on('change', '.il-ret-pick, .il-ret-status', ilRetSync);
    $('#il-ret-all').on('change', function() { $('.il-ret-pick').prop('checked', $(this).is(':checked')); ilRetSync(); });
    $(document).on('input', '.il-ret-note', function() { $(this).removeClass('is-invalid'); });

    $('#il-ret-save').on('click', function() {
        var items = [], ok = true, lost = 0;
        $('.il-ret-row').each(function() {
            if (!$(this).find('.il-ret-pick').is(':checked')) return;
            var status = $(this).find('.il-ret-status').val();
            var $note = $(this).find('.il-ret-note');
            if (status !== 'good' && !$note.val().trim()) { $note.addClass('is-invalid'); ok = false; }
            if (status === 'lost') lost++;
            items.push({ id: $(this).data('id'), status: status, note: $note.val().trim() || null });
        });
        if (!ok) { toastr.error('Add a note for units returned damaged or lost.'); $('.il-ret-note.is-invalid').first().trigger('focus'); return; }
        var $btn = $(this);
        var send = function() { ilPost(ilReturnUrl, { returned_at: $('#il-ret-date').val(), items: items }, $btn); };
        if (!lost) { send(); return; }
        Swal.fire({
            title: 'Mark ' + lost + ' unit(s) as lost?',
            text: 'Lost units are removed from stock under the borrower\'s name. This cannot be undone here.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, record', cancelButtonText: 'Cancel', confirmButtonColor: '#dc2626'
        }).then(function(r) { if (r.isConfirmed) send(); });
    });

    $('#il-ext-save').on('click', function() {
        if (!$('#il-ext-reason').val().trim()) { $('#il-ext-reason').addClass('is-invalid').trigger('focus'); toastr.error('Enter the reason for the extension.'); return; }
        ilPost(ilExtendUrl, { new_due_date: $('#il-ext-date').val(), reason: $('#il-ext-reason').val().trim() }, $(this));
    });

    $('#il-cancel-save').on('click', function() {
        if (!$('#il-cancel-reason').val().trim()) { $('#il-cancel-reason').addClass('is-invalid').trigger('focus'); toastr.error('Enter the reason for cancelling.'); return; }
        ilPost(ilCancelUrl, { reason: $('#il-cancel-reason').val().trim() }, $(this));
    });
});
</script>
@endsection

@push('page-scripts')
<script>
// Foto kondisi per unit: galeri + unggah (dikompres di browser).
const ilPhotoMax = {{ \App\Models\Document::MAX_FILES }};
const ilPhotoFileMax = {{ \Illuminate\Http\UploadedFile::getMaxFilesize() }};

function ilPhotoEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }

function ilPhotoRender($wrap, photos) {
    $wrap.data('photos', photos);
    var canUpload = String($wrap.data('can-upload')) === '1';
    var html = photos.map(function(p, idx) {
        return '<div class="gr-photo"><a href="' + p.url + '" target="_blank" rel="noopener" title="' + ilPhotoEsc(p.title) + '"><img src="' + p.url + '" alt="Photo ' + (idx + 1) + '" loading="lazy"></a>'
            + (p.delete_url ? '<button type="button" class="gr-photo-del" data-url="' + p.delete_url + '" title="Delete photo" aria-label="Delete photo ' + (idx + 1) + '"><i class="fa fa-xmark"></i></button>' : '') + '</div>';
    }).join('');
    if (!photos.length && !canUpload) html = '<span class="gr-photo-empty">No photos.</span>';
    if (canUpload && photos.length < ilPhotoMax) {
        html += '<button type="button" class="btn btn-outline-secondary btn-sm gr-photo-add">' + (photos.length ? 'Add more photos' : 'Add photos') + '</button>'
            + '<input type="file" class="d-none gr-photo-input" accept="image/jpeg,image/png,image/webp" multiple aria-label="Add photos">';
    }
    $wrap.html(html);
}

$(document).ready(function() {
    $('.gr-photos[data-item]').each(function() { ilPhotoRender($(this), $(this).data('photos') || []); });
    $(document).on('click', '.gr-photos .gr-photo-add', function() { $(this).siblings('.gr-photo-input').trigger('click'); });
    $(document).on('change', '.gr-photos .gr-photo-input', function() {
        var $wrap = $(this).closest('.gr-photos');
        var room = ilPhotoMax - ($wrap.data('photos') || []).length;
        var files = Array.prototype.slice.call(this.files || []).slice(0, Math.max(room, 0));
        this.value = '';
        if (!files.length) return;
        $wrap.find('.gr-photo-add').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Uploading…');
        Promise.all(files.map(function(f) { return window.compressImage(f); })).then(function(result) {
            var fd = new FormData();
            result.forEach(function(f) { if (f.size <= ilPhotoFileMax) fd.append('files[]', f, f.name); });
            if (!fd.has('files[]')) { toastr.error('Photos are too large for the server.'); ilPhotoRender($wrap, $wrap.data('photos') || []); return; }
            fd.append('_token', '{{ csrf_token() }}');
            $.ajax({ url: $wrap.data('upload-url'), method: 'POST', data: fd, processData: false, contentType: false })
                .done(function(res) { toastr.success(res.message || 'Uploaded.'); ilPhotoRender($wrap, res.photos || []); })
                .fail(function(xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to upload photos.';
                    if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
                    toastr.error(msg);
                    ilPhotoRender($wrap, $wrap.data('photos') || []);
                });
        });
    });
    $(document).on('click', '.gr-photos .gr-photo-del', function() {
        var $wrap = $(this).closest('.gr-photos');
        var url = $(this).data('url');
        Swal.fire({ title: 'Delete this photo?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel', confirmButtonColor: '#dc2626' })
            .then(function(r) {
                if (!r.isConfirmed) return;
                $.ajax({ url: url, method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                    .done(function(res) { toastr.success(res.message || 'Deleted.'); ilPhotoRender($wrap, res.photos || []); })
                    .fail(function(xhr) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete photo.'); });
            });
    });
});
</script>
@endpush

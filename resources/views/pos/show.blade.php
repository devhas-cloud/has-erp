@extends('layouts.app')

@section('title', 'POS Detail')
@section('page-title', 'POS Detail')

@php
    $fmtMoney = fn ($v) => 'Rp '.\App\Support\Number::money($v);
    $fmtQty = fn ($v) => \App\Support\Number::qty($v);

    $isDraft = $pos->status === \App\Models\Pos::STATUS_DRAFT;
    $isRejected = $pos->status === \App\Models\Pos::STATUS_REJECTED;
    $isWaiting = $pos->status === \App\Models\Pos::STATUS_WAITING_APPROVAL;
    $isApproved = $pos->status === \App\Models\Pos::STATUS_APPROVED;
    $canEdit = ($isDraft || $isRejected) && $canSubmit;

    // Tahapan: Draft -> Approval -> Approved -> Project.
    $steps = ['Draft', 'Approval', 'Approved', 'Project'];
    $stepIndex = match (true) {
        $isApproved && $pos->project_id => 4,
        $isApproved => 3,
        $isWaiting, $isRejected => 1,
        default => 0,
    };
    $stepNotes = [
        0 => trim(($pos->creator?->display_name ?? '').($pos->created_at ? ' · '.$pos->created_at->format('d/m/Y') : ''), ' ·'),
        1 => match (true) {
            $isRejected => 'Rejected '.($pos->rejecter?->display_name ?? '').($pos->rejected_at ? ' · '.$pos->rejected_at->format('d/m/Y') : ''),
            $isWaiting => 'Waiting for 1 approver',
            default => null,
        },
        2 => $pos->approved_at ? trim(($pos->approver?->display_name ?? '').' · '.$pos->approved_at->format('d/m/Y'), ' ·') : null,
        3 => $pos->project?->project_number,
    ];
@endphp

@section('styles')
<style>
    .pos-muted { color: var(--text-muted); font-size: 12px; }
    .pos-hero { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 14px; }
    .pos-hero-top { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .pos-number { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 4px 0 2px; font-variant-numeric: tabular-nums; }
    .pos-context a { color: inherit; font-weight: 600; text-decoration: none; }
    .pos-context a:hover { color: var(--accent); }
    .pos-total { text-align: right; }
    .pos-total .val { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
    @media (max-width: 575.98px) { .pos-total { text-align: left; } }

    /* Tahapan status */
    .pos-steps { display: flex; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--card-border); }
    .pos-step { flex: 1; text-align: center; position: relative; font-size: 12px; color: var(--text-muted); }
    .pos-step::before { content: ''; position: absolute; top: 11px; left: -50%; width: 100%; height: 2px; background: var(--card-border); }
    .pos-step:first-child::before { display: none; }
    .pos-step .dot { position: relative; width: 24px; height: 24px; border-radius: 50%; margin-bottom: 3px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; background: #fff; border: 2px solid var(--card-border); }
    .pos-step.done::before, .pos-step.current::before { background: var(--accent); }
    .pos-step.done .dot { background: var(--accent); border-color: var(--accent); color: #fff; }
    .pos-step.current { color: var(--text-primary); font-weight: 600; }
    .pos-step.current .dot { border-color: #f59e0b; color: #b45309; }
    .pos-step.stopped { color: #b91c1c; font-weight: 600; }
    .pos-step.stopped .dot { border-color: #dc2626; color: #dc2626; }
    .pos-step-note { font-size: 11px; color: var(--text-muted); font-weight: 400; }

    /* Langkah berikutnya */
    .pos-next { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border: 1px solid; border-radius: var(--radius); padding: 12px 16px; margin-bottom: 14px; }
    .pos-next .txt { flex: 1; min-width: 220px; font-size: 13px; }
    .pos-next .txt strong { display: block; font-size: 14px; }
    .pos-next .acts { display: flex; gap: 8px; flex-wrap: wrap; }
    .pos-next.info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .pos-next.warn { background: #fffbeb; border-color: #fde68a; color: #78350f; }
    .pos-next.ok { background: #f0fdf4; border-color: #bbf7d0; color: #14532d; }
    .pos-next.danger { background: #fef2f2; border-color: #fecaca; color: #7f1d1d; }

    .pos-items th, .pos-items td { font-size: 12.5px; vertical-align: top; }
    .pos-items td.pos-desc { min-width: 220px; }
    .pos-items td.pos-desc p { margin: 0; }
    .pos-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pos-unit { color: var(--text-muted); }

    .pos-sum { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; }
    .pos-sum .l { color: var(--text-muted); }
    .pos-sum .v { font-weight: 600; font-variant-numeric: tabular-nums; }
    .pos-sum.grand { border-top: 1px solid var(--card-border); margin-top: 4px; padding-top: 10px; font-size: 15px; font-weight: 700; }
    .pos-sum.grand .l { color: var(--text-primary); }

    .pos-dl { display: grid; grid-template-columns: 95px 1fr; gap: 7px 10px; margin: 0; font-size: 13px; }
    .pos-dl dt { color: var(--text-muted); font-weight: 500; font-size: 12px; }
    .pos-dl dd { margin: 0; word-break: break-word; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">POS Detail</h1>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('pos.index') }}" class="btn btn-secondary btn-sm">Back</a>
        @if($canEdit)
        <div class="dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">More</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('pos.edit', $pos->id) }}">Edit POS</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button type="button" class="dropdown-item text-danger" onclick="posDelete()">Delete POS</button></li>
            </ul>
        </div>
        @endif
    </div>
</div>

{{-- Ringkas: nomor, customer, total, tahapan --}}
<div class="pos-hero fade-in">
    <div class="pos-hero-top">
        <div>
            {!! $pos->statusBadgeHtml() !!}
            <div class="pos-number">{{ $pos->pos_number }}</div>
            <div class="pos-muted pos-context">
                {{ $pos->company?->account_name ?? '—' }}
                &middot; {{ $pos->date?->format('d/m/Y') ?? 'No date' }}
                @if($pos->quotation)
                    &middot; Quotation <a href="{{ route('quotation.show', $pos->quotation_id) }}">{{ $pos->quotation->quotation_number }}</a>
                @endif
            </div>
        </div>
        <div class="pos-total">
            <div class="pos-muted">Grand Total</div>
            <div class="val">{{ $fmtMoney($pos->grand_total) }}</div>
        </div>
    </div>
    <div class="pos-steps">
        @foreach($steps as $i => $label)
            @php
                $state = $i < $stepIndex ? 'done' : ($i === $stepIndex ? ($isRejected ? 'stopped' : 'current') : '');
            @endphp
            <div class="pos-step {{ $state }}">
                <div class="dot">{{ $i + 1 }}</div>
                <div>{{ $state === 'stopped' ? 'Rejected' : $label }}</div>
                @if(! empty($stepNotes[$i]))<div class="pos-step-note">{{ $stepNotes[$i] }}</div>@endif
            </div>
        @endforeach
    </div>
</div>

{{-- Langkah berikutnya + aksi utama --}}
@if($isDraft)
    <div class="pos-next info">
        <div class="txt"><strong>{{ $canSubmit ? 'Review items & totals, then submit for approval' : 'Still draft' }}</strong>The project can only be created after the POS is approved.</div>
        @if($canSubmit)
        <div class="acts">
            <button type="button" class="btn btn-primary btn-sm" id="pos-submit-btn" onclick="posSubmit()">Submit for Approval</button>
        </div>
        @endif
    </div>
@elseif($isWaiting)
    <div class="pos-next warn">
        <div class="txt"><strong>{{ $canDecide ? 'Waiting for your approval' : 'Waiting for approval' }}</strong>One approver is enough. Review the customer, items and grand total before deciding.</div>
        @if($canDecide)
        <div class="acts">
            <button type="button" class="btn btn-danger btn-sm" onclick="posOpenReject()">Reject</button>
            <button type="button" class="btn btn-success btn-sm" id="pos-approve-btn" onclick="posApprove()">Approve</button>
        </div>
        @endif
    </div>
@elseif($isRejected)
    <div class="pos-next danger">
        <div class="txt"><strong>Rejected{{ $pos->rejecter ? ' by '.$pos->rejecter->display_name : '' }}</strong>{{ $pos->reject_reason ?: 'No reason given.' }}<div class="mt-1">Fix the items or totals, then resubmit.</div></div>
        @if($canSubmit)
        <div class="acts">
            <button type="button" class="btn btn-primary btn-sm" id="pos-submit-btn" onclick="posSubmit()">Resubmit</button>
        </div>
        @endif
    </div>
@elseif($isApproved && $pos->project)
    <div class="pos-next ok">
        <div class="txt"><strong>Converted to project {{ $pos->project->project_number }}</strong>This POS is complete.</div>
        <div class="acts"><a href="{{ route('project.show', $pos->project_id) }}" class="btn btn-outline-secondary btn-sm">Open Project</a></div>
    </div>
@elseif($isApproved)
    <div class="pos-next ok">
        <div class="txt"><strong>Approved — ready to create the project</strong>{{ $pos->requires_dp ? 'This quotation requires a DP: make sure the DP is fulfilled before creating the project.' : 'The project is created from this POS quotation.' }}</div>
        @if($canRunProject)
        <div class="acts"><a href="{{ route('project.create', ['quotation_id' => $pos->quotation_id]) }}" class="btn btn-primary btn-sm">Create Project</a></div>
        @endif
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-custom fade-in">
            <div class="card-header-custom">Items <span style="color:var(--text-muted);font-weight:400">({{ $pos->items->count() }} · customer naming)</span></div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 pos-items">
                        <thead>
                            <tr>
                                <th style="width:34px" class="text-center">#</th>
                                <th>Description</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pos->items as $i => $item)
                                <tr>
                                    <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                                    <td class="pos-desc">{!! \App\Models\Quotation::renderDescription($item->description) ?: '—' !!}</td>
                                    <td class="pos-num">{{ $fmtQty($item->qty) }} <span class="pos-unit">{{ $item->unit }}</span></td>
                                    <td class="pos-num">{{ $fmtMoney($item->unit_price) }}</td>
                                    <td class="pos-num">{{ $fmtMoney($item->subtotal) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-3" style="color:var(--text-muted)">No items yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">Value Summary</div>
            <div class="card-body-custom">
                <div class="pos-sum"><span class="l">Subtotal</span><span class="v">{{ $fmtMoney($pos->subtotal) }}</span></div>
                <div class="pos-sum">
                    <span class="l">Discount @if($pos->discount_percent) ({{ \App\Support\Number::percent($pos->discount_percent) }}%) @endif</span>
                    <span class="v">{{ (float) $pos->discount_amount > 0 ? '-'.$fmtMoney($pos->discount_amount) : '—' }}</span>
                </div>
                <div class="pos-sum">
                    <span class="l">VAT @if($pos->ppn_percent) ({{ \App\Support\Number::percent($pos->ppn_percent) }}%) @endif</span>
                    <span class="v">{{ (float) $pos->ppn_amount > 0 ? $fmtMoney($pos->ppn_amount) : '—' }}</span>
                </div>
                <div class="pos-sum grand"><span class="l">Grand Total</span><span class="v">{{ $fmtMoney($pos->grand_total) }}</span></div>
            </div>
        </div>

        <div class="card-custom fade-in">
            <div class="card-header-custom">Information</div>
            <div class="card-body-custom">
                <dl class="pos-dl">
                    <dt>Customer</dt><dd>{{ $pos->company?->account_name ?? '—' }}</dd>
                    <dt>Payment</dt>
                    <dd>
                        @if($pos->requires_dp)
                            <span class="status-badge" style="background:#fef3c7;color:#92400e;">DP required</span>
                        @else
                            <span class="status-badge" style="background:#dcfce7;color:#065f46;">No DP</span>
                        @endif
                    </dd>
                    <dt>Quotation</dt><dd>@if($pos->quotation)<a href="{{ route('quotation.show', $pos->quotation_id) }}">{{ $pos->quotation->quotation_number }}</a>@else — @endif</dd>
                    <dt>Project</dt><dd>@if($pos->project)<a href="{{ route('project.show', $pos->project_id) }}">{{ $pos->project->project_number }}</a>@else — @endif</dd>
                    <dt>Created</dt><dd>{{ $pos->creator?->display_name ?? '—' }}@if($pos->created_at) <span class="pos-muted">· {{ $pos->created_at->format('d/m/Y H:i') }}</span>@endif</dd>
                    @if($pos->approver)
                        <dt>Approved</dt><dd>{{ $pos->approver->display_name }} <span class="pos-muted">· {{ $pos->approved_at?->format('d/m/Y H:i') }}</span></dd>
                    @endif
                    @if($isRejected && $pos->rejecter)
                        <dt>Rejected</dt><dd>{{ $pos->rejecter->display_name }} <span class="pos-muted">· {{ $pos->rejected_at?->format('d/m/Y H:i') }}</span></dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="pos-reject-modal" tabindex="-1" aria-labelledby="pos-reject-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pos-reject-title">Reject POS {{ $pos->pos_number }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="pos-reject-reason">Rejection reason <span class="text-danger">*</span></label>
                <textarea class="form-control" id="pos-reject-reason" rows="3" maxlength="255" placeholder="E.g. item prices do not match the negotiation yet"></textarea>
                <div class="form-text">The POS creator will see this reason and can resubmit after fixing it.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="pos-reject-btn" onclick="posReject()">Reject POS</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const posSubmitUrl = '{{ route("pos.submit", $pos->id) }}';
const posApproveUrl = '{{ route("pos.approve", $pos->id) }}';
const posRejectUrl = '{{ route("pos.reject", $pos->id) }}';
const posDeleteUrl = '{{ route("pos.destroy", $pos->id) }}';
const posIndexUrl = '{{ route("pos.index") }}';

function posMsg(xhr, fallback) {
    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback;
    if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
    return msg;
}

// Kirim aksi; tombol dikunci selama proses supaya tidak terkirim dua kali.
function posSend(url, method, payload, $btn, done) {
    if ($btn) $btn.prop('disabled', true);
    $.ajax({
        url: url, method: method, contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify(payload || {})
    }).done(function(res) {
        toastr.success(res.message || 'Done.');
        done(res);
    }).fail(function(xhr) {
        toastr.error(posMsg(xhr, 'Failed.'));
        if ($btn) $btn.prop('disabled', false);
    });
}

function posReload() { setTimeout(function() { location.reload(); }, 500); }

function posConfirm(options, onYes) {
    Swal.fire(Object.assign({ icon: 'question', showCancelButton: true, cancelButtonText: 'Cancel' }, options))
        .then(function(result) { if (result.isConfirmed) onYes(); });
}

function posSubmit() {
    posConfirm({
        title: 'Submit this POS for approval?',
        text: 'Once submitted, the POS cannot be edited unless the approver rejects it.',
        confirmButtonText: 'Yes, submit'
    }, function() { posSend(posSubmitUrl, 'POST', {}, $('#pos-submit-btn'), posReload); });
}

function posApprove() {
    posConfirm({
        title: 'Approve this POS?',
        text: 'An approved POS can no longer be edited, and its quotation is ready to become a project.',
        confirmButtonText: 'Yes, approve'
    }, function() { posSend(posApproveUrl, 'POST', {}, $('#pos-approve-btn'), posReload); });
}

function posOpenReject() {
    $('#pos-reject-reason').val('').removeClass('is-invalid');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('pos-reject-modal')).show();
    setTimeout(function() { $('#pos-reject-reason').trigger('focus'); }, 300);
}

function posReject() {
    const $reason = $('#pos-reject-reason');
    const reason = $reason.val().trim();
    if (!reason) { $reason.addClass('is-invalid').trigger('focus'); toastr.error('Rejection reason is required.'); return; }
    posSend(posRejectUrl, 'POST', { reason: reason }, $('#pos-reject-btn'), posReload);
}
$(document).on('input', '#pos-reject-reason', function() { $(this).removeClass('is-invalid'); });

function posDelete() {
    posConfirm({
        icon: 'warning',
        title: 'Delete POS ' + @json($pos->pos_number) + '?',
        text: 'The POS and its items are deleted permanently. This cannot be undone.',
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: '#dc2626'
    }, function() {
        posSend(posDeleteUrl, 'DELETE', null, null, function() { setTimeout(function() { window.location.href = posIndexUrl; }, 500); });
    });
}
</script>
@endsection

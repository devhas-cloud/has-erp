@extends('layouts.app')

@section('title', 'Detail POS')
@section('page-title', 'Detail POS')

@php
    $fmtMoney = fn ($v) => 'Rp '.\App\Support\Number::money($v);
    $fmtQty = fn ($v) => \App\Support\Number::qty($v);

    $isDraft = $pos->status === \App\Models\Pos::STATUS_DRAFT;
    $isRejected = $pos->status === \App\Models\Pos::STATUS_REJECTED;
    $isWaiting = $pos->status === \App\Models\Pos::STATUS_WAITING_APPROVAL;
    $isApproved = $pos->status === \App\Models\Pos::STATUS_APPROVED;

    $stepIndex = $isDraft ? 0 : ($isWaiting ? 1 : ($isApproved ? 2 : 1));
    $steps = ['Draft', 'Diajukan', 'Disetujui'];

    $state = match (true) {
        $isDraft => [
            'title' => 'Draft',
            'desc' => 'Belum diajukan. Periksa item dan nilai, lalu ajukan untuk persetujuan.',
            'icon' => 'fa-file-pen',
            'bg' => 'var(--info-soft)', 'color' => 'var(--info)',
        ],
        $isWaiting => [
            'title' => 'Menunggu persetujuan',
            'desc' => 'POS sudah diajukan dan sedang menunggu keputusan approver.',
            'icon' => 'fa-hourglass-half',
            'bg' => 'var(--warning-soft)', 'color' => 'var(--warning)',
        ],
        $isApproved => [
            'title' => 'Disetujui',
            'desc' => $pos->project
                ? 'POS disetujui dan sudah diteruskan menjadi project.'
                : 'POS disetujui. Langkah berikutnya: buat project dari quotation ini.',
            'icon' => 'fa-circle-check',
            'bg' => 'var(--success-soft)', 'color' => 'var(--success)',
        ],
        default => [
            'title' => 'Ditolak',
            'desc' => 'POS ditolak. Perbaiki item atau nilai, lalu ajukan ulang.',
            'icon' => 'fa-circle-xmark',
            'bg' => 'var(--danger-soft)', 'color' => 'var(--danger)',
        ],
    };
@endphp

@section('styles')
<style>
    /* ── Stepper alur dokumen ── */
    .pos-steps { display: flex; align-items: flex-start; gap: 0; }
    .pos-step { flex: 1; display: flex; align-items: flex-start; gap: 10px; position: relative; }
    .pos-step:not(:last-child)::after { content: ''; position: absolute; top: 14px; left: 38px; right: 10px; height: 2px; background: var(--card-border); }
    .pos-step.done:not(:last-child)::after { background: var(--accent); }
    .pos-dot { width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--card-border); background: var(--card); color: var(--text-muted); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex: none; }
    .pos-step.done .pos-dot { background: var(--accent); border-color: var(--accent); color: #fff; }
    .pos-step.current .pos-dot { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
    .pos-step.rejected .pos-dot { border-color: var(--danger); color: var(--danger); box-shadow: 0 0 0 4px var(--danger-soft); }
    .pos-step-label { font-size: 12.5px; line-height: 1.2; padding-top: 4px; color: var(--text-muted); }
    .pos-step.current .pos-step-label, .pos-step.rejected .pos-step-label { color: var(--text-primary); font-weight: 600; }
    .pos-step-label small { display: block; font-weight: 400; color: var(--text-muted); }
    @media (max-width: 767.98px) {
        .pos-steps { flex-direction: column; gap: 12px; }
        .pos-step:not(:last-child)::after { display: none; }
    }

    /* ── Tabel item ── */
    .pos-items th, .pos-items td { font-size: 12.5px; vertical-align: middle; }
    .pos-items td.pos-desc { min-width: 220px; }
    .pos-num { font-variant-numeric: tabular-nums; }

    /* ── Ledger nilai ── */
    .pos-ledger { font-size: 13px; }
    .pos-ledger .lrow { display: flex; align-items: baseline; gap: 8px; padding: 4px 0; }
    .pos-ledger .lbl { color: var(--text-muted); }
    .pos-ledger .dots { flex: 1; border-bottom: 1px dotted var(--card-border); min-width: 24px; }
    .pos-ledger .val { font-variant-numeric: tabular-nums; text-align: right; }
    .pos-grand-panel {
        display: flex; justify-content: space-between; align-items: center; gap: 12px;
        background: var(--accent-soft); border: 1px solid rgba(16, 185, 129, 0.18);
        border-radius: var(--radius-sm); padding: 12px 16px; margin-top: 10px;
    }
    .pos-grand-panel .t { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--accent-hover); }
    .pos-grand-panel .v { font-size: 21px; font-weight: 800; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.1; }

    /* ── Kartu status & info ── */
    .pos-status-head { display: flex; gap: 12px; align-items: flex-start; }
    .pos-status-icon { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex: none; }
    .pos-status-title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
    .pos-status-desc { font-size: 12.5px; color: var(--text-muted); line-height: 1.55; margin-top: 2px; }
    .pos-status-actions { display: grid; gap: 8px; margin-top: 14px; }
    .pos-reason { background: var(--danger-soft); border-left: 3px solid var(--danger); border-radius: 6px; padding: 8px 10px; font-size: 12.5px; color: var(--text-secondary); margin-top: 12px; }

    .pos-info .irow { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--card-border); font-size: 13px; }
    .pos-info .irow:last-child { border-bottom: 0; padding-bottom: 0; }
    .pos-info .k { color: var(--text-muted); flex: none; }
    .pos-info .v { text-align: right; color: var(--text-primary); min-width: 0; overflow-wrap: anywhere; }

    .pos-sticky { position: sticky; top: 1rem; }
    .pos-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--text-secondary); background: var(--card); border: 1px solid var(--card-border); border-radius: 999px; padding: 4px 12px; }
    .pos-chip i { color: var(--accent); font-size: 11px; }
    .pos-chip a { color: var(--text-primary); font-weight: 600; text-decoration: none; }
    .pos-chip a:hover { color: var(--accent-hover); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $pos->pos_number }}</h1>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <span class="pos-chip"><i class="fa-regular fa-calendar"></i>{{ $pos->date?->format('d/m/Y') }}</span>
            <span class="pos-chip"><i class="fa-regular fa-building"></i>{{ $pos->company?->account_name ?? '—' }}</span>
            @if($pos->quotation)
                <span class="pos-chip"><i class="fa-solid fa-file-invoice"></i><a href="{{ route('quotation.show', $pos->quotation_id) }}">{{ $pos->quotation->quotation_number }}</a></span>
            @endif
            @if($pos->project)
                <span class="pos-chip"><i class="fa-solid fa-diagram-project"></i><a href="{{ route('project.show', $pos->project_id) }}">{{ $pos->project->project_number }}</a></span>
            @endif
            {!! $pos->statusBadgeHtml() !!}
        </div>
    </div>
    <div class="page-header-actions d-flex gap-2 flex-wrap">
        <a href="{{ route('pos.index') }}" class="btn btn-secondary btn-sm">Kembali</a>
        @if($isDraft || $isRejected)
            <a href="{{ route('pos.edit', $pos->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-pen me-1"></i>Edit</a>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="posDelete()"><i class="fa fa-trash me-1"></i>Hapus</button>
        @endif
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom py-3">
        <div class="pos-steps">
            @foreach($steps as $n => $label)
                @php
                    $done = $isApproved || $n < $stepIndex;
                    $current = ! $isApproved && $n === $stepIndex;
                @endphp
                <div class="pos-step {{ $done ? 'done' : '' }} {{ $current ? ($isRejected ? 'rejected' : 'current') : '' }}">
                    <div class="pos-dot">
                        @if($done)
                            <i class="fa fa-check"></i>
                        @elseif($current && $isRejected)
                            <i class="fa fa-xmark"></i>
                        @else
                            {{ $n + 1 }}
                        @endif
                    </div>
                    <div class="pos-step-label">
                        {{ $label }}
                        @if($n === 1 && $isRejected)<small>Ditolak</small>@endif
                        @if($n === 1 && $isWaiting)<small>Menunggu persetujuan</small>@endif
                        @if($n === 2 && $isApproved)<small>{{ $pos->approved_at?->format('d/m/Y') }}</small>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8 order-2 order-lg-1">
        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item POS <span style="color:var(--text-muted);font-weight:400">({{ $pos->items->count() }})</span></span></div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 pos-items">
                        <thead>
                            <tr>
                                <th style="width:34px" class="text-center">#</th>
                                <th>Deskripsi (penamaan customer)</th>
                                <th class="text-end">Qty</th>
                                <th>Unit</th>
                                <th class="text-end">Harga</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pos->items as $i => $item)
                                <tr>
                                    <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                                    <td class="pos-desc">{!! \App\Models\Quotation::renderDescription($item->description) ?: '—' !!}</td>
                                    <td class="text-end pos-num">{{ $fmtQty($item->qty) }}</td>
                                    <td>{{ $item->unit ?? '—' }}</td>
                                    <td class="text-end pos-num">{{ $fmtMoney($item->unit_price) }}</td>
                                    <td class="text-end pos-num">{{ $fmtMoney($item->subtotal) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-3" style="color:var(--text-muted)">Belum ada item.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-2 pb-2 pt-1">
                    <div class="pos-ledger">
                        <div class="lrow"><span class="lbl">Subtotal</span><span class="dots"></span><span class="val">{{ $fmtMoney($pos->subtotal) }}</span></div>
                        @if((float) $pos->discount_amount > 0)
                            <div class="lrow"><span class="lbl">Diskon @if($pos->discount_percent !== null) ({{ \App\Support\Number::percent($pos->discount_percent) }}%) @endif</span><span class="dots"></span><span class="val" style="color:var(--danger)">-{{ $fmtMoney($pos->discount_amount) }}</span></div>
                        @endif
                        @if((float) $pos->ppn_amount > 0 || $pos->ppn_percent !== null)
                            <div class="lrow"><span class="lbl">PPN @if($pos->ppn_percent !== null) ({{ \App\Support\Number::percent($pos->ppn_percent) }}%) @endif</span><span class="dots"></span><span class="val">{{ $fmtMoney($pos->ppn_amount) }}</span></div>
                        @endif
                    </div>
                    <div class="pos-grand-panel">
                        <span class="t">Grand Total</span>
                        <span class="v">{{ $fmtMoney($pos->grand_total) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 order-1 order-lg-2">
        <div class="pos-sticky">
            <div class="card-custom fade-in mb-3">
                <div class="card-body-custom">
                    <div class="pos-status-head">
                        <div class="pos-status-icon" style="background:{{ $state['bg'] }};color:{{ $state['color'] }}"><i class="fa-solid {{ $state['icon'] }}"></i></div>
                        <div>
                            <div class="pos-status-title">{{ $state['title'] }}</div>
                            <div class="pos-status-desc">{{ $state['desc'] }}</div>
                        </div>
                    </div>

                    @if($isRejected && $pos->reject_reason)
                        <div class="pos-reason"><strong>Alasan:</strong> {{ $pos->reject_reason }}</div>
                    @endif

                    @if(($isDraft || $isRejected) && $canSubmit)
                        <div class="pos-status-actions">
                            <button type="button" class="btn btn-primary btn-sm" onclick="posSubmit()"><i class="fa fa-paper-plane me-1"></i>{{ $isRejected ? 'Ajukan ulang' : 'Ajukan untuk persetujuan' }}</button>
                        </div>
                    @elseif($isWaiting && $canDecide)
                        <div class="pos-status-actions">
                            <button type="button" class="btn btn-primary btn-sm" onclick="posApprove()"><i class="fa fa-check me-1"></i>Setujui</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="posOpenReject()"><i class="fa fa-xmark me-1"></i>Tolak</button>
                        </div>
                    @elseif($isApproved && $canRunProject)
                        <div class="pos-status-actions">
                            <a href="{{ route('project.create', ['quotation_id' => $pos->quotation_id]) }}" class="btn btn-accent btn-sm"><i class="fa fa-diagram-project me-1"></i>Buat Project</a>
                        </div>
                    @elseif($isApproved && $pos->project)
                        <div class="pos-status-actions">
                            <a href="{{ route('project.show', $pos->project_id) }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-diagram-project me-1"></i>Lihat Project</a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card-custom fade-in">
                <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
                <div class="card-body-custom">
                    <div class="pos-info">
                        <div class="irow"><span class="k">Customer</span><span class="v">{{ $pos->company?->account_name ?? '—' }}</span></div>
                        <div class="irow">
                            <span class="k">Pembayaran</span>
                            <span class="v">
                                @if($pos->requires_dp)
                                    <span class="status-badge" style="background:#fef3c7;color:#92400e;">Perlu DP</span>
                                @else
                                    <span class="status-badge" style="background:#dcfce7;color:#166534;">Tanpa DP</span>
                                @endif
                            </span>
                        </div>
                        <div class="irow"><span class="k">Dibuat</span><span class="v">{{ $pos->creator?->display_name ?? '—' }}@if($pos->created_at) <span style="color:var(--text-muted)">&middot; {{ $pos->created_at->format('d/m/Y H:i') }}</span>@endif</span></div>
                        @if($pos->approver)
                            <div class="irow"><span class="k">Disetujui</span><span class="v">{{ $pos->approver->display_name }} <span style="color:var(--text-muted)">&middot; {{ $pos->approved_at?->format('d/m/Y H:i') }}</span></span></div>
                        @endif
                        <div class="irow"><span class="k">Quotation</span><span class="v">@if($pos->quotation)<a href="{{ route('quotation.show', $pos->quotation_id) }}">{{ $pos->quotation->quotation_number }}</a>@else — @endif</span></div>
                        <div class="irow"><span class="k">Project</span><span class="v">@if($pos->project)<a href="{{ route('project.show', $pos->project_id) }}">{{ $pos->project->project_number }}</a>@else — @endif</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pos-reject-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tolak POS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="pos-reject-reason">Alasan penolakan <span class="text-danger">*</span></label>
                <textarea class="form-control" id="pos-reject-reason" rows="3" placeholder="Alasan…"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="posReject()">Tolak</button>
            </div>
        </div>
    </div>
</div>
@endsection

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

function posPost(url, payload, done) {
    $.ajax({
        url: url, method: 'POST', contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        data: JSON.stringify(payload || {})
    }).done(function(res) {
        toastr.success(res.message || 'Berhasil.');
        if (done) done(res);
    }).fail(function(xhr) {
        toastr.error(posMsg(xhr, 'Gagal.'));
    });
}

function posSubmit() {
    posPost(posSubmitUrl, {}, function() { setTimeout(function() { location.reload(); }, 500); });
}

function posApprove() {
    if (!confirm('Setujui POS ini?')) return;
    posPost(posApproveUrl, {}, function() { setTimeout(function() { location.reload(); }, 500); });
}

function posOpenReject() {
    $('#pos-reject-modal').modal('show');
}

function posReject() {
    const reason = $('#pos-reject-reason').val().trim();
    if (!reason) { toastr.error('Alasan penolakan wajib diisi.'); return; }
    posPost(posRejectUrl, { reason: reason }, function() { setTimeout(function() { location.reload(); }, 500); });
}

function posDelete() {
    if (!confirm('Hapus POS ini? Tindakan ini tidak bisa dibatalkan.')) return;
    $.ajax({
        url: posDeleteUrl, method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function(res) {
        toastr.success(res.message || 'Dihapus.');
        setTimeout(function() { window.location.href = posIndexUrl; }, 500);
    }).fail(function(xhr) {
        toastr.error(posMsg(xhr, 'Gagal menghapus.'));
    });
}
</script>
@endsection

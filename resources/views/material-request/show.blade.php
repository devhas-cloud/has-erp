@extends('layouts.app')

@section('title', 'Detail Material Request')
@section('page-title', 'Detail Material Request')

@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $requested = $mr->items->sum('qty');
    $handed = $mr->items->sum(fn ($item) => $item->handedQty());
    $progress = $requested > 0 ? (int) floor(min(1, $handed / $requested) * 100) : 0;
@endphp

@section('styles')
<style>
    .mr-stat { border-radius: var(--radius-sm); padding: 12px 14px; background: var(--accent-subtle); border: 1px solid var(--card-border); }
    .mr-stat .label { font-size: 11.5px; color: var(--text-muted); }
    .mr-stat .value { font-size: 20px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.2; }
    .mr-sn { display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; padding: 1px 6px; margin: 2px 2px 0 0; border-radius: 5px; background: #f1f5f9; color: var(--text-secondary); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $mr->request_number }}</h1>
        <p class="page-header-sub">{{ $mr->project?->project_number }} — {{ $mr->project?->project_name }} &middot; {{ $mr->branch?->name }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2 flex-wrap">
        <a href="{{ route('material-request.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
        @if($canManage && $canUpdate)
            <a href="{{ route('material-request.edit', $mr->id) }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-pen me-1"></i> Ubah</a>
        @endif
        @if($canManage && $canDelete)
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="mrDelete()"><i class="fa fa-trash me-1"></i> Hapus</button>
        @endif
        @if($canDecide)
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="mrReject()"><i class="fa fa-xmark me-1"></i> Tolak</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="mrApprove()"><i class="fa fa-check me-1"></i> Setujui</button>
        @endif
        @if($canClose && $canUpdate)
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="mrClose()"><i class="fa fa-lock me-1"></i> Tutup Sisa</button>
        @endif
        @if($canHandover && $mr->remainingQty() > 0)
            <a href="{{ route('material-handover.create', ['material_request_id' => $mr->id]) }}" class="btn btn-primary btn-sm"><i class="fa fa-people-carry-box me-1"></i> Serah Terima</a>
        @endif
    </div>
</div>

@if($mr->status === \App\Models\MaterialRequest::STATUS_REJECTED)
<div class="alert alert-danger py-2" style="font-size:13px">
    <i class="fa fa-circle-xmark me-1"></i>Ditolak oleh {{ $mr->rejecter?->display_name ?? '—' }} ({{ $mr->rejected_at?->format('d/m/Y H:i') }}): {{ $mr->reject_reason }}
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span>
                {!! $mr->statusBadgeHtml() !!}
            </div>
            <div class="card-body-custom">
                <table class="info-table" style="width:100%">
                    <tr><td>Project</td><td>{{ $mr->project?->project_number }} — {{ $mr->project?->project_name }}</td></tr>
                    <tr><td>Branch</td><td>{{ $mr->branch?->name ?? '—' }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $mr->request_date?->format('d/m/Y') }}</td></tr>
                    <tr><td>Dibuat</td><td>{{ $mr->creator?->display_name ?? '—' }} <span style="color:var(--text-muted)">&middot; {{ $mr->created_at?->format('d/m/Y H:i') }}</span></td></tr>
                    @if($mr->approver)
                    <tr><td>Disetujui</td><td>{{ $mr->approver->display_name }} <span style="color:var(--text-muted)">&middot; {{ $mr->approved_at?->format('d/m/Y H:i') }}</span></td></tr>
                    @endif
                    @if($mr->closer)
                    <tr><td>Ditutup</td><td>{{ $mr->closer->display_name }} <span style="color:var(--text-muted)">&middot; {{ $mr->closed_at?->format('d/m/Y H:i') }}</span></td></tr>
                    @endif
                    @if($mr->note)
                    <tr><td>Catatan</td><td>{{ $mr->note }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-chart-simple me-2" style="color:var(--accent)"></i>Ringkasan</span></div>
            <div class="card-body-custom">
                <div class="row g-2">
                    <div class="col-6"><div class="mr-stat"><div class="label">Material</div><div class="value">{{ $mr->items->count() }}</div></div></div>
                    <div class="col-6"><div class="mr-stat"><div class="label">Serah Terima</div><div class="value">{{ $mr->handovers->count() }}</div></div></div>
                </div>
                <div class="mt-3" style="font-size:12.5px;color:var(--text-muted)">Diserahkan</div>
                <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" style="width:{{ $progress }}%;background:var(--accent)"></div></div>
                    <span class="fw-semibold" style="font-size:13px">{{ $progress }}%</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Material Diminta</span></div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px" class="text-center">#</th>
                        <th>Produk</th>
                        <th class="text-end" style="width:120px">Diminta</th>
                        <th class="text-end" style="width:120px">Diserahkan</th>
                        <th class="text-end" style="width:120px">Sisa</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mr->items as $i => $item)
                        @php $remaining = $item->remainingQty(); @endphp
                        <tr>
                            <td class="text-center" style="color:var(--text-muted)">{{ $i + 1 }}</td>
                            <td>
                                <div class="fw-semibold" style="color:var(--text-primary)">{{ $item->masterProduct?->name ?: '—' }}</div>
                                <div style="font-size:11.5px;color:var(--text-muted)">{{ $item->masterProduct?->code }}{{ $item->note ? ' · '.$item->note : '' }}</div>
                            </td>
                            <td class="text-end">{{ $fmtQty($item->qty) }} <span style="color:var(--text-muted);font-size:12px">{{ $item->unit }}</span></td>
                            <td class="text-end">{{ $fmtQty($item->handedQty()) }}</td>
                            <td class="text-end">
                                @if($remaining > 0)
                                    <strong>{{ $fmtQty($remaining) }}</strong>
                                @else
                                    <i class="fa fa-circle-check" style="color:var(--accent)" title="Lengkap"></i>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-people-carry-box me-2" style="color:var(--accent)"></i>Riwayat Serah Terima</span></div>
            <div class="card-body-custom p-2">
                @forelse($mr->handovers as $handover)
                    <a href="{{ route('material-handover.show', $handover->id) }}" class="d-flex justify-content-between align-items-center px-2 py-2 text-decoration-none" style="border-bottom:1px solid var(--card-border)">
                        <span>
                            <strong style="color:var(--text-primary)">{{ $handover->handover_number }}</strong>
                            <span style="font-size:12px;color:var(--text-muted)">&middot; {{ $handover->handover_date?->format('d/m/Y') }} &middot; {{ $handover->creator?->display_name }}</span>
                        </span>
                        <span style="font-size:12px;color:var(--text-muted)">{{ $handover->items->count() }} baris</span>
                    </a>
                @empty
                    <div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Belum ada serah terima.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom"><span><i class="fa-solid fa-industry me-2" style="color:var(--accent)"></i>Material di Produksi</span></div>
            <div class="card-body-custom p-2">
                <div class="px-2 pb-2" style="font-size:12px;color:var(--text-muted)">Sisa material project ini di {{ $mr->branch?->name }} (semua MR) yang belum dipakai produksi.</div>
                @forelse($pool as $row)
                    <div class="d-flex justify-content-between align-items-start px-2 py-1" style="border-bottom:1px solid var(--card-border);font-size:12.5px">
                        <span>
                            <span class="fw-semibold">{{ $row->stock?->masterProduct?->code }}</span>
                            <span style="color:var(--text-muted)">{{ $row->stock?->masterProduct?->name }}</span>
                            @if($row->stock?->serial_number)<span class="mr-sn">{{ $row->stock->serial_number }}</span>@endif
                        </span>
                        <span class="text-nowrap">{{ $fmtQty($row->remainingQty()) }} {{ $row->stock?->unit }}</span>
                    </div>
                @empty
                    <div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Tidak ada sisa material.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function mrPost(url, method, data, okMessage, redirect) {
    return $.ajax({ url: url, method: method, data: data || {}, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .done(function(res) {
            toastr.success(res.message || okMessage);
            setTimeout(function() { window.location.href = redirect || window.location.href; }, 600);
        })
        .fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memproses.';
            if (xhr.responseJSON && xhr.responseJSON.errors) msg = xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0];
            toastr.error(msg);
        });
}

function mrApprove() {
    Swal.fire({ title: 'Setujui Material Request?', text: 'Gudang bisa langsung menyerahkan material.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Setujui', cancelButtonText: 'Batal' })
        .then(function(r) { if (r.isConfirmed) mrPost('{{ route("material-request.approve", $mr->id) }}', 'POST', {}, 'Disetujui.'); });
}

function mrReject() {
    Swal.fire({ title: 'Tolak Material Request?', input: 'text', inputLabel: 'Alasan penolakan', inputAttributes: { maxlength: 255 }, icon: 'warning', showCancelButton: true, confirmButtonText: 'Tolak', cancelButtonText: 'Batal',
        inputValidator: function(v) { if (!v) return 'Alasan wajib diisi.'; } })
        .then(function(r) { if (r.isConfirmed) mrPost('{{ route("material-request.reject", $mr->id) }}', 'POST', { reason: r.value }, 'Ditolak.'); });
}

function mrClose() {
    Swal.fire({ title: 'Tutup sisa permintaan?', text: 'Material yang belum diserahkan tidak akan diserahkan lagi.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Tutup', cancelButtonText: 'Batal' })
        .then(function(r) { if (r.isConfirmed) mrPost('{{ route("material-request.close", $mr->id) }}', 'PATCH', {}, 'Ditutup.'); });
}

function mrDelete() {
    Swal.fire({ title: 'Hapus Material Request?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function(r) { if (r.isConfirmed) mrPost('{{ route("material-request.destroy", $mr->id) }}', 'DELETE', {}, 'Dihapus.', '{{ route("material-request.index") }}'); });
}
</script>
@endsection

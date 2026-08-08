@extends('layouts.app')

@section('title', 'Detail Configuration #'.$quotation->id)
@section('page-title', 'Detail Configuration')

@section('styles')
<style>
    .info-table td { padding: 7px 0; vertical-align: top; line-height: 1.45; }
    .info-table td:first-child {
        color: var(--text-muted);
        width: 150px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        padding-right: 12px;
    }
    .info-table td:last-child {
        font-size: 13px;
        color: var(--text-primary);
        word-break: break-word;
    }
    .info-table tr + tr td { border-top: 1px solid var(--card-border); }

    .flow-steps { display: flex; align-items: center; gap: 0; flex-wrap: wrap; }
    .flow-step {
        display: flex; align-items: center; gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 12px; font-weight: 600;
        background: var(--bg); color: var(--text-muted);
        border: 1px solid var(--card-border);
    }
    .flow-step.active { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
    .flow-step.done { background: var(--accent); color: #fff; border-color: var(--accent); }
    .flow-step.rejected { background: var(--danger-soft); color: #7f1d1d; border-color: #fecaca; }
    .flow-arrow { color: var(--text-muted); font-size: 12px; margin: 0 6px; }

    .cat-title {
        font-size: 13px; font-weight: 700; color: var(--accent);
        text-transform: uppercase; letter-spacing: .5px;
        padding: 12px 16px 8px; margin: 0;
    }
    .wc-action-bar {
        display: flex; gap: 8px; flex-wrap: wrap;
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 14px 16px;
        margin-bottom: 20px;
        align-items: center;
    }
    .wc-action-bar .spacer { flex: 1; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Configuration #{{ $quotation->id }}
            <small style="font-size:14px;font-weight:400;color:var(--text-muted)">— {{ $quotation->task?->title ?? '—' }}</small>
        </h1>
        <p class="page-header-sub">Quote Configuration dari Task Quote #{{ $quotation->task_id }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('water-configuration.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
        <a href="{{ route('water-configuration.pdf', $quotation->id) }}" target="_blank" class="btn-accent">
            <i class="fa fa-file-pdf me-1"></i> <span>View PDF</span>
        </a>
    </div>
</div>

{{-- Workflow --}}
<div class="wc-action-bar">
    <div class="flow-steps">
        <span class="flow-step {{ in_array($quotation->status, ['draft', 'waiting_approval', 'approved']) ? 'done' : '' }}">
            <i class="fa fa-file-pen"></i> Draft
        </span>
        <span class="flow-arrow"><i class="fa fa-chevron-right"></i></span>
        <span class="flow-step {{ $quotation->status === 'waiting_approval' ? 'active' : ($quotation->status === 'approved' || $quotation->status === 'rejected' ? 'done' : '') }}">
            <i class="fa fa-paper-plane"></i> Waiting Approval
        </span>
        <span class="flow-arrow"><i class="fa fa-chevron-right"></i></span>
        @if($quotation->status === 'rejected')
            <span class="flow-step rejected">
                <i class="fa fa-xmark"></i> Rejected
            </span>
        @else
            <span class="flow-step {{ $quotation->status === 'approved' ? 'done' : '' }}">
                <i class="fa fa-check"></i> Approved
            </span>
        @endif
    </div>
    <div class="spacer"></div>
    <span>{!! $quotation->statusBadgeHtml() !!}</span>
</div>

{{-- Actions --}}
@if($quotation->status === 'draft' && auth()->id() === $quotation->created_by)
<div class="wc-action-bar justify-content-end">
    <a href="{{ route('water-configuration.edit', $quotation->id) }}" class="btn btn-primary btn-sm">
        <i class="fa fa-pen me-1"></i> Edit
    </a>
    <button type="button" class="btn btn-primary btn-sm" onclick="submitWc({{ $quotation->id }})">
        <i class="fa fa-paper-plane me-1"></i> Submit Approval
    </button>
</div>
@endif

@if($quotation->status === 'waiting_approval')
<div class="wc-action-bar">
    @if($canApprove && $isSameDivisionApprover)
        <button type="button" class="btn btn-success btn-sm" onclick="approveWc({{ $quotation->id }})">
            <i class="fa fa-check me-1"></i> Approve
        </button>
        <button type="button" class="btn btn-danger btn-sm" onclick="openRejectModal({{ $quotation->id }})">
            <i class="fa fa-xmark me-1"></i> Reject
        </button>
    @else
        <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:13px">
            <i class="fa fa-lock me-1"></i>
            Configuration menunggu approval dari user lain satu divisi dengan pembuat.
            Pembuat tidak bisa approve dokumennya sendiri.
        </div>
    @endif
    <div class="spacer"></div>
    <span class="text-muted" style="font-size:12px">Menunggu final check oleh user satu divisi dengan pembuat</span>
</div>
@endif

@if($quotation->status === 'rejected' && $quotation->approval_note)
<div class="wc-action-bar" style="border-color:#fecaca;background:#fef2f2;">
    <i class="fa fa-circle-exclamation" style="color:#b91c1c"></i>
    <div>
        <div style="font-weight:600;font-size:13px;color:#7f1d1d">Alasan Penolakan</div>
        <div style="font-size:13px;color:#7f1d1d">{{ $quotation->approval_note }}</div>
    </div>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-custom">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-building me-2" style="color:var(--accent)"></i>Informasi Customer</span>
            </div>
            <div class="card-body-custom">
                <table class="info-table">
                    <tr><td>Task</td><td>#{{ $quotation->task_id }} — {{ $quotation->task?->title ?? '—' }}</td></tr>
                    <tr><td>To</td><td>{{ $quotation->to_name ?? '—' }}</td></tr>
                    <tr><td>Address</td><td>{{ $quotation->address ?? '—' }}</td></tr>
                    <tr><td>Location / Company</td><td>{{ $quotation->location ?? '—' }}</td></tr>
                    <tr><td>PIC</td><td>{{ $quotation->pic_name ?? '—' }}</td></tr>
                    <tr><td>Phone</td><td>{{ $quotation->pic_phone ?? '—' }}</td></tr>
                    <tr><td>Email</td><td>{{ $quotation->pic_email ?? '—' }}</td></tr>
                    <tr><td>Sales (Pemberi Task)</td><td>{{ $quotation->sales_name ?? '—' }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $quotation->date?->format('d/m/Y') ?? '—' }}</td></tr>
                    <tr><td>Parameter</td><td>{{ $quotation->parameter_note ?? '—' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card-custom mt-4">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Approval</span>
            </div>
            <div class="card-body-custom">
                <table class="info-table">
                    <tr><td>Status</td><td>{!! $quotation->statusBadgeHtml() !!}</td></tr>
                    <tr><td>Created By</td><td>{{ $quotation->creator?->username ?? '—' }} ({{ $quotation->creator?->division?->division_name ?? '-' }})</td></tr>
                    <tr><td>Final Checked By</td><td>{{ $quotation->finalChecker?->username ?? '—' }}</td></tr>
                    <tr><td>Approved At</td><td>{{ $quotation->approved_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                    <tr><td>Rejected At</td><td>{{ $quotation->rejected_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                </table>
                <div class="alert alert-info py-2 px-3 mt-3 mb-0" style="font-size:12px">
                    <i class="fa fa-circle-info me-1"></i>
                    Aturan: user divisi WATER (pembuat) tidak bisa approve dokumennya sendiri. Approval hanya dapat
                    dilakukan oleh user lain yang satu divisi dengan pembuat (divisi WATER).
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-custom">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>List Part Instrument ({{ $quotation->items->count() }} item)</span>
            </div>
            <div class="card-body-custom p-2">
                @php $groups = $quotation->itemsGroupedByCategory(); $no = 1; @endphp
                @foreach($groups as $category => $items)
                    <div class="cat-title">{{ $category }}</div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:45px">No</th>
                                    <th style="width:170px">Part Number</th>
                                    <th>Deskripsi</th>
                                    <th style="width:60px" class="text-center">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td class="text-center">{{ $no++ }}</td>
                                        <td><code>{{ $item->part_number ?? '—' }}</code></td>
                                        <td>{{ $item->description }}</td>
                                        <td class="text-center">{{ $item->qty }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>

        @if($quotation->notes)
        <div class="card-custom mt-4">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-note-sticky me-2" style="color:var(--accent)"></i>Catatan</span>
            </div>
            <div class="card-body-custom">
                <p class="mb-0" style="white-space:pre-line;font-size:13px">{{ $quotation->notes }}</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Reject --}}
<div class="modal fade" id="wcRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tolak Quotation</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="wc-reject-note" class="form-control" rows="3" placeholder="Wajib diisi alasan penolakan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-reject-wc">
                    <i class="fa fa-xmark me-1"></i> Tolak
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let wcRejectModalInstance = null;
let wcRejectId = null;

const wcSubmitUrl = '{{ route("water-configuration.submit", "__ID__") }}';
const wcApproveUrl = '{{ route("water-configuration.approve", "__ID__") }}';
const wcRejectUrl = '{{ route("water-configuration.reject", "__ID__") }}';

function submitWc(id) {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Quotation akan dikirim untuk approval. Setelah di-submit tidak bisa diedit.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.post(wcSubmitUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation dikirim untuk approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function approveWc(id) {
    Swal.fire({
        title: 'Approve Quotation?',
        text: 'Anda yakin menyetujui quotation ini?',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.post(wcApproveUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function openRejectModal(id) {
    wcRejectId = id;
    $('#wc-reject-note').val('');
    if (!wcRejectModalInstance) {
        wcRejectModalInstance = new bootstrap.Modal(document.getElementById('wcRejectModal'));
    }
    wcRejectModalInstance.show();
}

$('#btn-reject-wc').on('click', function() {
    var note = $('#wc-reject-note').val().trim();
    if (!note) {
        toastr.error('Alasan penolakan wajib diisi.');
        return;
    }

    $('#btn-reject-wc').prop('disabled', true);

    $.ajax({
        url: wcRejectUrl.replace('__ID__', wcRejectId),
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', approval_note: note }
    }).done(function(res) {
        toastr.success(res.message || 'Quotation ditolak.');
        wcRejectModalInstance.hide();
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menolak.');
    }).always(function() {
        $('#btn-reject-wc').prop('disabled', false);
    });
});
</script>
@endsection

@extends('layouts.app')

@section('title', 'Detail Configuration '.$quotation->opportunity->opportunity_name)
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
@php
    $backTaskId = $back && str_starts_with($back, 'task-') ? str_replace('task-', '', $back) : null;
@endphp
<div class="page-header">
    <div>
        <h1 class="page-header-title">Configuration {{ $quotation->opportunity->opportunity_name }}
            <small style="font-size:14px;font-weight:400;color:var(--text-muted)">— {{ $quotation->task?->title ?? '—' }}</small>
        </h1>
        <p class="page-header-sub">IMS Configuration dari Task Quote #{{ $quotation->task_id }}</p>
    </div>
    <div class="page-header-actions">
        @if ($backTaskId)
            <a href="{{ route('task-planner.show', $backTaskId) }}" class="btn btn-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i> Kembali ke Task
            </a>
        @else
            <a href="{{ route('ims-configuration.index') }}" class="btn btn-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i> Kembali
            </a>
        @endif
        <a href="{{ route('ims-configuration.pdf', $quotation->id) }}" target="_blank" class="btn-accent">
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
    <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11px">Versi {{ $quotation->version }}</span>
    <button type="button" class="btn btn-sm btn-soft" style="font-size:12px" onclick="openTrackModal()">
        <i class="fa fa-clock-rotate-left me-1"></i> Riwayat
    </button>
    <span>{!! $quotation->statusBadgeHtml() !!}</span>
    @if($quotation->status === 'approved' && $quotation->isLocked())
        <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11px"><i class="fa fa-lock me-1"></i>Terkunci</span>
    @elseif($quotation->status === 'approved')
        <span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px"><i class="fa fa-lock-open me-1"></i>Unlocked</span>
    @endif
</div>

{{-- Actions --}}
@if($quotation->status === 'draft' && auth()->id() === $quotation->created_by)
<div class="wc-action-bar justify-content-end">
    <a href="{{ route('ims-configuration.edit', $quotation->id) }}" class="btn btn-primary btn-sm">
        <i class="fa fa-pen me-1"></i> Edit
    </a>
    <button type="button" class="btn btn-primary btn-sm" onclick="submitWc({{ $quotation->id }})">
        <i class="fa fa-paper-plane me-1"></i> Submit Approval
    </button>
</div>
@endif

@if($quotation->status === 'rejected' && auth()->id() === $quotation->created_by)
<div class="wc-action-bar justify-content-end">
    <button type="button" class="btn btn-primary btn-sm" onclick="reviseWc({{ $quotation->id }})">
        <i class="fa fa-copy me-1"></i> Buat Revisi
    </button>
</div>
@endif

@if($quotation->status === 'approved')
    @if($quotation->isLocked() && $canApprove && $isSameDivisionApprover)
    <div class="wc-action-bar justify-content-end">
        <button type="button" class="btn btn-warning btn-sm" onclick="unlockWc({{ $quotation->id }})">
            <i class="fa fa-lock-open me-1"></i> Buka Kunci
        </button>
    </div>
    @elseif(! $quotation->isLocked() && auth()->id() === $quotation->created_by)
    <div class="wc-action-bar justify-content-end">
        <button type="button" class="btn btn-primary btn-sm" onclick="reviseWc({{ $quotation->id }})">
            <i class="fa fa-copy me-1"></i> Buat Revisi
        </button>
    </div>
    @endif
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
        @if(auth()->id() === $quotation->created_by)
        <div style="font-size:12px;color:#92400e;margin-top:2px">
            <i class="fa fa-info-circle me-1"></i>Silakan revisi untuk di-submit ulang.
        </div>
        @endif
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
                    <tr><td>To</td><td>{{ $quotation->location ?? '—' }}</td></tr>
                    <tr><td>Address</td><td>{{ $quotation->address ?? '—' }}</td></tr>
                    <tr><td>PIC</td><td>{{ $quotation->pic_name ?? '—' }}</td></tr>
                    <tr><td>Phone</td><td>{{ $quotation->pic_phone ?? '—' }}</td></tr>
                    <tr><td>Email</td><td>{{ $quotation->pic_email ?? '—' }}</td></tr>
                    <tr><td>Sales (Pemberi Task)</td><td>{{ $quotation->sales_name ?? '—' }}</td></tr>
                    <tr><td>Tanggal</td><td>{{ $quotation->date?->format('d/m/Y') ?? '—' }}</td></tr>
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
                    Aturan: user divisi IMS (pembuat) tidak bisa approve dokumennya sendiri. Approval hanya dapat
                    dilakukan oleh user lain yang satu divisi dengan pembuat (divisi IMS).
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-custom">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>List Part Instrument</span>
            </div>
            <div class="card-body-custom p-2">
                @php
                    $all = $quotation->items->keyBy('id');
                    $children = $all->groupBy(fn ($i) => $i->parent_id ?: '_root');

                    // Kelompokkan root (parent) berdasarkan kategori, urut sesuai kemunculan pertama.
                    $groupMap = [];
                    $groupOrder = [];
                    $parents = $children['_root'] ?? collect();
                    foreach ($parents as $p) {
                        $cat = $p->category ?: 'Lainnya';
                        if (! isset($groupMap[$cat])) {
                            $groupMap[$cat] = [];
                            $groupOrder[] = $cat;
                        }
                        $groupMap[$cat][] = $p;
                    }

                    $groups = [];
                    foreach ($groupOrder as $category) {
                        $catRows = [];
                        $catSubtotal = 0;
                        $walk = function ($parentId, $depth) use (&$walk, &$catRows, &$catSubtotal, $children) {
                            foreach ($children[$parentId] ?? [] as $item) {
                                $catRows[] = ['item' => $item, 'depth' => $depth];
                                $catSubtotal += (($item->price ?? $item->product?->price) ?? 0) * $item->qty;
                                $walk($item->id, $depth + 1);
                            }
                        };
                        foreach ($groupMap[$category] as $root) {
                            $kids = $children[$root->id] ?? collect();
                            if ($kids->isEmpty()) {
                                // Root tanpa children -> dirender sbg baris data.
                                $catRows[] = ['item' => $root, 'depth' => 0];
                                $catSubtotal += (($root->price ?? $root->product?->price) ?? 0) * $root->qty;
                            } else {
                                // Root = judul kategori; children mulai kedalaman 1.
                                $walk($root->id, 1);
                            }
                        }
                        $groups[] = ['category' => $category, 'rows' => $catRows, 'subtotal' => $catSubtotal];
                    }

                    $totalPrice = $quotation->items->sum(fn ($item) => (($item->price ?? $item->product?->price) ?? 0) * $item->qty);
                @endphp

                @forelse($groups as $group)
                    <div class="cat-title">{{ $group['category'] }}</div>
                    <div class="table-responsive mb-4">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:55px">No</th>
                                    <th>Produk</th>
                                    <th>Deskripsi</th>
                                    <th style="width:80px" class="text-center">Qty</th>
                                    <th style="width:150px" class="text-end">Harga</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($group['rows'] as $row)
                                    @php
                                        $item = $row['item'];
                                        $depth = $row['depth'];
                                        $price = $item->price ?? $item->product?->price;
                                    @endphp
                                    <tr>
                                        <td class="text-center" style="padding-left:{{ 6 + $depth * 20 }}px">{{ $item->item_no }}</td>
                                        <td>
                                            <div style="margin-left:{{ $depth * 20 }}px">
                                                <strong>{{ $item->product?->name ?? ($item->part_number ?: '—') }}</strong>
                                                @if($item->part_number)
                                                    <div style="font-size:11px;color:var(--text-muted)">{{ $item->part_number }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div style="margin-left:{{ $depth * 20 }}px">
                                                {!! \App\Models\Quotation::renderDescription($item->description) !!}
                                            </div>
                                        </td>
                                        <td class="text-center">{{ $item->qty }} &ensp; {{ $item->unit ?: '' }}</td>
                                        <td class="text-end">{{ $price ? 'Rp '.number_format($price, 0, '.', ',') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center" style="color:var(--text-muted);padding:16px">Tidak ada item.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Sub Total</strong></td>
                                    <td class="text-end"><strong> Rp {{ number_format($group['subtotal'], 0, '.', ',') }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @empty
                    <div class="text-center" style="color:var(--text-muted);padding:24px">Belum ada item.</div>
                @endforelse

                @if($groups)
                <div class="d-flex justify-content-end mt-2">
                    <table class="table table-custom align-middle mb-0" style="max-width:340px">
                        <tr>
                            <td class="text-end"><strong>Total Keseluruhan</strong></td>
                            <td class="text-end fw-bold" style="color:var(--accent)">Rp {{ number_format($totalPrice, 0, '.', ',') }}</td>
                        </tr>
                    </table>
                </div>
                @endif
            </div>
        </div>

        @if($quotation->notes)
        <div class="card-custom mt-4">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-note-sticky me-2" style="color:var(--accent)"></i>Catatan</span>
            </div>
            <div class="card-body-custom">
                <p class="mb-0" style="white-space:pre-line;font-size:13px">{!! nl2br(e($quotation->notes)) !!}</p>
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

{{-- Modal Track / Riwayat Versi --}}
<div class="modal fade" id="wcTrackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--accent)"></i>Riwayat Versi</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Versi</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Item</th>
                                <th>Dibuat Oleh</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="wc-track-body">
                            <tr>
                                <td colspan="6" class="config-card-empty">
                                    <span class="config-spinner"></span>Memuat...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let wcRejectModalInstance = null;
let wcRejectId = null;
let wcTrackModalInstance = null;

const wcSubmitUrl = '{{ route("ims-configuration.submit", "__ID__") }}';
const wcApproveUrl = '{{ route("ims-configuration.approve", "__ID__") }}';
const wcRejectUrl = '{{ route("ims-configuration.reject", "__ID__") }}';
const wcUnlockUrl = '{{ route("ims-configuration.unlock", "__ID__") }}';
const wcReviseUrl = '{{ route("ims-configuration.revise", "__ID__") }}';
const wcVersionsUrl = '{{ route("ims-configuration.versions", "__ID__") }}';
const wcEditUrl = '{{ route("ims-configuration.edit", "__ID__") }}';

function unlockWc(id) {
    Swal.fire({
        title: 'Buka Kunci?',
        text: 'Pembuat akan dapat membuat revisi baru dari configuration ini.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buka Kunci',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.post(wcUnlockUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Kunci dibuka.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membuka kunci.');
            });
    });
}

function reviseWc(id) {
    Swal.fire({
        title: 'Buat Revisi?',
        text: 'Header & detail akan disalin menjadi versi baru (Draft). Versi lama tetap sebagai riwayat.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat Revisi',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.post(wcReviseUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Revisi dibuat.');
                setTimeout(function() { window.location.href = wcEditUrl.replace('__ID__', res.id); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membuat revisi.');
            });
    });
}

function openTrackModal() {
    var id = {{ $quotation->id }};
    $('#wc-track-body').html('<tr><td colspan="6" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>');

    $.get(wcVersionsUrl.replace('__ID__', id), function(res) {
        var versions = res.versions || [];
        if (versions.length === 0) {
            $('#wc-track-body').html('<tr><td colspan="6" class="config-card-empty"><i class="fa-solid fa-inbox"></i>Belum ada riwayat versi.</td></tr>');
        } else {
            var html = '';
            versions.forEach(function(v) {
                var current = v.is_current ? ' <span class="badge" style="background:var(--accent);color:#fff;font-size:10px">AKTIF</span>' : '';
                html += '<tr>';
                html += '<td><strong>Versi ' + v.version + '</strong>' + current + '</td>';
                html += '<td>' + v.status_badge + '</td>';
                html += '<td>' + v.date + '</td>';
                html += '<td class="text-center">' + v.item_count + '</td>';
                html += '<td>' + v.creator_name + '</td>';
                html += '<td class="text-center"><a href="' + v.show_url + '" class="btn-icon" title="View"><i class="fa fa-eye"></i></a></td>';
                html += '</tr>';
            });
            $('#wc-track-body').html(html);
        }
    }).fail(function() {
        $('#wc-track-body').html('<tr><td colspan="6" class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat riwayat.</td></tr>');
    });

    if (!wcTrackModalInstance) {
        wcTrackModalInstance = new bootstrap.Modal(document.getElementById('wcTrackModal'));
    }
    wcTrackModalInstance.show();
}

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

$(document).on('click', '#btn-reject-wc', function() {
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

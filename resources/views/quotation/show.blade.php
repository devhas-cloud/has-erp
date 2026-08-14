@extends('layouts.app')

@section('title', 'Detail Quotation '.($quotation->opportunity->opportunity_name ?? ''))
@section('page-title', 'Detail Quotation')

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
    .cat-title {
        font-size: 13px; font-weight: 700; color: var(--accent);
        text-transform: uppercase; letter-spacing: .5px;
        padding: 12px 16px 8px; margin: 0;
    }
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
    .qt-action-bar {
        display: flex; gap: 8px; flex-wrap: wrap;
        background: #fff;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        padding: 14px 16px;
        margin-bottom: 20px;
        align-items: center;
    }
    .qt-action-bar .spacer { flex: 1; }
</style>
<style>
    .quotation-info-card {
        overflow: hidden;
    }

    .quotation-info-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .quotation-info-header > span {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
        min-width: 0;
    }

    /*
     * Desktop
     * 2 kolom item:
     * Label | Value
     * Label | Value
     */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 12px;
        overflow: hidden;
        background: var(--card-bg, #fff);
    }

    .info-item {
        display: grid;
        grid-template-columns: minmax(140px, 40%) minmax(0, 1fr);
        align-items: start;
        min-width: 0;
        border-bottom: 1px solid var(--border-color, #e5e7eb);
        background: var(--card-bg, #fff);
        transition:
            background-color .2s ease,
            box-shadow .2s ease;
    }

    .info-item:hover {
        background: color-mix(
            in srgb,
            var(--accent, #6366f1) 3%,
            var(--card-bg, #fff)
        );
    }

    .info-item:nth-last-child(-n+2) {
        border-bottom: 0;
    }

    .info-item:nth-child(odd) {
        border-right: 1px solid var(--border-color, #e5e7eb);
    }

    .info-item-full {
        grid-column: 1 / -1;
        grid-template-columns: 20% minmax(0, 1fr);
        border-right: 0 !important;
    }

    .info-label {
        padding: 13px 16px;
        color: var(--text-muted, #6b7280);
        font-size: 12px;
        font-weight: 600;
        line-height: 1.5;
        letter-spacing: .01em;
    }

    .info-value {
        min-width: 0;
        padding: 13px 16px;
        color: var(--text-color, #1f2937);
        font-size: 13px;
        line-height: 1.55;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .info-value strong {
        color: var(--accent, #6366f1);
        font-weight: 700;
    }

    .info-note {
        white-space: pre-line;
    }

    .revision-value {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .active-version-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 999px;
        background: var(--accent, #6366f1);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.3;
        letter-spacing: .03em;
    }

    /*
     * Tablet
     */
    @media (max-width: 991.98px) {
        .info-grid {
            grid-template-columns: 1fr;
        }

        .info-item,
        .info-item-full {
            grid-column: auto;
            grid-template-columns: minmax(150px, 32%) minmax(0, 1fr);
            border-right: 0 !important;
        }

        .info-item:nth-last-child(-n+2) {
            border-bottom: 1px solid var(--border-color, #e5e7eb);
        }

        .info-item:last-child {
            border-bottom: 0;
        }
    }

    /*
     * Mobile
     *
     * Tidak lagi terasa seperti tabel.
     * Setiap item menjadi mini-card.
     */
    @media (max-width: 575.98px) {
        .quotation-info-header {
            align-items: flex-start;
        }

        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
            border: 0;
            border-radius: 0;
            background: transparent;
            overflow: visible;
        }

        .info-item,
        .info-item-full {
            display: block;
            border: 1px solid var(--border-color, #e5e7eb) !important;
            border-radius: 10px;
            overflow: hidden;
            background: var(--card-bg, #fff);
        }

        .info-item:hover {
            box-shadow: 0 3px 12px rgba(0, 0, 0, .05);
        }

        .info-label {
            padding: 9px 12px 2px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text-muted, #8a8f98);
        }

        .info-value {
            padding: 2px 12px 10px;
            font-size: 13px;
        }

        .info-value strong {
            font-size: 14px;
        }

        .revision-value {
            padding-top: 3px;
        }
    }

    /*
     * Very small phones
     */
    @media (max-width: 375px) {
        .info-grid {
            gap: 6px;
        }

        .info-label {
            padding-left: 10px;
            padding-right: 10px;
        }

        .info-value {
            padding-left: 10px;
            padding-right: 10px;
            font-size: 12px;
        }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $quotation->quotation_number ?? 'Quotation #'.$quotation->id }}</h1>
        <p class="page-header-sub">
            Dari #{{ $quotation->opportunity->opportunity_name ?? '—' }} &middot; {{ $quotation->to_name ?? '—' }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('quotation.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
        <a href="{{ route('quotation.pdf', $quotation->id) }}" target="_blank" class="btn-accent">
            <i class="fa fa-file-pdf me-1"></i> <span>View PDF</span>
        </a>
    </div>
</div>

{{-- Workflow --}}
<div class="qt-action-bar">
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
    <button type="button" class="btn btn-sm btn-soft" style="font-size:12px" onclick="openQtTrack()">
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
@if($quotation->status === 'draft' && (auth()->id() === $quotation->created_by || auth()->user()->role === 'Admin'))
<div class="qt-action-bar justify-content-end">
    <a href="{{ route('quotation.edit', $quotation->id) }}" class="btn btn-secondary btn-sm">
        <i class="fa fa-pen me-1"></i> Edit
    </a>
    <button type="button" class="btn btn-primary btn-sm" onclick="submitQuotation()">
        <i class="fa fa-paper-plane me-1"></i> Submit Approval
    </button>
    <button type="button" class="btn btn-danger btn-sm" onclick="deleteQuotation()">
        <i class="fa fa-trash me-1"></i> Hapus
    </button>
</div>
@endif

@if($quotation->status === 'waiting_approval' && $canApprove)
<div class="qt-action-bar justify-content-end">
    <button type="button" class="btn btn-success btn-sm" onclick="approveQuotation()">
        <i class="fa fa-check me-1"></i> Approve
    </button>
    <button type="button" class="btn btn-danger btn-sm" onclick="openQtReject()">
        <i class="fa fa-xmark me-1"></i> Reject
    </button>
</div>
@endif

@if($quotation->status === 'approved')
    @if($quotation->isLocked() && $canApprove)
    <div class="qt-action-bar justify-content-end">
        <button type="button" class="btn btn-warning btn-sm" onclick="unlockQuotation()">
            <i class="fa fa-lock-open me-1"></i> Buka Kunci
        </button>
    </div>
    @elseif(! $quotation->isLocked() && ($canCreate || $canUpdate || auth()->user()->role === 'Admin'))
    <div class="qt-action-bar justify-content-end">
        <button type="button" class="btn btn-primary btn-sm" onclick="reviseQuotation()">
            <i class="fa fa-copy me-1"></i> Buat Revisi
        </button>
    </div>
    @endif
@endif

@if($quotation->status === 'rejected' && ($canCreate || $canUpdate || auth()->user()->role === 'Admin'))
<div class="qt-action-bar justify-content-end">
    <button type="button" class="btn btn-primary btn-sm" onclick="reviseQuotation()">
        <i class="fa fa-copy me-1"></i> Buat Revisi
    </button>
</div>
@endif

@if($quotation->status === 'rejected' && $quotation->approval_note)
<div class="qt-action-bar" style="border-color:#fecaca;background:#fef2f2;">
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

<div class="card-custom fade-in mb-3 quotation-info-card">
    <div class="card-header-custom quotation-info-header">
        <span>
            <i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>
            Informasi Quotation
            {!! $quotation->statusBadgeHtml() !!}
        </span>
    </div>

    <div class="card-body-custom">
        <div class="info-grid">

            {{-- Nomor Quotation --}}
            <div class="info-item">
                <div class="info-label">Nomor Quotation</div>
                <div class="info-value">
                    <strong>{{ $quotation->quotation_number ?? '—' }}</strong>
                </div>
            </div>

            {{-- Tanggal --}}
            <div class="info-item">
                <div class="info-label">Tanggal</div>
                <div class="info-value">
                    {{ $quotation->date?->format('d/m/Y') ?? '—' }}
                </div>
            </div>

            {{-- Sumber Configuration --}}
            <div class="info-item">
                <div class="info-label">Sumber Configuration</div>
                <div class="info-value">
                    @if($quotation->configurations->isNotEmpty())
                        <span>
                            {{ count($quotation->configurations) }} config:
                        </span>
                        {{ $quotation->configurations->implode('division.division_name', ' + ') }}
                    @else
                        #{{ $quotation->quote_configuration_id ?? '—' }}
                    @endif
                </div>
            </div>

            {{-- Currency --}}
            <div class="info-item">
                <div class="info-label">Currency</div>
                <div class="info-value">
                    {{ $quotation->currency ?? 'Rupiah' }}
                </div>
            </div>

            {{-- To --}}
            <div class="info-item">
                <div class="info-label">To</div>
                <div class="info-value">
                    {{ $quotation->to_name ?? '—' }}
                </div>
            </div>

            {{-- Your Ref --}}
            <div class="info-item">
                <div class="info-label">Your Ref</div>
                <div class="info-value">
                    {{ $quotation->your_ref ?? '—' }}
                </div>
            </div>

            {{-- Address --}}
            <div class="info-item">
                <div class="info-label">Address</div>
                <div class="info-value">
                    {!! nl2br(e($quotation->address ?? '—')) !!}
                </div>
            </div>

            {{-- No of Pages --}}
            <div class="info-item">
                <div class="info-label">No of Pages</div>
                <div class="info-value">
                    {{ $quotation->no_of_pages ?? '—' }} Pages
                </div>
            </div>

            {{-- Attn --}}
            <div class="info-item">
                <div class="info-label">Attn</div>
                <div class="info-value">
                    {{ $quotation->attn_name ?? '—' }}
                </div>
            </div>

            {{-- From --}}
            <div class="info-item">
                <div class="info-label">From</div>
                <div class="info-value">
                    {{ $quotation->from_name ?? '—' }}
                </div>
            </div>

            {{-- Telp --}}
            <div class="info-item">
                <div class="info-label">Telp</div>
                <div class="info-value">
                    {{ $quotation->attn_phone ?? '—' }}
                </div>
            </div>

            {{-- Contact Person Phone --}}
            <div class="info-item">
                <div class="info-label">Contact Person Phone</div>
                <div class="info-value">
                    {{ $quotation->contact_phone ?? '—' }}
                </div>
            </div>

            {{-- Email --}}
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value">
                    {{ $quotation->attn_email ?? '—' }}
                </div>
            </div>

            {{-- Dibuat Oleh --}}
            <div class="info-item">
                <div class="info-label">Dibuat Oleh</div>
                <div class="info-value">
                    {{ $quotation->creator?->username ?? '—' }}
                </div>
            </div>

            {{-- Parameter --}}
            <div class="info-item info-item-full">
                <div class="info-label">Parameter</div>
                <div class="info-value info-note">
                    {{ $quotation->parameter_note ?? '—' }}
                </div>
            </div>

            {{-- Final Checked By --}}
            <div class="info-item">
                <div class="info-label">Final Checked By</div>
                <div class="info-value">
                    {{ $quotation->finalChecker?->username ?? '—' }}
                </div>
            </div>

            {{-- Approved At --}}
            <div class="info-item">
                <div class="info-label">Approved At</div>
                <div class="info-value">
                    {{ $quotation->approved_at?->format('d M Y H:i') ?? '—' }}
                </div>
            </div>

            {{-- Rejected At --}}
            <div class="info-item">
                <div class="info-label">Rejected At</div>
                <div class="info-value">
                    {{ $quotation->rejected_at?->format('d M Y H:i') ?? '—' }}
                </div>
            </div>

            {{-- Revisi --}}
            <div class="info-item">
                <div class="info-label">Revisi</div>
                <div class="info-value revision-value">
                    <span>Versi {{ $quotation->version }}</span>

                    @if($quotation->is_current)
                        <span class="active-version-badge">
                            AKTIF
                        </span>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>



<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Detail Quotation</span>
    </div>
    <div class="card-body-custom p-2">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#qt-show-items" type="button" role="tab">
                    List Item Quotation
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qt-show-configs" type="button" role="tab">
                    List Configuration
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qt-show-costs" type="button" role="tab">
                    Biaya
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qt-show-notes" type="button" role="tab">
                    Catatan
                </button>
            </li>
        </ul>
        <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="qt-show-items" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:45px">No</th>
                                <th>Deskripsi</th>
                                <th style="width:80px" class="text-center">Qty</th>
                                <th style="width:140px" class="text-end">Unit Price</th>
                                <th style="width:150px" class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $rows = $quotation->flattenTree(); @endphp
                            @forelse($rows as $row)
                                @php
                                    $item = $row['item'];
                                    $depth = $row['depth'];
                                @endphp
                                <tr>
                                    <td class="text-center" style="padding-left:{{ 8 + $depth * 20 }}px">{{ $item->item_no }}</td>
                                    <td>
                                        <div style="margin-left:{{ $depth * 20 }}px">
                                            {!! \App\Models\Quotation::renderDescription($item->description) !!}
                                            @if($item->part_number)
                                                <div><small style="color:var(--text-muted)">PN: {{ $item->part_number }}</small></div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center">{{ $item->qty ?: '' }} {{ $item->qty ? $item->unit : '' }}</td>
                                    <td class="text-end">{{ $item->price ? \App\Models\Quotation::formatMoney($item->price) : '' }}</td>
                                    <td class="text-end">{{ $item->qty && $item->price ? \App\Models\Quotation::formatMoney($item->qty * $item->price) : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center" style="color:var(--text-muted);padding:16px">Tidak ada item.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="qt-show-configs" role="tabpanel">
                @php $configItems = $quotation->configItems; @endphp
                @if($configItems->isNotEmpty())
                    @php $groups = $configItems->groupBy(fn ($it) => $it->category ?: 'Lainnya'); @endphp
                    @foreach($groups as $category => $citems)
                        <div style="font-size:13px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px;padding:10px 14px 6px">{{ $category }}</div>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:45px">No</th>
                                        <th>Part Number</th>
                                        <th>Deskripsi</th>
                                        <th style="width:80px" class="text-center">Qty</th>
                                        <th style="width:140px" class="text-end">Unit Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($citems as $idx => $it)
                                        <tr>
                                            <td class="text-center">{{ $idx + 1 }}</td>
                                            <td><code>{{ $it->part_number ?? '—' }}</code></td>
                                            <td>{!! \App\Models\Quotation::renderDescription($it->description) !!}</td>
                                            <td class="text-center">{{ $it->qty ?: '' }} {{ $it->unit ?: '' }}</td>
                                            <td class="text-end">{{ $it->price ? \App\Models\Quotation::formatMoney($it->price) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                @else
                    <div class="text-center" style="color:var(--text-muted);padding:16px">Belum ada list configuration.</div>
                @endif
            </div>
            <div class="tab-pane fade" id="qt-show-costs" role="tabpanel">
                <div class="d-flex justify-content-end mb-2">
                    <a href="{{ route('quotation.pdf-cost', $quotation->id) }}" target="_blank" class="btn btn-sm btn-soft">
                        <i class="fa fa-file-pdf me-1"></i> View PDF Biaya
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:45px">No</th>
                                <th>Judul / Deskripsi</th>
                                <th style="width:100px" class="text-center">Qty</th>
                                <th style="width:140px" class="text-end">Harga</th>
                                <th style="width:150px" class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $costRows = $quotation->flattenCostTree(); @endphp
                            @forelse($costRows as $row)
                                @php
                                    $citem = $row['item'];
                                    $cdepth = $row['depth'];
                                    $isTitle = (bool) $citem->title;
                                    $desc = $isTitle ? $citem->title : $citem->description;
                                @endphp
                                <tr>
                                    <td class="text-center" style="padding-left:{{ 8 + $cdepth * 20 }}px">{{ $citem->item_no }}</td>
                                    <td>
                                        <div style="margin-left:{{ $cdepth * 20 }}px;{{ $isTitle ? 'font-weight:700;' : '' }}">
                                            {!! \App\Models\Quotation::renderDescription($desc) !!}
                                        </div>
                                    </td>
                                    <td class="text-center">{{ $citem->qty ?: '' }} {{ $citem->qty ? $citem->unit : '' }}</td>
                                    <td class="text-end">{{ $citem->price ? \App\Models\Quotation::formatMoney($citem->price) : '' }}</td>
                                    <td class="text-end">{{ $citem->qty && $citem->price ? \App\Models\Quotation::formatMoney($citem->qty * $citem->price) : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center" style="color:var(--text-muted);padding:16px">Belum ada biaya.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @php
                    $costTotal = $quotation->costItems->reduce(fn ($c, $i) => $c + (($i->qty ?? 0) * ($i->price ?? 0)), 0);
                @endphp
                <div class="d-flex justify-content-end mt-2">
                    <table class="table table-custom align-middle mb-0" style="max-width:340px">
                        <tr>
                            <td class="text-end fw-bold">Total Price Biaya</td>
                            <td class="text-end fw-bold">{{ \App\Models\Quotation::formatMoney($costTotal) }}</td>
                        </tr>
                    </table>
                </div>
                @if($quotation->cost_notes)
                    <div class="mt-3 p-3" style="background:var(--bg);border:1px solid var(--card-border);border-radius:8px">
                        <strong style="font-size:13px">Catatan Biaya</strong>
                        <div style="font-size:13px;white-space:pre-line;margin-top:4px">{!! e($quotation->cost_notes) !!}</div>
                    </div>
                @endif
            </div>
            <div class="tab-pane fade" id="qt-show-notes" role="tabpanel">
                @if($quotation->notes)
                    <div style="font-size:13px;white-space:pre-line;padding:14px">{!! nl2br(e($quotation->notes)) !!}</div>
                @else
                    <div class="text-center" style="color:var(--text-muted);padding:16px">Belum ada catatan.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3" id="qt-show-price-summary">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan Harga</span>
    </div>
    <div class="card-body-custom">
        <div class="row">
            <div class="col-md-6">
                @if($quotation->terms)
                    <div class="mb-2"><strong style="font-size:13px">Term & Conditions</strong></div>
                   <div style="font-family:monospace;font-size:12px; white-space: pre-wrap;">{!! e($quotation->terms) !!}</div>
                @endif
            </div>
            <div class="col-md-6">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <tbody>
                            <tr>
                                <td>Subtotal</td>
                                <td class="text-end fw-bold">{{ \App\Models\Quotation::formatMoney($quotation->subtotal) }}</td>
                            </tr>
                            @if($quotation->discount_amount > 0)
                            <tr>
                                <td>Diskon</td>
                                <td class="text-end">({{ \App\Models\Quotation::formatMoney($quotation->discount_amount) }})</td>
                            </tr>
                            @endif
                            <tr>
                                <td>Netto / DPP Pajak</td>
                                <td class="text-end">{{ \App\Models\Quotation::formatMoney($quotation->dpp) }}</td>
                            </tr>
                            <tr>
                                <td>PPN @if($quotation->ppn_percent)({{ $quotation->ppn_percent }}%)@endif</td>
                                <td class="text-end">{{ \App\Models\Quotation::formatMoney($quotation->ppn) }}</td>
                            </tr>
                            <tr style="background:var(--accent-soft)">
                                <td class="fw-bold">Full Amount</td>
                                <td class="text-end fw-bold" style="color:var(--accent);font-size:16px">{{ \App\Models\Quotation::formatMoney($quotation->grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- @if($quotation->terms)
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-contract me-2" style="color:var(--accent)"></i>Term &amp; Conditions</span>
    </div>
    <div class="card-body-custom">
        <div style="font-family:monospace;font-size:12px; white-space: pre-wrap;">{!! e($quotation->terms) !!}</div>
    </div>
</div>
@endif --}}

@endsection

@push('modals')
<div class="modal fade" id="qtRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Tolak Quotation</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea id="qt-reject-note" class="form-control" rows="3" placeholder="Wajib diisi alasan penolakan"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-reject-qt">
                    <i class="fa fa-xmark me-1"></i> Tolak
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qtTrackModal" tabindex="-1" aria-hidden="true">
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
                        <tbody id="qt-track-body">
                            <tr>
                                <td colspan="6" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td>
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
let qtRejectModalInstance = null;
let qtTrackModalInstance = null;
const qtId = {{ $quotation->id }};

$(document).ready(function() {
    // Ringkasan Harga hanya tampil saat tab "List Item Quotation" aktif.
    $(document).on('shown.bs.tab', 'button[data-bs-toggle="tab"]', function(e) {
        $('#qt-show-price-summary').toggle($(e.target).attr('data-bs-target') === '#qt-show-items');
    });
});

const quotationSubmitUrl = '{{ route("quotation.submit", "__ID__") }}';
const quotationApproveUrl = '{{ route("quotation.approve", "__ID__") }}';
const quotationRejectUrl = '{{ route("quotation.reject", "__ID__") }}';
const quotationUnlockUrl = '{{ route("quotation.unlock", "__ID__") }}';
const quotationReviseUrl = '{{ route("quotation.revise", "__ID__") }}';
const quotationVersionsUrl = '{{ route("quotation.versions", "__ID__") }}';
const quotationDeleteUrl = '{{ route("quotation.destroy", "__ID__") }}';
const quotationEditUrl = '{{ route("quotation.edit", "__ID__") }}';

function submitQuotation() {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Quotation akan dikirim untuk approval. Setelah di-submit tidak bisa diedit.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationSubmitUrl.replace('__ID__', qtId), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation dikirim untuk approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function approveQuotation() {
    Swal.fire({
        title: 'Approve Quotation?',
        text: 'Anda yakin menyetujui quotation ini?',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationApproveUrl.replace('__ID__', qtId), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function openQtReject() {
    $('#qt-reject-note').val('');
    if (!qtRejectModalInstance) {
        qtRejectModalInstance = new bootstrap.Modal(document.getElementById('qtRejectModal'));
    }
    qtRejectModalInstance.show();
}

$(document).on('click', '#btn-reject-qt', function() {
    var note = $('#qt-reject-note').val().trim();
    if (!note) {
        toastr.error('Alasan penolakan wajib diisi.');
        return;
    }
    $('#btn-reject-qt').prop('disabled', true);
    $.ajax({
        url: quotationRejectUrl.replace('__ID__', qtId),
        method: 'POST',
        data: { _token: '{{ csrf_token() }}', approval_note: note }
    }).done(function(res) {
        toastr.success(res.message || 'Quotation ditolak.');
        qtRejectModalInstance.hide();
        setTimeout(function() { window.location.reload(); }, 800);
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menolak.');
    }).always(function() {
        $('#btn-reject-qt').prop('disabled', false);
    });
});

function unlockQuotation() {
    Swal.fire({
        title: 'Buka Kunci?',
        text: 'Quotation dapat direvisi setelah kunci dibuka.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buka Kunci',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationUnlockUrl.replace('__ID__', qtId), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Kunci dibuka.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membuka kunci.');
            });
    });
}

function reviseQuotation() {
    Swal.fire({
        title: 'Buat Revisi?',
        text: 'Header & detail akan disalin menjadi versi baru (Draft). Versi lama tetap sebagai riwayat.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat Revisi',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationReviseUrl.replace('__ID__', qtId), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Revisi dibuat.');
                setTimeout(function() {
                    window.location.href = quotationEditUrl.replace('__ID__', res.id);
                }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membuat revisi.');
            });
    });
}

function openQtTrack() {
    $('#qt-track-body').html('<tr><td colspan="6" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>');
    $.get(quotationVersionsUrl.replace('__ID__', qtId), function(res) {
        var versions = res.versions || [];
        if (versions.length === 0) {
            $('#qt-track-body').html('<tr><td colspan="6" class="config-card-empty"><i class="fa-solid fa-inbox"></i>Belum ada riwayat versi.</td></tr>');
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
            $('#qt-track-body').html(html);
        }
    }).fail(function() {
        $('#qt-track-body').html('<tr><td colspan="6" class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat riwayat.</td></tr>');
    });

    if (!qtTrackModalInstance) {
        qtTrackModalInstance = new bootstrap.Modal(document.getElementById('qtTrackModal'));
    }
    qtTrackModalInstance.show();
}

function deleteQuotation() {
    Swal.fire({
        title: 'Hapus Quotation?',
        text: 'Quotation #' + qtId + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: quotationDeleteUrl.replace('__ID__', qtId),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Quotation dihapus.');
            setTimeout(function() { window.location.replace('{{ route("quotation.index") }}'); }, 800);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}
</script>
@endsection

@extends('layouts.app')

@section('title', 'Detail IMS Project')
@section('page-title', 'Detail IMS Project')

@section('styles')
<style>
    .ip-label { font-size: 11.5px; color: var(--text-muted); margin-bottom: 2px; text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
    .ip-value { font-size: 14px; font-weight: 600; color: var(--text-primary); }
    .ip-muted { font-size: 12px; color: var(--text-muted); font-weight: 400; }
    .ip-empty { font-size: 13px; color: var(--text-muted); }

    .ip-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .ip-pill.ok { background: var(--accent-soft); color: var(--accent); }
    .ip-pill.warn { background: #fef3c7; color: #92400e; }
    .ip-pill.danger { background: var(--danger-soft); color: #7f1d1d; }
    .ip-pill.muted { background: transparent; border: 1px solid var(--card-border); color: var(--text-muted); font-weight: 500; }

    /* Status bar */
    .ip-statusbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .ip-statusbar .spacer { flex: 1; }

    /* Ringkasan */
    .ip-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 991px) { .ip-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ip-stat { border: 1px solid var(--card-border); border-radius: var(--radius); padding: 12px 14px; background: var(--card-bg, #fff); min-width: 0; }
    .ip-stat .k { font-size: 11.5px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
    .ip-stat .k i { color: var(--accent); }
    .ip-stat .v { font-size: 16px; font-weight: 700; color: var(--text-primary); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ip-stat .s { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }
    .ip-stat .s.danger { color: var(--danger); font-weight: 600; }
    .ip-stat .s.warn { color: #92400e; font-weight: 600; }

    /* Timeline PO */
    .ip-timeline { display: flex; gap: 0; margin-top: 4px; }
    .ip-timeline .step { flex: 1; position: relative; padding-top: 18px; min-width: 0; }
    .ip-timeline .step::before { content: ''; position: absolute; top: 5px; left: 0; right: 0; height: 2px; background: var(--card-border); }
    .ip-timeline .step:first-child::before { left: 6px; }
    .ip-timeline .step:last-child::before { right: calc(100% - 6px); }
    .ip-timeline .dot { position: absolute; top: 0; left: 0; width: 12px; height: 12px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
    .ip-timeline .step.future .dot { background: #fff; border: 2px solid var(--card-border); box-shadow: none; }
    .ip-timeline .step.late .dot { background: var(--danger); box-shadow: 0 0 0 3px var(--danger-soft); }
    .ip-timeline .when { font-size: 13.5px; font-weight: 600; color: var(--text-primary); }

    /* Dokumen */
    .ip-doc-list { display: flex; flex-direction: column; gap: 6px; }
    .ip-doc-row {
        display: flex; align-items: center; gap: 10px; padding: 8px 10px;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 13px;
        color: inherit; text-decoration: none; transition: border-color .15s, background .15s;
    }
    a.ip-doc-row:hover, .ip-doc-row:hover { border-color: var(--accent); background: var(--accent-subtle); }
    .ip-doc-row > i { color: var(--danger); font-size: 17px; width: 18px; text-align: center; }
    .ip-doc-row .meta { font-size: 11.5px; color: var(--text-muted); }
    .ip-doc-row .name { font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ip-doc-tag { font-size: 10.5px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; color: var(--accent); background: var(--accent-soft); border-radius: 4px; padding: 1px 6px; }
    .ip-doc-group + .ip-doc-group { margin-top: 14px; }

    /* Garansi */
    .ip-warranty-bar { height: 8px; border-radius: 999px; background: var(--card-border); overflow: hidden; margin: 10px 0 6px; }
    .ip-warranty-bar > div { height: 100%; border-radius: 999px; background: var(--accent); }
    .ip-warranty-bar.warn > div { background: #f59e0b; }
    .ip-warranty-bar.danger > div { background: var(--danger); }
    .ip-warranty-days { font-size: 26px; font-weight: 800; line-height: 1; color: var(--text-primary); }

    /* Checklist kelengkapan */
    .ip-check { list-style: none; padding: 0; margin: 0; }
    .ip-check li { display: flex; align-items: center; gap: 10px; padding: 7px 0; font-size: 13px; }
    .ip-check li + li { border-top: 1px dashed var(--card-border); }
    .ip-check .ic { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
    .ip-check .ic.ok { background: var(--accent-soft); color: var(--accent); }
    .ip-check .ic.no { background: #f1f5f9; color: var(--text-muted); }
    .ip-check .act { margin-left: auto; font-size: 12px; text-decoration: none; }

    .ip-meta-row { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; padding: 5px 0; }
    .ip-meta-row .k { color: var(--text-muted); }
    .ip-meta-row .v { text-align: right; font-weight: 500; }
</style>
@endsection

@section('content')
@php
    $warranty = $project->warranty;
    $warrantyMeta = $warranty?->statusMeta();
    $isHybrid = $project->isImsHybrid();
    $today = now()->startOfDay();

    $dueDays = $project->po_due_date ? (int) $today->diffInDays($project->po_due_date->copy()->startOfDay(), false) : null;
    $dueText = $dueDays === null ? null : ($dueDays < 0 ? 'Lewat '.abs($dueDays).' hari' : ($dueDays === 0 ? 'Hari ini' : $dueDays.' hari lagi'));

    $warrantyPct = null;
    if ($warranty) {
        $total = max(1, $warranty->start_date->diffInDays($warranty->end_date));
        $elapsed = $warranty->start_date->copy()->startOfDay()->diffInDays($today, false);
        $warrantyPct = (int) round(min(100, max(0, $elapsed / $total * 100)));
    }

    $docCount = count($agreements) + ($project->hasPoDocument() ? 1 : 0);
    $checklist = [
        ['ok' => $project->hasPoDocument(), 'label' => 'Dokumen PO'],
        ['ok' => count($agreements) > 0, 'label' => 'Dokumen pendukung'],
        ['ok' => (bool) $warranty, 'label' => 'Masa garansi'],
        ['ok' => ! $isHybrid, 'label' => 'Nilai kontrak & sales', 'project' => true],
    ];
    $doneCount = collect($checklist)->where('ok', true)->count();
@endphp

<div class="page-header">
    <div style="min-width:0">
        <h1 class="page-header-title">{{ $project->project_name }}</h1>
        <p class="page-header-sub">{{ $project->project_number }} &middot; {{ $project->company?->account_name ?? '—' }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('ims-project.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

{{-- Status & aksi --}}
<div class="card-custom fade-in mb-3">
    <div class="card-body-custom py-2">
        <div class="ip-statusbar">
            {!! $project->statusBadgeHtml() !!}
            @if($isHybrid)
                <span class="ip-pill warn" title="Nilai kontrak & sales belum diisi"><i class="fa-solid fa-triangle-exclamation"></i>Belum lengkap</span>
            @elseif($project->quotation)
                <span class="ip-pill muted"><i class="fa-solid fa-file-lines"></i>{{ $project->quotation->quotation_number }}</span>
            @else
                <span class="ip-pill muted"><i class="fa-solid fa-pen-ruler"></i>Project manual</span>
            @endif
            @if($warranty)
                <span class="ip-pill {{ $warrantyMeta['class'] }}"><i class="fa-solid fa-shield-halved"></i>Garansi {{ $warrantyMeta['label'] }}</span>
            @endif
            <div class="spacer"></div>
            @if($canCompleteProject && $isHybrid)
                <a href="{{ route('project.edit', $project->id) }}" class="btn btn-soft btn-sm" title="Isi nilai kontrak, sales, dan timeline penagihan di modul Project">
                    <i class="fa fa-diagram-project me-1"></i> Lengkapi di Project
                </a>
            @elseif($canCompleteProject)
                <a href="{{ route('project.show', $project->id) }}" class="btn btn-soft btn-sm" title="Lihat nilai, timeline penagihan, dan invoice di modul Project">
                    <i class="fa fa-diagram-project me-1"></i> Buka di Project
                </a>
            @endif
            @if(($canCreateTask ?? false) && ($projectActive ?? false))
                <button type="button" class="btn btn-soft btn-sm" onclick="ptkOpen()">
                    <i class="fa fa-list-check me-1"></i> Tambah Task
                </button>
            @endif
            @if($canUpdate ?? false)
                <a href="{{ route('ims-project.edit', $project->id) }}" class="btn btn-accent btn-sm">
                    <i class="fa fa-pen me-1"></i> Edit
                </a>
            @endif
        </div>
    </div>
</div>

{{-- Ringkasan --}}
<div class="ip-stats mb-3 fade-in">
    <div class="ip-stat">
        <div class="k"><i class="fa-solid fa-file-invoice"></i>No PO Customer</div>
        <div class="v" title="{{ $project->po_number_company }}">{{ $project->po_number_company }}</div>
        <div class="s">{{ $project->po_date?->format('d M Y') ?? '—' }}</div>
    </div>
    <div class="ip-stat">
        <div class="k"><i class="fa-solid fa-hourglass-half"></i>Jatuh Tempo</div>
        <div class="v">{{ $project->po_due_date?->format('d M Y') ?? '—' }}</div>
        <div class="s {{ $dueDays !== null && $dueDays < 0 ? 'danger' : ($dueDays !== null && $dueDays <= 7 ? 'warn' : '') }}">{{ $dueText ?? 'Belum diisi' }}</div>
    </div>
    <div class="ip-stat">
        <div class="k"><i class="fa-solid fa-shield-halved"></i>Akhir Garansi</div>
        <div class="v">{{ $warranty?->end_date->format('d M Y') ?? '—' }}</div>
        <div class="s {{ $warrantyMeta['class'] ?? '' }}">
            @if(! $warranty)
                Belum diisi
            @elseif($warranty->status() === \App\Models\ProjectWarranty::STATUS_UPCOMING)
                Mulai {{ $warranty->start_date->format('d M Y') }}
            @elseif($warranty->status() === \App\Models\ProjectWarranty::STATUS_EXPIRED)
                Berakhir {{ abs($warranty->daysLeft()) }} hari lalu
            @else
                {{ $warranty->daysLeft() === 0 ? 'Berakhir hari ini' : 'Sisa '.$warranty->daysLeft().' hari' }}
            @endif
        </div>
    </div>
    <div class="ip-stat">
        <div class="k"><i class="fa-solid fa-list-check"></i>Task IMS</div>
        <div class="v">{{ $project->tasks->count() }}</div>
        <div class="s">{{ $project->tasks->whereNotIn('status', ['done', 'cancelled'])->count() }} belum selesai</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        {{-- Project & PO --}}
        {{-- <div class="card-custom fade-in mb-3">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Project &amp; PO</span>
            </div>
            <div class="card-body-custom">
                <div class="mb-3">
                    <div class="ip-label">Deskripsi</div>
                    @if($project->project_description)
                        <div style="white-space:pre-line;font-size:13.5px;color:var(--text-secondary)">{{ $project->project_description }}</div>
                    @else
                        <div class="ip-empty">Belum ada deskripsi.</div>
                    @endif
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="ip-label">Company</div>
                        <div class="ip-value">{{ $project->company?->account_name ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="ip-label">No PO Customer</div>
                        <div class="ip-value">{{ $project->po_number_company }}</div>
                    </div>
                </div>
                <div class="ip-label mb-1">Timeline PO</div>
                <div class="ip-timeline">
                    <div class="step">
                        <span class="dot"></span>
                        <div class="ip-muted">Tanggal PO</div>
                        <div class="when">{{ $project->po_date?->format('d M Y') ?? '—' }}</div>
                    </div>
                    <div class="step {{ $project->po_date_received && $project->po_date_received->isFuture() ? 'future' : '' }}">
                        <span class="dot"></span>
                        <div class="ip-muted">PO Diterima</div>
                        <div class="when">{{ $project->po_date_received?->format('d M Y') ?? '—' }}</div>
                    </div>
                    <div class="step {{ $dueDays === null || $dueDays > 0 ? 'future' : ($dueDays < 0 ? 'late' : '') }}">
                        <span class="dot"></span>
                        <div class="ip-muted">Jatuh Tempo</div>
                        <div class="when">{{ $project->po_due_date?->format('d M Y') ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div> --}}

        {{-- Dokumen --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-folder-open me-2" style="color:var(--accent)"></i>Dokumen <span class="ip-muted">({{ $docCount }})</span></span>
                @if($canUpdate ?? false)
                    <a href="{{ route('ims-project.edit', $project->id) }}" class="ip-muted text-decoration-none"><i class="fa fa-upload me-1"></i>Upload</a>
                @endif
            </div>
            <div class="card-body-custom">
                <div class="ip-doc-group">
                    <div class="ip-label mb-1">Dokumen PO</div>
                    @if($project->hasPoDocument())
                        <a href="{{ route('ims-project.po', $project->id) }}" target="_blank" class="ip-doc-row">
                            <i class="fa fa-file-pdf"></i>
                            <div style="min-width:0;flex:1">
                                <div class="name">{{ $project->po_document_name }}</div>
                                <div class="meta">{{ $project->poUploader?->display_name ?? '—' }}{{ $project->po_uploaded_at ? ', '.$project->po_uploaded_at->format('d M Y H:i') : '' }}</div>
                            </div>
                            <span class="ip-doc-tag">PO</span>
                            <i class="fa fa-arrow-up-right-from-square" style="color:var(--text-muted);font-size:12px"></i>
                        </a>
                    @else
                        <div class="ip-empty">Belum ada dokumen PO.</div>
                    @endif
                </div>
                <div class="ip-doc-group">
                    <div class="ip-label mb-1">Dokumen Pendukung</div>
                    @if(count($agreements))
                        <div class="ip-doc-list">
                            @foreach($agreements as $doc)
                                <a href="{{ $doc['view_url'] }}" target="_blank" class="ip-doc-row">
                                    <i class="fa {{ $doc['icon'] }}"></i>
                                    <div style="min-width:0;flex:1">
                                        <div class="name">{{ $doc['file_name'] }}</div>
                                        <div class="meta">{{ $doc['size'] }} · {{ $doc['uploaded_by'] ?? '—' }}, {{ $doc['uploaded_at'] }}</div>
                                    </div>
                                    @if($doc['notes'])
                                        <span class="ip-doc-tag" title="{{ $doc['notes'] }}">{{ \Illuminate\Support\Str::limit($doc['notes'], 22) }}</span>
                                    @endif
                                    <i class="fa fa-arrow-up-right-from-square" style="color:var(--text-muted);font-size:12px"></i>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="ip-empty">Belum ada dokumen pendukung (BAST, kontrak maintenance, dll.).</div>
                    @endif
                </div>
            </div>
        </div>

        @include('ims-project._tasks', ['tasks' => $project->tasks, 'class' => '', 'taskDivision' => \App\Http\Controllers\ImsProjectController::TASK_DIVISION])
    </div>

    <div class="col-lg-4">
        {{-- Garansi --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-shield-halved me-2" style="color:var(--accent)"></i>Masa Garansi</span>
                @if($warranty)
                    <span class="ip-pill {{ $warrantyMeta['class'] }}">{{ $warrantyMeta['label'] }}</span>
                @endif
            </div>
            <div class="card-body-custom">
                @if($warranty)
                    @php $wStatus = $warranty->status(); @endphp
                    <div class="d-flex align-items-end gap-2">
                        @if($wStatus === \App\Models\ProjectWarranty::STATUS_ACTIVE)
                            <span class="ip-warranty-days">{{ $warranty->daysLeft() }}</span><span class="ip-muted mb-1">hari tersisa</span>
                        @elseif($wStatus === \App\Models\ProjectWarranty::STATUS_EXPIRED)
                            <span class="ip-warranty-days" style="color:var(--danger)">{{ abs($warranty->daysLeft()) }}</span><span class="ip-muted mb-1">hari sejak berakhir</span>
                        @else
                            <span class="ip-warranty-days">{{ (int) $today->diffInDays($warranty->start_date->copy()->startOfDay()) }}</span><span class="ip-muted mb-1">hari lagi mulai</span>
                        @endif
                    </div>
                    <div class="ip-warranty-bar {{ $warrantyMeta['class'] }}" title="{{ $warrantyPct }}% masa garansi berjalan"><div style="width:{{ $warrantyPct }}%"></div></div>
                    <div class="d-flex justify-content-between ip-muted">
                        <span>{{ $warranty->start_date->format('d M Y') }}</span>
                        <span>{{ $warranty->durationMonths() > 0 ? $warranty->durationMonths().' bulan' : $warranty->start_date->diffInDays($warranty->end_date).' hari' }}</span>
                        <span>{{ $warranty->end_date->format('d M Y') }}</span>
                    </div>
                @else
                    <div class="text-center py-2">
                        <i class="fa-solid fa-shield-halved" style="font-size:24px;color:var(--card-border)"></i>
                        <div class="ip-empty mt-2">Masa garansi belum diisi.</div>
                        @if($canUpdate ?? false)
                            <a href="{{ route('ims-project.edit', $project->id) }}#ip-warranty-start" class="btn btn-soft btn-sm mt-2"><i class="fa fa-plus me-1"></i>Isi masa garansi</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Kelengkapan data --}}
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-clipboard-check me-2" style="color:var(--accent)"></i>Kelengkapan Data</span>
                <span class="ip-muted">{{ $doneCount }}/{{ count($checklist) }}</span>
            </div>
            <div class="card-body-custom py-2">
                <ul class="ip-check">
                    @foreach($checklist as $item)
                        <li>
                            <span class="ic {{ $item['ok'] ? 'ok' : 'no' }}"><i class="fa {{ $item['ok'] ? 'fa-check' : 'fa-minus' }}"></i></span>
                            <span style="{{ $item['ok'] ? '' : 'color:var(--text-muted)' }}">{{ $item['label'] }}</span>
                            @if(! $item['ok'])
                                @if(! empty($item['project']))
                                    @if($canCompleteProject)
                                        <a href="{{ route('project.edit', $project->id) }}" class="act">Lengkapi</a>
                                    @else
                                        <span class="act ip-muted">di modul Project</span>
                                    @endif
                                @elseif($canUpdate ?? false)
                                    <a href="{{ route('ims-project.edit', $project->id) }}" class="act">Isi</a>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Riwayat --}}
        <div class="card-custom fade-in">
            <div class="card-body-custom py-2">
                <div class="ip-meta-row"><span class="k">Sumber</span><span class="v">{{ $project->quotation?->quotation_number ? 'Quotation '.$project->quotation->quotation_number : ($isHybrid ? 'IMS Project / manual' : 'Project manual') }}</span></div>
                <div class="ip-meta-row"><span class="k">Dibuat</span><span class="v">{{ $project->creator?->display_name ?? '—' }}<div class="ip-muted">{{ $project->created_at?->format('d M Y H:i') }}</div></span></div>
                <div class="ip-meta-row"><span class="k">Diperbarui</span><span class="v">{{ $project->updater?->display_name ?? '—' }}<div class="ip-muted">{{ $project->updated_at?->format('d M Y H:i') }}</div></span></div>
            </div>
        </div>
    </div>
</div>
@endsection

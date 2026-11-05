@extends('layouts.app')

@section('title', 'Detail Project')
@section('page-title', 'Detail Project')

@section('styles')
<style>
    .pj-label { font-size: 12px; color: var(--text-muted); margin-bottom: 2px; }
    .pj-muted { font-size: 12px; color: var(--text-muted); font-weight: 400; }

    .pj-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .pj-actions .spacer { flex: 1; }

    .pj-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .pj-kpi { padding: 14px 16px; border: 1px solid var(--card-border); border-radius: var(--radius-sm); background: var(--card); }
    .pj-kpi.primary { background: var(--accent-soft); border-color: transparent; }
    .pj-kpi .val { font-size: 20px; font-weight: 800; line-height: 1.25; font-variant-numeric: tabular-nums; color: var(--text-primary); }
    .pj-kpi.primary .val { color: var(--accent); }
    .pj-kpi .sub { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
    .pj-bar { height: 6px; border-radius: 999px; background: var(--card-border); overflow: hidden; margin-top: 8px; }
    .pj-bar > span { display: block; height: 100%; background: var(--accent); border-radius: 999px; }

    .pj-dl { display: grid; grid-template-columns: 150px 1fr; gap: 9px 12px; font-size: 13.5px; margin: 0; }
    .pj-dl dt { color: var(--text-muted); font-weight: 400; }
    .pj-dl dd { margin: 0; font-weight: 600; color: var(--text-primary); }

    .pj-timeline { list-style: none; margin: 0; padding: 0; }
    .pj-timeline li { position: relative; padding: 0 0 16px 26px; font-size: 13.5px; }
    .pj-timeline li:last-child { padding-bottom: 0; }
    .pj-timeline li::before {
        content: ''; position: absolute; left: 5px; top: 6px; width: 10px; height: 10px;
        border-radius: 50%; background: var(--accent);
    }
    .pj-timeline li:not(:last-child)::after {
        content: ''; position: absolute; left: 9px; top: 18px; bottom: 0; width: 2px; background: var(--card-border);
    }
    .pj-timeline li.future::before { background: var(--card); border: 2px solid var(--accent); }
    .pj-timeline .when { font-weight: 600; color: var(--text-primary); }

    .pj-pill { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; }
    .pj-pill.ok { background: var(--accent-soft); color: var(--accent); }
    .pj-pill.warn { background: #fef3c7; color: #92400e; }
    .pj-pill.danger { background: var(--danger-soft); color: #7f1d1d; }

    .pj-doc {
        display: flex; align-items: center; gap: 10px; padding: 8px 12px; margin-top: 14px;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 13px;
    }
    .pj-doc > i { color: var(--danger); font-size: 18px; }

    .pj-sales-item { display: flex; align-items: center; gap: 12px; padding: 10px 4px; }
    .pj-sales-item + .pj-sales-item { border-top: 1px dashed var(--card-border); }
    .pj-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--accent-soft); color: var(--accent); font-size: 12px; font-weight: 700;
    }

    .pj-table tfoot td { border: 0; padding-top: 8px; padding-bottom: 8px; }

    /* Timeline penagihan & dokumen */
    .pj-term-table td { vertical-align: middle; }
    .pj-term-table .desc { font-size: 12px; color: var(--text-muted); margin-top: 1px; }
    .pj-term-table .num { color: var(--text-muted); font-weight: 600; }
    .pj-term-table tfoot td { font-weight: 600; border-top: 2px solid var(--card-border); }
    .pj-status { display: inline-block; font-size: 11.5px; font-weight: 600; padding: 2px 9px; border-radius: 999px; white-space: nowrap; }
    .pj-doc-btn {
        display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px;
        border: 1px solid var(--card-border); background: var(--card); font-size: 12px; font-weight: 600; color: var(--text-primary);
    }
    .pj-doc-btn:hover { border-color: var(--accent); color: var(--accent); }
    .pj-doc-btn.empty { color: var(--text-muted); border-style: dashed; }
    .pj-term-progress { display: flex; align-items: center; gap: 8px; font-size: 12.5px; }
    .pj-term-progress .pj-bar { width: 120px; margin-top: 0; }
    .pj-doc-item {
        display: flex; align-items: center; gap: 10px; padding: 8px 10px;
        border: 1px solid var(--card-border); border-radius: var(--radius-sm); font-size: 13px;
    }
    .pj-doc-item + .pj-doc-item { margin-top: 6px; }
    .pj-doc-item > i { color: var(--danger); font-size: 17px; }
    .pj-doc-item .name { font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pj-doc-type { display: inline-block; font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); }
    .pj-table tfoot tr:first-child td { border-top: 2px solid var(--card-border); }

    /* Rincian nilai: Total Sale → Net Sale dan dasar penagihan */
    .pj-breakdown-title { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; margin-bottom: 6px; }
    .pj-breakdown { width: 100%; font-size: 13.5px; border-collapse: collapse; }
    .pj-breakdown td { padding: 6px 4px; vertical-align: middle; }
    .pj-breakdown td.op { width: 18px; color: var(--text-muted); font-weight: 700; text-align: center; }
    .pj-breakdown td.k { color: var(--text-muted); }
    .pj-breakdown td.v { text-align: right; font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; color: var(--text-primary); }
    .pj-breakdown .tag { display: block; font-size: 11px; font-weight: 400; color: var(--text-muted); }
    .pj-breakdown tr.start td.k { color: var(--text-primary); font-weight: 600; }
    .pj-breakdown tr.minus td.v { color: var(--danger); font-weight: 500; }
    .pj-breakdown tr.info td { color: var(--text-muted); font-size: 12.5px; }
    .pj-breakdown tr.info td.v { color: var(--text-muted); font-weight: 500; }
    .pj-breakdown tr.sub td { border-top: 1px dashed var(--card-border); }
    .pj-breakdown tr.sub td.k { color: var(--text-primary); font-weight: 600; }
    .pj-breakdown tr.final td { background: var(--accent-soft); color: var(--accent); font-weight: 700; padding-top: 9px; padding-bottom: 9px; }
    .pj-breakdown tr.final td.k, .pj-breakdown tr.final td.v { color: var(--accent); }
    .pj-breakdown tr.final td:first-child { border-radius: var(--radius-sm) 0 0 var(--radius-sm); }
    .pj-breakdown tr.final td:last-child { border-radius: 0 var(--radius-sm) var(--radius-sm) 0; font-size: 15px; }
    .pj-breakdown tr.final .tag { color: var(--accent); opacity: .8; }
    .pj-breakdown-note { font-size: 11.5px; color: var(--text-muted); margin-top: 8px; }
</style>
@endsection

@section('content')
@php
    $achievementTotal = (float) $project->achievements->sum('amount');
    $achievementDiff = round($achievementTotal - (float) $project->net_sale_amount, 2);

    $activeInvoices = $project->invoices->where('status', '!=', \App\Models\Invoice::STATUS_CANCELLED);
    $paidInvoices = $project->invoices->where('status', \App\Models\Invoice::STATUS_PAID);
    // Persen progres hanya dari invoice termin; tagihan di luar timeline tidak menambah persen.
    $billedPercent = min(100, (float) $activeInvoices->whereNotNull('billing_term_id')->sum('percent_payment'));
    $paidPercent = min(100, (float) $paidInvoices->whereNotNull('billing_term_id')->sum('percent_payment'));
    $billedAmount = (float) $activeInvoices->sum('total_payment');
    $paidAmount = (float) $paidInvoices->sum('total_payment');

    $dueDays = $project->po_due_date
        ? (int) now()->startOfDay()->diffInDays($project->po_due_date->copy()->startOfDay(), false)
        : null;

    $terms = $project->billingTerms;
    $termTotal = round((float) $terms->sum('percent'), 2);
    $termBilledCount = $terms->filter(fn ($t) => $t->hasActiveInvoice())->count();
    $termOpenCount = $terms->filter(fn ($t) => $t->isOpen())->count();
    $termLockedCount = $terms->count() - $termBilledCount - $termOpenCount;
    $canUploadDocs = $canCreate ?? false;
    $warranty = $project->warranty;
    $warrantyMeta = $warranty?->statusMeta();

    $initials = function ($name) {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $parts = array_values(array_filter($parts));
        if (! $parts) return '?';
        return strtoupper(mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    };
@endphp

<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $project->project_name }}</h1>
        <p class="page-header-sub">
            {{ $project->project_number }} &middot; {{ $project->company?->account_name ?? '—' }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

{{-- Status & aksi --}}
<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <div class="pj-actions">
            <span>{!! $project->statusBadgeHtml() !!}</span>
            <span class="status-badge" style="background:var(--accent-soft);color:var(--accent)">{{ $project->type_label }}</span>
            @if($project->is_retention)
                <span class="status-badge" style="background:#fef3c7;color:#92400e;">Retensi</span>
            @endif
            @if($project->isImsHybrid())
                <span class="pj-pill warn" title="Dibuat dari modul IMS Project tanpa nilai kontrak & sales. Lengkapi lewat Edit.">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Nilai &amp; sales belum diisi
                </span>
            @endif
            @if($warranty)
                <span class="pj-pill {{ $warrantyMeta['class'] }}" title="Masa garansi {{ $warranty->start_date->format('d M Y') }} – {{ $warranty->end_date->format('d M Y') }}">
                    <i class="fa-solid fa-shield-halved me-1"></i>Garansi {{ $warrantyMeta['label'] }} · s/d {{ $warranty->end_date->format('d M Y') }}
                </span>
            @endif
            <div class="spacer"></div>
            {{-- @if($project->hasPoDocument())
                <a href="{{ route('project.po', $project->id) }}" target="_blank" class="btn btn-soft btn-sm">
                    <i class="fa fa-file-pdf me-1"></i> Lihat PO
                </a>
            @endif
            @if($canCreateInvoice ?? false)
                <a href="{{ route('invoice.create', ['project_id' => $project->id]) }}" class="btn btn-soft btn-sm">
                    <i class="fa fa-file-invoice-dollar me-1"></i> Buat Invoice
                </a>
            @endif --}}
            @if($canUpdate ?? false)
                <a href="{{ route('project.achievements', $project->id) }}" class="btn btn-soft btn-sm">
                    <i class="fa fa-list-check me-1"></i> Kelola Device
                </a>
                <a href="{{ route('project.edit', $project->id) }}" class="btn btn-secondary btn-sm">
                    <i class="fa fa-pen me-1"></i> Edit
                </a>
            @endif
            @if($canDelete ?? false)
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="pjDeleteProject()">
                    <i class="fa fa-trash me-1"></i> Hapus
                </button>
            @endif
        </div>
    </div>
</div>

{{-- Angka utama --}}
<div class="pj-kpis mb-3 fade-in">

    <div class="pj-kpi">
        <div class="pj-label">Total Sale Amount</div>
        <div class="val">{{ \App\Models\Project::formatMoney($project->total_sale_amount) }}</div>
        <div class="sub">Nilai quotation</div>
    </div>

    <div class="pj-kpi primary">
        <div class="pj-label">Net Sale Amount</div>
        <div class="val">{{ \App\Models\Project::formatMoney($project->net_sale_amount) }}</div>
        <div class="sub">Nilai riil project (Profit Estimate)</div>
    </div>
    <div class="pj-kpi">
        <div class="pj-label">Sudah Ditagih</div>
        <div class="val">{{ \App\Models\Project::formatNumber($billedPercent) }}%</div>
        <div class="sub">{{ \App\Models\Project::formatMoney($billedAmount) }} &middot; {{ $activeInvoices->count() }} invoice</div>
        <div class="pj-bar"><span style="width:{{ $billedPercent }}%"></span></div>
    </div>
    <div class="pj-kpi">
        <div class="pj-label">Sudah Dibayar</div>
        <div class="val">{{ \App\Models\Project::formatNumber($paidPercent) }}%</div>
        <div class="sub">{{ \App\Models\Project::formatMoney($paidAmount) }}</div>
        <div class="pj-bar"><span style="width:{{ $paidPercent }}%;background:#16a34a"></span></div>
    </div>
</div>

{{-- Rincian nilai: dari Total Sale Amount sampai Net Sale Amount, plus dasar timeline penagihan --}}
@php
    $money = fn ($v) => \App\Models\Project::formatMoney($v);
    $sb = $saleBreakdown;
    $bb = $billingBreakdown;
@endphp
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Rincian Nilai Project</span>
    </div>
    <div class="card-body-custom">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="pj-breakdown-title">Total Sale → Net Sale</div>
                @if($sb)
                    <table class="pj-breakdown">
                        <tr class="start">
                            <td class="op"></td>
                            <td class="k">Total Sale Amount<span class="tag">{{ $sb['source'] === 'manual' ? 'Nilai PO customer' : 'Nilai quotation' }}, termasuk PPN</span></td>
                            <td class="v">{{ $money($sb['total_sale']) }}</td>
                        </tr>
                        <tr class="minus">
                            <td class="op">−</td>
                            <td class="k">PPN</td>
                            <td class="v">− {{ $money($sb['ppn']) }}</td>
                        </tr>
                        @if($sb['source'] === 'manual' && isset($sb['base']))
                            <tr class="sub">
                                <td class="op">=</td>
                                <td class="k">Nilai Sesudah Diskon</td>
                                <td class="v">{{ $money($sb['base']) }}</td>
                            </tr>
                            <tr class="minus">
                                <td class="op">−</td>
                                <td class="k">Referen {{ \App\Models\Project::formatNumber($sb['referral_percent'] ?? 0) }}%</td>
                                <td class="v">{{ $sb['referral'] > 0 ? '− '.$money($sb['referral']) : '—' }}</td>
                            </tr>
                        @endif
                        @if($sb['source'] === 'profit_estimate')
                            <tr class="sub">
                                <td class="op">=</td>
                                <td class="k">Sub Total I</td>
                                <td class="v">{{ $money($sb['subtotal_1']) }}</td>
                            </tr>
                            <tr class="minus">
                                <td class="op">−</td>
                                <td class="k">Discount</td>
                                <td class="v">− {{ $money($sb['discount']) }}</td>
                            </tr>
                            <tr class="sub">
                                <td class="op">=</td>
                                <td class="k">Sub Total II</td>
                                <td class="v">{{ $money($sb['subtotal_2']) }}</td>
                            </tr>
                            <tr class="minus">
                                <td class="op">−</td>
                                <td class="k">Referen {{ \App\Models\Project::formatNumber($sb['referral_percent']) }}%</td>
                                <td class="v">− {{ $money($sb['referral']) }}</td>
                            </tr>
                        @endif
                        <tr class="final">
                            <td class="op">=</td>
                            <td class="k">Net Sale Amount<span class="tag">Nilai riil project · dasar achievement sales</span></td>
                            <td class="v">{{ $money($sb['net_sale']) }}</td>
                        </tr>
                    </table>
                    <div class="pj-breakdown-note">
                        @if($sb['source'] === 'profit_estimate')
                            <i class="fa fa-lock me-1"></i>Dari Estimasi PL.
                            <a href="{{ route('profit-estimate.show', $sb['estimate_id']) }}" target="_blank" class="text-decoration-none">Lihat Estimasi PL <i class="fa fa-arrow-up-right-from-square ms-1" style="font-size:10px"></i></a>
                            @if($sb['differs'])
                                <div class="text-danger mt-1"><i class="fa fa-triangle-exclamation me-1"></i>Estimasi PL sudah berubah sejak project disimpan (project: Total {{ $money($project->total_sale_amount) }}, Net {{ $money($project->net_sale_amount) }}).</div>
                            @endif
                        @else
                            <i class="fa fa-pen me-1"></i>Project tanpa quotation: nilai diisi manual.
                        @endif
                    </div>
                @else
                    <div class="pj-muted py-3"><i class="fa fa-circle-info me-1"></i>Estimasi PL quotation tidak tersedia, rincian tidak bisa ditampilkan.</div>
                @endif
            </div>

            <div class="col-lg-6">
                <div class="pj-breakdown-title">Dasar Timeline Penagihan</div>
                <table class="pj-breakdown">
                    @if($bb['subtotal'] !== null)
                        <tr class="start">
                            <td class="op"></td>
                            <td class="k">Subtotal {{ $bb['source'] === 'quotation' ? 'Quotation' : 'Kontrak' }}<span class="tag">Sebelum diskon &amp; PPN</span></td>
                            <td class="v">{{ $money($bb['subtotal']) }}</td>
                        </tr>
                        <tr class="minus">
                            <td class="op">−</td>
                            <td class="k">Diskon{{ $bb['discount_percent'] ? ' '.\App\Models\Project::formatNumber($bb['discount_percent']).'%' : '' }}</td>
                            <td class="v">{{ $bb['discount'] > 0 ? '− '.$money($bb['discount']) : '—' }}</td>
                        </tr>
                    @endif
                    <tr class="final">
                        <td class="op">{{ $bb['subtotal'] !== null ? '=' : '' }}</td>
                        <td class="k">Nilai Sesudah Diskon<span class="tag">Sebelum PPN · dasar % tiap termin</span></td>
                        <td class="v">{{ $money($bb['base']) }}</td>
                    </tr>
                    <tr class="info">
                        <td class="op">+</td>
                        <td class="k">PPN <span class="tag">Tidak memengaruhi dasar penagihan</span></td>
                        <td class="v">{{ $money($bb['ppn']) }}</td>
                    </tr>
                    <tr class="info">
                        <td class="op">=</td>
                        <td class="k">Grand Total{{ $bb['source'] === 'quotation' ? ' Quotation' : '' }}</td>
                        <td class="v">{{ $money($bb['grand_total']) }}</td>
                    </tr>
                </table>
                <div class="pj-breakdown-note">
                    @if($bb['subtotal'] !== null)
                        Persentase termin dihitung dari nilai sesudah diskon. Ada atau tidaknya PPN, dasar penagihan tetap sama.
                        @if($bb['source'] === 'manual') Rincian diisi manual (tanpa quotation). @endif
                    @else
                        Project lama tanpa rincian: dasar penagihan = Net Sale Amount. Lengkapi rincian lewat Edit agar diskon tampil di invoice.
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi Project</span>
            </div>
            <div class="card-body-custom">
                <dl class="pj-dl">
                    <dt>No Project</dt>
                    <dd>{{ $project->project_number }}</dd>
                    <dt>Customer</dt>
                    <dd>{{ $project->company?->account_name ?? '—' }}</dd>
                    <dt>Quotation</dt>
                    <dd>
                        @if($project->quotation)
                            <a href="{{ route('quotation.show', $project->quotation_id) }}" class="text-decoration-none">{{ $project->quotation->quotation_number ?? '#'.$project->quotation_id }}</a>
                        @else
                            <span class="pj-muted">Tanpa quotation (data lama/manual)</span>
                        @endif
                    </dd>
                    <dt>No PO Customer</dt>
                    <dd>{{ $project->po_number_company }}</dd>
                    <dt>Tipe / Retensi</dt>
                    <dd>{{ $project->type_label }} &middot; {{ $project->is_retention ? 'Ada retensi' : 'Tanpa retensi' }}</dd>
                    @if($project->project_description)
                        <dt>Deskripsi</dt>
                        <dd style="font-weight:400;white-space:pre-line">{{ $project->project_description }}</dd>
                    @endif
                    <dt>Dibuat</dt>
                    <dd>{{ $project->creator?->display_name ?? '—' }} <span class="pj-muted">&middot; {{ $project->created_at?->format('d/m/Y H:i') }}</span></dd>
                    <dt>Terakhir Diubah</dt>
                    <dd>{{ $project->updater?->display_name ?? '—' }} <span class="pj-muted">&middot; {{ $project->updated_at?->format('d/m/Y H:i') }}</span></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-calendar-days me-2" style="color:var(--accent)"></i>Timeline PO &amp; Garansi</span>
            </div>
            <div class="card-body-custom">
                <ul class="pj-timeline">
                    <li>
                        <div class="pj-label mb-0">Tanggal PO</div>
                        <div class="when">{{ $project->po_date?->format('d M Y') ?? '—' }}</div>
                    </li>
                    <li>
                        <div class="pj-label mb-0">PO Diterima</div>
                        <div class="when">{{ $project->po_date_received?->format('d M Y') ?? '—' }}</div>
                    </li>
                    <li class="{{ $dueDays !== null && $dueDays > 0 ? 'future' : '' }}">
                        <div class="pj-label mb-0">Jatuh Tempo PO</div>
                        <div class="when">
                            {{ $project->po_due_date?->format('d M Y') ?? '—' }}
                            @if($dueDays !== null && $project->status === \App\Models\Project::STATUS_ACTIVE)
                                @if($dueDays < 0)
                                    <span class="pj-pill danger ms-1">Lewat {{ abs($dueDays) }} hari</span>
                                @elseif($dueDays === 0)
                                    <span class="pj-pill warn ms-1">Hari ini</span>
                                @elseif($dueDays <= 14)
                                    <span class="pj-pill warn ms-1">{{ $dueDays }} hari lagi</span>
                                @else
                                    <span class="pj-pill ok ms-1">{{ $dueDays }} hari lagi</span>
                                @endif
                            @endif
                        </div>
                    </li>
                    @if($warranty)
                        <li class="{{ $warranty->status() === \App\Models\ProjectWarranty::STATUS_UPCOMING ? 'future' : '' }}">
                            <div class="pj-label mb-0">Mulai Garansi</div>
                            <div class="when">{{ $warranty->start_date->format('d M Y') }}</div>
                        </li>
                        <li class="{{ $warranty->status() !== \App\Models\ProjectWarranty::STATUS_EXPIRED ? 'future' : '' }}">
                            <div class="pj-label mb-0">Akhir Garansi <span class="pj-muted">({{ $warranty->durationMonths() > 0 ? $warranty->durationMonths().' bulan' : $warranty->start_date->diffInDays($warranty->end_date).' hari' }})</span></div>
                            <div class="when">
                                {{ $warranty->end_date->format('d M Y') }}
                                <span class="pj-pill {{ $warrantyMeta['class'] }} ms-1">
                                    @if($warranty->status() === \App\Models\ProjectWarranty::STATUS_ACTIVE)
                                        {{ $warranty->daysLeft() === 0 ? 'Berakhir hari ini' : $warranty->daysLeft().' hari lagi' }}
                                    @elseif($warranty->status() === \App\Models\ProjectWarranty::STATUS_EXPIRED)
                                        Berakhir {{ abs($warranty->daysLeft()) }} hari lalu
                                    @else
                                        Belum mulai
                                    @endif
                                </span>
                            </div>
                        </li>
                    @else
                        <li class="future">
                            <div class="pj-label mb-0">Masa Garansi</div>
                            <div class="when pj-muted" style="font-weight:400">
                                Belum diisi.
                                @if($canUpdate ?? false)
                                    <a href="{{ route('project.edit', $project->id) }}#pj-warranty-start" class="text-decoration-none">Isi masa garansi</a>
                                @endif
                            </div>
                        </li>
                    @endif
                </ul>

            </div>
        </div>
    </div>
</div>

{{-- Timeline penagihan: tiap baris menjadi sumber invoice --}}
<div class="card-custom fade-in mb-3" id="timeline-penagihan">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fa-solid fa-timeline me-2" style="color:var(--accent)"></i>Timeline Penagihan <span class="pj-muted">({{ $terms->count() }} tahap)</span>
            @if($terms->isNotEmpty())
                <span class="pj-muted ms-2">
                    <i class="fa fa-lock me-1"></i>{{ $termLockedCount }} terkunci ·
                    <span style="color:#166534">{{ $termOpenCount }} open</span> ·
                    {{ $termBilledCount }} ditagih
                </span>
            @endif
        </span>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            @if($terms->isNotEmpty())
                <span class="pj-term-progress">
                    <span class="pj-bar"><span style="width:{{ min(100, $termTotal) }}%;background:{{ $termTotal == 100 ? '#16a34a' : '#f59e0b' }}"></span></span>
                    @if($termTotal == 100)
                        <span style="color:#16a34a;font-weight:600"><i class="fa fa-circle-check me-1"></i>100% terjadwal</span>
                    @else
                        <span style="color:#b45309;font-weight:600">{{ \App\Models\Project::formatNumber($termTotal) }}% · sisa {{ \App\Models\Project::formatNumber(100 - $termTotal) }}%</span>
                    @endif
                </span>
            @endif
            @if($canUpdate ?? false)
                <a href="{{ route('project.edit', $project->id) }}#pj-term-table" class="btn btn-secondary btn-sm"><i class="fa fa-pen me-1"></i> {{ $terms->isEmpty() ? 'Susun Timeline' : 'Ubah Timeline' }}</a>
            @endif
        </div>
    </div>
    <div class="card-body-custom p-2">
        @if($terms->isEmpty())
            <div class="text-center py-4" style="color:var(--text-muted);font-size:13px">
                <i class="fa-solid fa-timeline me-1"></i> Belum ada timeline penagihan.
                <div class="mt-1">Susun tahap pembayaran (mis. DP, Termin 1, Termin 2) agar invoice bisa dibuat langsung dari tiap tahap.</div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-custom mb-0 pj-term-table">
                    <thead>
                        <tr>
                            <th style="width:40px" class="text-center">#</th>
                            <th>Nama Pembayaran</th>
                            <th class="text-end" style="width:70px">%</th>
                            <th class="text-end" style="width:150px">≈ Nominal</th>
                            <th style="width:110px">Target Tagih</th>
                            <th class="text-center" style="width:110px">Dokumen</th>
                            <th style="width:200px">Invoice / Status</th>
                            <th class="text-end" style="width:130px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($terms as $i => $term)
                            @php
                                $active = $term->activeInvoice();
                                $meta = $term->statusMeta();
                                $docCount = $term->documents->count();
                                $cancelledCount = $term->invoices->where('status', \App\Models\Invoice::STATUS_CANCELLED)->count();
                            @endphp
                            <tr>
                                <td class="text-center num">{{ $i + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $term->name }}</div>
                                    @if($term->description)<div class="desc">{{ $term->description }}</div>@endif
                                </td>
                                <td class="text-end fw-semibold">{{ \App\Models\Project::formatNumber($term->percent) }}%</td>
                                <td class="text-end" style="font-variant-numeric:tabular-nums">{{ \App\Models\Project::formatMoney(round($billingBase * $term->percent / 100, 2)) }}</td>
                                <td>{{ $term->target_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-center">
                                    <button type="button" class="pj-doc-btn {{ $docCount ? '' : 'empty' }}" onclick="pjOpenDocs({{ $term->id }})" title="Lihat / upload dokumen pendukung">
                                        <i class="fa fa-paperclip"></i> <span data-doc-count="{{ $term->id }}">{{ $docCount }}</span>
                                    </button>
                                </td>
                                <td>
                                    @if($active)
                                        <a href="{{ route('invoice.show', $active->id) }}" class="fw-semibold text-decoration-none">{{ $active->invoice_number }}</a>
                                        <span class="pj-status ms-1" style="{{ $meta['style'] }}">{{ $meta['label'] }}</span>
                                    @elseif($term->isUnlocked())
                                        <span class="pj-status" style="{{ $meta['style'] }}"><i class="fa fa-lock-open me-1"></i>Open</span>
                                        <div class="desc">Dibuka {{ $term->unlocker?->display_name ?? '—' }}, {{ $term->unlocked_at?->format('d M Y') }}</div>
                                    @else
                                        <span class="pj-status" style="{{ $meta['style'] }}"><i class="fa fa-lock me-1"></i>Terkunci</span>
                                        <div class="desc">Belum dibuka untuk ditagih</div>
                                    @endif
                                    @if($cancelledCount)
                                        <div class="desc">{{ $cancelledCount }} invoice dibatalkan</div>
                                    @endif
                                </td>
                                <td class="text-end" style="white-space:nowrap">
                                    @if($active)
                                        <a href="{{ route('invoice.show', $active->id) }}" class="btn btn-secondary btn-sm">Lihat</a>
                                    @elseif($term->isUnlocked())
                                        @if($canCreateInvoice ?? false)
                                            <a href="{{ route('invoice.create', ['project_id' => $project->id, 'billing_term_id' => $term->id]) }}" class="btn btn-primary btn-sm">
                                                <i class="fa fa-file-invoice-dollar me-1"></i> Buat Invoice
                                            </a>
                                        @endif
                                        @if($canUpdate ?? false)
                                            <button type="button" class="btn btn-outline-secondary btn-sm" title="Kunci kembali" onclick="pjSetTermAccess({{ $term->id }}, true)"><i class="fa fa-lock"></i></button>
                                        @endif
                                    @elseif($canUpdate ?? false)
                                        <button type="button" class="btn btn-soft btn-sm" onclick="pjSetTermAccess({{ $term->id }}, false)">
                                            <i class="fa fa-lock-open me-1"></i> Buka Tagihan
                                        </button>
                                    @else
                                        <span class="pj-muted"><i class="fa fa-hourglass-half me-1"></i>Menunggu dibuka</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-end pj-muted" style="font-size:13px">Total</td>
                            <td class="text-end">{{ \App\Models\Project::formatNumber($termTotal) }}%</td>
                            <td class="text-end">{{ \App\Models\Project::formatMoney(round($billingBase * $termTotal / 100, 2)) }}</td>
                            <td colspan="4" class="pj-muted" style="font-size:12px;font-weight:400">Dari nilai sesudah diskon (sebelum PPN) {{ \App\Models\Project::formatMoney($billingBase) }}{{ $project->quotation_id || $project->hasManualBreakdown() ? '' : ' (Net Sale Amount)' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Dokumen project: PO customer + kontrak/perjanjian --}}
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-folder-open me-2" style="color:var(--accent)"></i>Dokumen Project</span>
        @if($canUploadDocs)
            <button type="button" class="btn btn-soft btn-sm" onclick="pjOpenDocs(null)"><i class="fa fa-upload me-1"></i> Upload Dokumen Pendukung</button>
        @endif
    </div>
    <div class="card-body-custom">
        <div class="row g-2">
            <div class="col-lg-6">
                <div class="pj-label">PO Customer</div>
                @if($project->hasPoDocument())
                    <a href="{{ route('project.po', $project->id) }}" target="_blank" class="pj-doc-item text-decoration-none">
                        <i class="fa fa-file-pdf"></i>
                        <div style="min-width:0;flex:1">
                            <div class="name">{{ $project->po_document_name }}</div>
                            <div class="pj-muted">No. {{ $project->po_number_company }} · diunggah {{ $project->poUploader?->display_name ?? '—' }}</div>
                        </div>
                        <i class="fa fa-arrow-up-right-from-square" style="color:var(--text-muted);font-size:12px"></i>
                    </a>
                @else
                    <div class="pj-doc-item pj-muted"><i class="fa fa-file-circle-xmark" style="color:var(--text-muted)"></i> Dokumen PO belum diunggah.</div>
                @endif
            </div>
            <div class="col-lg-6">
                <div class="pj-label">Dokumen Pendukung</div>
                <div id="pj-agreement-docs"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-list-check me-2" style="color:var(--accent)"></i>Rincian Achievement per Device</span>
                @if($project->achievements->isNotEmpty())
                    @if(abs($achievementDiff) < 0.01)
                        <span class="pj-pill ok"><i class="fa fa-check me-1"></i>Sesuai net sale</span>
                    @else
                        <span class="pj-pill danger"><i class="fa fa-triangle-exclamation me-1"></i>Selisih {{ \App\Models\Project::formatMoney(abs($achievementDiff)) }}</span>
                    @endif
                @endif
            </div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0 pj-table">
                        <thead>
                            <tr>
                                <th style="width:45px" class="text-center">#</th>
                                <th>Principal</th>
                                <th>Brand</th>
                                <th>Nama Device</th>
                                <th class="text-center">Qty</th>
                                <th>Divisi</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($project->achievements as $i => $row)
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td>{{ $row->principal ?: '—' }}</td>
                                <td>{{ $row->brand ?: '—' }}</td>
                                <td style="font-weight:600">{{ $row->device_name }}</td>
                                <td class="text-center">{{ trim(\App\Models\Project::formatNumber($row->qty).' '.$row->unit) }}</td>
                                <td>{{ $row->division?->division_name ?? '—' }}</td>
                                <td class="text-end">{{ \App\Models\Project::formatMoney($row->amount) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4" style="color:var(--text-muted)">
                                    <i class="fa-solid fa-inbox me-1"></i> Belum ada rincian device.
                                    @if($canUpdate ?? false)
                                        <a href="{{ route('project.achievements', $project->id) }}" class="ms-1">Tambahkan sekarang</a>
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($project->achievements->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-end pj-muted" style="font-size:13px">Total Rincian Device</td>
                                <td class="text-end fw-bold">{{ \App\Models\Project::formatMoney($achievementTotal) }}</td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-custom fade-in h-100">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-user-tie me-2" style="color:var(--accent)"></i>Pembagian Achievement Sales</span>
            </div>
            <div class="card-body-custom">
                @forelse($project->salesAchievements as $row)
                    @php
                        $pct = $project->net_sale_amount > 0 ? $row->amount_achieved / $project->net_sale_amount * 100 : 0;
                    @endphp
                    <div class="pj-sales-item">
                        <span class="pj-avatar">{{ $initials($row->salesPerson?->display_name) }}</span>
                        <div class="flex-grow-1" style="min-width:0">
                            <div class="d-flex justify-content-between gap-2">
                                <span style="font-weight:600;font-size:13.5px">{{ $row->salesPerson?->display_name ?? '—' }}</span>
                                <span style="font-weight:700;font-size:13.5px;font-variant-numeric:tabular-nums">{{ \App\Models\Project::formatMoney($row->amount_achieved) }}</span>
                            </div>
                            <div class="pj-bar"><span style="width:{{ min(100, $pct) }}%"></span></div>
                            <div class="pj-muted mt-1">{{ \App\Models\Project::formatNumber($pct) }}% dari net sale</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4" style="color:var(--text-muted);font-size:13px">
                        <i class="fa-solid fa-user-plus me-1"></i> Belum ada sales.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-4">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-file-invoice-dollar me-2" style="color:var(--accent)"></i>Invoice Project <span class="pj-muted">({{ $project->invoices->count() }})</span></span>
        @if($canCreateInvoice ?? false)
            <a href="{{ route('invoice.create', ['project_id' => $project->id]) }}" class="btn btn-secondary btn-sm">
                <i class="fa fa-plus me-1"></i> Buat Invoice
            </a>
        @endif
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. Invoice</th>
                        <th>Tanggal</th>
                        <th>Termin</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Approval</th>
                        <th class="text-center">Status Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($project->invoices as $inv)
                    <tr @if($inv->status === \App\Models\Invoice::STATUS_CANCELLED) style="opacity:.55" @endif>
                        <td><a href="{{ route('invoice.show', $inv->id) }}" class="fw-semibold text-decoration-none">{{ $inv->invoice_number }}</a></td>
                        <td>{{ $inv->invoice_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            {{ $inv->noted ?: 'Pembayaran' }} <span class="pj-muted">({{ \App\Models\Invoice::formatNumber($inv->percent_payment) }}%)</span>
                            @if($inv->isOutsideTimeline())
                                <span class="status-badge ms-1" style="background:#fef3c7;color:#92400e;" title="{{ $inv->non_term_reason }}">Di luar timeline</span>
                            @endif
                        </td>
                        <td>{{ $inv->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end">{{ \App\Models\Invoice::formatMoney($inv->total_payment) }}</td>
                        <td class="text-center">{!! $inv->approvalBadgeHtml() !!}</td>
                        <td class="text-center">{!! $inv->statusBadgeHtml() !!}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4" style="color:var(--text-muted)">
                            <i class="fa-solid fa-inbox me-1"></i> Belum ada invoice untuk project ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Biaya pembelian project (Finance): realisasi vs Estimasi PL --}}
{{-- @if($purchaseCost)
@php $pc = $purchaseCost; $fmt = fn ($v) => \App\Support\Number::money((float) $v); @endphp
<div class="card-custom fade-in mb-4">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fa-solid fa-coins me-2" style="color:var(--accent)"></i>Biaya Pembelian <span class="pj-muted">(sebelum pajak, {{ \App\Models\Currency::baseName() }})</span></span>
        @if($pc['estimate'] !== null)
            <span class="pj-pill {{ $pc['variance'] < 0 ? 'danger' : 'ok' }}">{{ $pc['variance'] < 0 ? 'Melebihi estimasi '.$fmt(abs($pc['variance'])) : 'Sisa anggaran '.$fmt($pc['variance']) }}</span>
        @endif
    </div>
    <div class="card-body-custom">
        <div class="row g-3">
            @foreach([['Komitmen (PO/DP)', $pc['commitment']], ['Diterima', $pc['received']], ['Ditagih', $pc['billed']], ['Terbayar', $pc['paid']], ['Alokasi Stok', $pc['stock_allocated']], ['Total Biaya', $pc['actual']], ['Estimasi Biaya (PL)', $pc['estimate']]] as [$label, $value])
                <div class="col-6 col-md-3">
                    <div class="pj-label">{{ $label }}</div>
                    <div class="fw-semibold" style="font-variant-numeric:tabular-nums">{{ $value !== null ? $fmt($value) : '—' }}</div>
                </div>
            @endforeach
        </div>
        @if($pc['estimate'])
            @php $pct = min(100, $pc['actual'] / $pc['estimate'] * 100); @endphp
            <div style="height:6px;border-radius:99px;background:#eef2f7;overflow:hidden;margin-top:14px" title="Realisasi terhadap estimasi"><span style="display:block;height:100%;width:{{ $pct }}%;background:{{ $pc['variance'] < 0 ? '#dc2626' : 'var(--accent)' }}"></span></div>
            <div class="pj-muted mt-1">Realisasi {{ \App\Support\Number::format($pc['actual'] / $pc['estimate'] * 100, 1) }}% dari estimasi biaya.</div>
        @endif
    </div>
</div>
@endif --}}

{{-- Barang project: stok teralokasi (belum dikirim) & Delivery Order --}}
<div class="card-custom fade-in mb-4">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-truck me-2" style="color:var(--accent)"></i>Barang &amp; Pengiriman <span class="pj-muted">({{ $project->deliveryOrders->count() }} DO)</span></span>
        @if($canCreateDeliveryOrder && $projectGoods->sum('in_stock') > 0)
            <a href="{{ route('delivery-order.create', ['project_id' => $project->id]) }}" class="btn btn-secondary btn-sm">
                <i class="fa fa-plus me-1"></i> Buat Delivery Order
            </a>
        @endif
    </div>
    <div class="card-body-custom p-2">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="pj-label px-2">Barang Milik Project</div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th class="text-center">Di Gudang</th>
                                <th class="text-center">Terkirim</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projectGoods as $g)
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="font-size:13px">{{ $g['name'] }}</div>
                                    <div class="pj-muted">{{ $g['code'] }}@if($g['locations']) · {{ implode(', ', $g['locations']) }}@endif</div>
                                </td>
                                <td class="text-center">{{ $g['in_stock'] }} {{ $g['unit'] }}</td>
                                <td class="text-center">{{ $g['delivered'] }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-4" style="color:var(--text-muted)">
                                    <i class="fa-solid fa-inbox me-1"></i> Belum ada barang teralokasi ke project ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="pj-label px-2">Delivery Order</div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>No. DO</th>
                                <th>Tanggal</th>
                                <th>Penerima</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($project->deliveryOrders as $do)
                            <tr>
                                <td><a href="{{ route('delivery-order.show', $do->id) }}" class="fw-semibold text-decoration-none">{{ $do->do_number }}</a></td>
                                <td>{{ $do->delivery_date?->format('d/m/Y') }}</td>
                                <td>{{ $do->recipient_name }}</td>
                                <td class="text-center">{!! $do->statusBadgeHtml() !!}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4" style="color:var(--text-muted)">
                                    <i class="fa-solid fa-inbox me-1"></i> Belum ada Delivery Order.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('ims-project._tasks', ['tasks' => $project->tasks])
@endsection

@push('modals')
<div class="modal fade" id="pjDocsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h6 class="modal-title" id="pj-docs-title">Dokumen</h6>
                    <div class="pj-muted" id="pj-docs-subtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="pj-docs-list"></div>
                @if($canUploadDocs)
                <div class="mt-3 p-3" style="background:var(--accent-subtle);border-radius:var(--radius-sm)">
                    <div class="fw-semibold mb-2" style="font-size:13px"><i class="fa fa-upload me-1" style="color:var(--accent)"></i> Upload dokumen baru</div>
                    <div class="row g-2">
                        <div class="col-md-7">
                            <input type="file" id="pj-docs-files" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" multiple>
                            <div class="pj-muted mt-1">PDF, JPG, atau PNG, maksimal 10MB per file.</div>
                        </div>
                        <div class="col-md-5">
                            <input type="text" id="pj-docs-notes" class="form-control form-control-sm" maxlength="255" placeholder="Keterangan (opsional)">
                        </div>
                    </div>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                @if($canUploadDocs)
                <button type="button" class="btn btn-primary btn-sm" id="pj-docs-upload-btn" onclick="pjUploadDocs()">
                    <i class="fa fa-upload me-1"></i> Upload
                </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const pjDeleteUrl = '{{ route("project.destroy", $project->id) }}';
const pjDocUploadUrl = '{{ route("project.documents.store", $project->id) }}';
const pjTermAccessUrl = '{{ route("project.billing-terms.access", [$project->id, "__TERM__"]) }}';

// Gerbang tagihan termin: buka (Open, bisa dibuatkan invoice) atau kunci kembali.
function pjSetTermAccess(termId, lock) {
    var term = pjTerms[termId] || {};
    Swal.fire({
        title: lock ? 'Kunci kembali termin?' : 'Buka termin untuk ditagih?',
        html: lock
            ? '<b>' + pjEsc(term.name) + '</b> tidak bisa dibuatkan invoice sampai dibuka lagi.'
            : '<b>' + pjEsc(term.name) + ' (' + term.percent + '%)</b> akan berstatus Open dan bisa dibuatkan invoice. Tim invoice akan menerima notifikasi.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: lock ? 'Ya, Kunci' : 'Ya, Buka Tagihan',
        cancelButtonText: 'Batal'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.ajax({
            url: pjTermAccessUrl.replace('__TERM__', termId),
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', _method: 'PATCH', locked: lock ? 1 : 0 }
        }).done(function(res) {
            toastr.success(res.message || 'Tersimpan.');
            setTimeout(function() { window.location.hash = 'timeline-penagihan'; window.location.reload(); }, 600);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal mengubah status termin.');
        });
    });
}
const pjCanDeleteDocs = {{ ($canDelete ?? false) ? 'true' : 'false' }};
const pjTerms = @json($terms->mapWithKeys(fn ($t) => [$t->id => ['name' => $t->name, 'percent' => $t->percent, 'description' => $t->description]]));
// Dokumen per termin (key = id termin) dan kontrak project (key "project").
const pjDocs = Object.assign({ project: @json($agreements) }, @json($termDocuments));
let pjDocsTermId = null;
let pjDocsModal = null;

function pjEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function pjDocKey(termId) {
    return termId ? String(termId) : 'project';
}

function pjDocItemHtml(doc, key) {
    var html = '<div class="pj-doc-item">';
    html += '<i class="fa ' + pjEsc(doc.icon) + '"></i>';
    html += '<div style="min-width:0;flex:1"><div class="name">' + pjEsc(doc.file_name) + '</div>';
    html += '<div class="pj-muted">' + (doc.notes ? pjEsc(doc.notes) + ' · ' : '') + pjEsc(doc.size) + ' · ' + pjEsc(doc.uploaded_by || '—') + ', ' + pjEsc(doc.uploaded_at || '') + '</div></div>';
    html += '<a href="' + doc.view_url + '" target="_blank" class="btn-icon" title="Lihat"><i class="fa fa-eye"></i></a>';
    if (pjCanDeleteDocs && doc.delete_url) {
        html += '<button type="button" class="btn-icon text-danger" title="Hapus" onclick="pjDeleteDoc(\'' + doc.delete_url + '\', \'' + key + '\')"><i class="fa fa-trash"></i></button>';
    } else if (!doc.delete_url) {
        html += '<span class="btn-icon" style="opacity:.4" title="Terkunci: invoice termin sudah Approved"><i class="fa fa-lock"></i></span>';
    }
    return html + '</div>';
}

function pjRenderDocs() {
    var key = pjDocKey(pjDocsTermId);
    var docs = pjDocs[key] || [];
    $('#pj-docs-list').html(docs.length
        ? docs.map(function(d) { return pjDocItemHtml(d, key); }).join('')
        : '<div class="text-center py-3 pj-muted"><i class="fa fa-paperclip me-1"></i>Belum ada dokumen.</div>');

    if (pjDocsTermId) $('[data-doc-count="' + pjDocsTermId + '"]').text(docs.length).closest('.pj-doc-btn').toggleClass('empty', !docs.length);
    pjRenderAgreements();
}

function pjRenderAgreements() {
    var docs = pjDocs.project || [];
    $('#pj-agreement-docs').html(docs.length
        ? docs.map(function(d) { return pjDocItemHtml(d, 'project'); }).join('')
        : '<div class="pj-doc-item pj-muted"><i class="fa fa-file-circle-xmark" style="color:var(--text-muted)"></i> Belum ada dokumen pendukung.</div>');
}

function pjOpenDocs(termId) {
    pjDocsTermId = termId;
    var term = termId ? pjTerms[termId] : null;
    $('#pj-docs-title').text(term ? 'Dokumen Pendukung — ' + term.name : 'Dokumen Pendukung');
    $('#pj-docs-subtitle').text(term
        ? (term.description || '') + (term.description ? ' · ' : '') + 'Dokumen ini ikut tampil di invoice termin.'
        : 'Dokumen dasar project, ditampilkan bersama PO customer.');
    $('#pj-docs-files').val('');
    $('#pj-docs-notes').val('');
    pjRenderDocs();
    if (!pjDocsModal) pjDocsModal = new bootstrap.Modal(document.getElementById('pjDocsModal'));
    pjDocsModal.show();
}

function pjUploadDocs() {
    var files = document.getElementById('pj-docs-files').files;
    if (!files.length) { toastr.error('Pilih minimal satu file.'); return; }
    for (var i = 0; i < files.length; i++) {
        if (files[i].size > 10 * 1024 * 1024) { toastr.error('File "' + files[i].name + '" lebih dari 10MB.'); return; }
    }

    var fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    if (pjDocsTermId) fd.append('billing_term_id', pjDocsTermId);
    fd.append('notes', $('#pj-docs-notes').val());
    for (var f = 0; f < files.length; f++) fd.append('files[' + f + ']', files[f]);

    var $btn = $('#pj-docs-upload-btn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Mengupload...');
    $.ajax({ url: pjDocUploadUrl, method: 'POST', data: fd, processData: false, contentType: false })
        .done(function(res) {
            pjDocs[pjDocKey(pjDocsTermId)] = res.documents || [];
            $('#pj-docs-files').val('');
            $('#pj-docs-notes').val('');
            pjRenderDocs();
            toastr.success(res.message || 'Dokumen diupload.');
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal mengupload dokumen.');
        })
        .always(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i> Upload');
        });
}

function pjDeleteDoc(url, key) {
    Swal.fire({
        title: 'Hapus dokumen?',
        text: 'File akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.ajax({ url: url, method: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function(res) {
                pjDocs[key] = (pjDocs[key] || []).filter(function(d) { return d.delete_url !== url; });
                if (key !== 'project') $('[data-doc-count="' + key + '"]').text(pjDocs[key].length).closest('.pj-doc-btn').toggleClass('empty', !pjDocs[key].length);
                pjRenderDocs();
                toastr.success(res.message || 'Dokumen dihapus.');
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus dokumen.');
            });
    });
}

$(document).ready(pjRenderAgreements);

function pjDeleteProject() {
    Swal.fire({
        title: 'Hapus Project?',
        text: 'Data project, achievement, dan pembagian sales akan dihapus.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({ url: pjDeleteUrl, method: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function(res) {
                toastr.success(res.message || 'Project dihapus.');
                setTimeout(function() { window.location.href = '{{ route("project.index") }}'; }, 600);
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus project.');
            });
    });
}
</script>
@endsection

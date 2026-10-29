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
    .pj-table tfoot tr:first-child td { border-top: 2px solid var(--card-border); }
</style>
@endsection

@section('content')
@php
    $achievementTotal = (float) $project->achievements->sum('amount');
    $achievementDiff = round($achievementTotal - (float) $project->net_sale_amount, 2);

    $activeInvoices = $project->invoices->where('status', '!=', \App\Models\Invoice::STATUS_CANCELLED);
    $paidInvoices = $project->invoices->where('status', \App\Models\Invoice::STATUS_PAID);
    $billedPercent = min(100, (float) $activeInvoices->sum('percent_payment'));
    $paidPercent = min(100, (float) $paidInvoices->sum('percent_payment'));
    $billedAmount = (float) $activeInvoices->sum('total_payment');
    $paidAmount = (float) $paidInvoices->sum('total_payment');

    $dueDays = $project->po_due_date
        ? (int) now()->startOfDay()->diffInDays($project->po_due_date->copy()->startOfDay(), false)
        : null;

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
        <a href="{{ route('project.index') }}" class="btn btn-secondary btn-sm">
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
            <div class="spacer"></div>
            @if($project->hasPoDocument())
                <a href="{{ route('project.po', $project->id) }}" target="_blank" class="btn btn-soft btn-sm">
                    <i class="fa fa-file-pdf me-1"></i> Lihat PO
                </a>
            @endif
            @if($canCreateInvoice ?? false)
                <a href="{{ route('invoice.create', ['project_id' => $project->id]) }}" class="btn btn-soft btn-sm">
                    <i class="fa fa-file-invoice-dollar me-1"></i> Buat Invoice
                </a>
            @endif
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
    <div class="pj-kpi primary">
        <div class="pj-label">Net Sale Amount</div>
        <div class="val">{{ \App\Models\Project::formatMoney($project->net_sale_amount) }}</div>
        <div class="sub">Nilai riil project (Profit Estimate)</div>
    </div>
    <div class="pj-kpi">
        <div class="pj-label">Total Sale Amount</div>
        <div class="val">{{ \App\Models\Project::formatMoney($project->total_sale_amount) }}</div>
        <div class="sub">Nilai quotation</div>
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
                <span><i class="fa-solid fa-calendar-days me-2" style="color:var(--accent)"></i>Timeline PO</span>
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
                </ul>

                @if($project->hasPoDocument())
                    <a href="{{ route('project.po', $project->id) }}" target="_blank" class="pj-doc text-decoration-none">
                        <i class="fa fa-file-pdf"></i>
                        <div style="min-width:0">
                            <div style="font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $project->po_document_name }}</div>
                            <div class="pj-muted">Diunggah {{ $project->poUploader?->display_name ?? '—' }}</div>
                        </div>
                        <i class="fa fa-arrow-up-right-from-square ms-auto" style="color:var(--text-muted);font-size:12px"></i>
                    </a>
                @else
                    <div class="pj-doc pj-muted"><i class="fa fa-file-circle-xmark" style="color:var(--text-muted)"></i> Dokumen PO belum diunggah.</div>
                @endif
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
                                <td style="font-weight:600">{{ $row->device_name }}</td>
                                <td class="text-center">{{ trim(\App\Models\Project::formatNumber($row->qty).' '.$row->unit) }}</td>
                                <td>{{ $row->division?->division_name ?? '—' }}</td>
                                <td class="text-end">{{ \App\Models\Project::formatMoney($row->amount) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4" style="color:var(--text-muted)">
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
                                <td colspan="4" class="text-end pj-muted" style="font-size:13px">Total Rincian Device</td>
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
                        <td>{{ $inv->noted ?: 'Pembayaran' }} <span class="pj-muted">({{ \App\Models\Invoice::formatNumber($inv->percent_payment) }}%)</span></td>
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
@endsection

@section('scripts')
<script>
const pjDeleteUrl = '{{ route("project.destroy", $project->id) }}';

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

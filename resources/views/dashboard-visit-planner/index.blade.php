@extends('layouts.app')

@section('title', 'Dashboard Task Planner')
@section('page-title', 'Dashboard Task Planner')

@section('styles')
<style>
    .dash-stat-card {
        border-radius: var(--radius);
        padding: 20px 22px;
        background: #fff;
        border: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: box-shadow 0.2s;
    }
    .dash-stat-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    .dash-stat-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .dash-stat-content { flex: 1; min-width: 0; }
    .dash-stat-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }
    .dash-stat-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .dash-stat-value.danger { color: #dc3545; }
    .dash-stat-value.success { color: #10b981; }
    .dash-stat-value.warning { color: #f59e0b; }
    .dash-stat-value.info { color: #3b82f6; }

    .category-progress {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 0;
    }
    .category-progress .cat-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        width: 150px;
        flex-shrink: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .category-progress .cat-bar-wrap {
        flex: 1;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        min-width: 100px;
    }
    .category-progress .cat-bar-fill {
        height: 100%;
        background: var(--accent);
        border-radius: 4px;
        transition: width 0.5s ease;
    }
    .category-progress .cat-stat {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        width: 60px;
        text-align: right;
        flex-shrink: 0;
    }

    /* Jadwal visit 7 hari kerja */
    .vs-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .vs-filter select, .vs-filter input[type=text] {
        padding: 6px 10px; border: 1px solid var(--card-border); border-radius: var(--radius-sm);
        font-size: 12.5px; color: var(--text-primary); background: #fff;
    }
    .vs-filter label.vs-check { font-size: 12.5px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 5px; margin: 0; cursor: pointer; }
    .vs-summary { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; font-size: 13px; color: var(--text-muted); }
    .vs-summary strong { color: var(--text-primary); font-size: 15px; }
    .vs-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 11.5px; color: var(--text-muted); margin-left: auto; }
    .vs-legend span::before { content: ''; display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 4px; vertical-align: -1px; background: var(--c); }

    .vs-wrap { overflow-x: auto; border: 1px solid var(--card-border); border-radius: var(--radius-sm); }
    .vs-table { width: 100%; min-width: 980px; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .vs-table th, .vs-table td { border-bottom: 1px solid var(--card-border); border-right: 1px solid var(--card-border); vertical-align: top; padding: 6px; }
    .vs-table th:last-child, .vs-table td:last-child { border-right: 0; }
    .vs-table thead th { background: #f8fafc; text-align: center; font-weight: 600; color: var(--text-primary); position: sticky; top: 0; z-index: 2; }
    .vs-table thead th .d { display: block; font-size: 11px; font-weight: 500; color: var(--text-muted); }
    .vs-table .vs-person { position: sticky; left: 0; z-index: 1; background: #fff; width: 190px; min-width: 190px; }
    .vs-table thead .vs-person { z-index: 3; background: #f8fafc; text-align: left; }
    .vs-table .vs-person .n { font-weight: 600; color: var(--text-primary); }
    .vs-table .vs-person .c { font-size: 11px; color: var(--text-muted); }
    .vs-table td.vs-day { width: calc((100% - 190px) / 7); min-width: 110px; }
    .vs-table .is-today { background: var(--accent-subtle, #f0fdfa); }
    .vs-table thead th.is-today { background: var(--accent-soft); color: var(--accent); }
    .vs-table tr.vs-group td { background: #f1f5f9; font-weight: 700; font-size: 12px; cursor: pointer; padding: 7px 10px; }
    .vs-table tr.vs-group td .meta { font-weight: 500; color: var(--text-muted); margin-left: 6px; }
    .vs-table tr.vs-group .chev { transition: transform .15s; display: inline-block; margin-right: 6px; color: var(--text-muted); }
    .vs-table tr.vs-group.collapsed .chev { transform: rotate(-90deg); }
    .vs-table tfoot td { background: #f8fafc; font-weight: 700; text-align: center; }
    .vs-table tfoot td.vs-person { text-align: left; background: #f8fafc; }

    .vs-chip {
        display: block; text-decoration: none; border-radius: 6px; padding: 4px 6px; margin-bottom: 4px;
        border-left: 3px solid var(--c); background: color-mix(in srgb, var(--c) 10%, #fff); color: var(--text-primary);
        line-height: 1.25; overflow: hidden;
    }
    .vs-chip:hover { box-shadow: 0 1px 6px rgba(0,0,0,.08); color: var(--text-primary); }
    .vs-chip .t { font-weight: 700; font-size: 11px; color: var(--c); }
    .vs-chip .l { font-weight: 600; font-size: 11.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
    .vs-chip .g { font-size: 10.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
    .vs-more { font-size: 11px; color: var(--accent); cursor: pointer; background: none; border: 0; padding: 0; }
    .vs-chip.extra { display: none; }
    .vs-day.expanded .vs-chip.extra { display: block; }
    .vs-empty-cell { color: #cbd5e1; text-align: center; font-size: 11px; padding-top: 4px; }


    /* Report visit per salesperson */
    .vd-people { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
    .vd-person {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 12px 14px; border: 1px solid var(--card-border); border-left: 3px solid #10b981;
        border-radius: var(--radius-sm); background: #fff;
    }
    .vd-person .n { font-size: 13.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .vd-person .d { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .vd-person .c { font-size: 24px; font-weight: 700; line-height: 1; color: #10b981; flex-shrink: 0; }
    .vd-person.zero { border-left-color: var(--card-border); background: #f8fafc; }
    .vd-person.zero .c { color: var(--text-muted); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Dashboard Task Planner</h1>
        <p class="page-header-sub">Jadwal visit lintas divisi dan monitoring tugas Anda</p>
    </div>
</div>


@php
    $vs = $visit;
    $dayNames = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum'];
    $statusColor = [
        'todo' => '#f59e0b',
        'in_progress' => '#3b82f6',
        'waiting_approval' => '#a855f7',
        'done' => '#10b981',
        'cancelled' => '#ef4444',
    ];
    $statusLabel = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'waiting_approval' => 'Waiting Approval', 'done' => 'Done', 'cancelled' => 'Cancelled'];
    $doneParams = request()->only(['done_period', 'done_year', 'done_quarter', 'done_start', 'done_end']);
    $visitParams = request()->only(['visit_start', 'visit_division', 'visit_category', 'visit_status', 'visit_q']);
    $vsQuery = fn (array $over) => route('dashboard-visit-planner.index', array_filter(array_merge($doneParams, [
        'visit_start' => $vs['days'][0]->toDateString(),
        'visit_division' => $vs['filters']['division_id'],
        'visit_category' => $vs['filters']['category_id'],
        'visit_status' => $vs['filters']['status'],
        'visit_q' => $vs['filters']['q'] ?: null,
    ], $over), fn ($v) => $v !== null && $v !== '')).'#jadwal-visit';
    $firstDay = $vs['days'][0];
    $lastDay = end($vs['days']);
@endphp

<div class="card-custom fade-in mb-4" id="jadwal-visit">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fa fa-route me-2" style="color:var(--accent)"></i>Jadwal Visit · 7 Hari Kerja
            <span style="font-size:12px;color:var(--text-muted);font-weight:500">{{ $firstDay->format('d M') }} – {{ $lastDay->format('d M Y') }}</span>
        </span>
        <div class="d-flex gap-1">
            <a href="{{ $vsQuery(['visit_start' => $vs['prev_start']]) }}" class="btn btn-sm btn-ghost" title="7 hari kerja sebelumnya"><i class="fa fa-chevron-left"></i></a>
            <a href="{{ $vsQuery(['visit_start' => null]) }}" class="btn btn-sm {{ $vs['is_current'] ? 'btn-accent' : 'btn-ghost' }}" style="font-size:12px">Hari ini</a>
            <a href="{{ $vsQuery(['visit_start' => $vs['next_start']]) }}" class="btn btn-sm btn-ghost" title="7 hari kerja berikutnya"><i class="fa fa-chevron-right"></i></a>
        </div>
    </div>
    <div class="card-body-custom">
        <form method="GET" action="{{ route('dashboard-visit-planner.index') }}#jadwal-visit" class="vs-filter mb-3">
            <input type="hidden" name="visit_start" value="{{ $firstDay->toDateString() }}">
            @foreach($doneParams as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="visit_division" onchange="this.form.submit()">
                <option value="">Semua Divisi</option>
                @foreach($vs['divisions'] as $d)
                    <option value="{{ $d->id }}" @selected($vs['filters']['division_id'] === $d->id)>{{ $d->division_name }}</option>
                @endforeach
            </select>
            <select name="visit_category" onchange="this.form.submit()">
                <option value="">Semua Kategori Visit</option>
                @foreach($vs['categories'] as $c)
                    <option value="{{ $c->id }}" @selected($vs['filters']['category_id'] === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <select name="visit_status" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                @foreach($statusLabel + ['late' => 'Terlambat'] as $key => $label)
                    <option value="{{ $key }}" @selected($vs['filters']['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="text" name="visit_q" value="{{ $vs['filters']['q'] }}" placeholder="Cari nama..." style="width:160px">
            <button type="submit" class="btn btn-sm btn-accent" style="font-size:12px"><i class="fa fa-search me-1"></i>Terapkan</button>
            @if(array_filter($vs['filters']))
                <a href="{{ route('dashboard-visit-planner.index', ['visit_start' => $firstDay->toDateString()] + $doneParams) }}#jadwal-visit" style="font-size:12px">Reset</a>
            @endif
        </form>

        <div class="vs-summary mb-2">
            <span><strong>{{ $vs['summary']['tasks'] }}</strong> visit</span>
            <span><strong>{{ $vs['summary']['people'] }}</strong> salesperson</span>
            <span><strong>{{ $vs['summary']['divisions'] }}</strong> divisi</span>
            <div class="vs-legend">
                @foreach($statusLabel as $key => $label)
                    <span style="--c:{{ $statusColor[$key] }}">{{ $label }}</span>
                @endforeach
                <span style="--c:#dc2626">Terlambat</span>
                <span style="--c:transparent">📍 Sudah check-in</span>
            </div>
        </div>

        @if($vs['groups']->isEmpty())
            <div class="empty-state">
                <i class="fa fa-route"></i>
                <p>Tidak ada task visit salesperson pada {{ $firstDay->format('d M') }} – {{ $lastDay->format('d M Y') }}{{ array_filter($vs['filters']) ? ' dengan filter ini' : '' }}.</p>
            </div>
        @else
            <div class="vs-wrap">
                <table class="vs-table">
                    <thead>
                        <tr>
                            <th class="vs-person">Orang</th>
                            @foreach($vs['days'] as $day)
                                <th class="{{ $day->equalTo($vs['today']) ? 'is-today' : '' }}">
                                    {{ $dayNames[$day->dayOfWeekIso] }} {{ $day->format('d') }}
                                    <span class="d">{{ $day->equalTo($vs['today']) ? 'Hari ini' : $day->format('M') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vs['groups'] as $gi => $group)
                            <tr class="vs-group" data-group="{{ $gi }}" onclick="vsToggleGroup(this)">
                                <td colspan="{{ count($vs['days']) + 1 }}">
                                    <span class="chev"><i class="fa fa-chevron-down"></i></span>{{ $group['name'] }}
                                    <span class="meta">{{ $group['rows']->count() }} orang · {{ $group['visits'] }} visit</span>
                                </td>
                            </tr>
                            @foreach($group['rows'] as $row)
                                <tr data-group-row="{{ $gi }}">
                                    <td class="vs-person">
                                        <div class="n">{{ $row['user']->display_name }}</div>
                                        <div class="c">{{ $row['count'] }} visit</div>
                                    </td>
                                    @foreach($vs['days'] as $day)
                                        @php $items = $row['days'][$day->toDateString()]; @endphp
                                        <td class="vs-day {{ $day->equalTo($vs['today']) ? 'is-today' : '' }}">
                                            @forelse($items as $i => $item)
                                                @php $c = $item['late'] ? '#dc2626' : ($statusColor[$item['status']] ?? '#94a3b8'); @endphp
                                                <a href="{{ route('task-planner.show', $item['id']) }}" class="vs-chip {{ $i >= 2 ? 'extra' : '' }}" style="--c:{{ $c }}"
                                                   title="{{ $item['title'] }} · {{ $item['category'] }} · {{ $item['late'] ? 'Terlambat' : ($statusLabel[$item['status']] ?? $item['status']) }}{{ $item['target'] ? ' · '.$item['target'] : '' }}">
                                                    <span class="t">{{ $item['time'] ?? '—' }} · {{ $item['category'] }}</span>@if($item['checked_in']) 📍@endif
                                                    <span class="l">{{ $item['title'] }}</span>
                                                    @if($item['target'])<span class="g">{{ $item['target'] }}</span>@endif
                                                </a>
                                            @empty
                                                <div class="vs-empty-cell">·</div>
                                            @endforelse
                                            @if(count($items) > 2)
                                                <button type="button" class="vs-more" onclick="vsExpand(this)">+{{ count($items) - 2 }} lainnya</button>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="vs-person">Total / hari</td>
                            @foreach($vs['days'] as $day)
                                <td class="{{ $day->equalTo($vs['today']) ? 'is-today' : '' }}">{{ $vs['day_totals'][$day->toDateString()] }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Report visit per salesperson --}}
@php
    $vd = $visitDone;
    $vdLabel = match ($vd['period']) {
        'quarter' => 'Q'.$vd['quarter'].' '.$vd['year'],
        'custom' => 'Rentang tanggal',
        default => 'Minggu ini',
    };
@endphp
<div class="card-custom fade-in mb-4" id="report-visit">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fa fa-clipboard-list me-2" style="color:var(--accent)"></i>Report Visit
            <span style="font-size:12px;color:var(--text-muted);font-weight:500">{{ $vdLabel }} · {{ $vd['start']->format('d M Y') }} – {{ $vd['end']->format('d M Y') }}</span>
        </span>
        <form method="GET" action="{{ route('dashboard-visit-planner.index') }}#report-visit" class="vs-filter" id="vd-form">
            @foreach($visitParams as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="done_period" id="vd-period">
                <option value="week" @selected($vd['period'] === 'week')>Minggu ini (Sen–Jum)</option>
                <option value="quarter" @selected($vd['period'] === 'quarter')>Per kuartal</option>
                <option value="custom" @selected($vd['period'] === 'custom')>Rentang tanggal</option>
            </select>
            <span class="vd-opt" data-for="quarter">
                <select name="done_quarter">
                    @foreach([1, 2, 3, 4] as $q)
                        <option value="{{ $q }}" @selected($vd['quarter'] === $q)>Q{{ $q }}</option>
                    @endforeach
                </select>
                <select name="done_year">
                    @foreach(range(now()->year, now()->year - 4) as $y)
                        <option value="{{ $y }}" @selected($vd['year'] === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </span>
            <span class="vd-opt" data-for="custom">
                <input type="date" name="done_start" value="{{ $vd['start']->toDateString() }}">
                <span style="font-size:12px;color:var(--text-muted)">s/d</span>
                <input type="date" name="done_end" value="{{ $vd['end']->toDateString() }}">
            </span>
            <button type="submit" class="btn btn-sm btn-accent" style="font-size:12px">Terapkan</button>
        </form>
    </div>
    <div class="card-body-custom">
        @if($vd['people']->isEmpty())
            <div class="empty-state">
                <i class="fa fa-user-tie"></i>
                <p>Belum ada user bertanda Salesperson. Atur di User Management.</p>
            </div>
        @else
            <div class="vd-people">
                @foreach($vd['people'] as $p)
                    <div class="vd-person {{ $p['done'] === 0 ? 'zero' : '' }}">
                        <div style="min-width:0;flex:1">
                            <div class="n" title="{{ $p['user']->display_name }}">{{ $p['user']->display_name }}</div>
                            <div class="d">{{ $p['division'] }}</div>
                        </div>
                        <div class="text-end">
                            <div class="c">{{ $p['done'] }}</div>
                            <div class="d">visit</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <div style="font-size:11.5px;color:var(--text-muted);margin-top:10px">
            Jumlah visit selesai (task kategori visit berstatus Done) per salesperson pada periode ini, berdasarkan jatuh tempo task.
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
function vdSyncPeriod() {
    var period = document.getElementById('vd-period').value;
    document.querySelectorAll('#vd-form .vd-opt').forEach(function(el) {
        var active = el.getAttribute('data-for') === period;
        el.style.display = active ? '' : 'none';
        el.querySelectorAll('input, select').forEach(function(input) { input.disabled = !active; });
    });
}
document.addEventListener('DOMContentLoaded', function() {
    var period = document.getElementById('vd-period');
    if (!period) return;
    period.addEventListener('change', function() {
        vdSyncPeriod();
        if (this.value === 'week') this.form.submit();
    });
    vdSyncPeriod();
});

function vsToggleGroup(tr) {
    var group = tr.getAttribute('data-group');
    var collapsed = tr.classList.toggle('collapsed');
    document.querySelectorAll('tr[data-group-row="' + group + '"]').forEach(function(row) {
        row.style.display = collapsed ? 'none' : '';
    });
}

function vsExpand(btn) {
    var cell = btn.closest('.vs-day');
    var expanded = cell.classList.toggle('expanded');
    btn.textContent = expanded ? 'Sembunyikan' : '+' + cell.querySelectorAll('.vs-chip.extra').length + ' lainnya';
}
</script>
@endsection

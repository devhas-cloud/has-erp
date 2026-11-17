@extends('layouts.app')

@section('title', 'Project')
@section('page-title', 'Project')

@section('styles')
<style>
    .pj-tag-ok, .pj-tag-warn, .pj-tag-danger { display: inline-block; margin-top: 2px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
    .pj-tag-ok { background: var(--accent-soft); color: var(--accent); }
    .pj-tag-warn { background: #fef3c7; color: #92400e; }
    .pj-tag-danger { background: var(--danger-soft); color: #7f1d1d; }

    /* ===== Filter toolbar (List & Achievements) ===== */
    .pj-toolbar { margin: 6px 6px 14px; border: 1px solid var(--card-border); border-radius: var(--radius); background: linear-gradient(135deg, #f8fafc, #f1f5f9); }
    .pj-toolbar-main { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px 24px; padding: 14px 16px; }
    .pj-toolbar-group { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
    .pj-toolbar-filters { flex: 1 1 auto; min-width: 0; }
    .pj-select-filter { flex: 0 0 auto; }
    .pj-toolbar-label { font-size: 10.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
    .pj-toolbar-label i { color: var(--accent); margin-right: 5px; }
    .pj-toolbar-controls { display: flex; align-items: center; gap: 8px 10px; flex-wrap: wrap; }
    .pj-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .pj-chip { padding: 7px 13px; border-radius: 20px; border: 1px solid var(--card-border); background: var(--card); color: var(--text-secondary); font-size: 12px; font-weight: 700; font-family: inherit; line-height: 1.2; cursor: pointer; white-space: nowrap; transition: all 0.2s var(--ease); }
    .pj-chip:hover { border-color: rgba(16, 185, 129, 0.3); color: var(--accent); background: var(--accent-soft); }
    .pj-chip.active { background: linear-gradient(135deg, var(--accent), #059669); border-color: transparent; color: #fff; box-shadow: 0 2px 8px var(--accent-glow); }
    .pj-chip:focus-visible, .pj-summary-reset:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .pj-range { display: flex; align-items: center; gap: 6px; }
    .pj-range .form-control { width: 148px; padding: 6px 10px; font-size: 12.5px; }
    .pj-range-sep { color: var(--text-muted); font-size: 12px; font-weight: 700; }
    .pj-range.is-custom .form-control { border-color: var(--accent); }
    .pj-select-filter .form-select { min-width: 180px; padding: 7px 34px 7px 12px; font-size: 12.5px; font-weight: 600; }
    .pj-summary { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 9px 16px; border-top: 1px solid var(--card-border); font-size: 12.5px; color: var(--text-secondary); font-weight: 500; }
    .pj-summary strong { color: var(--text-primary); font-weight: 800; }
    .pj-summary-reset { border: none; background: none; padding: 2px 4px; color: var(--danger); font-size: 12px; font-weight: 700; font-family: inherit; cursor: pointer; border-radius: 6px; }
    .pj-summary-reset:hover { text-decoration: underline; }
    @media (max-width: 768px) {
        .pj-toolbar-main { align-items: stretch; flex-direction: column; }
        .pj-range { width: 100%; }
        .pj-range .form-control { flex: 1; width: auto; min-width: 0; }
        .pj-select-filter .form-select { width: 100%; min-width: 0; }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Project</h1>
        <p class="page-header-sub">Projects from closed quotations. Sales are selected in the project form; device details are managed in the Achievements tab.</p>
    </div>
    @if($canCreate ?? false)
    <div class="page-header-actions">
        <a href="{{ route('project.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Create Project</span>
        </a>
    </div>
    @endif
</div>

<ul class="nav nav-tabs mb-3" id="pj-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pj-tab-list-btn" data-bs-toggle="tab" data-bs-target="#pj-tab-list" type="button" role="tab">
            <i class="fa fa-list me-1"></i> List
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="pj-tab-ach-btn" data-bs-toggle="tab" data-bs-target="#pj-tab-ach" type="button" role="tab">
            <i class="fa fa-trophy me-1"></i> Achievements
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="pj-tab-list" role="tabpanel">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-diagram-project me-2" style="color:var(--accent)"></i>Project List</span>
            </div>
            <div class="card-body-custom p-2">
                <div class="pj-toolbar" id="pj-filter">
                    <div class="pj-toolbar-main">
                        <div class="pj-toolbar-filters">
                            <div class="pj-toolbar-group">
                                <span class="pj-toolbar-label"><i class="fa fa-calendar-days"></i>PO Date</span>
                                <div class="pj-toolbar-controls">
                                    <div class="pj-chips" role="group" aria-label="PO date range">
                                        <button type="button" class="pj-chip" data-preset="q1" title="January – March">Q1</button>
                                        <button type="button" class="pj-chip" data-preset="q2" title="April – June">Q2</button>
                                        <button type="button" class="pj-chip" data-preset="q3" title="July – September">Q3</button>
                                        <button type="button" class="pj-chip" data-preset="q4" title="October – December">Q4</button>
                                        <button type="button" class="pj-chip" data-preset="year" title="January – December">This Year</button>
                                        <button type="button" class="pj-chip" data-preset="all" title="No date filter">All</button>
                                    </div>
                                    <div class="pj-range">
                                        <input type="date" class="form-control pj-date-from" aria-label="PO date from" title="From date">
                                        <span class="pj-range-sep">–</span>
                                        <input type="date" class="form-control pj-date-to" aria-label="PO date to" title="To date">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pj-toolbar-group pj-select-filter">
                            <label class="pj-toolbar-label" for="pj-filter-status"><i class="fa fa-circle-check"></i>Status</label>
                            <select id="pj-filter-status" class="form-select" data-filter-label="Status" data-filter-all="All Statuses">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pj-toolbar-group pj-select-filter">
                            <label class="pj-toolbar-label" for="pj-filter-type"><i class="fa fa-tag"></i>Type</label>
                            <select id="pj-filter-type" class="form-select" data-filter-label="Type" data-filter-all="All Types">
                                <option value="">All Types</option>
                                @foreach($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="pj-summary" aria-live="polite">
                        <span class="pj-summary-text">Loading…</span>
                        <button type="button" class="pj-summary-reset" style="display:none">
                            <i class="fa fa-xmark me-1"></i>Clear filters
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="pj-table" class="table table-custom align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th>No Project</th>
                                <th>Project Name</th>
                                <th>Quotation</th>
                                <th>Company</th>
                                <th>Customer PO No.</th>
                                <th>PO Date</th>
                                <th class="text-end">Net Sale</th>
                                <th>Type</th>
                                <th>Warranty</th>
                                <th>Status</th>
                                <th class="text-center" style="width:120px">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pj-tab-ach" role="tabpanel">
        <div class="card-custom fade-in">
            <div class="card-header-custom">
                <span><i class="fa-solid fa-trophy me-2" style="color:var(--accent)"></i>Achievement Details per Device</span>
            </div>
            <div class="card-body-custom p-2">
                <div class="pj-toolbar" id="pja-filter">
                    <div class="pj-toolbar-main">
                        <div class="pj-toolbar-filters">
                            <div class="pj-toolbar-group">
                                <span class="pj-toolbar-label"><i class="fa fa-calendar-days"></i>PO Date</span>
                                <div class="pj-toolbar-controls">
                                    <div class="pj-chips" role="group" aria-label="PO date range">
                                        <button type="button" class="pj-chip" data-preset="q1" title="January – March">Q1</button>
                                        <button type="button" class="pj-chip" data-preset="q2" title="April – June">Q2</button>
                                        <button type="button" class="pj-chip" data-preset="q3" title="July – September">Q3</button>
                                        <button type="button" class="pj-chip" data-preset="q4" title="October – December">Q4</button>
                                        <button type="button" class="pj-chip" data-preset="year" title="January – December">This Year</button>
                                        <button type="button" class="pj-chip" data-preset="all" title="No date filter">All</button>
                                    </div>
                                    <div class="pj-range">
                                        <input type="date" class="form-control pj-date-from" aria-label="PO date from" title="From date">
                                        <span class="pj-range-sep">–</span>
                                        <input type="date" class="form-control pj-date-to" aria-label="PO date to" title="To date">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pj-toolbar-group pj-select-filter">
                            <label class="pj-toolbar-label" for="pja-filter-division"><i class="fa fa-building"></i>Division</label>
                            <select id="pja-filter-division" class="form-select" data-filter-label="Division" data-filter-all="All Divisions">
                                <option value="">All Divisions</option>
                                @foreach($divisions as $d)
                                <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="pj-summary" aria-live="polite">
                        <span class="pj-summary-text">Loading…</span>
                        <button type="button" class="pj-summary-reset" style="display:none">
                            <i class="fa fa-xmark me-1"></i>Clear filters
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="pj-ach-table" class="table table-custom align-middle mb-0" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th>No Project</th>
                                <th>Company</th>
                                <th>Device Name</th>
                                <th>Qty </th>
                                <th class="text-end">Amount</th>
                                <th>PO Date</th>
                                <th>Division</th>
                                <th>Sales</th>
                                <th>PO Number</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
@include('project.partials._number-format')
let pjTable = null;
let pjAchTable = null;

/**
 * Filter toolbar (PO Date preset/range + dropdowns) for one DataTable.
 * opts: root (toolbar selector), selects ({ param: '#select' }), noun, table() → DataTable.
 */
function PjFilter(opts) {
    var self = this;
    var $root = $(opts.root);
    var $from = $root.find('.pj-date-from');
    var $to = $root.find('.pj-date-to');
    var preset = 'all';
    var FMT = 'YYYY-MM-DD';
    var VERSION = 1;

    function reload() { var t = opts.table(); if (t) t.ajax.reload(); }

    function presetRange(p) {
        var year = moment().year();
        if (p === 'year') return [moment({ year: year }).startOf('year'), moment({ year: year }).endOf('year')];
        var q = /^q([1-4])$/.exec(p);
        if (!q) return null;
        var start = moment({ year: year, month: (parseInt(q[1], 10) - 1) * 3, day: 1 });
        return [start, start.clone().add(2, 'months').endOf('month')];
    }

    function setRange(from, to, p) {
        from = from || ''; to = to || '';
        $from.val(from); $to.val(to);
        preset = p || ((from || to) ? 'custom' : 'all');
        $root.find('.pj-chip[data-preset]').each(function() {
            var active = $(this).data('preset') === preset;
            $(this).toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
        });
        $root.find('.pj-range').toggleClass('is-custom', preset === 'custom');
    }

    function applyPreset(p) {
        var r = presetRange(p);
        if (r) setRange(r[0].format(FMT), r[1].format(FMT), p);
        else setRange('', '', 'all');
    }

    function setSelect(sel, value) {
        value = String(value || '');
        $(sel).val($(sel).find('option[value="' + value + '"]').length ? value : '');
    }

    function rangeLabel() {
        var from = $from.val(), to = $to.val();
        var fmt = function(d) { return moment(d).format('DD MMM YYYY'); };
        var q = /^q([1-4])$/.exec(preset);
        if (q) return 'Q' + q[1] + ' ' + moment().year() + ' (' + moment(from).format('DD MMM') + ' – ' + fmt(to) + ')';
        if (preset === 'year') return 'Year ' + moment().year();
        if (from && to) return from === to ? fmt(from) : fmt(from) + ' – ' + fmt(to);
        if (from) return 'from ' + fmt(from);
        if (to) return 'until ' + fmt(to);
        return '';
    }

    // Parameter tambahan untuk ajax DataTables
    self.params = function(d) {
        d.date_from = $from.val();
        d.date_to = $to.val();
        $.each(opts.selects, function(param, sel) { d[param] = $(sel).val(); });
    };

    self.saveState = function(data) {
        var selects = {};
        $.each(opts.selects, function(param, sel) { selects[param] = $(sel).val(); });
        data.pjFilter = { v: VERSION, preset: preset, from: $from.val(), to: $to.val(), selects: selects };
    };

    self.loadState = function(data) {
        var saved = data.pjFilter;
        if (!saved || saved.v !== VERSION) return;
        $.each(opts.selects, function(param, sel) { setSelect(sel, (saved.selects || {})[param]); });
        if (/^(q[1-4]|year|all)$/.test(saved.preset || '')) applyPreset(saved.preset);
        else setRange(saved.from, saved.to);
    };

    self.drawSummary = function(settings) {
        var info = new $.fn.dataTable.Api(settings).page.info();
        var label = rangeLabel();
        var esc = function(t) { return $('<span>').text(t).html(); };
        var html = '<strong>' + info.recordsDisplay.toLocaleString('en-US') + '</strong> ' + opts.noun +
            (label ? ' · PO Date <strong>' + label + '</strong>' : ' · all dates');
        var active = !!label;
        $.each(opts.selects, function(param, sel) {
            var $s = $(sel);
            if ($s.val()) {
                active = true;
                html += ' · ' + esc($s.data('filter-label')) + ' <strong>' + esc($s.find('option:selected').text().trim()) + '</strong>';
            } else {
                html += ' · ' + esc(String($s.data('filter-all')).toLowerCase());
            }
        });
        $root.find('.pj-summary-text').html(html);
        $root.find('.pj-summary-reset').toggle(active);
    };

    $root.on('click', '.pj-chip[data-preset]', function() { applyPreset($(this).data('preset')); reload(); });
    $from.add($to).on('change', function() {
        var from = $from.val(), to = $to.val();
        if (from && to && from > to) { var tmp = from; from = to; to = tmp; } // rentang terbalik → tukar
        setRange(from, to);
        reload();
    });
    $.each(opts.selects, function(param, sel) { $(sel).on('change', reload); });
    $root.on('click', '.pj-summary-reset', function() {
        applyPreset('all');
        $.each(opts.selects, function(param, sel) { setSelect(sel, ''); });
        reload();
    });

    applyPreset('all'); // bawaan; ditimpa oleh filter tersimpan (loadState) bila ada
}

const pjFilter = new PjFilter({
    root: '#pj-filter',
    selects: { status: '#pj-filter-status', type: '#pj-filter-type' },
    noun: 'project(s)',
    table: function() { return pjTable; },
});
const pjAchFilter = new PjFilter({
    root: '#pja-filter',
    selects: { division_id: '#pja-filter-division' },
    noun: 'device(s)',
    table: function() { return pjAchTable; },
});

// Opsi bersama: filter + stateSave agar filter tetap saat kembali dari halaman detail
function pjFilterOptions(filter, url) {
    return {
        ajax: { url: url, data: filter.params },
        stateSave: true,
        stateDuration: -1,
        stateSaveParams: function(settings, data) { filter.saveState(data); },
        stateLoadParams: function(settings, data) { filter.loadState(data); },
        drawCallback: function(settings) { filter.drawSummary(settings); },
    };
}

const pjShowUrl = '{{ route("project.show", "__ID__") }}';
const pjEditUrl = '{{ route("project.edit", "__ID__") }}';
const pjDeleteUrl = '{{ route("project.destroy", "__ID__") }}';
const pjAchievementsUrl = '{{ route("project.achievements", "__ID__") }}';
const pjCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const pjCanDelete = {{ $canDelete ? 'true' : 'false' }};

function initPjTable() {
    if (pjTable) pjTable.destroy();

    pjTable = $('#pj-table').DataTable($.extend(pjFilterOptions(pjFilter, '{{ route("project.data") }}'), {
        processing: true,
        serverSide: true,
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'project_number', orderable: false, searchable: true,
                render: function(data) { return '<strong style="color:var(--text-primary)">' + data + '</strong>'; }
            },
            { data: 'project_name', orderable: false, searchable: true,
                render: function(data, type, row) {
                    var name = $('<div>').text(data).html();
                    return row.is_hybrid
                        ? name + '<div><span class="pj-tag-warn" title="Created from IMS Project, contract value &amp; sales not yet filled">Incomplete</span></div>'
                        : name;
                }
            },
            { data: 'quotation_number', orderable: false, searchable: true },
            { data: 'company_name', orderable: false, searchable: true },
            { data: 'po_number_company', orderable: false, searchable: true },
            { data: 'po_date', orderable: false, searchable: false },
            { data: 'net_sale_amount', orderable: false, searchable: false, className: 'text-end',
                render: function(data) { return pjFormatMoney(data); }
            },
            { data: 'type_label', orderable: false, searchable: false },
            { data: 'warranty_label', orderable: false, searchable: false,
                render: function(data, type, row) {
                    if (!data) return '<span style="color:var(--text-muted)">—</span>';
                    return '<span class="pj-tag-' + row.warranty_class + '">' + data + '</span>'
                        + '<div style="font-size:11.5px;color:var(--text-muted);white-space:nowrap">until ' + row.warranty_end + '</div>';
                }
            },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + pjShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (pjCanUpdate) {
                        btn += '<a href="' + pjEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                    }
                    if (pjCanDelete) {
                        btn += '<button class="btn-icon text-danger" title="Delete" onclick="pjDelete(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    }));
}

function initPjAchTable() {
    if (pjAchTable) return;

    pjAchTable = $('#pj-ach-table').DataTable($.extend(pjFilterOptions(pjAchFilter, '{{ route("project.achievements-data") }}'), {
        processing: true,
        serverSide: true,
        order: [],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'project_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    return '<a href="' + pjAchievementsUrl.replace('__ID__', row.project_id) + '" style="color:var(--accent);font-weight:600">' + data + '</a>';
                }
            },
            { data: 'company_name', orderable: false, searchable: true },
            { data: 'device_name', orderable: false, searchable: true },
            { data: 'qty', orderable: false, searchable: false,
                render: function(data, type, row) {
                    var qty = pjFormatNumber(data);
                    return row.unit ? qty + ' ' + row.unit : qty;
                }
            },
            { data: 'amount', orderable: false, searchable: false, className: 'text-end',
                render: function(data) { return pjFormatMoney(data); }
            },
            { data: 'po_date', orderable: false, searchable: false },
            { data: 'division_name', orderable: false, searchable: true },
            { data: 'sales_names', orderable: false, searchable: true },
            { data: 'po_number_company', orderable: false, searchable: true }
        ]
    }));
}

function pjDelete(id) {
    Swal.fire({
        title: 'Delete Project?',
        text: 'Project data, achievements, and sales distribution will be deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc2626'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({ url: pjDeleteUrl.replace('__ID__', id), method: 'DELETE', data: { _token: '{{ csrf_token() }}' } })
            .done(function(res) {
                toastr.success(res.message || 'Project deleted.');
                pjTable.ajax.reload(null, false);
                if (pjAchTable) pjAchTable.ajax.reload(null, false);
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete project.');
            });
    });
}

$(document).ready(function() {
    initPjTable();

    $('#pj-tab-ach-btn').on('shown.bs.tab', function() {
        initPjAchTable();
    });
});
</script>
@endsection

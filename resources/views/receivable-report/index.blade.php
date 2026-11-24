@extends('layouts.app')

@section('title', 'Receivables Report')
@section('page-title', 'Receivables Report')

@section('styles')
    @include('partials.list-toolbar')
    <style>
        .rr-tabs {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--card-border);
            margin-bottom: 6px;
        }

        .rr-tabs a {
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .rr-tabs a.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .rr-tabs a:hover {
            color: var(--text-primary);
        }

        .rr-tabs .rr-count {
            font-size: 11px;
            font-weight: 600;
            padding: 0 6px;
            border-radius: 99px;
            background: #f1f5f9;
            color: #475569;
        }

        .rr-tabs a.active .rr-count {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .rr-toolbar {
            position: sticky;
            top: calc(var(--topbar-h) + 8px);
            z-index: 5;
            background: #f8fafc;
            border: 1px solid var(--card-border);
            border-radius: var(--radius-sm);
        }

        .rr-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .rr-muted {
            font-size: 12px;
            color: var(--text-muted);
        }

        .rr-bar {
            display: flex;
            height: 6px;
            border-radius: 99px;
            overflow: hidden;
            background: #eef2f7;
            min-width: 120px;
        }

        .rr-bar span {
            display: block;
            height: 100%;
        }

        .rr-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .rr-legend i {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            margin-right: 4px;
            vertical-align: -1px;
        }

        .rr-tag {
            display: inline-block;
            font-size: 10.5px;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 99px;
        }

        .rr-tag.certain {
            background: #dcfce7;
            color: #166534;
        }

        .rr-tag.estimate {
            background: #f1f5f9;
            color: #475569;
        }

        table.rr-table {
            width: 100%;
        }

        table.rr-table thead th {
            font-weight: 700;
            white-space: nowrap;
        }

        table.rr-table tbody tr td {
            transition: background .12s ease;
        }

        table.rr-table tbody tr:hover td {
            background: #f8fafc;
        }

        table.rr-table tbody tr.rr-row-overdue td {
            background: #fef2f2;
        }

        table.rr-table tbody tr.rr-row-overdue td:first-child {
            box-shadow: inset 3px 0 0 #dc2626;
        }

        table.rr-table tbody tr.rr-row-late td {
            background: #fffbeb;
        }

        table.rr-table tbody tr.rr-row-late td:first-child {
            box-shadow: inset 3px 0 0 #f59e0b;
        }

        table.rr-table tfoot td {
            font-weight: 700;
            border-top: 2px solid var(--card-border);
        }

        .rr-group-row td {
            background: var(--accent-subtle) !important;
            font-weight: 600;
        }

        .rr-group-row.overdue td {
            background: #fef2f2 !important;
            color: #7f1d1d;
        }

        .rr-group-row.late td {
            background: #fffbeb !important;
            color: #92400e;
        }

        .rr-group-head {
            cursor: pointer;
        }

        .rr-group-head:hover td {
            filter: brightness(.98);
        }

        .rr-empty {
            text-align: center;
            padding: 36px 0;
            color: var(--text-muted);
        }

        /* Lebar kolom tetap supaya header, body, dan footer sejajar */
        table.rr-table { table-layout: auto; }
        table.rr-table th, table.rr-table td { vertical-align: middle; }
        table.rr-table .rr-col-no { width: 48px; }
        table.rr-table .rr-col-date { width: 110px; }
        table.rr-table .rr-col-money { width: 150px; }
        table.rr-table .rr-col-count { width: 110px; }
        table.rr-table tfoot td { background: #f8fafc; }

        .rr-status { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 1px 7px; border-radius: 99px; margin-top: 3px; }
        .rr-chev { font-size: 10px; color: var(--text-muted); width: 12px; }
    </style>
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header-title">Receivables Report</h1>
            <p class="page-header-sub">Customer receivables as of {{ now()->format('d/m/Y') }}. The Customer and Project tabs
                use the tax base (DPP) before VAT; the collection forecast shows invoices including VAT and term estimates
                before VAT.</p>
        </div>
    </div>

    <div class="card-custom fade-in">
        <div class="card-body-custom">
            <nav class="rr-tabs" aria-label="Report type">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('receivable-report.index', ['tab' => $key]) }}"
                        class="{{ $tab === $key ? 'active' : '' }}"
                        @if ($tab === $key) aria-current="page" @endif>
                        {{ $label }}
                        {{-- <span class="rr-count">{{ $tabCounts[$key] }}</span> --}}
                    </a>
                @endforeach
            </nav>

            <div class="list-toolbar rr-toolbar" id="rr-toolbar">
                <div class="lt-search">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="search" class="form-control" data-search autocomplete="off"
                        placeholder="@if ($tab === 'customer')Search customer…@elseif($tab === 'project')Search project no. / name, customer…@else Search customer, project, invoice no.…@endif">
                    <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
                </div>
                @if ($tab === 'customer' || $tab === 'project')
                    <select class="form-select" data-filter="status" aria-label="Receivable status">
                        <option value="">All Status</option>
                        <option value="receivable">Outstanding</option>
                        <option value="paid">Paid</option>
                    </select>
                @else
                    <select class="form-select" data-filter="kind" aria-label="Type">
                        <option value="">All Types</option>
                        <option value="invoice">Confirmed (invoice)</option>
                        <option value="estimate">Estimate (term)</option>
                    </select>
                    <select class="form-select" data-filter="group_by" aria-label="Group by">
                        <option value="period">Group by week</option>
                        <option value="customer">Group by customer</option>
                    </select>
                    <div class="lt-date" title="Due date range">
                        <input type="date" class="form-control" data-filter="from" aria-label="From date">
                        <span>–</span>
                        <input type="date" class="form-control" data-filter="to" aria-label="To date">
                    </div>
                @endif
                <a href="#" class="btn btn-sm btn-outline-secondary rr-export" title="Export this tab to Excel with the active filters">Export Excel</a>
                <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled>Reset <span class="badge rounded-pill ms-1" style="display:none">0</span></button>
            </div>

            {{-- @if ($tab === 'forecast')
                <p class="rr-muted mb-2">
                    <span class="rr-tag certain">Confirmed</span> approved invoices by due date (including VAT).
                    <span class="rr-tag estimate ms-1">Estimate</span> terms not yet invoiced, by their Billing Target;
                    if empty, the project PO due date is used (before VAT, except invoices in process).
                </p>
            @endif --}}

            <div class="table-responsive">
                @switch($tab)
                    @case('forecast')
                        <table id="rr-forecast" class="table table-custom align-middle mb-0 rr-table">
                            <thead>
                                <tr>
                                    <th class="text-center rr-col-no">No</th>
                                    <th>Customer</th>
                                    <th>Project</th>
                                    <th>Term / Invoice</th>
                                    <th class="text-center rr-col-date">Due Date</th>
                                    <th class="text-center" style="width:200px">Position</th>
                                    <th class="rr-num rr-col-money">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7">
                                        <div class="rr-empty"><span class="spinner-border spinner-border-sm me-1"></span>Loading forecast…</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    @break

                    @case('project')
                        <table id="rr-project" class="table table-custom align-middle mb-0 rr-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="text-center rr-col-no">No</th>
                                    <th>Customer</th>
                                    <th>Project</th>
                                    <th class="text-center rr-col-date">PO Date</th>
                                    <th class="text-center rr-col-date">PO Received</th>
                                    <th class="text-center rr-col-date">PO Due Date</th>
                                    <th class="text-center rr-col-date">Delivery Date</th>
                                    <th class="rr-num rr-col-money">Contract Value</th>
                                    <th class="rr-num rr-col-money">Paid</th>
                                    <th class="rr-num rr-col-money">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <td class="text-center"></td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-center"></td>
                                    <td class="text-center"></td>
                                    <td class="text-center"></td>
                                    <td class="text-center"></td>
                                    <td class="rr-num"></td>
                                    <td class="rr-num"></td>
                                    <td class="rr-num"></td>
                                </tr>
                            </tfoot>
                        </table>
                    @break

                    @default
                        <table id="rr-customer" class="table table-custom align-middle mb-0 rr-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="text-center rr-col-no">No</th>
                                    <th>Customer</th>
                                    <th class="text-center rr-col-count">Projects</th>
                                    <th class="rr-num rr-col-money">Contract Value</th>
                                    <th class="rr-num rr-col-money">Paid</th>
                                    <th class="rr-num rr-col-money">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <td class="text-center"></td>
                                    <td></td>
                                    <td class="text-center"></td>
                                    <td class="rr-num"></td>
                                    <td class="rr-num"></td>
                                    <td class="rr-num"></td>
                                </tr>
                            </tfoot>
                        </table>
                @endswitch
            </div>
        </div>
    </div>

    {{-- Status pengingat WhatsApp; pengaturannya di Master Data → Notifikasi Finance --}}
    <div class="d-flex align-items-center flex-wrap gap-2 mt-3 rr-muted">
        <span>Receivable reminder (WhatsApp):</span>
        <span class="status-badge"
            style="{{ $reminder['enabled'] ? 'background:#dcfce7;color:#166534' : 'background:#f3f4f6;color:#4b5563' }}">{{ $reminder['enabled'] ? 'Active · daily at ' . $reminder['send_time'] : 'Inactive' }}</span>
        @if ($reminder['last_sent_date'])
            <span>· last sent
                {{ \Illuminate\Support\Carbon::parse($reminder['last_sent_date'])->format('d/m/Y') }}</span>
        @endif
        @if ($canManageNotification)
            <a href="{{ route('finance-notification.index') }}" class="ms-1">Reminder settings</a>
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        const rrDataUrl = '{{ route('receivable-report.data') }}';
        const rrForecastUrl = '{{ route('receivable-report.forecast') }}';
        const rrExportBase = '{{ route('receivable-report.export') }}';
        const rrTab = '{{ $tab }}';
        const rrLang = {
            lengthMenu: 'Show _MENU_ rows',
            info: 'Showing _START_–_END_ of _TOTAL_ rows',
            infoEmpty: 'No rows',
            infoFiltered: '(filtered from _MAX_)',
            processing: 'Loading…',
            zeroRecords: '<span style="color:var(--text-muted)">No rows match the search / filters.</span>',
            emptyTable: '<span style="color:var(--text-muted)">No receivables yet.</span>',
            paginate: {
                first: '«',
                previous: '‹',
                next: '›',
                last: '»'
            }
        };
        let rrToolbar = null;

        function rrToolbarParams(extra) {
            var params = $.extend({
                tab: rrTab
            }, extra || {});
            $('#rr-toolbar [data-filter]').each(function() {
                var v = $(this).val();
                if (v) params[$(this).data('filter')] = v;
            });
            var q = $('#rr-toolbar [data-search]').val();
            if (q) params.q = q;
            return params;
        }

        function rrSyncExport() {
            $('.rr-export').attr('href', rrExportBase + '?' + $.param(rrToolbarParams()));
        }

        $(document).ready(function() {
            // Export mengikuti filter aktif; diperbarui setiap ada interaksi toolbar.
            $('#rr-toolbar').on('change input click', '[data-filter], [data-search], .lt-clear, [data-reset]',
                function() {
                    setTimeout(rrSyncExport, 60);
                });

            // Reset mengosongkan semua [data-filter]; group_by adalah mode kelompok, kembalikan ke default.
            $(document).on('click', '[data-reset]', function() {
                setTimeout(function() {
                    var $g = $('#rr-toolbar [data-filter="group_by"]');
                    if ($g.length && !$g.val()) {
                        $g.val('period');
                    }
                }, 0);
            });

            if (rrTab === 'forecast') {
                function loadForecast() {
                    $('#rr-forecast tbody').remove();
                    $('#rr-forecast').append(
                        '<tbody><tr><td colspan="7"><div class="rr-empty"><span class="spinner-border spinner-border-sm me-1"></span>Loading forecast…</div></td></tr></tbody>'
                        );
                    $.get(rrForecastUrl + '?' + $.param(rrToolbarParams())).done(function(html) {
                        $('#rr-forecast tbody').remove();
                        $('#rr-forecast').append(html);
                    });
                }
                var forecastShim = {
                    ajax: {
                        reload: loadForecast
                    },
                    search: function() {
                        return forecastShim;
                    },
                    draw: loadForecast
                };
                rrToolbar = ListToolbar('#rr-toolbar', function() {
                    return forecastShim;
                });
                loadForecast();
                rrSyncExport();
                return;
            }

            // Jumlah kolom DataTables = jumlah kolom header (header, body, footer harus sama).
            var $rrTableEl = $('#' + (rrTab === 'project' ? 'rr-project' : 'rr-customer'));
            var cols = $rrTableEl.find('thead th').length;
            rrToolbar = ListToolbar('#rr-toolbar', function() {
                return rrTable;
            });
            var rrTable = $rrTableEl.DataTable({
                layout: ListToolbar.layout,
                autoWidth: false,
                language: rrLang,
                processing: true,
                serverSide: true,
                ajax: {
                    url: rrDataUrl,
                    data: function(d) {
                        d.tab = rrTab;
                        return rrToolbar.params(d);
                    }
                },
                order: [],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                // Kelas kolom mengikuti header supaya perataan body sama dengan header.
                columns: $rrTableEl.find('thead th').map(function() {
                    return {
                        data: null,
                        defaultContent: '',
                        className: this.className.replace(/rr-col-\S+/g, '').trim(),
                        render: function() {
                            return '';
                        }
                    };
                }).get(),
                columnDefs: [{
                    orderable: false,
                    targets: '_all'
                }],
                createdRow: function(row, data) {
                    $(row).html(data.html);
                }
            });
            rrTable.on('xhr.dt', function(e, s, json) {
                if (json && json.footer) {
                    json.footer.forEach(function(cell, idx) {
                        $('#rr-' + rrTab + ' tfoot td').eq(idx).html(cell);
                    });
                }
                rrSyncExport();
            });
            rrSyncExport();
        });

        // Kelompok Proyeksi Piutang bisa dibuka/ditutup.
        $(document).on('click', '.rr-group-head', function() {
            $(this).closest('tbody').find('.rr-group-item').toggle();
            $(this).find('.rr-chev').toggleClass('fa-chevron-down fa-chevron-right');
        });
    </script>
@endsection

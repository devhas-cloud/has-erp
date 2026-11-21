@extends('layouts.app')

@section('title', 'PO Supplier Approval')
@section('page-title', 'PO Supplier Approval')

@section('styles')
    @include('partials.list-toolbar')
    <style>
        #psa-table th {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .psa-sub {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .psa-progress {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .psa-dot {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            border: 2px solid var(--card-border, #d1d5db);
            color: var(--text-muted);
            background: var(--card, #fff);
        }

        .psa-dot.on {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .psa-badge {
            font-size: 10.5px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .psa-badge-ready {
            background: #dcfce7;
            color: #166534;
        }

        .psa-badge-wait {
            background: #fef3c7;
            color: #92400e;
        }

        .psa-badge-none {
            background: #f3f4f6;
            color: #4b5563;
        }

        .psa-me {
            color: var(--accent);
            font-weight: 600;
        }

        /* Tombol approve: pill hijau lembut, terisi saat hover */
        .psa-approve-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 30px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid #a7f3d0;
            background: #ecfdf5;
            color: #047857;
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .psa-approve-btn .psa-approve-ic {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--accent);
            color: #fff;
            font-size: 9.5px;
            transition: background-color .15s ease, color .15s ease;
        }

        .psa-approve-btn:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, .28);
            transform: translateY(-1px);
        }

        .psa-approve-btn:hover .psa-approve-ic {
            background: #fff;
            color: var(--accent);
        }

        .psa-approve-btn:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .psa-approve-btn:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }

        .psa-approve-btn:disabled {
            opacity: .6;
            pointer-events: none;
        }

        /* Status aksi (bukan tombol) */
        .psa-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
        }

        .psa-chip-done {
            background: #f0fdf4;
            color: #15803d;
            border: 1px dashed #86efac;
        }

        .psa-chip-ready {
            background: #f3f4f6;
            color: #4b5563;
        }
    </style>
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header-title">PO Supplier Approval</h1>
            <p class="page-header-sub">Active projects with an uploaded customer PO. Purchasing may create the supplier PO
                once <strong>2 different approvers</strong> have approved.</p>
        </div>
    </div>

    <div class="card-custom fade-in">
        <div class="card-header-custom">
            <span><i class="fa-solid fa-truck-fast me-2" style="color:var(--accent)"></i>Projects</span>
        </div>
        <div class="card-body-custom p-2">
            <div class="list-toolbar" id="psa-toolbar">
                <div class="lt-search">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="search" class="form-control" data-search
                        placeholder="Search project no./name, customer PO no., customer, creator…" autocomplete="off">
                    <button type="button" class="lt-clear" title="Clear search"><i class="fa fa-xmark"></i></button>
                </div>
                <select class="form-select" data-filter="status" aria-label="Approval status">
                    <option value="">All approval statuses</option>
                    @foreach ($statusLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if ($companies->count() > 1)
                    <select class="form-select" data-filter="company" data-searchable aria-label="Customer">
                        <option value="">All customers</option>
                        @foreach ($companies as $c)
                            <option value="{{ $c->id }}">{{ $c->account_name }}</option>
                        @endforeach
                    </select>
                @endif
                <div class="lt-date" title="Customer PO date">
                    <input type="date" class="form-control" data-filter="date_from" aria-label="PO date from">
                    <span>–</span>
                    <input type="date" class="form-control" data-filter="date_to" aria-label="PO date to">
                </div>
                <select class="form-select" data-filter="mine" aria-label="Creator">
                    <option value="">All creators</option>
                    <option value="1">Created by me</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary lt-reset" data-reset disabled><i
                        class="fa fa-rotate-left me-1"></i>Reset <span class="badge rounded-pill ms-1"
                        style="display:none">0</span></button>
            </div>
            <div class="table-responsive">
                <table id="psa-table" class="table table-custom align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>Project</th>
                            <th>Customer &amp; PO</th>
                            <th class="text-end" style="width:150px">Net Sale</th>
                            <th style="width:150px">Created By</th>
                            <th style="width:240px">Approval</th>
                            <th class="text-center" style="width:150px">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        let psaTable = null;
        let psaToolbar = null;
        const psaApproveUrl = '{{ route('po-supplier-approval.approve', '__ID__') }}';
        const psaProjectUrl = '{{ route('project.show', '__ID__') }}';
        const psaCanApprove = {{ $canApprove ? 'true' : 'false' }};
        const psaLabels = @json($statusLabels);

        function psaEsc(s) {
            return $('<div>').text(s == null ? '' : s).html();
        }

        $(document).ready(function() {
            psaToolbar = ListToolbar('#psa-toolbar', function() {
                return psaTable;
            });
            psaTable = $('#psa-table').DataTable({
                layout: ListToolbar.layout,
                language: {
                    zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>No projects match the search / filters.</span>',
                    emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>No active projects yet.</span>'
                },
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('po-supplier-approval.data') }}',
                    data: function(d) {
                        return psaToolbar.params(d);
                    }
                },
                order: [],
                createdRow: function(tr, row) {
                    if (psaCanApprove && !row.ready_for_supplier_po && !row.already_approved_by_me) $(
                        tr).addClass('psa-needs-me');
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'project_number',
                        orderable: false,
                        searchable: true,
                        render: function(data, type, row) {
                            return '<a href="' + psaProjectUrl.replace('__ID__', row.id) +
                                '" style="font-weight:700;color:var(--text-primary);font-variant-numeric:tabular-nums">' +
                                psaEsc(data) + '</a>' +
                                '<div>' + (row.project_name ? psaEsc(row.project_name) :
                                    '<span class="psa-sub">—</span>') + '</div>';
                        }
                    },
                    {
                        data: 'company_name',
                        orderable: false,
                        searchable: true,
                        render: function(data, type, row) {
                            return '<div class="fw-semibold">' + psaEsc(data) + '</div>' +
                                '<div class="psa-sub">PO ' + psaEsc(row.po_number_company) + (row
                                    .po_date ? ' · ' + psaEsc(row.po_date) : '') + '</div>';
                        }
                    },
                    {
                        data: 'net_sale_label',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function(data) {
                            return '<span style="font-variant-numeric:tabular-nums">' + psaEsc(
                                data) + '</span>';
                        }
                    },
                    {
                        data: 'creator_name',
                        orderable: false,
                        searchable: true,
                        render: function(data) {
                            return psaEsc(data);
                        }
                    },
                    {
                        data: 'approval_count',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            var dots = '';
                            for (var i = 0; i < row.required; i++) dots += '<span class="psa-dot' +
                                (i < data ? ' on' : '') + '">' + (i < data ?
                                    '<i class="fa fa-check"></i>' : (i + 1)) + '</span>';
                            var badge = row.ready_for_supplier_po ?
                                '<span class="psa-badge psa-badge-ready">Ready for supplier PO</span>' :
                                (data > 0 ?
                                    '<span class="psa-badge psa-badge-wait">Waiting for 2nd approver</span>' :
                                    '<span class="psa-badge psa-badge-none">No approvals yet</span>'
                                    );
                            var names = (row.approvers || []).map(function(a) {
                                return '<div class="psa-sub"><span class="' + (a.is_me ?
                                        'psa-me' : '') + '">' + psaEsc(a.is_me ? 'You' : a
                                        .name) + '</span> · ' + psaEsc(a.approved_at) +
                                    '</div>';
                            }).join('');
                            return '<div class="psa-progress">' + dots + badge + '</div>' + names;
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            var html =
                                '<div class="d-flex justify-content-center align-items-center gap-2">';
                            if (psaCanApprove && !row.ready_for_supplier_po && !row
                                .already_approved_by_me) {
                                html +=
                                    '<button type="button" class="psa-approve-btn" onclick="psaApprove(' +
                                    data + ', this)" data-name="' + psaEsc(row.project_number) +
                                    '" title="Approve supplier PO for ' + psaEsc(row
                                    .project_number) + '">' +
                                    '<span class="psa-approve-ic"><i class="fa fa-check"></i></span>Approve</button>';
                            } else if (row.ready_for_supplier_po) {
                                html +=
                                    '<span class="psa-chip psa-chip-ready" title="2 approvals reached"><i class="fa fa-lock"></i>Done</span>';
                            } else if (row.already_approved_by_me) {
                                html +=
                                    '<span class="psa-chip psa-chip-done" title="You have approved — waiting for another approver"><i class="fa fa-check"></i>Approved</span>';
                            }
                            html += '<a href="' + psaProjectUrl.replace('__ID__', data) +
                                '" class="btn-icon" title="Project details"><i class="fa fa-eye"></i></a>';
                            return html + '</div>';
                        }
                    }
                ]
            });

            psaTable.on('xhr', function() {
                var json = psaTable.ajax.json() || {};
                var counts = json.status_counts || {};
                // Jumlah per status tampil di opsi filter Status (mengikuti cari & filter lain).
                $.each(psaLabels, function(key, label) {
                    $('#psa-toolbar [data-filter="status"] option[value="' + key + '"]').text(
                        label + ' (' + (counts[key] || 0) + ')');
                });
            });
        });

        function psaApprove(id, btn) {
            Swal.fire({
                title: 'Approve supplier PO?',
                html: 'Project <strong>' + psaEsc($(btn).data('name')) +
                    '</strong>. Your approval will be recorded; 2 different approvers are required before purchasing can create the supplier PO.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, approve',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                if (!result.isConfirmed) return;
                $(btn).prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>Approving…'
                    );
                $.post(psaApproveUrl.replace('__ID__', id), {
                        _token: '{{ csrf_token() }}'
                    })
                    .done(function(res) {
                        toastr.success(res.message || 'Approval recorded.');
                        psaTable.ajax.reload(null, false);
                    }).fail(function(xhr) {
                        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                            'Failed to approve.');
                        $(btn).prop('disabled', false).html(
                            '<span class="psa-approve-ic"><i class="fa fa-check"></i></span>Approve');
                    });
            });
        }
    </script>
@endsection

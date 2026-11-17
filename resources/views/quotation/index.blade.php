@extends('layouts.app')

@section('title', 'Quotation')
@section('page-title', 'Quotation')

@section('styles')
<style>
    /* ===== Toolbar: filter Tanggal + Status + Division ===== */
    .qt-toolbar {
        margin: 6px 6px 14px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }
    .qt-toolbar-main {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px 24px;
        padding: 14px 16px;
    }
    .qt-toolbar-group { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
    .qt-toolbar-filters { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
    .qt-select-filter { flex: 0 0 auto; }
    .qt-toolbar-label {
        font-size: 10.5px;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .qt-toolbar-label i { color: var(--accent); margin-right: 5px; }
    .qt-toolbar-controls { display: flex; align-items: center; gap: 8px 10px; flex-wrap: wrap; }

    .qt-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .qt-chip {
        padding: 7px 13px;
        border-radius: 20px;
        border: 1px solid var(--card-border);
        background: var(--card);
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 700;
        font-family: inherit;
        line-height: 1.2;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s var(--ease);
    }
    .qt-chip:hover {
        border-color: rgba(16, 185, 129, 0.3);
        color: var(--accent);
        background: var(--accent-soft);
    }
    .qt-chip.active {
        background: linear-gradient(135deg, var(--accent), #059669);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 2px 8px var(--accent-glow);
    }
    .qt-chip:focus-visible, .qt-summary-reset:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .qt-range { display: flex; align-items: center; gap: 6px; }
    .qt-range .form-control { width: 148px; padding: 6px 10px; font-size: 12.5px; }
    .qt-range-sep { color: var(--text-muted); font-size: 12px; font-weight: 700; }
    .qt-range.is-custom .form-control { border-color: var(--accent); }

    .qt-select-filter .form-select { min-width: 190px; padding: 7px 34px 7px 12px; font-size: 12.5px; font-weight: 600; }

    .qt-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        padding: 9px 16px;
        border-top: 1px solid var(--card-border);
        font-size: 12.5px;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .qt-summary strong { color: var(--text-primary); font-weight: 800; }
    .qt-summary-reset {
        border: none;
        background: none;
        padding: 2px 4px;
        color: var(--danger);
        font-size: 12px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        border-radius: 6px;
    }
    .qt-summary-reset:hover { text-decoration: underline; }

    @media (max-width: 768px) {
        .qt-toolbar-main { align-items: stretch; flex-direction: column; }
        .qt-range { width: 100%; }
        .qt-range .form-control { flex: 1; width: auto; min-width: 0; }
        .qt-select-filter .form-select { width: 100%; min-width: 0; }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Quotation</h1>
        <p class="page-header-sub">Dokumen quotation resmi yang dibuat dari quote configuration yang sudah disetujui</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('quotation.create') }}" class="btn-accent">
            <i class="fa fa-plus"></i>
            <span>Buat Quotation</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Daftar Quotation</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="qt-toolbar">
            <div class="qt-toolbar-main">
                <div class="qt-toolbar-filters">
                    <div class="qt-toolbar-group">
                        <span class="qt-toolbar-label"><i class="fa fa-calendar-days"></i>Tanggal</span>
                        <div class="qt-toolbar-controls">
                            <div class="qt-chips" role="group" aria-label="Rentang tanggal quotation">
                                <button type="button" class="qt-chip" data-preset="q1" title="Januari – Maret">Kuartal 1</button>
                                <button type="button" class="qt-chip" data-preset="q2" title="April – Juni">Kuartal 2</button>
                                <button type="button" class="qt-chip" data-preset="q3" title="Juli – September">Kuartal 3</button>
                                <button type="button" class="qt-chip" data-preset="q4" title="Oktober – Desember">Kuartal 4</button>
                                <button type="button" class="qt-chip" data-preset="year" title="Januari – Desember">Setahun</button>
                                <button type="button" class="qt-chip" data-preset="all" title="Tanpa filter tanggal">Semua</button>
                            </div>
                            <div class="qt-range" id="qt-range">
                                <input type="date" id="filter-date-from" class="form-control" aria-label="Tanggal dari" title="Dari tanggal">
                                <span class="qt-range-sep">–</span>
                                <input type="date" id="filter-date-to" class="form-control" aria-label="Tanggal sampai" title="Sampai tanggal">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="qt-toolbar-group qt-select-filter">
                    <label class="qt-toolbar-label" for="filter-status"><i class="fa fa-circle-check"></i>Status</label>
                    <select id="filter-status" class="form-select">
                        <option value="">Semua Status</option>
                        @foreach($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="qt-toolbar-group qt-select-filter">
                    <label class="qt-toolbar-label" for="filter-division"><i class="fa fa-building"></i>Division</label>
                    <select id="filter-division" class="form-select">
                        <option value="">Semua Division</option>
                        @foreach($divisions as $d)
                        <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="qt-summary" aria-live="polite">
                <span id="qt-summary-text">Memuat…</span>
                <button type="button" class="qt-summary-reset" id="btn-reset-qt-filter" style="display:none">
                    <i class="fa fa-xmark me-1"></i>Hapus filter
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table id="quotation-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nomor Quotation</th>
                        <th>To (Company)</th>
                        <th>Division</th>
                        <th>Tanggal</th>
                        <th>Sales</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-center" style="width:220px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
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
let quotationTable = null;
let qtRejectId = null;
let qtRejectModalInstance = null;
let qtTrackModalInstance = null;

const quotationShowUrl = '{{ route("quotation.show", "__ID__") }}';
const quotationEditUrl = '{{ route("quotation.edit", "__ID__") }}';
const quotationDeleteUrl = '{{ route("quotation.destroy", "__ID__") }}';
const quotationSubmitUrl = '{{ route("quotation.submit", "__ID__") }}';
const quotationApproveUrl = '{{ route("quotation.approve", "__ID__") }}';
const quotationRejectUrl = '{{ route("quotation.reject", "__ID__") }}';
const quotationUnlockUrl = '{{ route("quotation.unlock", "__ID__") }}';
const quotationReviseUrl = '{{ route("quotation.revise", "__ID__") }}';
const quotationVersionsUrl = '{{ route("quotation.versions", "__ID__") }}';
const quotationPdfUrl = '{{ route("quotation.pdf", "__ID__") }}';
const quotationCanApprove = {{ $canApprove ? 'true' : 'false' }};

function initQuotationTable() {
    if (quotationTable) {
        quotationTable.destroy();
    }

    quotationTable = $('#quotation-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("quotation.data") }}',
            data: function(d) {
                d.date_from = $('#filter-date-from').val();
                d.date_to = $('#filter-date-to').val();
                d.status = $('#filter-status').val();
                d.division_id = $('#filter-division').val();
            }
        },
        order: [[4, 'desc']],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'quotation_number', orderable: false, searchable: true,
                render: function(data, type, row) {
                    var badge = row.version > 1 ? ' <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:10px;vertical-align:middle">v' + row.version + '</span>' : '';
                    return '<strong style="color:var(--text-primary)">' + data + '</strong>' + badge;
                }
            },
            { data: 'to_name', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'division_name', orderable: false, searchable: true,
                render: function(data) { return data || '<span style="color:var(--text-muted)">—</span>'; }
            },
            { data: 'date', orderable: true, searchable: false },
            { data: 'sales_name', orderable: false, searchable: true },
            { data: 'grand_total_label', orderable: true, searchable: false, className: 'text-end' },
            { data: 'status_badge', orderable: false, searchable: false },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1 flex-wrap">';
                    btn += '<a href="' + quotationShowUrl.replace('__ID__', data) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    btn += '<a href="' + quotationPdfUrl.replace('__ID__', data) + '" target="_blank" class="btn-icon" title="View PDF"><i class="fa fa-file-pdf"></i></a>';
                    btn += '<button class="btn-icon" title="Riwayat" onclick="openQtTrack(' + data + ')"><i class="fa fa-clock-rotate-left"></i></button>';

                    if (row.status === 'draft') {
                        btn += '<a href="' + quotationEditUrl.replace('__ID__', data) + '" class="btn-icon" title="Edit"><i class="fa fa-pen"></i></a>';
                        btn += '<button class="btn-icon" title="Submit Approval" onclick="submitQuotation(' + data + ')"><i class="fa fa-paper-plane"></i></button>';
                        btn += '<button class="btn-icon" title="Hapus" onclick="deleteQuotation(' + data + ')"><i class="fa fa-trash"></i></button>';
                    }
                    if (row.status === 'waiting_approval' && quotationCanApprove) {
                        btn += '<button class="btn-icon" title="Approve" onclick="approveQuotation(' + data + ')"><i class="fa fa-check"></i></button>';
                        btn += '<button class="btn-icon text-danger" title="Reject" onclick="openQtReject(' + data + ')"><i class="fa fa-xmark"></i></button>';
                    }
                    // if (row.status === 'approved' && row.locked && row.can_approve) {
                    //     btn += '<button class="btn-icon" title="Buka Kunci" onclick="unlockQuotation(' + data + ')"><i class="fa fa-lock-open"></i></button>';
                    // }
                    // if (row.can_revise) {
                    //     btn += '<button class="btn-icon" title="Buat Revisi" onclick="reviseQuotation(' + data + ')"><i class="fa fa-copy"></i></button>';
                    // }
                    btn += '</div>';
                    return btn;
                }
            }
        ],
        // Filter, urutan, pencarian & halaman tetap saat kembali dari halaman detail
        stateSave: true,
        stateDuration: -1,
        stateSaveParams: function(settings, data) {
            data.qtFilter = {
                v: QT_FILTER_VERSION,
                preset: qtActivePreset,
                from: $('#filter-date-from').val(),
                to: $('#filter-date-to').val(),
                status: $('#filter-status').val(),
                division: $('#filter-division').val(),
            };
        },
        stateLoadParams: function(settings, data) {
            var saved = data.qtFilter;
            if (!saved || saved.v !== QT_FILTER_VERSION) return;
            setSelectValue('#filter-status', saved.status);
            setSelectValue('#filter-division', saved.division);
            if (isDatePreset(saved.preset)) {
                applyDatePreset(saved.preset);
            } else {
                setDateRange(saved.from, saved.to);
            }
        },
    });
}

// --- Filter Tanggal (preset + rentang kustom) ---
const QT_DATE_FMT = 'YYYY-MM-DD';
const QT_DEFAULT_PRESET = 'all';
// Naikkan angka ini bila bawaan filter berubah, agar filter tersimpan versi lama tidak dipakai lagi.
const QT_FILTER_VERSION = 1;
let qtActivePreset = 'all';

function isDatePreset(preset) {
    return /^(q[1-4]|year|all)$/.test(preset || '');
}

function datePresetRange(preset) {
    var year = moment().year();
    if (preset === 'year') {
        return [moment({ year: year }).startOf('year'), moment({ year: year }).endOf('year')];
    }
    var q = /^q([1-4])$/.exec(preset);
    if (!q) return null;
    var start = moment({ year: year, month: (parseInt(q[1], 10) - 1) * 3, day: 1 });
    return [start, start.clone().add(2, 'months').endOf('month')];
}

function setDateRange(from, to, preset) {
    from = from || '';
    to = to || '';
    $('#filter-date-from').val(from);
    $('#filter-date-to').val(to);

    qtActivePreset = preset || ((from || to) ? 'custom' : 'all');
    $('.qt-chip[data-preset]').each(function() {
        var active = $(this).data('preset') === qtActivePreset;
        $(this).toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
    });
    $('#qt-range').toggleClass('is-custom', qtActivePreset === 'custom');
}

function applyDatePreset(preset) {
    var range = datePresetRange(preset);
    if (range) {
        setDateRange(range[0].format(QT_DATE_FMT), range[1].format(QT_DATE_FMT), preset);
    } else {
        setDateRange('', '', 'all');
    }
}

function dateRangeLabel() {
    var from = $('#filter-date-from').val();
    var to = $('#filter-date-to').val();
    var fmt = function(d) { return moment(d).format('DD MMM YYYY'); };

    var q = /^q([1-4])$/.exec(qtActivePreset);
    if (q) return 'Kuartal ' + q[1] + ' ' + moment().year() + ' (' + moment(from).format('DD MMM') + ' – ' + fmt(to) + ')';
    if (qtActivePreset === 'year') return 'Tahun ' + moment().year();

    if (from && to) return from === to ? fmt(from) : fmt(from) + ' – ' + fmt(to);
    if (from) return 'mulai ' + fmt(from);
    if (to) return 'sampai ' + fmt(to);
    return '';
}

$(document).on('click', '.qt-chip[data-preset]', function() {
    applyDatePreset($(this).data('preset'));
    if (quotationTable) quotationTable.ajax.reload();
});

$('#filter-date-from, #filter-date-to').on('change', function() {
    var from = $('#filter-date-from').val();
    var to = $('#filter-date-to').val();
    // Rentang terbalik → tukar otomatis
    if (from && to && from > to) {
        var tmp = from; from = to; to = tmp;
    }
    setDateRange(from, to);
    if (quotationTable) quotationTable.ajax.reload();
});

// --- Filter Status & Division (dropdown; kosong = semua) ---
function setSelectValue(selector, value) {
    value = String(value || '');
    // Nilai tersimpan yang sudah tidak ada → kembali ke "Semua"
    $(selector).val($(selector).find('option[value="' + value + '"]').length ? value : '');
}

function selectLabel(selector) {
    return $(selector).val() ? $(selector).find('option:selected').text().trim() : '';
}

$('#filter-status, #filter-division').on('change', function() {
    if (quotationTable) quotationTable.ajax.reload();
});

$('#btn-reset-qt-filter').on('click', function() {
    applyDatePreset('all');
    setSelectValue('#filter-status', '');
    setSelectValue('#filter-division', '');
    if (quotationTable) quotationTable.ajax.reload();
});

// Ringkasan hasil di bawah toolbar
$('#quotation-table').on('draw.dt', function(e, settings) {
    var info = new $.fn.dataTable.Api(settings).page.info();
    var label = dateRangeLabel();
    var status = selectLabel('#filter-status');
    var division = selectLabel('#filter-division');
    var esc = function(t) { return $('<span>').text(t).html(); };

    $('#qt-summary-text').html(
        '<strong>' + info.recordsDisplay.toLocaleString('id-ID') + '</strong> quotation' +
        (label ? ' · Tanggal <strong>' + label + '</strong>' : ' · semua tanggal') +
        (status ? ' · Status <strong>' + esc(status) + '</strong>' : ' · semua status') +
        (division ? ' · Division <strong>' + esc(division) + '</strong>' : ' · semua division')
    );
    $('#btn-reset-qt-filter').toggle(!!(label || status || division));
});

function submitQuotation(id) {
    Swal.fire({
        title: 'Submit Approval?',
        text: 'Quotation akan dikirim untuk approval. Setelah di-submit tidak bisa diedit.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Submit',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationSubmitUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation dikirim untuk approval.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal submit.');
            });
    });
}

function approveQuotation(id) {
    Swal.fire({
        title: 'Approve Quotation?',
        text: 'Anda yakin menyetujui quotation ini?',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationApproveUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation disetujui.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal approve.');
            });
    });
}

function openQtReject(id) {
    qtRejectId = id;
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
        url: quotationRejectUrl.replace('__ID__', qtRejectId),
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

function unlockQuotation(id) {
    Swal.fire({
        title: 'Buka Kunci?',
        text: 'Pembuat akan dapat membuat revisi baru dari quotation ini.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buka Kunci',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationUnlockUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Kunci dibuka.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal membuka kunci.');
            });
    });
}

function reviseQuotation(id) {
    Swal.fire({
        title: 'Buat Revisi?',
        text: 'Header & detail akan disalin menjadi versi baru (Draft). Versi lama tetap sebagai riwayat.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat Revisi',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.post(quotationReviseUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
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

function openQtTrack(id) {
    $('#qt-track-body').html('<tr><td colspan="6" class="config-card-empty"><span class="config-spinner"></span>Memuat...</td></tr>');
    $.get(quotationVersionsUrl.replace('__ID__', id), function(res) {
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

function deleteQuotation(id) {
    Swal.fire({
        title: 'Hapus Quotation?',
        text: 'Quotation #' + id + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: quotationDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Quotation dihapus.');
            quotationTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() {
    applyDatePreset(QT_DEFAULT_PRESET); // ditimpa oleh filter tersimpan (stateLoadParams) bila ada
    initQuotationTable();
});
</script>
@endsection

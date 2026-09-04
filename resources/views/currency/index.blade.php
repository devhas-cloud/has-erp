@extends('layouts.app')

@section('title', 'Currency Management')
@section('page-title', 'Currency Management')

@section('styles')
<style>
    .info-table td { padding: 7px 0; vertical-align: top; line-height: 1.45; }
    .info-table td:first-child {
        color: var(--text-muted);
        width: 140px;
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
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Currency Management</h1>
        <p class="page-header-sub">Kelola mata uang dan nilai kurs konversi</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Tambah Currency</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-money-bill me-2" style="color:var(--accent)"></i>Daftar Mata Uang</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="currency-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Nama</th>
                        <th>Simbol</th>
                        <th>Nilai Kurs (Rate)</th>
                        <th>Base</th>
                        <th>Status</th>
                        <th class="text-center" style="width:130px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Modal Create / Edit --}}
<div class="modal fade" id="currencyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="currencyModalTitle">Tambah Currency</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="currency-form" autocomplete="off">
                    <input type="hidden" id="currency-edit-id" value="">
                    <input type="hidden" id="currency-edit-is-base" value="">
                    <div class="mb-3">
                        <label class="form-label">Nama / Kode <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="currency-name" class="form-control" maxlength="10"
                            placeholder="Contoh: IDR, USD, EUR, GBP" required>
                        <div class="form-text">Kode standar mata uang (ISO 4217), contoh IDR / USD.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Simbol</label>
                                <input type="text" id="currency-symbol" class="form-control" maxlength="10"
                                    placeholder="Rp / $ / € / £">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status <span style="color:var(--danger)">*</span></label>
                                <select id="currency-status" class="form-select" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nilai Kurs (Rate) <span style="color:var(--danger)">*</span></label>
                                <input type="number" id="currency-rate" class="form-control" min="0.0001" step="0.0001"
                                    placeholder="1.00" required>
                                <div class="form-text">1 unit mata uang ini = berapa base (contoh: USD 20000).</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jadikan Base</label>
                                <select id="currency-is-base" class="form-select">
                                    <option value="0">Tidak</option>
                                    <option value="1">Ya</option>
                                </select>
                                <div class="form-text">Base = acuan kurs. Hanya boleh satu.</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea id="currency-description" class="form-control" rows="2"
                            placeholder="Keterangan tambahan (opsional)"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-currency">
                    <i class="fa fa-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
let currencyModalInstance = null;
let currencyTable = null;

const currencyEditUrl = '{{ route("currency.edit", "__ID__") }}';
const currencyUpdateUrl = '{{ route("currency.update", "__ID__") }}';
const currencyDeleteUrl = '{{ route("currency.destroy", "__ID__") }}';

function initCurrencyTable() {
    if (currencyTable) {
        currencyTable.destroy();
    }

    currencyTable = $('#currency-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("currency.data") }}',
        order: [[1, 'asc']],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            { data: 'name', orderable: true, searchable: true,
                render: function(data, type, row) {
                    return '<strong style="color:var(--text-primary);font-weight:700;letter-spacing:0.5px">' + data + '</strong>';
                }
            },
            { data: 'symbol', orderable: true, searchable: true,
                render: function(data, type, row) {
                    return data
                        ? '<span style="font-size:14px;font-weight:700">' + data + '</span>'
                        : '<span style="color:var(--text-muted)">—</span>';
                }
            },
            { data: 'rate_formatted', orderable: true, searchable: false, className: 'text-end',
                render: function(data, type, row) {
                    if (row.is_base) {
                        return '<strong>1.00</strong> <span class="status-badge status-active" style="padding:2px 8px;font-size:10px">Base</span>';
                    }
                    return '<strong>' + data + '</strong>';
                }
            },
            { data: 'is_base_label', orderable: true, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    return row.is_base
                        ? '<span class="status-badge status-active">Base</span>'
                        : '<span style="color:var(--text-muted);font-size:12px">—</span>';
                }
            },
            { data: 'status', orderable: true, searchable: false,
                render: function(data) {
                    if (data === 'Active') {
                        return '<span class="status-badge status-active">Active</span>';
                    }
                    return '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d;">Inactive</span>';
                }
            },
            {
                data: 'id', orderable: false, searchable: false, className: 'text-center',
                render: function(data, type, row) {
                    var btn = '<div class="d-flex justify-content-center gap-1">';
                    @if($canUpdate)
                    btn += '<button class="btn-icon" title="Edit" onclick="openEditModal(' + data + ')"><i class="fa-solid fa-pen"></i></button>';
                    @endif
                    @if($canDelete)
                    btn += '<button class="btn-icon danger" title="Hapus" onclick="deleteCurrency(' + data + ', \'' + row.name + '\')"><i class="fa-solid fa-trash-can"></i></button>';
                    @endif
                    btn += '</div>';
                    return btn;
                }
            }
        ]
    });
}

function resetCurrencyForm() {
    document.getElementById('currency-form').reset();
    document.getElementById('currency-edit-id').value = '';
    document.getElementById('currency-edit-is-base').value = '';
    document.getElementById('currency-status').value = 'Active';
    document.getElementById('currency-is-base').value = '0';
    $('#currency-form .is-invalid').removeClass('is-invalid');
}

function openCreateModal() {
    resetCurrencyForm();
    document.getElementById('currencyModalTitle').textContent = 'Tambah Currency';
    if (!currencyModalInstance) {
        currencyModalInstance = new bootstrap.Modal(document.getElementById('currencyModal'));
    }
    currencyModalInstance.show();
}

function openEditModal(id) {
    resetCurrencyForm();
    document.getElementById('currencyModalTitle').textContent = 'Edit Currency';

    $.get(currencyEditUrl.replace('__ID__', id), function(res) {
        var c = res.data;
        $('#currency-edit-id').val(c.id);
        $('#currency-edit-is-base').val(c.is_base ? '1' : '0');
        $('#currency-name').val(c.name || '');
        $('#currency-symbol').val(c.symbol || '');
        $('#currency-rate').val(c.rate || '');
        $('#currency-is-base').val(c.is_base ? '1' : '0');
        $('#currency-status').val(c.status || 'Active');
        $('#currency-description').val(c.description || '');

        var isBase = !!c.is_base;
        if (isBase) {
            $('#currency-rate').prop('disabled', true).attr('placeholder', '1.00');
            $('#currency-status').val('Active').prop('disabled', true);
            $('#currency-is-base').prop('disabled', true);
        } else {
            $('#currency-rate').prop('disabled', false);
            $('#currency-status').prop('disabled', false);
            $('#currency-is-base').prop('disabled', false);
        }

        if (!currencyModalInstance) {
            currencyModalInstance = new bootstrap.Modal(document.getElementById('currencyModal'));
        }
        currencyModalInstance.show();
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat data currency.');
    });
}

$(document).on('hidden.bs.modal', '#currencyModal', function() {
    $('#currency-rate').prop('disabled', false);
    $('#currency-status').prop('disabled', false);
    $('#currency-is-base').prop('disabled', false);
});

$(document).on('click', '#btn-save-currency', function() {
    var name = $('#currency-name').val().trim();
    var rate = $('#currency-rate').val();
    var status = $('#currency-status').val();

    if (!name) { toastr.error('Nama currency wajib diisi.'); $('#currency-name').addClass('is-invalid'); return; }
    if ($('#currency-is-base').val() === '0' && (rate === '' || isNaN(parseFloat(rate)) || parseFloat(rate) <= 0)) {
        toastr.error('Nilai kurs wajib diisi lebih dari 0.'); $('#currency-rate').addClass('is-invalid'); return;
    }
    if (!status) { toastr.error('Status wajib diisi.'); return; }

    var id = $('#currency-edit-id').val();
    var isEdit = !!id;

    var payload = {
        name: name,
        symbol: $('#currency-symbol').val().trim(),
        rate: $('#currency-is-base').val() === '1' ? 1 : rate,
        is_base: $('#currency-is-base').val(),
        status: status,
        description: $('#currency-description').val().trim()
    };

    $('#btn-save-currency').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Menyimpan...');

    $.ajax({
        url: isEdit ? currencyUpdateUrl.replace('__ID__', id) : '{{ route("currency.store") }}',
        method: isEdit ? 'PUT' : 'POST',
        data: payload,
        dataType: 'json',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(function(res) {
        toastr.success(res.message || 'Currency berhasil disimpan.');
        currencyModalInstance.hide();
        currencyTable.ajax.reload();
    }).fail(function(xhr) {
        var msg = 'Terjadi kesalahan saat menyimpan.';
        if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                msg = xhr.responseJSON.errors[firstKey][0];
                var fieldMap = {
                    name: 'currency-name',
                    symbol: 'currency-symbol',
                    rate: 'currency-rate',
                    is_base: 'currency-is-base',
                    status: 'currency-status',
                    description: 'currency-description'
                };
                $('#currency-form .is-invalid').removeClass('is-invalid');
                var target = fieldMap[firstKey] || firstKey;
                $('#' + target).addClass('is-invalid');
            } else if (xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
        }
        toastr.error(msg);
    }).always(function() {
        $('#btn-save-currency').prop('disabled', false).html('<i class="fa fa-save me-1"></i> Simpan');
    });
});

function deleteCurrency(id, name) {
    Swal.fire({
        title: 'Hapus Currency?',
        text: 'Currency "' + name + '" akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.ajax({
            url: currencyDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Currency dihapus.');
            currencyTable.ajax.reload();
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}

$(document).ready(function() {
    initCurrencyTable();
});
</script>
@endsection

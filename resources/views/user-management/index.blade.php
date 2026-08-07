@extends('layouts.app')

@section('title', 'User Management')
@section('page-title', 'User Management')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">User Management</h1>
        <p class="page-header-sub">Kelola data pengguna yang memiliki akses ke sistem</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <a href="{{ route('user-management.create') }}" class="btn-accent">
            <i class="fa-solid fa-plus"></i>
            <span>Tambah User</span>
        </a>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-users me-2" style="color:var(--accent)"></i>Daftar User</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table id="users-table" class="table table-custom align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Divisi</th>
                        <th>Role</th>
                        <th>Task Role</th>
                        <th>Dibuat</th>
                        <th class="text-center" style="width:140px">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Delete confirmation modal --}}
<form id="delete-form" method="POST" style="display:none">
    @csrf @method('DELETE')
</form>
@endpush

@section('scripts')
<script>
const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
const canDelete = {{ $canDelete ? 'true' : 'false' }};
const showUrl = '{{ route("user-management.show", "__ID__") }}';
const editUrl = '{{ route("user-management.edit", "__ID__") }}';
const deleteUrl = '{{ route("user-management.destroy", "__ID__") }}';

let usersTable = null;

function initUsersTable() {
    if (usersTable) {
        usersTable.destroy();
    }

    usersTable = $('#users-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("user-management.data") }}',
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            {
                data: 'username', orderable: true, searchable: true,
                render: function(data, type, row) {
                    var avatar = row.icon
                        ? '<img src="' + row.icon + '" class="avatar-circle" alt="" style="background:transparent">'
                        : '<div class="avatar-circle">' + row.initials + '</div>';
                    return '<div style="display:flex;align-items:center;gap:10px">' +
                        avatar +
                        '<strong style="color:var(--text-primary);font-weight:600">' + row.username + '</strong>' +
                        '</div>';
                }
            },
            { data: 'email', orderable: true, searchable: true,
                render: function(data) {
                    return '<span style="color:var(--text-secondary)">' + data + '</span>';
                }
            },
            {
                data: 'division_name', orderable: false, searchable: true,
                render: function(data) {
                    return data
                        ? data
                        : '<span style="color:var(--text-muted)">—</span>';
                }
            },
            {
                data: 'role', orderable: true, searchable: true,
                render: function(data) {
                    if (data === 'Admin') {
                        return '<span class="status-badge" style="background:var(--danger-soft);color:#7f1d1d">' +
                            '<i class="fa-solid fa-shield-halved" style="font-size:10px"></i> Admin</span>';
                    }
                    return '<span class="status-badge" style="background:var(--info-soft);color:#1e40af">' +
                        '<i class="fa-solid fa-user" style="font-size:10px"></i> User</span>';
                }
            },
            {
                data: 'task_role_name', orderable: false, searchable: true,
                render: function(data) {
                    return data
                        ? '<span class="status-badge" style="background:var(--accent-soft);color:var(--accent)">' + data + '</span>'
                        : '<span style="color:var(--text-muted)">—</span>';
                }
            },
            { data: 'created_at', orderable: true, searchable: false,
                render: function(data) {
                    return '<span style="color:var(--text-muted);font-size:13px">' + data + '</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div style="display:flex;gap:5px;justify-content:center">';
                    html += '<a href="' + showUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Detail"><i class="fa-solid fa-eye"></i></a>';
                    if (canUpdate) {
                        html += '<a href="' + editUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Edit"><i class="fa-solid fa-pen"></i></a>';
                    }
                    if (canDelete) {
                        html += '<button type="button" class="btn-icon danger btn-delete-user" title="Hapus" data-id="' + row.id + '"><i class="fa-solid fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
    });
}

$(document).on('click', '.btn-delete-user', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Yakin?',
        text: 'Data yang dihapus tidak dapat dikembalikan!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: deleteUrl.replace('__ID__', id),
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    _method: 'DELETE'
                },
                success: function(res) {
                    toastr.success('User berhasil dihapus.');
                    if (usersTable) usersTable.ajax.reload(null, false);
                },
                error: function() {
                    toastr.error('Gagal menghapus user.');
                }
            });
        }
    });
});

initUsersTable();
</script>
@endsection

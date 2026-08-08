@extends('layouts.app')

@section('title', 'Configuration')
@section('page-title', 'Configuration')
@section('styles')
    <style>
        /* ========== CONFIG ACCORDION ========== */
        .config-accordion {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .config-card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: box-shadow 0.3s ease;
        }

        .config-card:hover {
            box-shadow: var(--card-shadow-hover);
        }

        .config-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            cursor: pointer;
            user-select: none;
            background: rgba(248, 250, 252, 0.4);
            transition: background 0.15s var(--ease);
            border-bottom: 1px solid transparent;
        }

        .config-card-header:hover {
            background: #f1f5f9;
        }

        .config-card.open .config-card-header {
            border-bottom-color: var(--card-border);
            background: #f8fafc;
        }

        .config-card-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .config-card-icon {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            transition: all 0.25s var(--ease);
            flex-shrink: 0;
        }

        .config-card.open .config-card-icon {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .config-card-title {
            font-weight: 700;
            font-size: 14.5px;
            color: var(--text-primary);
            letter-spacing: -0.2px;
        }

        .config-card-header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .config-card-count {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        .config-card-count.loaded {
            color: var(--text-secondary);
            font-weight: 600;
        }

        .config-card-count .count-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 20px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 11px;
            font-weight: 700;
        }

        .config-card-count.loaded .count-num {
            background: var(--accent);
            color: #fff;
        }

        .config-card-chevron {
            color: var(--text-muted);
            font-size: 11px;
            transition: transform 0.3s var(--ease), color 0.2s var(--ease);
        }

        .config-card.open .config-card-chevron {
            transform: rotate(180deg);
            color: var(--accent);
        }

        /* Body */
        .config-card-body {
            display: none;
            opacity: 0;
        }

        .config-card.open .config-card-body {
            display: block;
            animation: configFadeIn 0.3s var(--ease) 0.05s both;
        }

        @keyframes configFadeIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Toolbar */
        .config-card-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px 10px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .config-card-toolbar .form-control {
            max-width: 220px;
            padding: 7px 12px;
            font-size: 12.5px;
        }

        /* Table */
        .config-card-table-wrap {
            padding: 0 20px 6px;
            overflow-x: auto;
        }

        .config-card-table {
            width: 100%;
            font-size: 13.5px;
            margin-bottom: 0;
        }

        .config-card-table thead th {
            background: #f8fafc;
            border-bottom: 1px solid var(--card-border);
            font-weight: 700;
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 10px 14px;
            white-space: nowrap;
        }

        .config-card-table tbody td {
            padding: 11px 14px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-secondary);
        }

        .config-card-table tbody tr {
            transition: background 0.15s;
        }

        .config-card-table tbody tr:hover td {
            background: rgba(16, 185, 129, 0.02);
        }

        .config-card-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Empty */
        .config-card-empty {
            text-align: center;
            padding: 36px 16px;
            color: var(--text-muted);
            font-size: 13.5px;
            font-weight: 500;
        }

        .config-card-empty i {
            display: block;
            font-size: 28px;
            opacity: 0.2;
            margin-bottom: 10px;
        }

        /* Footer */
        .config-card-footer {
            display: none;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px 16px;
        }

        .config-card.open .config-card-footer.has-data {
            display: flex;
        }

        .config-card-footer-info {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Pagination */
        .config-pagination {
            display: flex;
            gap: 3px;
        }

        .config-pagination .page-btn {
            padding: 4px 10px;
            border: 1px solid var(--card-border);
            background: var(--card);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 600;
            font-family: inherit;
            transition: all 0.2s var(--ease);
            line-height: 1.5;
        }

        .config-pagination .page-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-soft);
        }

        .config-pagination .page-btn.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            box-shadow: 0 2px 8px var(--accent-glow);
        }

        .config-pagination .page-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        .config-pagination .page-btn:disabled:hover {
            border-color: var(--card-border);
            color: var(--text-secondary);
            background: var(--card);
        }

        /* Loading spinner */
        .config-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--card-border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: configSpin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }

        @keyframes configSpin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Action buttons in table */
        .config-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
    </style>
@endsection

@section('content')
    <!-- Breadcrumb -->
    {{-- <div class="breadcrumb-custom fade-in">
        <a href="">Dashboard</a>
        <span class="bc-sep"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="bc-current">Configuration</span>
    </div> --}}

    <!-- Page Header -->
    <div class="page-header fade-in stagger-1">
        <div>
            <h1 class="page-header-title">Configuration</h1>
            <p class="page-header-sub">Kelola data master dan pengaturan sistem</p>
        </div>
    </div>

    <!-- Divisi Penanganan -->

    <div class="config-accordion" id="config-accordion">
        @foreach ($config as $key => $cfg)
            @if (!empty($cfg['hidden']))
                @continue
            @endif
            <div class="config-card" id="card-{{ $key }}" data-table="{{ $key }}">
                <div class="config-card-header" onclick="toggleSection('{{ $key }}')">
                    <div class="config-card-header-left">
                        <div class="config-card-icon">
                            <i class="fa fa-gear"></i>
                        </div>
                        <span class="config-card-title">{{ $cfg['label'] }}</span>
                    </div>
                    <div class="config-card-header-right">
                        <span class="config-card-count" id="count-{{ $key }}">
                            <span class="count-text">--</span>
                        </span>
                        <span class="config-card-chevron"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </div>
                <div class="config-card-body">
                    <div class="config-card-toolbar" id="toolbar-{{ $key }}">
                        <input type="text" class="form-control form-control-sm" placeholder="Cari..."
                            data-table="{{ $key }}" oninput="onSearch(this, '{{ $key }}')">
                        <div>
                            @if ($canCreate)
                                <button class="btn btn-primary btn-sm btn-add-config" data-table="{{ $key }}">
                                    <i class="fa fa-plus me-1"></i> Tambah
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="config-card-table-wrap">
                        <table class="config-card-table">
                            <thead id="thead-{{ $key }}"></thead>
                            <tbody id="tbody-{{ $key }}">
                                <tr>
                                    <td colspan="4" class="config-card-empty">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="config-card-footer" id="footer-{{ $key }}">
                        <span class="config-card-footer-info" id="footer-info-{{ $key }}"></span>
                        <div class="config-pagination" id="pagination-{{ $key }}"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('modals')
<div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="configModalTitle">Tambah Data</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="config-form" autocomplete="off">
                    <input type="hidden" id="edit-id">
                    <input type="hidden" id="active-table">
                    <div class="mb-3" id="field-name-group">
                        <label for="field-name" class="form-label" id="label-name">Name</label>
                        <input type="text" class="form-control" id="field-name" name="name" required>
                    </div>
                    <div class="mb-3" id="field-desc-group">
                        <label for="field-description" class="form-label">Description</label>
                        <textarea class="form-control" id="field-description" name="description" rows="2"></textarea>
                    </div>
                    <div id="dynamic-extra-fields"></div>
                    <div class="mb-3" id="field-status-group">
                        <label for="field-status" class="form-label">Status</label>
                        <select class="form-select" id="field-status" name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save">
                    <i class="fa fa-check me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
    <script>
        const configMeta = @json($config);
        const sectionStates = {};
        const itemsCache = {};
        let configModalInstance = null;

        const routes = {
            list: '{{ route('configuration.list', ['table' => '__TABLE__']) }}',
            store: '{{ route('configuration.store', ['table' => '__TABLE__']) }}',
            update: '{{ route('configuration.update', ['table' => '__TABLE__', 'id' => '__ID__']) }}',
            destroy: '{{ route('configuration.destroy', ['table' => '__TABLE__', 'id' => '__ID__']) }}',
        };

        // ===================== HELPER FUNCTIONS =====================

        function getElement(id) {
            return document.getElementById(id);
        }

        function toggleSection(table) {
            const card = getElement('card-' + table);
            if (!card) return;
            const isOpen = card.classList.contains('open');

            if (isOpen) {
                card.classList.remove('open');
                return;
            }

            card.classList.add('open');

            if (!sectionStates[table]) {
                sectionStates[table] = {
                    page: 1,
                    search: ''
                };
                loadCount(table);
                loadTable(table, 1);
            }
        }

        function loadCount(table) {
            const countEl = getElement('count-' + table);
            if (!countEl) return;

            $.get(routes.list.replace('__TABLE__', table), {
                page: 1,
                per_page: 1
            }, function(res) {
                countEl.classList.add('loaded');
                countEl.innerHTML = '<span class="count-num">' + res.pagination.total + '</span>';
            }).fail(function() {
                countEl.innerHTML = '<span class="count-text">--</span>';
            });
        }

        function loadTable(table, page) {
            const state = sectionStates[table];
            if (!state) return;
            state.page = page;

            const tbody = getElement('tbody-' + table);
            if (!tbody) return;

            const params = {
                page: page,
                per_page: 15
            };
            if (state.search) params.search = state.search;

            const colCount = configMeta[table] && configMeta[table].columns ?
                configMeta[table].columns.length + 2 : 4;
            tbody.innerHTML = '<tr><td colspan="' + colCount +
                '" class="config-card-empty"><span class="config-spinner"></span>Memuat data...</td></tr>';

            $.get(routes.list.replace('__TABLE__', table), params, function(res) {
                renderSectionTable(table, res.columns, res.data, res.pagination, res.label);
            }).fail(function() {
                tbody.innerHTML = '<tr><td colspan="' + colCount +
                    '" class="config-card-empty"><i class="fa-solid fa-triangle-exclamation"></i>Gagal memuat data.</td></tr>';
            });
        }

        function renderSectionTable(table, columns, data, pagination, label) {
            const thead = getElement('thead-' + table);
            const tbody = getElement('tbody-' + table);
            const footer = getElement('footer-' + table);
            const footerInfo = getElement('footer-info-' + table);

            if (!thead || !tbody) return;

            const cfgMeta = configMeta[table] || {};
            const colLabels = columns.map(function(c) {
                return (cfgMeta.column_labels && cfgMeta.column_labels[c]) ?
                    cfgMeta.column_labels[c] :
                    c.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                        return l.toUpperCase();
                    });
            });

            let theadHtml = '<tr><th style="width:50px;">#</th>';
            colLabels.forEach(function(c) {
                theadHtml += '<th>' + c + '</th>';
            });
            theadHtml += '<th style="width:110px;" class="text-center">Aksi</th></tr>';
            thead.innerHTML = theadHtml;

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="' + (columns.length + 2) +
                    '" class="config-card-empty"><i class="fa-solid fa-inbox"></i>Tidak ada data.</td></tr>';
                if (footer) footer.classList.remove('has-data');
                return;
            }

            const baseFrom = (pagination.current_page - 1) * pagination.per_page;
            const nameCol = columns[0];

            let rows = '';
                    data.forEach(function(item, i) {
                        const cacheKey = table + '_' + item.id;
                        const cacheEntry = {
                            id: item.id,
                            name: item[nameCol] || '',
                            description: item.description || '',
                            status: item.status || 'Active',
                            type: item.type || null
                        };
                        const cfg = configMeta[table];
                        if (cfg && cfg.extra_fields) {
                            Object.keys(cfg.extra_fields).forEach(function(k) {
                                cacheEntry[k] = item[k] !== undefined ? item[k] : null;
                            });
                        }
                        itemsCache[cacheKey] = cacheEntry;

                rows += '<tr>';
                rows += '<td>' + (baseFrom + i + 1) + '</td>';
                columns.forEach(function(col) {
                    const val = item[col] != null ? item[col] : '';
                    if (col === 'status') {
                        const cls = val === 'Active' ? 'status-active' : 'status-inactive';
                        rows += '<td><span class="status-badge ' + cls + '">' + val + '</span></td>';
                    } else {
                        rows += '<td>' + (val || '-') + '</td>';
                    }
                });
                rows += '<td><div class="config-actions">';
                @if ($canUpdate)
                    rows += '<button class="btn-icon btn-edit-config" title="Edit" data-table="' + table +
                        '" data-id="' + item.id + '"><i class="fa-solid fa-pen-to-square"></i></button>';
                @endif
                @if ($canDelete)
                    rows += '<button class="btn-icon danger btn-delete-config" title="Hapus" data-table="' + table +
                        '" data-id="' + item.id + '"><i class="fa-solid fa-trash-can"></i></button>';
                @endif
                rows += '</div></td>';
                rows += '</tr>';
            });
            tbody.innerHTML = rows;

            renderSectionPagination(table, pagination);
            if (footer) footer.classList.add('has-data');
            if (footerInfo) footerInfo.textContent = 'Menampilkan ' + pagination.from + ' – ' + pagination.to + ' dari ' +
                pagination.total;
        }

        function renderSectionPagination(table, pagination) {
            const container = getElement('pagination-' + table);
            if (!container) return;

            if (pagination.total <= pagination.per_page) {
                container.innerHTML = '';
                return;
            }

            let html = '';
            html += '<button class="page-btn" ' + (pagination.current_page === 1 ? 'disabled' : '') +
                ' onclick="goPage(\'' + table + '\', ' + (pagination.current_page - 1) +
                ')"><i class="fa-solid fa-chevron-left" style="font-size:10px;"></i></button>';

            let startPage = Math.max(1, pagination.current_page - 2);
            let endPage = Math.min(pagination.last_page, startPage + 4);
            if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

            if (startPage > 1) {
                html += '<button class="page-btn" onclick="goPage(\'' + table + '\', 1)">1</button>';
                if (startPage > 2) html += '<button class="page-btn" disabled>…</button>';
            }

            for (let p = startPage; p <= endPage; p++) {
                html += '<button class="page-btn' + (p === pagination.current_page ? ' active' : '') +
                    '" onclick="goPage(\'' + table + '\', ' + p + ')">' + p + '</button>';
            }

            if (endPage < pagination.last_page) {
                if (endPage < pagination.last_page - 1) html += '<button class="page-btn" disabled>…</button>';
                html += '<button class="page-btn" onclick="goPage(\'' + table + '\', ' + pagination.last_page + ')">' +
                    pagination.last_page + '</button>';
            }

            html += '<button class="page-btn" ' + (pagination.current_page === pagination.last_page ? 'disabled' : '') +
                ' onclick="goPage(\'' + table + '\', ' + (pagination.current_page + 1) +
                ')"><i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></button>';
            container.innerHTML = html;
        }

        function goPage(table, page) {
            loadTable(table, page);
        }

        function onSearch(input, table) {
            if (!sectionStates[table]) return;
            clearTimeout(input._timer);
            input._timer = setTimeout(function() {
                sectionStates[table].search = input.value;
                sectionStates[table].page = 1;
                loadTable(table, 1);
            }, 400);
        }

        // ===================== MODAL FUNCTIONS =====================

        function resetModalForm() {
            const form = getElement('config-form');
            const editId = getElement('edit-id');
            const activeTable = getElement('active-table');
            const fieldName = getElement('field-name');
            const fieldDesc = getElement('field-description');
            const fieldStatus = getElement('field-status');
            const btnSave = getElement('btn-save');

            if (form) form.reset();
            if (editId) editId.value = '';
            if (activeTable) activeTable.value = '';
            if (fieldName) {
                fieldName.value = '';
                fieldName.classList.remove('is-invalid');
            }
            if (fieldDesc) {
                fieldDesc.value = '';
                fieldDesc.classList.remove('is-invalid');
            }
            if (fieldStatus) {
                fieldStatus.selectedIndex = 0;
                fieldStatus.classList.remove('is-invalid');
            }
            if (btnSave) {
                btnSave.disabled = false;
                btnSave.innerHTML = '<i class="fa-solid fa-check"></i> Simpan';
            }

            const descGroup = getElement('field-desc-group');
            if (descGroup) descGroup.style.display = '';
            const statusGroup = getElement('field-status-group');
            if (statusGroup) statusGroup.style.display = '';

            const extraContainer = getElement('dynamic-extra-fields');
            if (extraContainer) extraContainer.innerHTML = '';
        }

        function openModal(mode, table, itemData) {
            resetModalForm();

            const cfg = configMeta[table];
            const label = cfg ? cfg.label : 'Data';
            const colName = cfg && cfg.columns && cfg.columns[0] ?
                cfg.columns[0].replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                    return l.toUpperCase();
                }) :
                'Name';

            const activeTable = getElement('active-table');
            const labelName = getElement('label-name');
            const modalTitle = getElement('configModalTitle');
            const editId = getElement('edit-id');
            const fieldName = getElement('field-name');
            const fieldDesc = getElement('field-description');
            const fieldStatus = getElement('field-status');

            if (activeTable) activeTable.value = table;
            if (labelName) labelName.textContent = colName;

            const hasDesc = cfg && cfg.rules && Object.keys(cfg.rules).includes('description');
            const hasStatus = cfg && cfg.rules && Object.keys(cfg.rules).includes('status');
            const descGroup = getElement('field-desc-group');
            const statusGroup = getElement('field-status-group');
            const nameGroup = getElement('field-name-group');
            if (descGroup) descGroup.style.display = hasDesc ? '' : 'none';
            if (statusGroup) statusGroup.style.display = hasStatus ? '' : 'none';
            if (nameGroup) nameGroup.style.display = (cfg && cfg.no_name_field) ? 'none' : '';

            if (mode === 'create') {
                if (modalTitle) modalTitle.textContent = 'Tambah ' + label;
            } else if (mode === 'edit' && itemData) {
                if (modalTitle) modalTitle.textContent = 'Edit ' + label;
                if (editId) editId.value = itemData.id;
                if (fieldName) fieldName.value = itemData.name || '';
                if (hasDesc && fieldDesc) fieldDesc.value = itemData.description || '';

                if (hasStatus && fieldStatus) {
                    const statusValue = itemData.status || 'Active';
                    for (let i = 0; i < fieldStatus.options.length; i++) {
                        if (fieldStatus.options[i].value === statusValue) {
                            fieldStatus.selectedIndex = i;
                            break;
                        }
                    }
                }
            }

            renderExtraFields(cfg, mode, itemData);

            const modalEl = getElement('configModal');
            if (!modalEl) {
                console.error('Modal element not found');
                return;
            }

            if (!configModalInstance) {
                configModalInstance = new bootstrap.Modal(modalEl);
            }

            configModalInstance.show();

            setTimeout(function() {
                if (fieldName) fieldName.focus();
            }, 300);
        }

        function renderExtraFields(cfg, mode, itemData) {
            const container = getElement('dynamic-extra-fields');
            if (!container) return;
            container.innerHTML = '';

            const extraFields = cfg && cfg.extra_fields ? cfg.extra_fields : null;
            if (!extraFields) return;

            Object.keys(extraFields).forEach(function(key) {
                const ef = extraFields[key];
                const fieldId = 'extra-' + key;
                const div = document.createElement('div');
                div.className = 'mb-3';

                const label = document.createElement('label');
                label.className = 'form-label';
                label.setAttribute('for', fieldId);
                label.textContent = ef.label;
                div.appendChild(label);

                let input;

                if (ef.type === 'select' && ef.options) {
                    input = document.createElement('select');
                    input.className = 'form-select';
                    input.id = fieldId;
                    input.name = key;
                    ef.options.forEach(function(opt) {
                        const option = document.createElement('option');
                        option.value = opt;
                        option.textContent = opt;
                        input.appendChild(option);
                    });
                    if (mode === 'edit' && itemData && itemData[key] !== undefined) {
                        input.value = itemData[key];
                    } else if (ef.default !== undefined) {
                        input.value = ef.default;
                    }
                } else if (ef.type === 'number') {
                    input = document.createElement('input');
                    input.type = 'number';
                    input.className = 'form-control';
                    input.id = fieldId;
                    input.name = key;
                    if (ef.min !== undefined) input.min = ef.min;
                    if (mode === 'edit' && itemData && itemData[key] !== undefined) {
                        input.value = itemData[key];
                    } else if (ef.default !== undefined) {
                        input.value = ef.default;
                    }
                } else if (ef.type === 'select_fk' && ef.source) {
                    input = document.createElement('select');
                    input.className = 'form-select';
                    input.id = fieldId;
                    input.name = key;
                    var optEmpty = document.createElement('option');
                    optEmpty.value = '';
                    optEmpty.textContent = '— Select —';
                    input.appendChild(optEmpty);
                    div.appendChild(label);
                    div.appendChild(input);
                    container.appendChild(div);

                    $.get('{{ route("configuration.list", ["table" => "__FK__"]) }}'.replace('__FK__', ef.source), { per_page: 100 }, function(res) {
                        var sel = document.getElementById(fieldId);
                        if (!sel) return;
                        var nameCol = res.columns[0];
                        res.data.forEach(function(item) {
                            var opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item[nameCol];
                            sel.appendChild(opt);
                        });
                        if (mode === 'edit' && itemData && itemData[key] !== undefined) {
                            sel.value = itemData[key];
                        }
                    });

                    return;
                } else if (ef.type === 'multi_select' && ef.source) {
                    input = document.createElement('select');
                    input.className = 'form-select';
                    input.id = fieldId;
                    input.name = key + '[]';
                    input.multiple = true;
                    div.appendChild(label);
                    div.appendChild(input);
                    container.appendChild(div);

                    $.get('{{ route("configuration.list", ["table" => "__FK__"]) }}'.replace('__FK__', ef.source), { per_page: 100 }, function(res) {
                        var sel = document.getElementById(fieldId);
                        if (!sel) return;
                        var nameCol = res.columns[0];
                        res.data.forEach(function(item) {
                            var opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item[nameCol];
                            sel.appendChild(opt);
                        });
                        $(sel).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Pilih anggota...',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#configModal')
                        });
                        if (mode === 'edit' && itemData && itemData[key]) {
                            var vals = Array.isArray(itemData[key]) ? itemData[key] : [itemData[key]];
                            $(sel).val(vals).trigger('change');
                        }
                    });

                    return;
                } else {
                    input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control';
                    input.id = fieldId;
                    input.name = key;
                    if (mode === 'edit' && itemData && itemData[key] !== undefined) {
                        input.value = itemData[key];
                    } else if (ef.default !== undefined) {
                        input.value = ef.default;
                    }
                }

                div.appendChild(input);
                container.appendChild(div);
            });
        }

        function closeModal() {
            if (configModalInstance) {
                configModalInstance.hide();
            }
        }

        // ===================== EVENT LISTENERS (menggunakan jQuery delegation) =====================

        $(document).on('click', '.btn-add-config', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const table = $(this).data('table');
            if (table) {
                openModal('create', table, null);
            }
        });

        $(document).on('click', '.btn-edit-config', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const table = $(this).data('table');
            const id = $(this).data('id');
            const cacheKey = table + '_' + id;

            let data = itemsCache[cacheKey];

            if (!data) {
                const btn = $(this);
                btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

                $.get(routes.list.replace('__TABLE__', table), {
                    page: 1,
                    per_page: 100
                }, function(res) {
                    const nameCol = res.columns[0];
                    res.data.forEach(function(item) {
                        itemsCache[table + '_' + item.id] = {
                            id: item.id,
                            name: item[nameCol] || '',
                            description: item.description || '',
                            status: item.status || 'Active'
                        };
                    });

                    data = itemsCache[cacheKey];
                    btn.prop('disabled', false).html('<i class="fa-solid fa-pen-to-square"></i>');

                    if (data) {
                        openModal('edit', table, data);
                    } else {
                        toastr.error('Data tidak ditemukan.');
                    }
                }).fail(function() {
                    btn.prop('disabled', false).html('<i class="fa-solid fa-pen-to-square"></i>');
                    toastr.error('Gagal memuat data.');
                });
            } else {
                openModal('edit', table, data);
            }
        });

        $(document).on('click', '.btn-delete-config', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const table = $(this).data('table');
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
                        url: routes.destroy.replace('__TABLE__', table).replace('__ID__', id),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(res) {
                            delete itemsCache[table + '_' + id];
                            toastr.success(res.message);
                            loadCount(table);
                            loadTable(table, sectionStates[table] ? sectionStates[table].page :
                                1);
                        },
                        error: function() {
                            toastr.error('Gagal menghapus data.');
                        }
                    });
                }
            });
        });

        // Gunakan jQuery delegation untuk btn-save
        $(document).on('click', '#btn-save', function() {
            const btn = this;
            const table = $('#active-table').val();
            const editId = $('#edit-id').val();
            const $nameField = $('#field-name');
            const nameVal = $nameField.val().trim();

            if (!table) {
                toastr.error('Terjadi kesalahan. Silakan refresh halaman.');
                return;
            }

            const cfg = configMeta[table];
            const noNameField = cfg && cfg.no_name_field;
            const firstCol = cfg && cfg.columns && cfg.columns[0] ? cfg.columns[0] : 'name';

            if (!noNameField && !nameVal) {
                $nameField.addClass('is-invalid').focus();
                toastr.error('Field ' + ($('#label-name').text() || 'Name') + ' wajib diisi.');
                return;
            }

            $nameField.removeClass('is-invalid');

            const data = {
                _token: '{{ csrf_token() }}'
            };
            if (!noNameField) data[firstCol] = nameVal;
            data.description = $('#field-description').val().trim();
            data.status = $('#field-status').val();

            if (cfg && cfg.extra_fields && cfg.extra_fields.type) {
                data.type = $('#field-extra-type').val();
            }

            if (cfg && cfg.extra_fields) {
                Object.keys(cfg.extra_fields).forEach(function(key) {
                    var ef = cfg.extra_fields[key];
                    var input = document.getElementById('extra-' + key);
                    if (input) {
                        data[key] = (ef.type === 'multi_select') ? ($(input).val() || []) : input.value;
                    }
                });
            }

            let url, method;
            if (editId) {
                url = routes.update.replace('__TABLE__', table).replace('__ID__', editId);
                method = 'POST';
                data._method = 'PUT';
            } else {
                url = routes.store.replace('__TABLE__', table);
                method = 'POST';
            }

            $(btn).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...');

            $.ajax({
                url: url,
                type: method,
                data: data,
                success: function(res) {
                    toastr.success(res.message || 'Data berhasil disimpan.');

                    if (editId) {
                        var cacheEntry = {
                            id: editId,
                            name: nameVal,
                            description: data.description,
                            status: data.status,
                            type: data.type || null
                        };
                        if (cfg && cfg.extra_fields) {
                            Object.keys(cfg.extra_fields).forEach(function(k) {
                                cacheEntry[k] = data[k];
                            });
                        }
                        itemsCache[table + '_' + editId] = cacheEntry;
                    }

                    closeModal();
                    loadCount(table);
                    loadTable(table, sectionStates[table] ? sectionStates[table].page : 1);
                },
                error: function(xhr) {
                    $(btn).prop('disabled', false).html('<i class="fa-solid fa-check"></i> Simpan');

                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON ? xhr.responseJSON.errors : null;
                        if (errors) {
                            const firstKey = Object.keys(errors)[0];
                            const firstError = Array.isArray(errors[firstKey]) ? errors[firstKey][0] :
                                errors[firstKey];
                            toastr.error(firstError);

                            if (errors[firstCol] || errors.name) {
                                $nameField.addClass('is-invalid');
                            }
                        }
                    } else if (xhr.status === 419) {
                        toastr.error('Sesi telah berakhir. Silakan refresh halaman.');
                    } else {
                        const msg = xhr.responseJSON && xhr.responseJSON.message ?
                            xhr.responseJSON.message :
                            'Gagal menyimpan data.';
                        toastr.error(msg);
                    }
                }
            });
        });

        // Gunakan jQuery delegation untuk remove invalid class
        $(document).on('input', '#field-name', function() {
            $(this).removeClass('is-invalid');
        });

        // Gunakan jQuery untuk modal event
        $(document).on('hidden.bs.modal', '#configModal', function() {
            resetModalForm();
        });

        // ===================== DIVISI PENANGANAN =====================

        // Load counts on page init
        Object.keys(configMeta).forEach(function(table) {
            loadCount(table);
        });
    </script>
@endsection

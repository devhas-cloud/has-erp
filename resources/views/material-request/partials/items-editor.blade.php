{{--
    Editor daftar material + popup "Pilih Produk" — dipakai form Material Request
    dan form Template Material. Isi tabel disimpan di MrItems (JS):
      MrItems.init(initialItems)          isi awal
      MrItems.add(list, {merge:true})     tambah; produk sama -> qty dijumlahkan
      MrItems.collect()                   {items, error} untuk disimpan
    Param Blade: $editorTitle (judul kartu), $editorExtraButtons (HTML tombol tambahan).
--}}
<style>
    .mr-items-card .card-header-custom { gap: 8px; flex-wrap: wrap; }
    .mr-items-table th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); white-space: nowrap; }
    .mr-items-table td { vertical-align: middle; }
    .mr-prod-code { font-weight: 600; font-size: 13px; font-variant-numeric: tabular-nums; }
    .mr-prod-name { font-size: 12.5px; color: var(--text-secondary); }
    .mr-kind { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
    .mr-kind-material { background: #e0f2fe; color: #075985; }
    .mr-kind-serial { background: #fef3c7; color: #92400e; }
    .mr-qty-group { max-width: 170px; margin-left: auto; }
    .mr-qty-group .form-control { text-align: right; font-variant-numeric: tabular-nums; }
    .mr-qty-group .input-group-text { font-size: 12px; min-width: 52px; justify-content: center; color: var(--text-muted); }
    .mr-items-table tr.is-new td { animation: mrFlash 1.6s ease; }
    @keyframes mrFlash { 0% { background: #d1fae5; } 100% { background: transparent; } }
    .mr-items-table .is-invalid { border-color: var(--danger, #dc3545) !important; }
    .mr-empty { text-align: center; padding: 34px 16px; }
    .mr-empty .mr-empty-icon { font-size: 30px; color: var(--accent); opacity: .7; }
    .mr-empty .mr-empty-title { font-weight: 600; margin-top: 8px; }
    .mr-empty .mr-empty-sub { font-size: 12.5px; color: var(--text-muted); margin-bottom: 14px; }

    /* Popup Pilih Produk — skin sama dengan picker IH / Purchase Request */
    .mr-pp-toolbar { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.2fr); gap: 8px; }
    @media (max-width: 767.98px) { .mr-pp-toolbar { grid-template-columns: 1fr; } }
    .mr-pp-search { position: relative; }
    .mr-pp-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
    .mr-pp-search input { padding-left: 30px; }
    .mr-pp-table-wrap { border: 1px solid var(--card-border, #e5e7eb); border-radius: var(--radius-sm, 6px); overflow: auto; max-height: 52vh; }
    .mr-pp-table-wrap thead th { position: sticky; top: 0; z-index: 1; background: var(--card, #fff); font-size: 11.5px; text-transform: uppercase; color: var(--text-muted); }
    #mr-pp-body tr { cursor: pointer; transition: background-color .15s ease; user-select: none; }
    #mr-pp-body tr.is-selected, #mr-pp-body tr.is-selected td { background-color: #ecfdf5 !important; }
    #mr-pp-body tr:not(.is-added):hover, #mr-pp-body tr:not(.is-added):hover td { background-color: #d1fae5 !important; color: #065f46; }
    #mr-pp-body tr.is-added { cursor: default; }
    #mr-pp-body tr.is-added td { opacity: .7; }
    .mr-pp-check, #mr-pp-check-all { width: 16px; height: 16px; cursor: pointer; }
    .mr-pp-desc { font-size: 12px; color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .mr-pp-info { font-size: 12.5px; color: var(--text-muted); }
    .mr-pp-badge { font-size: 11.5px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 999px; padding: 2px 9px; }
    .mr-pp-existing { font-size: 10.5px; background: #fef3c7; color: #92400e; border-radius: 999px; padding: 2px 7px; white-space: nowrap; }
    .mr-pp-chips { display: flex; flex-wrap: wrap; gap: 4px; max-height: 56px; overflow-y: auto; margin-bottom: 6px; }
    .mr-pp-chip { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; padding: 1px 3px 1px 8px; border-radius: 999px; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .mr-pp-chip button { border: 0; background: transparent; color: inherit; line-height: 1; padding: 0 4px; }
    .mr-pp-pager .page-link { font-size: 12.5px; padding: 3px 9px; }
    .mr-pp-empty { text-align: center; padding: 28px 10px; color: var(--text-muted); font-size: 13px; }
</style>

<div class="card-custom fade-in mr-items-card">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--accent)"></i>{{ $editorTitle ?? 'Material' }} <span class="badge rounded-pill ms-1" style="background:var(--accent-subtle,#ecfdf5);color:var(--accent);font-size:11px" id="mr-count-badge">0</span></span>
        <div class="d-flex gap-2 flex-wrap">
            {!! $editorExtraButtons ?? '' !!}
            <button type="button" class="btn btn-sm btn-primary" onclick="MrPicker.open()"><i class="fa fa-magnifying-glass-plus me-1"></i>Pilih Produk</button>
        </div>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0 mr-items-table">
                <thead>
                    <tr>
                        <th style="width:40px" class="text-center">#</th>
                        <th>Produk</th>
                        <th style="width:110px">Jenis</th>
                        <th style="width:190px" class="text-end">Qty</th>
                        <th style="width:230px">Catatan</th>
                        <th style="width:44px"></th>
                    </tr>
                </thead>
                <tbody id="mr-rows"></tbody>
            </table>
        </div>
    </div>
</div>

@push('modals')
<div class="modal fade" id="mrProductPicker" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Pilih Produk</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mr-pp-toolbar mb-2">
                    <div class="mr-pp-search">
                        <i class="fa fa-magnifying-glass"></i>
                        <input type="text" class="form-control form-control-sm" id="mr-pp-search" placeholder="Cari kode, nama, merek, atau deskripsi…" autocomplete="off">
                    </div>
                    <select id="mr-pp-kind" class="form-select form-select-sm">
                        <option value="">Semua jenis</option>
                        <option value="material">Material (per qty)</option>
                        <option value="serial">Barang ber-SN</option>
                    </select>
                    <select id="mr-pp-category" class="form-select form-select-sm">
                        <option value="">Semua kategori</option>
                    </select>
                </div>
                <div class="mr-pp-chips" id="mr-pp-chips"></div>
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <span class="mr-pp-info" id="mr-pp-info">Memuat…</span>
                    <div class="d-flex align-items-center gap-2">
                        <span class="mr-pp-badge" id="mr-pp-selected">0 dipilih</span>
                        <button type="button" class="btn btn-link btn-sm p-0" id="mr-pp-clear" style="display:none">Kosongkan pilihan</button>
                    </div>
                </div>
                <div class="mr-pp-table-wrap">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px" class="text-center"><input type="checkbox" id="mr-pp-check-all" title="Pilih semua di halaman ini"></th>
                                <th style="width:160px">Kode</th>
                                <th>Nama / Deskripsi</th>
                                <th style="width:130px">Jenis / Satuan</th>
                            </tr>
                        </thead>
                        <tbody id="mr-pp-body"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 mr-pp-info">
                        Tampilkan
                        <select id="mr-pp-per-page" class="form-select form-select-sm" style="width:auto">
                            <option value="10">10</option>
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        per halaman
                    </div>
                    <nav><ul class="pagination pagination-sm mb-0 mr-pp-pager" id="mr-pp-pager"></ul></nav>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small style="color:var(--text-muted);font-size:12px"><i class="fa fa-circle-info me-1"></i>Klik baris untuk memilih · Shift+klik untuk memilih rentang · Enter untuk menambahkan</small>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" id="mr-pp-apply" disabled>
                        <i class="fa fa-plus me-1"></i> Tambahkan <span id="mr-pp-apply-count">0</span> Produk
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('page-scripts')
<script>
function mrEsc(s) { return $('<div>').text(s == null ? '' : s).html(); }
function mrRound(v) { return Math.round(v * 10000) / 10000; }

/* ── Daftar material di form ── */
var MrItems = (function() {
    var items = [];
    var onChange = function() {};

    function kindBadge(isMaterial) {
        return isMaterial
            ? '<span class="mr-kind mr-kind-material" title="Stok per jumlah — qty boleh desimal">Material</span>'
            : '<span class="mr-kind mr-kind-serial" title="Barang ber-SN — qty bilangan bulat">Ber-SN</span>';
    }

    function render(newIds) {
        newIds = newIds || [];
        var $body = $('#mr-rows');
        if (!items.length) {
            $body.html('<tr><td colspan="6"><div class="mr-empty">'
                + '<div class="mr-empty-icon"><i class="fa-solid fa-boxes-stacked"></i></div>'
                + '<div class="mr-empty-title">Belum ada material</div>'
                + '<div class="mr-empty-sub">Pilih produk dari master, atau ambil isi template yang sudah ada.</div>'
                + '<div class="d-flex justify-content-center gap-2 flex-wrap">'
                + '<button type="button" class="btn btn-primary btn-sm" onclick="MrPicker.open()"><i class="fa fa-magnifying-glass-plus me-1"></i>Pilih Produk</button>'
                + (window.MrTemplatePicker ? '<button type="button" class="btn btn-outline-primary btn-sm" onclick="MrTemplatePicker.open()"><i class="fa fa-layer-group me-1"></i>Ambil dari Template</button>' : '')
                + '</div></div></td></tr>');
        } else {
            var html = '';
            items.forEach(function(it, i) {
                html += '<tr data-idx="' + i + '"' + (newIds.indexOf(String(it.master_product_id)) !== -1 ? ' class="is-new"' : '') + '>'
                    + '<td class="text-center" style="color:var(--text-muted)">' + (i + 1) + '</td>'
                    + '<td><div class="mr-prod-code">' + mrEsc(it.code || '—') + '</div><div class="mr-prod-name">' + mrEsc(it.name || '') + '</div></td>'
                    + '<td>' + kindBadge(it.is_material) + '</td>'
                    + '<td><div class="input-group input-group-sm mr-qty-group">'
                    + '<input type="text" inputmode="decimal" class="form-control mr-qty" placeholder="0" value="' + (it.qty != null && it.qty !== '' ? mrEsc(it.qty) : '') + '">'
                    + '<span class="input-group-text">' + mrEsc(it.unit || '—') + '</span></div></td>'
                    + '<td><input type="text" class="form-control form-control-sm mr-item-note" maxlength="255" placeholder="Opsional" value="' + mrEsc(it.note || '') + '"></td>'
                    + '<td class="text-center"><button type="button" class="btn-icon danger mr-remove" title="Hapus baris"><i class="fa fa-trash"></i></button></td>'
                    + '</tr>';
            });
            $body.html(html);
        }
        $('#mr-count-badge').text(items.length);
        onChange(items);
    }

    function indexOf(id) {
        for (var i = 0; i < items.length; i++) if (String(items[i].master_product_id) === String(id)) return i;
        return -1;
    }

    function normalizeQty(it, value) {
        var v = String(value == null ? '' : value).replace(/,/g, '').replace(/[^\d.]/g, '');
        if (!it.is_material) v = v.replace(/\..*$/, '');
        return v;
    }

    $(document).on('input', '#mr-rows .mr-qty', function() {
        var it = items[$(this).closest('tr').data('idx')];
        var clean = normalizeQty(it, this.value);
        if (clean !== this.value) this.value = clean;
        it.qty = clean;
        $(this).removeClass('is-invalid');
        onChange(items);
    });
    $(document).on('input', '#mr-rows .mr-item-note', function() {
        items[$(this).closest('tr').data('idx')].note = this.value;
    });
    $(document).on('click', '#mr-rows .mr-remove', function() {
        items.splice($(this).closest('tr').data('idx'), 1);
        render();
    });
    // Enter di qty -> lompat ke qty baris berikutnya.
    $(document).on('keydown', '#mr-rows .mr-qty', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var $next = $(this).closest('tr').next('tr').find('.mr-qty');
        if ($next.length) $next.trigger('focus').trigger('select');
    });

    return {
        init: function(list, changeCb) {
            if (changeCb) onChange = changeCb;
            items = (list || []).map(function(x) { return $.extend({}, x, { qty: x.qty != null ? String(mrRound(parseFloat(x.qty))) : '' }); });
            render();
        },
        ids: function() { return items.map(function(x) { return String(x.master_product_id); }); },
        all: function() { return items; },
        /** Tambah produk; produk yang sudah ada -> qty dijumlahkan (bila qty diberikan). */
        add: function(list, opts) {
            opts = opts || {};
            var added = 0, merged = 0, newIds = [];
            list.forEach(function(p) {
                var i = indexOf(p.master_product_id);
                var qty = p.qty != null && p.qty !== '' ? parseFloat(p.qty) : null;
                if (qty != null && !p.is_material) qty = Math.round(qty);
                if (i !== -1) {
                    if (qty != null) {
                        var cur = parseFloat(items[i].qty) || 0;
                        items[i].qty = String(mrRound(cur + qty));
                        merged++;
                        newIds.push(String(p.master_product_id));
                    }
                    return;
                }
                items.push({
                    master_product_id: p.master_product_id, code: p.code, name: p.name, unit: p.unit,
                    is_material: !!p.is_material, qty: qty != null ? String(mrRound(qty)) : '', note: p.note || ''
                });
                added++;
                newIds.push(String(p.master_product_id));
            });
            render(newIds);
            // Fokus ke qty pertama yang masih kosong.
            if (opts.focusEmpty !== false) {
                var $empty = $('#mr-rows .mr-qty').filter(function() { return !this.value; }).first();
                if ($empty.length) $empty.trigger('focus');
            }
            return { added: added, merged: merged };
        },
        /** Kumpulkan & validasi untuk disimpan. */
        collect: function() {
            var out = [], firstBad = null;
            $('#mr-rows .mr-qty').removeClass('is-invalid');
            items.forEach(function(it, i) {
                var qty = parseFloat(it.qty);
                if (!(qty > 0)) {
                    var $inp = $('#mr-rows tr[data-idx="' + i + '"] .mr-qty').addClass('is-invalid');
                    if (!firstBad) firstBad = $inp;
                    return;
                }
                out.push({ master_product_id: it.master_product_id, qty: qty, note: it.note || '' });
            });
            if (!items.length) return { error: 'Tambahkan minimal satu material.' };
            if (firstBad) { firstBad.trigger('focus'); return { error: 'Isi qty (lebih dari 0) untuk baris yang ditandai merah.' }; }
            return { items: out };
        }
    };
})();

/* ── Popup Pilih Produk ── */
var MrPicker = (function() {
    var url = '{{ route("material-request.search-products") }}';
    var modal = null, page = 1, rows = [], selected = {}, order = [], lastIdx = null, timer = null, xhr = null, categoriesLoaded = false;

    function isAdded(id) { return MrItems.ids().indexOf(String(id)) !== -1; }

    function sync() {
        var n = order.length;
        $('#mr-pp-selected').text(n + ' dipilih');
        $('#mr-pp-clear').toggle(n > 0);
        $('#mr-pp-apply-count').text(n);
        $('#mr-pp-apply').prop('disabled', n === 0);
        var chips = '';
        order.forEach(function(id) {
            chips += '<span class="mr-pp-chip">' + mrEsc(selected[id].code) + '<button type="button" data-unpick="' + id + '" title="Batalkan">&times;</button></span>';
        });
        $('#mr-pp-chips').html(chips).toggle(n > 0);
        var $checks = $('#mr-pp-body .mr-pp-check');
        $('#mr-pp-check-all').prop('checked', $checks.length > 0 && $checks.filter(':checked').length === $checks.length);
    }

    function setPicked(idx, on) {
        var p = rows[idx];
        if (!p || isAdded(p.id)) return;
        var key = String(p.id);
        if (on && !selected[key]) { selected[key] = p; order.push(key); }
        if (!on && selected[key]) { delete selected[key]; order = order.filter(function(x) { return x !== key; }); }
        var $tr = $('#mr-pp-body tr[data-idx="' + idx + '"]');
        $tr.toggleClass('is-selected', on).find('.mr-pp-check').prop('checked', on);
    }

    function renderRows(meta) {
        if (!rows.length) {
            $('#mr-pp-body').html('<tr><td colspan="4" class="mr-pp-empty"><i class="fa-solid fa-inbox me-1"></i>Produk tidak ditemukan. Coba kata kunci atau filter lain.</td></tr>');
        } else {
            var html = '';
            rows.forEach(function(p, idx) {
                var added = isAdded(p.id), picked = !!selected[String(p.id)];
                html += '<tr data-idx="' + idx + '" class="' + (added ? 'is-added' : (picked ? 'is-selected' : '')) + '">'
                    + '<td class="text-center">' + (added ? '<i class="fa fa-check" style="color:var(--accent)"></i>' : '<input type="checkbox" class="mr-pp-check"' + (picked ? ' checked' : '') + '>') + '</td>'
                    + '<td><code style="font-size:12.5px">' + mrEsc(p.code) + '</code>' + (added ? '<div><span class="mr-pp-existing">Sudah ditambahkan</span></div>' : '') + '</td>'
                    + '<td><div style="font-weight:600;font-size:13px">' + mrEsc(p.name || '—') + '</div>'
                    + (p.description ? '<div class="mr-pp-desc">' + mrEsc(p.description) + '</div>' : '')
                    + ((p.brand || p.category) ? '<div style="font-size:11.5px;color:var(--text-muted)">' + mrEsc([p.brand, p.category].filter(Boolean).join(' · ')) + '</div>' : '') + '</td>'
                    + '<td><span class="mr-kind ' + (p.is_material ? 'mr-kind-material">Material' : 'mr-kind-serial">Ber-SN') + '</span>'
                    + '<div style="font-size:11.5px;color:var(--text-muted);margin-top:3px">' + mrEsc(p.unit || 'tanpa satuan') + '</div></td>'
                    + '</tr>';
            });
            $('#mr-pp-body').html(html);
        }
        $('#mr-pp-info').text(meta && meta.total ? 'Menampilkan ' + meta.from + '–' + meta.to + ' dari ' + meta.total + ' produk' : '0 produk');
        renderPager(meta);
        sync();
    }

    function renderPager(meta) {
        var last = meta ? meta.last_page : 1, cur = meta ? meta.current_page : 1;
        if (last <= 1) { $('#mr-pp-pager').html(''); return; }
        var html = '', from = Math.max(1, cur - 2), to = Math.min(last, cur + 2);
        function li(p, label, disabled, active) {
            return '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + p + '">' + label + '</a></li>';
        }
        html += li(cur - 1, '&laquo;', cur === 1);
        if (from > 1) html += li(1, '1') + (from > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '');
        for (var p = from; p <= to; p++) html += li(p, p, false, p === cur);
        if (to < last) html += (to < last - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '') + li(last, last);
        html += li(cur + 1, '&raquo;', cur === last);
        $('#mr-pp-pager').html(html);
    }

    function load(p) {
        page = p || 1;
        lastIdx = null;
        if (xhr) xhr.abort();
        $('#mr-pp-body').html('<tr><td colspan="4" class="mr-pp-empty"><span class="spinner-border spinner-border-sm me-1"></span>Memuat…</td></tr>');
        xhr = $.getJSON(url, {
            q: $('#mr-pp-search').val(), kind: $('#mr-pp-kind').val(), category: $('#mr-pp-category').val(),
            per_page: $('#mr-pp-per-page').val(), page: page, with_categories: categoriesLoaded ? 0 : 1
        }).done(function(res) {
            rows = res.data || [];
            if (!categoriesLoaded && res.categories) {
                var opts = '<option value="">Semua kategori</option>';
                res.categories.forEach(function(c) { opts += '<option value="' + mrEsc(c) + '">' + mrEsc(c) + '</option>'; });
                $('#mr-pp-category').html(opts);
                categoriesLoaded = true;
            }
            renderRows(res.meta);
        }).fail(function(x) {
            if (x.statusText === 'abort') return;
            $('#mr-pp-body').html('<tr><td colspan="4" class="mr-pp-empty"><i class="fa-solid fa-triangle-exclamation me-1"></i>Gagal memuat produk.</td></tr>');
        });
    }

    function apply() {
        if (!order.length) return;
        var list = order.map(function(id) {
            var p = selected[id];
            return { master_product_id: p.id, code: p.code, name: p.name, unit: p.unit, is_material: p.is_material };
        });
        var res = MrItems.add(list);
        modal.hide();
        toastr.success(res.added + ' produk ditambahkan. Isi qty-nya.');
    }

    $(document).on('click', '#mr-pp-body tr[data-idx]', function(e) {
        var idx = $(this).data('idx');
        if (!rows[idx] || isAdded(rows[idx].id)) return;
        var on = !selected[String(rows[idx].id)];
        if ($(e.target).is('.mr-pp-check')) on = e.target.checked;
        if (e.shiftKey && lastIdx !== null) {
            var a = Math.min(lastIdx, idx), b = Math.max(lastIdx, idx);
            for (var i = a; i <= b; i++) setPicked(i, on);
        } else {
            setPicked(idx, on);
        }
        lastIdx = idx;
        sync();
    });
    $(document).on('click', '#mr-pp-pager a[data-page]', function(e) {
        e.preventDefault();
        if ($(this).parent().hasClass('disabled') || $(this).parent().hasClass('active')) return;
        load(parseInt($(this).data('page'), 10));
    });
    $(document).on('click', '#mr-pp-chips [data-unpick]', function() {
        var id = String($(this).data('unpick'));
        delete selected[id];
        order = order.filter(function(x) { return x !== id; });
        rows.forEach(function(p, idx) { if (String(p.id) === id) $('#mr-pp-body tr[data-idx="' + idx + '"]').removeClass('is-selected').find('.mr-pp-check').prop('checked', false); });
        sync();
    });

    $(function() {
        $('#mr-pp-search').on('input', function() { clearTimeout(timer); timer = setTimeout(function() { load(1); }, 300); })
            .on('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); if (order.length) apply(); } });
        $('#mr-pp-kind, #mr-pp-category, #mr-pp-per-page').on('change', function() { load(1); });
        $('#mr-pp-check-all').on('change', function() {
            var on = this.checked;
            rows.forEach(function(p, idx) { setPicked(idx, on); });
            sync();
        });
        $('#mr-pp-clear').on('click', function() {
            selected = {}; order = [];
            $('#mr-pp-body tr').removeClass('is-selected').find('.mr-pp-check').prop('checked', false);
            sync();
        });
        $('#mr-pp-apply').on('click', apply);
        $('#mrProductPicker').on('shown.bs.modal', function() { $('#mr-pp-search').trigger('focus'); });
    });

    return {
        open: function() {
            if (!modal) modal = new bootstrap.Modal(document.getElementById('mrProductPicker'));
            selected = {}; order = [];
            sync();
            modal.show();
            load(1);
        }
    };
})();
</script>
@endpush

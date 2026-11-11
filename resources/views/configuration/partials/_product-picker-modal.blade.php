{{--
    Modal "Pilih Part Instrument" (dipakai bersama oleh semua form Configuration).
    Param: $ppColumns = daftar label header kolom setelah kolom checkbox.

    Halaman pemakai tetap mendefinisikan DataTable #pp-table, global `ppSelected`,
    `existingProductIds`, dan handler #btn-pp-add. Script di bawah hanya menambah UX:
    header/search/footer tetap terlihat, counter & chip pilihan, penanda part yang
    sudah ada, dan shortcut keyboard.
--}}
<style>
    #productPickerModal .modal-content { height: calc(100vh - 3.5rem); }
    #productPickerModal .pp-toolbar {
        flex-shrink: 0;
        padding: .75rem 1rem .5rem;
        border-bottom: 1px solid var(--card-border, #e5e7eb);
    }
    #productPickerModal .pp-search-wrap { position: relative; }
    #productPickerModal .pp-search-wrap > .fa-magnifying-glass {
        position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
        color: var(--text-muted); pointer-events: none;
    }
    #productPickerModal #pp-search { padding-left: 2.1rem; padding-right: 2.1rem; }
    #productPickerModal #pp-search-clear {
        position: absolute; right: .4rem; top: 50%; transform: translateY(-50%);
        border: 0; background: transparent; color: var(--text-muted); padding: .15rem .4rem;
    }
    #productPickerModal .pp-chips {
        display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .5rem;
        max-height: 62px; overflow-y: auto;
    }
    #productPickerModal .pp-chip {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: 11px; padding: .15rem .25rem .15rem .55rem; border-radius: 999px;
        background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;
    }
    #productPickerModal .pp-chip button {
        border: 0; background: transparent; color: inherit; line-height: 1; padding: 0 .25rem;
    }
    #productPickerModal .modal-body { padding-top: 0; }
    /* Header tabel menempel saat body di-scroll */
    #productPickerModal #pp-table thead th {
        position: sticky; top: 0; z-index: 2;
        background: var(--card-bg, #fff);
        box-shadow: inset 0 -1px 0 var(--card-border, #e5e7eb);
    }
    #productPickerModal #pp-table td { word-break: break-word; }
    #productPickerModal #pp-table tbody tr.pp-row-selected,
    #productPickerModal #pp-table tbody tr.pp-row-selected td { background-color: #ecfdf5 !important; }
    #productPickerModal #pp-table tbody tr.pp-row-existing td { opacity: .75; }
    #productPickerModal .pp-existing-badge {
        display: inline-block; margin-left: .35rem; font-size: 10px; padding: .05rem .4rem;
        border-radius: 4px; background: #fef3c7; color: #92400e; vertical-align: middle;
    }
    #productPickerModal .pp-check { width: 16px; height: 16px; cursor: pointer; }
    /* Info & paging DataTables dipindah ke footer; sembunyikan baris layout kosong */
    #productPickerModal .dt-container > .dt-layout-row:not(.dt-layout-table) { display: none; }
    #productPickerModal .pp-footer-nav { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; font-size: 12px; }
    #productPickerModal .pp-footer-nav .dt-info { color: var(--text-muted); padding: 0; }
    #productPickerModal .pp-footer-nav .dt-paging .pagination { margin: 0; }
    #productPickerModal .pp-footer-nav .dt-paging .page-link { padding: .2rem .55rem; font-size: 12px; }
    #productPickerModal .pp-kbd { font-size: 11px; color: var(--text-muted); }
    #productPickerModal .pp-kbd kbd { font-size: 10px; padding: .05rem .3rem; }
</style>

<div class="modal fade" id="productPickerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-box-open me-2" style="color:var(--accent)"></i>Pilih Part Instrument
                    <small id="pp-target-label" style="font-size:11px;color:var(--text-muted);font-weight:400"></small></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="pp-toolbar">
                <div class="pp-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="pp-search" class="form-control" autocomplete="off"
                           placeholder="Cari part number / brand / kategori...">
                    <button type="button" id="pp-search-clear" class="d-none" title="Hapus pencarian"><i class="fa fa-xmark"></i></button>
                </div>
                <div class="pp-chips d-none" id="pp-chips"></div>
            </div>
            <div class="modal-body">
                <table id="pp-table" class="table table-custom align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            @foreach ($ppColumns as $ppCol)
                                <th>{{ $ppCol }}</th>
                            @endforeach
                        </tr>
                    </thead>
                </table>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="pp-footer-nav" id="pp-footer-nav"></div>
                <div class="d-flex align-items-center gap-2">
                    <span class="pp-kbd d-none d-lg-inline"><kbd>Ctrl</kbd>+<kbd>Enter</kbd> tambah</span>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none d-none" id="pp-clear-selection">Kosongkan</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-pp-add" disabled>
                        <i class="fa fa-plus me-1"></i> Tambah <span id="pp-count-label"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function selectedMap() { return (typeof ppSelected !== 'undefined' && ppSelected) ? ppSelected : {}; }
    function existingIds() {
        return (typeof existingProductIds !== 'undefined' && existingProductIds ? existingProductIds : []).map(String);
    }

    function refreshUi() {
        var sel = selectedMap();
        var ids = Object.keys(sel);
        var n = ids.length;

        $('#btn-pp-add').prop('disabled', n === 0);
        $('#pp-count-label').text(n ? '(' + n + ')' : '');
        $('#pp-clear-selection').toggleClass('d-none', n === 0);

        var $chips = $('#pp-chips').empty().toggleClass('d-none', n === 0);
        ids.forEach(function (id) {
            var p = sel[id] || {};
            var $chip = $('<span class="pp-chip">').attr('title', p.name || p.description || '');
            $chip.append($('<span>').text(p.code || id));
            $chip.append($('<button type="button" class="pp-chip-remove" title="Batalkan pilihan">&times;</button>').attr('data-id', id));
            $chips.append($chip);
        });

        var existing = existingIds();
        $('#pp-table tbody tr').each(function () {
            var $cb = $(this).find('.pp-check');
            if (!$cb.length) return;
            var id = String($cb.val());
            var isSel = !!sel[id];
            $cb.prop('checked', isSel);
            $(this).toggleClass('pp-row-selected', isSel);
            var isExisting = existing.indexOf(id) !== -1;
            $(this).toggleClass('pp-row-existing', isExisting);
            var $codeCell = $(this).children('td').eq(1);
            if (isExisting && !$codeCell.find('.pp-existing-badge').length) {
                $codeCell.append('<span class="pp-existing-badge" title="Part ini sudah ada di daftar item">Sudah ada</span>');
            }
        });
    }
    window.ppRefreshUi = refreshUi;

    // Handler halaman yang mengubah ppSelected berjalan setelah ini → tunda sejenak.
    function refreshSoon() { setTimeout(refreshUi, 0); }

    $(document).on('draw.dt', '#pp-table', function () {
        var $nav = $('#pp-footer-nav');
        if (!$nav.children().length) {
            var $wrap = $('#pp-table').closest('.dt-container');
            $wrap.find('.dt-info, .dt-paging').appendTo($nav);
        }
        $('#productPickerModal .modal-body').scrollTop(0);
        refreshUi();
    });

    $(document).on('change', '.pp-check', refreshSoon);
    $(document).on('click', '#btn-pp-add', refreshSoon);

    $(document).on('click', '.pp-chip-remove', function () {
        delete selectedMap()[String($(this).data('id'))];
        refreshUi();
    });

    $(document).on('click', '#pp-clear-selection', function () {
        var sel = selectedMap();
        Object.keys(sel).forEach(function (k) { delete sel[k]; });
        refreshUi();
    });

    // Pencarian: tombol clear, Enter = cari langsung, Esc di search = kosongkan dulu
    $(document).on('input', '#pp-search', function () {
        $('#pp-search-clear').toggleClass('d-none', !$(this).val());
    });
    $(document).on('click', '#pp-search-clear', function () {
        $('#pp-search').val('').trigger('input').trigger('focus');
    });
    // Bind langsung (bukan delegasi) agar Esc bisa dicegah sebelum sampai ke handler Bootstrap
    $('#pp-search').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            if (typeof ppTable !== 'undefined' && ppTable) ppTable.ajax.reload();
        } else if (e.key === 'Escape' && $(this).val()) {
            e.preventDefault();
            e.stopPropagation();
            $(this).val('').trigger('input');
        }
    });

    $(document).on('keydown', '#productPickerModal', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            if (!$('#btn-pp-add').prop('disabled')) $('#btn-pp-add').trigger('click');
        }
    });

    $(document).on('shown.bs.modal', '#productPickerModal', function () {
        refreshUi();
        $('#pp-search').trigger('focus');
    });
    $(document).on('hidden.bs.modal', '#productPickerModal', function () {
        $('#pp-search-clear').addClass('d-none');
        refreshSoon();
    });
})();
</script>

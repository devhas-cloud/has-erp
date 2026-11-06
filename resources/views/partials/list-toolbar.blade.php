{{--
    Toolbar daftar: kotak cari (menggantikan search bawaan DataTables) + filter
    + reset dalam satu baris. Pakai di @section('styles') -> @include ini, lalu
    di JS: const tb = ListToolbar('#xx-toolbar', () => xxTable);
      - ajax.data: tb.params           - DataTables: layout: ListToolbar.layout
      - xhr.dt:    tb.statusCounts(json)
    Atribut kontrol:
      [data-filter="name"]                     nilai dikirim sebagai parameter
      [data-searchable]                         select panjang (bisa dicari)
      [data-status-select] option[data-label]  label + jumlah per status
      [data-hide-when="filter:value"]          sembunyikan (dan kosongkan) bila filter lain bernilai tsb
--}}
<style>
    .list-toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 6px 6px 10px; }
    .list-toolbar .lt-search { position: relative; flex: 1 1 240px; max-width: 340px; }
    .list-toolbar .lt-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px; }
    .list-toolbar .lt-search input { padding-left: 30px; font-size: 12.5px; height: 32px; }
    .list-toolbar .lt-search .lt-clear { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: none; color: var(--text-muted); font-size: 12px; display: none; }
    .list-toolbar select.form-select, .list-toolbar input[type="date"] { font-size: 12.5px; height: 32px; padding-top: 4px; padding-bottom: 4px; width: auto; max-width: 200px; }
    .list-toolbar .lt-date { display: inline-flex; align-items: center; gap: 4px; }
    .list-toolbar .lt-date span { font-size: 12px; color: var(--text-muted); }
    .list-toolbar .select2-container { min-width: 170px; max-width: 240px; }
    .list-toolbar .select2-container .select2-selection--single { height: 32px !important; min-height: 32px; font-size: 12.5px; }
    .list-toolbar .active, .list-toolbar .active + .select2-container .select2-selection { border-color: var(--accent) !important; background-color: var(--accent-subtle) !important; }
    .list-toolbar .lt-reset { font-size: 12.5px; height: 32px; white-space: nowrap; }
    .list-toolbar .lt-reset .badge { background: var(--accent); font-size: 10px; }
    @media (max-width: 767.98px) {
        .list-toolbar .lt-search { max-width: none; flex-basis: 100%; }
        .list-toolbar select.form-select, .list-toolbar .select2-container { max-width: none; flex: 1 1 45%; }
    }
</style>

@push('page-scripts')
<script>
/**
 * Toolbar daftar bersama (Permintaan Barang, Purchase Order, Direct Purchase).
 */
function ListToolbar(rootSelector, getTable) {
    var $root = $(rootSelector);
    var searchTimer = null;

    $root.find('select[data-searchable]').each(function() {
        $(this).select2({
            theme: 'bootstrap-5',
            width: 'style',
            placeholder: $(this).find('option:first').text(),
            allowClear: true,
            selectionCssClass: 'select2--small',
            dropdownCssClass: 'select2--small'
        });
    });

    function sync() {
        // Filter yang tidak relevan (mis. Project saat tujuan Stok/Internal) disembunyikan & dikosongkan.
        $root.find('[data-hide-when]').each(function() {
            var rule = String($(this).data('hide-when')).split(':');
            var hide = $root.find('[data-filter="' + rule[0] + '"]').val() === rule[1];
            if (hide && $(this).val()) $(this).val('').trigger('change.select2');
            $(this).toggle(!hide);
            $(this).next('.select2-container').toggle(!hide);
        });

        var active = 0;
        $root.find('[data-filter]').each(function() {
            var on = !!$(this).val();
            $(this).toggleClass('active', on);
            if (on) active++;
        });
        var search = $root.find('[data-search]').val();
        if (search) active++;
        $root.find('.lt-clear').toggle(!!search);
        $root.find('[data-reset]').prop('disabled', active === 0)
            .find('.badge').text(active).toggle(active > 0);
    }

    function reload() {
        sync();
        getTable().ajax.reload();
    }

    $root.on('change', '[data-filter]', reload);

    $root.on('input', '[data-search]', function() {
        var value = $(this).val();
        sync();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() { getTable().search(value).draw(); }, 350);
    });

    $root.on('click', '.lt-clear', function() {
        $root.find('[data-search]').val('');
        sync();
        getTable().search('').draw();
    });

    $root.on('click', '[data-reset]', function() {
        $root.find('[data-filter]').val('').trigger('change.select2');
        $root.find('[data-search]').val('');
        sync();
        getTable().search('').ajax.reload();
    });

    sync();

    return {
        params: function(d) {
            $root.find('[data-filter]').each(function() { d[$(this).data('filter')] = $(this).val() || ''; });
            return d;
        },
        // Jumlah per status (mengikuti cari & filter lain) di opsi Status.
        statusCounts: function(json) {
            if (!json || !json.status_counts) return;
            var all = 0;
            $.each(json.status_counts, function(key, total) {
                all += total;
                var $opt = $root.find('[data-status-select] option[value="' + key + '"]');
                $opt.text($opt.data('label') + ' (' + total + ')');
            });
            $root.find('[data-status-select] option[value=""]').text('All Statuses (' + all + ')');
        }
    };
}

// Search bawaan DataTables diganti kotak cari toolbar.
ListToolbar.layout = { topStart: null, topEnd: null, bottomStart: ['pageLength', 'info'], bottomEnd: 'paging' };
ListToolbar.language = {
    zeroRecords: '<span style="color:var(--text-muted)"><i class="fa-solid fa-filter-circle-xmark me-1"></i>No data matches the search / filter.</span>',
    emptyTable: '<span style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i>No data yet.</span>'
};
</script>
@endpush

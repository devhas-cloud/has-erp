{{--
    Pemilih baris stok bersama (Stock Out, Transfer, Delivery Order, Retur).
    Data dari endpoint modul pemanggil (App\Support\StockPicker::row()).

    StockPick.load($el, url, params, opts) — opts:
        requireSerial : unit ber-SN tanpa SN tidak bisa dipilih (DO, retur, pemakaian)
        selected      : {stock_id: qty} pilihan awal (mode edit)
        onChange(total)
    StockPick.picks($el)  -> [{stock_id, qty}]
    StockPick.total($el)  -> jumlah qty dipilih
--}}
<style>
    .sp-table td, .sp-table th { font-size: 12.5px; vertical-align: middle; }
    .sp-table tr.sp-disabled { opacity: .55; }
    .sp-table .sp-qty { width: 96px; }
</style>
<script>
window.StockPick = (function() {
    var detailUrl = '{{ route("stock-detail.index") }}';

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function render($el, rows, opts) {
        if (!rows.length) {
            $el.html('<div class="text-center py-3" style="color:var(--text-muted);font-size:13px"><i class="fa-solid fa-inbox me-1"></i>Tidak ada stok yang bisa dipilih.</div>');
            return;
        }
        var selected = opts.selected || {};
        var html = '<div class="table-responsive" style="max-height:340px;overflow:auto"><table class="table table-sm sp-table mb-0"><thead><tr>'
            + '<th style="width:30px"></th><th>Barang</th><th>Posisi</th><th>Masuk / Kedaluwarsa</th><th class="text-end">Sisa</th><th class="text-end">Ambil</th></tr></thead><tbody>';
        rows.forEach(function(r) {
            var blocked = opts.requireSerial && !r.can_leave;
            var picked = selected[r.id] != null;
            var qty = picked ? selected[r.id] : r.qty;
            html += '<tr data-stock-id="' + r.id + '" data-max="' + r.qty + '" data-serialized="' + (r.serialized ? 1 : 0) + '"' + (blocked ? ' class="sp-disabled"' : '') + '>';
            html += '<td><input type="checkbox" class="form-check-input sp-check"' + (blocked ? ' disabled' : '') + (picked && !blocked ? ' checked' : '') + '></td>';
            html += '<td><div class="fw-semibold">' + esc(r.product_code) + '</div><div style="color:var(--text-muted)">' + esc(r.identity)
                + (blocked ? ' &middot; <a href="' + detailUrl + '?product_id=' + r.master_product_id + '" target="_blank">lengkapi SN</a>' : '')
                + (r.note ? '<br><i class="fa fa-note-sticky me-1"></i>' + esc(r.note) : '') + '</div></td>';
            html += '<td>' + esc(r.warehouse || '') + '<div style="color:var(--text-muted)">' + (r.project ? esc(r.project) : 'Stok bebas') + '</div></td>';
            html += '<td>' + esc(r.received_date || '—') + (r.expiry_date ? '<div style="color:var(--text-muted)">exp ' + esc(r.expiry_date) + '</div>' : '') + '</td>';
            html += '<td class="text-end">' + esc(r.qty_label) + ' ' + esc(r.unit || '') + '</td>';
            html += '<td class="text-end">' + (r.serialized
                ? '<span style="color:var(--text-muted)">1 unit</span><input type="hidden" class="sp-qty" value="1">'
                : '<input type="text" inputmode="decimal" class="form-control form-control-sm text-end sp-qty d-inline-block" value="' + qty + '"' + (blocked ? ' disabled' : '') + '>') + '</td>';
            html += '</tr>';
        });
        $el.html(html + '</tbody></table></div><div class="sp-summary mt-2" style="font-size:12.5px"></div>');
        summary($el, opts);
    }

    function summary($el, opts) {
        var total = api.total($el), count = api.picks($el).length;
        $el.find('.sp-summary').html('Dipilih: <strong>' + count + '</strong> baris, total <strong>' + Num.format(total, 4) + '</strong>');
        if (opts.onChange) opts.onChange(total);
    }

    var api = {
        load: function($el, url, params, opts) {
            opts = opts || {};
            $el.data('sp-opts', opts);
            $el.html('<div class="text-center py-3" style="color:var(--text-muted)"><span class="spinner-border spinner-border-sm me-1"></span>Memuat stok...</div>');
            return $.get(url, params).done(function(res) { render($el, res.data || [], opts); })
                .fail(function(xhr) {
                    $el.html('<div class="text-danger py-2" style="font-size:13px">' + esc(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat stok.') + '</div>');
                });
        },
        picks: function($el) {
            var out = [];
            $el.find('tr[data-stock-id]').each(function() {
                if (!$(this).find('.sp-check').is(':checked')) return;
                var qty = Num.parseOrNull($(this).find('.sp-qty').val()) || 0;
                if (qty > 0) out.push({ stock_id: $(this).data('stock-id'), qty: qty });
            });
            return out;
        },
        total: function($el) {
            return api.picks($el).reduce(function(sum, p) { return sum + p.qty; }, 0);
        }
    };

    $(document).on('change', '.sp-check', function() {
        var $el = $(this).closest('table').parent().parent();
        summary($el, $el.data('sp-opts') || {});
    });
    $(document).on('input', '.sp-qty', function() {
        this.value = this.value.replace(/[^\d.]/g, '');
        var $tr = $(this).closest('tr');
        var max = parseFloat($tr.data('max')) || 0;
        if ((Num.parseOrNull(this.value) || 0) > max) this.value = max;
        $tr.find('.sp-check').prop('checked', (Num.parseOrNull(this.value) || 0) > 0);
        var $el = $(this).closest('table').parent().parent();
        summary($el, $el.data('sp-opts') || {});
    });

    return api;
})();
</script>

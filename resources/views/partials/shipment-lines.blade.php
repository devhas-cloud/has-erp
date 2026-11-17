{{--
    Editor baris dokumen pengiriman (koli Packing List / baris DO Langsung).
    Tiap baris: deskripsi (dari item Quotation atau bebas), qty, satuan, dan isi
    baris stok (pemilih stok bersama — wajib minimal satu).
    Butuh partials.stock-pick dimuat lebih dulu.

    var editor = ShipmentLines({
        el: '#container', boxMode: true|false, stockUrl: '...',
        params: function() { return { project_id: .., warehouse_id: .. }; },
        onChange: function() {}
    });
    editor.setQuotationItems(items); editor.addLine(data); editor.openQuotation();
    editor.reloadStocks(); editor.lines() -> payload; editor.count()
--}}
<style>
    .sl-line { border: 1px solid var(--card-border); border-radius: var(--radius-sm); margin-bottom: 10px; background: var(--card, #fff); }
    .sl-line-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--card-border); background: var(--accent-subtle); }
    .sl-line-head .sl-no { font-weight: 700; font-size: 13px; }
    .sl-line-body { padding: 10px 12px; }
    .sl-line textarea { font-size: 13px; }
    .sl-qref { font-size: 11.5px; color: var(--text-muted); }
    .sl-stock-toggle { font-size: 12.5px; cursor: pointer; user-select: none; }
    .sl-stock-count.empty { color: var(--danger, #b42318); }
    #slQuotationModal .sl-q-row { cursor: pointer; }
    #slQuotationModal .sl-q-row.is-child td:nth-child(3) { padding-left: 24px; }
    #slQuotationModal .sl-q-desc { white-space: pre-line; font-size: 12px; color: var(--text-muted); max-height: 3.6em; overflow: hidden; }
</style>
<script>
window.ShipmentLines = function(opts) {
    var $root = $(opts.el);
    var seq = 0;
    var quotationItems = [];

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function num(v) { var n = Num.parseOrNull(v); return n == null ? '' : n; }

    function renumber() {
        var $lines = $root.find('.sl-line');
        $lines.each(function(i) {
            $(this).find('.sl-no').text((opts.boxMode ? 'Koli ' + (i + 1) + ' of ' + $lines.length : 'Baris ' + (i + 1)));
        });
        if (opts.onChange) opts.onChange();
    }

    function stockCount($line) {
        var n = Object.keys($line.data('selected') || {}).length;
        $line.find('.sl-stock-count').text(n + ' barang dari stok').toggleClass('empty', n === 0);
    }

    function syncSelected($line) {
        var selected = $line.data('selected') || {};
        var $picker = $line.find('.sl-picker');
        $picker.find('tr[data-stock-id]').each(function() { delete selected[$(this).data('stock-id')]; });
        StockPick.picks($picker).forEach(function(p) { selected[p.stock_id] = p.qty; });
        $line.data('selected', selected);
        stockCount($line);
    }

    function loadStocks($line) {
        var params = opts.params();
        var $picker = $line.find('.sl-picker');
        if (!params.project_id || !params.warehouse_id) {
            $picker.html('<div class="text-center py-3" style="color:var(--text-muted);font-size:13px">Pilih project dan gudang terlebih dahulu.</div>');
            return;
        }
        StockPick.load($picker, opts.stockUrl, $.extend({ q: $line.find('.sl-q').val() }, params), {
            requireSerial: true,
            selected: $line.data('selected') || {},
            onChange: function() { syncSelected($line); }
        });
    }

    function quotationRef(id) {
        var item = quotationItems.find(function(q) { return q.id === id; });
        if (!item) return '';
        return 'Quotation ' + (item.item_no || '') + ' · terkirim ' + Num.format(item.delivered, 4) + (item.qty != null ? ' dari ' + Num.format(item.qty, 4) : '') + ' ' + (item.unit || '');
    }

    function addLine(data) {
        data = data || {};
        var id = ++seq;
        var html = '<div class="sl-line" data-key="' + id + '">'
            + '<div class="sl-line-head"><span class="sl-no"></span>'
            + '<button type="button" class="btn-icon danger sl-remove" title="Hapus"><i class="fa fa-trash"></i></button></div>'
            + '<div class="sl-line-body"><div class="row g-2">'
            + '<div class="col-12"><textarea class="form-control sl-desc" rows="3" maxlength="5000" placeholder="Deskripsi (contoh: part number, nama barang, isi paket)">' + esc(data.description || '') + '</textarea>'
            + '<div class="sl-qref"></div></div>';
        if (opts.boxMode) {
            html += '<div class="col-md-2 col-4"><label class="form-label mb-0" style="font-size:12px">Qty Order</label><input type="text" inputmode="decimal" class="form-control form-control-sm text-end sl-qty-order" value="' + num(data.qty_order) + '"></div>';
        }
        html += '<div class="col-md-2 col-4"><label class="form-label mb-0" style="font-size:12px">' + (opts.boxMode ? 'Qty Kirim' : 'Qty') + ' <span class="text-danger">*</span></label><input type="text" inputmode="decimal" class="form-control form-control-sm text-end sl-qty" value="' + num(data.qty) + '"></div>'
            + '<div class="col-md-2 col-4"><label class="form-label mb-0" style="font-size:12px">Satuan</label><input type="text" class="form-control form-control-sm sl-unit" maxlength="50" value="' + esc(data.unit || '') + '" placeholder="Set / Unit"></div>';
        if (opts.boxMode) {
            html += '<div class="col-md-4 col-8"><label class="form-label mb-0" style="font-size:12px">Dimensi P × L × T (cm)</label><div class="input-group input-group-sm">'
                + '<input type="text" inputmode="decimal" class="form-control text-end sl-l" value="' + num(data.length_cm) + '" placeholder="P">'
                + '<input type="text" inputmode="decimal" class="form-control text-end sl-w" value="' + num(data.width_cm) + '" placeholder="L">'
                + '<input type="text" inputmode="decimal" class="form-control text-end sl-h" value="' + num(data.height_cm) + '" placeholder="T"></div></div>'
                + '<div class="col-md-2 col-4"><label class="form-label mb-0" style="font-size:12px">Berat (kg)</label><input type="text" inputmode="decimal" class="form-control form-control-sm text-end sl-kg" value="' + num(data.weight_kg) + '"></div>';
        }
        html += '</div>'
            + '<div class="mt-2 d-flex justify-content-between align-items-center flex-wrap gap-2">'
            + '<span class="sl-stock-toggle"><i class="fa fa-chevron-down me-1"></i><strong>Isi dari stok</strong> · <span class="sl-stock-count"></span></span>'
            + '<input type="search" class="form-control form-control-sm sl-q" style="max-width:220px" placeholder="Cari kode, nama, SN, batch">'
            + '</div><div class="sl-picker mt-2"></div>'
            + '</div></div>';

        var $line = $(html);
        $line.data('qid', data.quotation_item_id || null);
        var selected = {};
        (data.stocks || []).forEach(function(s) { selected[s.stock_id] = s.qty; });
        $line.data('selected', selected);
        $line.find('.sl-qref').text(data.quotation_item_id ? quotationRef(data.quotation_item_id) : '');
        $root.append($line);
        stockCount($line);
        loadStocks($line);
        renumber();
        return $line;
    }

    $root.on('click', '.sl-remove', function() { $(this).closest('.sl-line').remove(); renumber(); });
    $root.on('click', '.sl-stock-toggle', function() {
        var $line = $(this).closest('.sl-line');
        $line.find('.sl-picker').toggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-right');
    });
    var searchTimers = {};
    $root.on('input', '.sl-q', function() {
        var $line = $(this).closest('.sl-line');
        syncSelected($line);
        clearTimeout(searchTimers[$line.data('key')]);
        searchTimers[$line.data('key')] = setTimeout(function() { loadStocks($line); }, 350);
    });
    $root.on('input', '.sl-qty, .sl-qty-order, .sl-l, .sl-w, .sl-h, .sl-kg', function() {
        this.value = this.value.replace(/[^\d.]/g, '');
        if (opts.onChange) opts.onChange();
    });

    // ── Pop-up item Quotation ──
    var modal = null;
    function ensureModal() {
        if (modal) return;
        $('body').append('<div class="modal fade" id="slQuotationModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">'
            + '<div class="modal-header"><h6 class="modal-title"><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Ambil dari Item Quotation</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
            + '<div class="modal-body"><div class="table-responsive"><table class="table table-custom align-middle mb-0"><thead><tr>'
            + '<th style="width:36px"></th><th style="width:70px">No</th><th>Item</th><th class="text-end" style="width:110px">Qty</th><th class="text-end" style="width:120px">Terkirim</th></tr></thead><tbody id="sl-q-body"></tbody></table></div></div>'
            + '<div class="modal-footer justify-content-between"><small style="color:var(--text-muted)">Setiap item terpilih menjadi satu ' + (opts.boxMode ? 'koli' : 'baris') + '; deskripsi & qty bisa diubah. Terkirim = DO terposting (info saja).</small>'
            + '<div class="d-flex gap-2"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary btn-sm" id="sl-q-apply"><i class="fa fa-plus me-1"></i> Tambahkan</button></div></div>'
            + '</div></div></div>');
        modal = new bootstrap.Modal(document.getElementById('slQuotationModal'));
        $(document).on('click', '#slQuotationModal .sl-q-row', function(e) {
            if ($(e.target).is('input')) return;
            var $c = $(this).find('input'); $c.prop('checked', !$c.is(':checked'));
        });
        $(document).on('click', '#sl-q-apply', function() {
            var picked = [];
            $('#sl-q-body input:checked').each(function() { picked.push(quotationItems[$(this).data('idx')]); });
            if (!picked.length) { toastr.error('Pilih minimal satu item.'); return; }
            picked.forEach(function(q) {
                var remaining = q.qty != null ? Math.max(q.qty - q.delivered, 0) : null;
                addLine({
                    quotation_item_id: q.id,
                    description: [q.part_number, q.description].filter(Boolean).join('\n'),
                    qty_order: q.qty,
                    qty: remaining || q.qty || '',
                    unit: q.unit
                });
            });
            modal.hide();
        });
    }

    function openQuotation() {
        if (!quotationItems.length) { toastr.warning('Project ini tidak punya item Quotation. Isi deskripsi sendiri.'); return; }
        ensureModal();
        var html = '';
        quotationItems.forEach(function(q, idx) {
            var full = q.qty != null && q.delivered >= q.qty;
            html += '<tr class="sl-q-row' + (q.is_child ? ' is-child' : '') + '">'
                + '<td><input type="checkbox" class="form-check-input" data-idx="' + idx + '"></td>'
                + '<td>' + esc(q.item_no || '') + '</td>'
                + '<td><div class="fw-semibold">' + esc(q.part_number || '') + '</div><div class="sl-q-desc">' + esc(q.description || '') + '</div></td>'
                + '<td class="text-end">' + (q.qty != null ? Num.format(q.qty, 4) : '—') + ' ' + esc(q.unit || '') + '</td>'
                + '<td class="text-end">' + Num.format(q.delivered, 4) + (full ? ' <i class="fa fa-circle-check" style="color:var(--accent)" title="Sudah terkirim semua"></i>' : '') + '</td></tr>';
        });
        $('#sl-q-body').html(html);
        modal.show();
    }

    return {
        addLine: addLine,
        openQuotation: openQuotation,
        setQuotationItems: function(items) {
            quotationItems = items || [];
            $root.find('.sl-line').each(function() { var qid = $(this).data('qid'); $(this).find('.sl-qref').text(qid ? quotationRef(qid) : ''); });
        },
        reloadStocks: function(reset) {
            $root.find('.sl-line').each(function() {
                if (reset) { $(this).data('selected', {}); stockCount($(this)); }
                loadStocks($(this));
            });
        },
        count: function() { return $root.find('.sl-line').length; },
        totalWeight: function() {
            var total = 0;
            $root.find('.sl-kg').each(function() { total += Num.parseOrNull(this.value) || 0; });
            return total;
        },
        clear: function() { $root.empty(); renumber(); },
        lines: function() {
            var out = [];
            $root.find('.sl-line').each(function() {
                var $l = $(this);
                syncSelected($l);
                var selected = $l.data('selected') || {};
                var line = {
                    quotation_item_id: $l.data('qid') || null,
                    description: $.trim($l.find('.sl-desc').val()),
                    qty: Num.parseOrNull($l.find('.sl-qty').val()),
                    unit: $.trim($l.find('.sl-unit').val()),
                    stocks: Object.keys(selected).map(function(id) { return { stock_id: parseInt(id, 10), qty: selected[id] }; })
                };
                if (opts.boxMode) {
                    line.qty_order = Num.parseOrNull($l.find('.sl-qty-order').val());
                    line.length_cm = Num.parseOrNull($l.find('.sl-l').val());
                    line.width_cm = Num.parseOrNull($l.find('.sl-w').val());
                    line.height_cm = Num.parseOrNull($l.find('.sl-h').val());
                    line.weight_kg = Num.parseOrNull($l.find('.sl-kg').val());
                }
                out.push(line);
            });
            return out;
        }
    };
};
</script>

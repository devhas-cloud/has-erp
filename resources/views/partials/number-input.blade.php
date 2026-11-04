<script>
/**
 * Num — format & input angka satu standar (gaya US: 10,000.50), pasangan
 * App\Support\Number di PHP. Server selalu menerima angka mentah (Num.parse).
 *
 * Input cukup diberi data-number="money|qty|percent|rate|decimal":
 *   - pemisah ribuan muncul saat mengetik (posisi kursor dijaga);
 *   - desimal dibatasi per jenis (money 2, percent 2, rate 6, qty 0) —
 *     "10.000" di field uang tidak bisa diketik, jadi salah kebiasaan terlihat;
 *   - tempel angka gaya Indonesia ("10.000,50") dikenali & diubah ke 10,000.50.
 */
(function (window, $) {
    var TYPES = {
        money: { decimals: 2, group: true },
        qty: { decimals: 0, group: true },
        percent: { decimals: 2, group: false },
        rate: { decimals: 6, group: true },
        decimal: { decimals: 4, group: true }   // nominal/qty presisi tinggi (mis. Estimasi PL)
    };

    function typeOf(el) {
        return TYPES[$(el).data('number')] || TYPES.money;
    }

    /**
     * Teks -> angka. Gaya US: koma dibuang, titik = desimal. Bila koma muncul
     * setelah titik terakhir ("10.000,50") atau satu koma dengan 1-2 digit
     * ("10,5"), dianggap gaya Indonesia (hasil tempel dari Excel/dokumen lain).
     */
    function parse(value) {
        if (typeof value === 'number') return isFinite(value) ? value : 0;
        var s = String(value == null ? '' : value).trim().replace(/^(Rp|IDR|USD|EUR|SGD|\$|€)\s*/i, '');
        s = s.replace(/[^0-9.,-]/g, '');
        if (s === '' || s === '-' || s === '.') return 0;

        var lastComma = s.lastIndexOf(','), lastDot = s.lastIndexOf('.');
        if (lastComma > -1 && lastDot > -1 && lastComma > lastDot) {
            s = s.replace(/\./g, '').replace(',', '.');
        } else if (lastComma > -1 && lastDot === -1 && (s.match(/,/g) || []).length === 1 && /,\d{1,2}$/.test(s)) {
            s = s.replace(',', '.');
        } else {
            s = s.replace(/,/g, '');
        }

        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    /** Teks kosong -> null (untuk field opsional). */
    function parseOrNull(value) {
        return String(value == null ? '' : value).trim() === '' ? null : parse(value);
    }

    function format(value, maxDecimals, minDecimals) {
        var n = typeof value === 'number' ? value : parse(value);
        if (!isFinite(n)) n = 0;
        return n.toLocaleString('en-US', {
            minimumFractionDigits: minDecimals || 0,
            maximumFractionDigits: maxDecimals == null ? 2 : maxDecimals
        });
    }

    /** Rupiah tanpa desimal kecuali ada sen; valas selalu 2 desimal. */
    function money(value, currency, base) {
        var n = typeof value === 'number' ? value : parse(value);
        var isBase = !currency || String(currency).toUpperCase() === String(base || window.NUM_BASE_CURRENCY || 'IDR').toUpperCase();
        if (!isBase) return format(n, 2, 2);
        var cents = Math.round(n * 100) / 100;
        return Math.abs(cents - Math.round(cents)) >= 0.005 ? format(cents, 2, 2) : format(Math.round(cents), 0, 0);
    }

    function percent(value) { return format(value, 2).replace(/,/g, ''); }
    function rate(value) { return format(value, 6); }

    /** Teks input yang sedang diketik -> teks ber-format sesuai jenis (tanpa memaksa desimal). */
    function formatTyping(raw, type) {
        var s = String(raw == null ? '' : raw).replace(/,/g, '');
        var negative = /^-/.test(s);
        s = s.replace(type.decimals > 0 ? /[^\d.]/g : /\D/g, '');
        var parts = s.split('.');
        var intPart = parts.shift().replace(/^0+(?=\d)/, '');
        var hasDot = type.decimals > 0 && s.indexOf('.') !== -1;
        var dec = parts.join('').slice(0, type.decimals);
        if (intPart === '' && hasDot) intPart = '0';
        if (type.group && intPart !== '') intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return (negative ? '-' : '') + intPart + (hasDot ? '.' + dec : '');
    }

    function reformat(el) {
        var type = typeOf(el);
        var value = el.value;
        var caret = el.selectionStart == null ? value.length : el.selectionStart;
        var digitsBefore = value.slice(0, caret).replace(/[^\d.]/g, '').length;
        var formatted = formatTyping(value, type);

        if (formatted === value) return;
        el.value = formatted;

        var pos = 0, seen = 0;
        while (pos < formatted.length && seen < digitsBefore) {
            if (/[\d.]/.test(formatted[pos])) seen++;
            pos++;
        }
        try { el.setSelectionRange(pos, pos); } catch (e) {}
    }

    function finalize(el) {
        if (String(el.value).trim() === '') return;
        var type = typeOf(el);
        var n = parse(el.value);
        el.value = type.group ? format(n, type.decimals) : format(n, type.decimals).replace(/,/g, '');
    }

    $(document)
        .on('input', 'input[data-number]', function () { reformat(this); })
        .on('blur', 'input[data-number]', function () { finalize(this); })
        .on('paste', 'input[data-number]', function (e) {
            var text = (e.originalEvent && e.originalEvent.clipboardData) ? e.originalEvent.clipboardData.getData('text') : null;
            if (text == null) return;
            e.preventDefault();
            var type = typeOf(this);
            var n = parse(text);
            this.value = type.group ? format(n, type.decimals) : format(n, type.decimals).replace(/,/g, '');
            $(this).trigger('input');
        });

    window.Num = {
        parse: parse,
        parseOrNull: parseOrNull,
        format: format,
        money: money,
        percent: percent,
        rate: rate,
        /** Nilai mentah sebuah input (angka). */
        val: function (el) { return parse($(el).val()); },
        /** Format awal semua input data-number di dalam container. */
        formatAll: function (root) { $(root || document).find('input[data-number]').each(function () { finalize(this); }); }
    };
})(window, jQuery);
</script>

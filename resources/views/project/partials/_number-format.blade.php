function pjToRaw(value) {
    return String(value == null ? '' : value).replace(/,/g, '');
}

function pjFormatInput(value) {
    var s = String(value == null ? '' : value).replace(/[^\d.]/g, '');
    if (s === '') return '';

    var parts = s.split('.');
    var intRaw = parts.shift();
    var dec = parts.join('').slice(0, 2);
    var hasDot = s.indexOf('.') !== -1;
    var int = intRaw === '' ? '' : Number(intRaw).toLocaleString('en-US');

    if (int === '' && hasDot) int = '0';

    return int + (hasDot ? '.' + dec : '');
}

function pjFormatStored(value) {
    var raw = pjToRaw(value).trim();
    if (raw === '') return '';

    var n = Number(raw);
    if (!isFinite(n)) return pjFormatInput(raw);

    return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function pjFormatNumber(value) {
    return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function pjFormatMoney(value) {
    return  pjFormatNumber(value);
}

function pjCents(value) {
    return Math.round((parseFloat(pjToRaw(value)) || 0) * 100);
}

function pjFormatNum(el) {
    var $el = $(el);
    var start = 0;
    try { start = el.selectionStart || 0; } catch (e) {}

    var before = pjToRaw($el.val()).slice(0, start);
    var digitsBefore = before.replace(/\D/g, '').length;
    var formatted = pjFormatInput($el.val());
    var caret = 0;
    var seen = 0;

    for (var i = 0; i < formatted.length && seen < digitsBefore; i++) {
        caret = i + 1;
        if (/\d/.test(formatted[i])) seen++;
    }

    $el.val(formatted);
    try { el.setSelectionRange(caret, caret); } catch (e) {}
}

function pjFormatAllStored(scope) {
    $(scope || document).find('.pj-number').each(function() {
        $(this).val(pjFormatStored($(this).val()));
    });
}

$(document).on('input', '.pj-number', function() {
    pjFormatNum(this);
});

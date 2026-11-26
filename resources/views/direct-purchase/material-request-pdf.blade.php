<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Material Request — Purchase Reference</title>
    <style>
        @page { margin: 12mm 14mm 16mm 14mm !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; line-height: 1.35; }
        table { border-collapse: collapse; }
        .doc-footer { position: fixed; bottom: -10mm; left: 0; right: 0; }
        .doc-footer td { font-size: 7.5px; color: #777; }
        .doc-footer td.pg { text-align: right; }
        .doc-footer td.pg:after { content: counter(page); }
        h1 { font-family: 'DejaVu Serif', serif; font-size: 16px; margin: 16px 0 8px; }
        table.meta td { padding: 1px 0; font-size: 10px; vertical-align: top; }
        table.meta td.k { width: 90px; color: #444; }
        table.meta td.sep { width: 10px; }
        table.items { width: 100%; margin-top: 12px; border: 1.5px solid #111; }
        table.items th { border: 1px solid #111; background: #e5e5e5; font-size: 9.5px; font-weight: 700; padding: 4px; text-align: center; }
        table.items td { border: 1px solid #111; padding: 4px; font-size: 9.5px; vertical-align: top; }
        table.items tr { page-break-inside: avoid; }
        table.items thead { display: table-header-group; }
        table.items td.c { text-align: center; }
        table.items td.r { text-align: right; white-space: nowrap; }
        .note { margin-top: 8px; font-size: 8.5px; color: #555; }
    </style>
</head>
<body>
@php $fmtQty = fn ($v) => \App\Support\StockCost::qty($v); @endphp
    <div class="doc-footer">
        <table style="width:100%"><tr><td>Material Request — Purchase Reference · printed {{ now()->format('d/m/Y H:i') }} by {{ $printedBy }}</td><td class="pg">Page </td></tr></table>
    </div>

    @include('partials.pdf-letterhead')

    <h1>MATERIAL REQUEST — PURCHASE REFERENCE</h1>

    <table class="meta">
        <tr><td class="k">Branch</td><td class="sep">:</td><td>{{ $branch?->name ?? '—' }}</td></tr>
        <tr><td class="k">Printed</td><td class="sep">:</td><td>{{ now()->format('d/m/Y H:i') }} · {{ $printedBy }}</td></tr>
        <tr><td class="k">Material Request</td><td class="sep">:</td><td>{{ $requestNumbers->implode(', ') ?: '—' }}</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:6%">No</th>
                <th style="width:20%">Code</th>
                <th>Product</th>
                <th style="width:16%">Remaining</th>
                <th style="width:16%">In Stock</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="r">{{ $fmtQty($row['need']) }} {{ $row['unit'] }}</td>
                    <td class="r">{{ $fmtQty($row['available']) }} {{ $row['unit'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="c" style="color:#777;padding:14px">No approved Material Request waiting in this branch.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="note">Remaining = approved Material Request quantity not yet handed over (same product merged). In Stock = free stock + stock of those projects in this branch's warehouses.</div>
</body>
</html>

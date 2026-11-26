<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Serah Terima {{ $handover->handover_number }}</title>
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
        table.meta { width: 100%; }
        table.meta td { padding: 1px 0; font-size: 10px; vertical-align: top; }
        table.meta td.k { width: 95px; color: #444; }
        table.meta td.sep { width: 10px; }
        table.items { width: 100%; margin-top: 12px; border: 1.5px solid #111; }
        table.items th { border: 1px solid #111; background: #e5e5e5; font-size: 9.5px; font-weight: 700; padding: 4px; text-align: center; }
        table.items td { border: 1px solid #111; padding: 4px; font-size: 9.5px; vertical-align: top; }
        table.items tr { page-break-inside: avoid; }
        table.items thead { display: table-header-group; }
        table.items td.c { text-align: center; }
        table.items td.r { text-align: right; white-space: nowrap; }
        table.sign { width: 100%; margin-top: 26px; page-break-inside: avoid; }
        table.sign td { width: 50%; text-align: center; font-size: 10px; vertical-align: top; }
        table.sign .space { height: 60px; }
        table.sign .name { border-top: 1px solid #111; margin: 0 40px; padding-top: 2px; font-weight: 700; }
    </style>
</head>
<body>
@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $mr = $handover->materialRequest;
@endphp
    <div class="doc-footer">
        <table style="width:100%"><tr><td>Bukti Serah Terima {{ $handover->handover_number }} · dicetak {{ now()->format('d/m/Y H:i') }}</td><td class="pg">Hal. </td></tr></table>
    </div>

    @include('partials.pdf-letterhead')

    <h1>BUKTI SERAH TERIMA MATERIAL</h1>

    <table class="meta">
        <tr>
            <td style="width:55%">
                <table style="width:100%">
                    <tr><td class="k">No. Serah Terima</td><td class="sep">:</td><td>{{ $handover->handover_number }}</td></tr>
                    <tr><td class="k">Tanggal</td><td class="sep">:</td><td>{{ $handover->handover_date?->format('d/m/Y') }}</td></tr>
                    <tr><td class="k">Material Request</td><td class="sep">:</td><td>{{ $mr?->request_number }}</td></tr>
                </table>
            </td>
            <td style="width:45%">
                <table style="width:100%">
                    <tr><td class="k">Project</td><td class="sep">:</td><td>{{ $mr?->project?->project_number }} — {{ $mr?->project?->project_name }}</td></tr>
                    <tr><td class="k">Branch</td><td class="sep">:</td><td>{{ $mr?->branch?->name }}</td></tr>
                    <tr><td class="k">Peminta</td><td class="sep">:</td><td>{{ $mr?->creator?->display_name ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:17%">Kode</th>
                <th>Nama Barang</th>
                <th style="width:20%">SN / Batch</th>
                <th style="width:12%">Gudang</th>
                <th style="width:12%">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($handover->items as $i => $item)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $item->stock?->masterProduct?->code }}</td>
                    <td>{{ $item->stock?->masterProduct?->name }}</td>
                    <td>{{ $item->stock?->serial_number ?: $item->stock?->batch_number }}</td>
                    <td class="c">{{ $item->stock?->warehouse?->code }}</td>
                    <td class="r">{{ $fmtQty($item->qty) }} {{ $item->stock?->unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($handover->note)
        <div style="margin-top:8px;font-size:9.5px"><strong>Catatan:</strong> {{ $handover->note }}</div>
    @endif
    <div style="margin-top:6px;font-size:9px;color:#444">Barang di atas telah diserahkan dan diterima dalam keadaan baik dan lengkap.</div>

    <table class="sign">
        <tr><td>Diserahkan oleh (Gudang),</td><td>Diterima oleh (Produksi),</td></tr>
        <tr><td class="space"></td><td class="space"></td></tr>
        <tr>
            <td><div class="name">{{ $handover->creator?->display_name ?? '' }}&nbsp;</div></td>
            <td><div class="name">{{ $handover->received_by_name }}&nbsp;</div></td>
        </tr>
    </table>
</body>
</html>

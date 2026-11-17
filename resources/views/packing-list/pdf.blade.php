<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Packing List {{ $pl->packing_number }}</title>
    <style>
        @page { margin: 12mm 14mm 14mm 14mm !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; line-height: 1.35; }
        table { border-collapse: collapse; }
        h1 { font-family: 'DejaVu Serif', serif; font-size: 18px; margin: 16px 0 10px; }
        table.meta { width: 100%; }
        table.meta td { vertical-align: top; padding: 1px 0; font-size: 10px; }
        table.meta td.k { width: 52px; }
        table.meta td.sep { width: 10px; }
        table.items { width: 100%; margin-top: 12px; border: 1.5px solid #111; }
        table.items th { border: 1px solid #111; border-bottom: 2px solid #111; font-size: 9.5px; font-weight: 400; padding: 3px 4px; text-align: center; vertical-align: middle; }
        table.items td { border-left: 1px solid #111; border-right: 1px solid #111; padding: 3px 4px; font-size: 9.5px; vertical-align: top; }
        table.items tr.last td { border-bottom: 1.5px solid #111; height: 40px; }
        table.items td.c { text-align: center; }
        table.items td.r { text-align: right; }
        table.foot { margin-top: 14px; }
        table.foot td { padding: 3px 0; font-size: 10px; vertical-align: top; }
        table.sign { width: 100%; margin-top: 4px; page-break-inside: avoid; }
        table.sign td { width: 50%; font-size: 10px; vertical-align: top; padding-left: 40px; }
        .detail h2 { font-size: 12px; margin: 0 0 8px; }
        table.detail-items { width: 100%; border: 1px solid #111; }
        table.detail-items th, table.detail-items td { border: 1px solid #111; padding: 2px 4px; font-size: 9px; text-align: left; }
    </style>
</head>
<body>
@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale('id')->translatedFormat('d F Y') : '';
    $count = $pl->boxes->count();
@endphp
    @include('partials.pdf-letterhead')

    <h1>PACKING LIST</h1>

    <table class="meta">
        <tr>
            <td style="width:62%">
                <table style="width:100%">
                    <tr><td class="k">To</td><td class="sep">:</td><td>{{ $pl->recipient_name }}</td></tr>
                    <tr><td class="k">Address</td><td class="sep">:</td><td style="white-space:pre-line">{{ $pl->recipient_address }}</td></tr>
                    <tr><td class="k">Phone</td><td class="sep">:</td><td>{{ $pl->recipient_phone }}</td></tr>
                    <tr><td class="k">Attn</td><td class="sep">:</td><td>{{ $pl->recipient_attn }}</td></tr>
                </table>
            </td>
            <td style="width:38%">
                <table style="width:100%">
                    <tr><td style="width:80px">Date</td><td class="sep">:</td><td>{{ $fmtDate($pl->customer_ref_date) }}</td></tr>
                    <tr><td>PO/SPK/No</td><td class="sep">:</td><td>{{ $pl->customer_ref }}</td></tr>
                    <tr><td>No. PKL</td><td class="sep">:</td><td>{{ $pl->packing_number }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:10%">Qty<br>Order</th>
                <th>Description</th>
                <th style="width:10%">Box No.</th>
                <th style="width:10%">Qty In Box/<br>Delivery</th>
                <th style="width:13%">Dimension<br>PxLxT (cm)</th>
                <th style="width:9%">Weight</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pl->boxes as $i => $box)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td class="c">{{ $box->qty_order !== null ? $fmtQty($box->qty_order).' '.$box->unit : '' }}</td>
                <td style="white-space:pre-line">{{ $box->description }}</td>
                <td class="c">{{ $i + 1 }} Of {{ $count }}</td>
                <td class="c">{{ $fmtQty($box->qty) }} {{ $box->unit }}</td>
                <td class="c">{{ $box->dimensionLabel() }}</td>
                <td class="r">{{ $box->weight_kg !== null ? $fmtQty($box->weight_kg).'Kg' : '' }}</td>
            </tr>
            @endforeach
            <tr class="last"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        </tbody>
    </table>

    <table class="foot">
        <tr><td style="width:90px">Total Package</td><td>: {{ $count }} Package box = {{ $fmtQty($pl->totalWeight()) }}Kg</td></tr>
        <tr><td style="height:8px"></td><td></td></tr>
        <tr><td>Marking</td><td>: {{ $pl->marking }}</td></tr>
        <tr><td style="height:8px"></td><td></td></tr>
        <tr><td>Date</td><td>: {{ $fmtDate($pl->packing_date) }}</td></tr>
    </table>

    <table class="sign">
        <tr>
            <td>Shipper / Supplier<br>PT. Has Environmental</td>
            <td>Customer / Receiver<br>{{ $pl->recipient_name }}</td>
        </tr>
        <tr><td style="height:55px"></td><td></td></tr>
        <tr>
            <td>Name : {{ $pl->signer_name }}<br>Position : {{ $pl->signer_position }}</td>
            <td>Name :<br>Position :</td>
        </tr>
    </table>

    @if($withDetail)
    <div class="detail" style="page-break-before:always">
        @include('partials.pdf-letterhead')
        <h2 style="margin-top:14px">Lampiran Rincian Barang — {{ $pl->packing_number }}</h2>
        <table class="detail-items">
            <thead>
                <tr><th style="width:9%">Box</th><th style="width:16%">Kode</th><th>Nama Barang</th><th style="width:24%">SN / Batch</th><th style="width:12%">Qty</th></tr>
            </thead>
            <tbody>
                @foreach($pl->boxes as $i => $box)
                    @foreach($box->items as $item)
                    <tr>
                        <td>{{ $i + 1 }} of {{ $count }}</td>
                        <td>{{ $item->stock?->masterProduct?->code }}</td>
                        <td>{{ $item->stock?->masterProduct?->name }}</td>
                        <td>{{ $item->stock?->serial_number ?: $item->stock?->batch_number }}</td>
                        <td>{{ $fmtQty($item->qty) }} {{ $item->unit }}</td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</body>
</html>

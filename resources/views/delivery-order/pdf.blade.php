<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Delivery Order {{ $order->do_number }}</title>
    <style>
        @page { margin: 12mm 14mm 14mm 14mm !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; line-height: 1.35; }
        table { border-collapse: collapse; }
        h1 { font-family: 'DejaVu Serif', serif; font-size: 18px; margin: 14px 0 10px; }
        table.meta { width: 100%; }
        table.meta td { vertical-align: top; padding: 1px 0; font-size: 10px; }
        table.meta td.k { width: 52px; }
        table.meta td.sep { width: 10px; }
        table.items { width: 100%; margin-top: 12px; border: 1.5px solid #111; }
        table.items th { border: 1px solid #111; border-bottom: 2px solid #111; font-size: 9.5px; font-weight: 400; padding: 2px 4px; text-align: center; }
        table.items td { border-left: 1px solid #111; border-right: 1px solid #111; padding: 3px 4px; font-size: 9.5px; vertical-align: top; }
        table.items tr.first td { padding-top: 10px; }
        table.items tr.last td { border-bottom: 1.5px solid #111; height: 60px; }
        table.items td.c { text-align: center; }
        table.foot { width: 100%; margin-top: 12px; page-break-inside: avoid; }
        table.foot td { vertical-align: top; font-size: 10px; }
        .line { display: inline-block; min-width: 160px; border-bottom: 1px solid #111; padding: 0 3px; }
        .recv { border: 1.5px solid #111; width: 100%; }
        .recv td { text-align: center; padding: 4px; }
    </style>
</head>
<body>
@php
    $fmtQty = fn ($v) => \App\Support\StockCost::qty($v);
    $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale('id')->translatedFormat('d F Y') : '';
@endphp
    @include('partials.pdf-letterhead')

    <h1>DELIVERY ORDER</h1>

    <table class="meta">
        <tr>
            <td style="width:58%">
                <table style="width:100%">
                    <tr><td class="k">To</td><td class="sep">:</td><td>{{ $order->recipient_name }}</td></tr>
                    <tr><td class="k">Address</td><td class="sep">:</td><td style="white-space:pre-line">{{ $order->shipping_address }}</td></tr>
                    <tr><td style="height:8px" colspan="3"></td></tr>
                    <tr><td class="k">Phone</td><td class="sep">:</td><td>{{ $order->recipient_phone }}</td></tr>
                    <tr><td class="k">Attn</td><td class="sep">:</td><td>{{ $order->recipient_attn }}</td></tr>
                </table>
            </td>
            <td style="width:42%">
                <table style="width:100%">
                    <tr><td style="width:82px">DO Number</td><td class="sep">:</td><td>{{ $order->do_number }}</td></tr>
                    <tr><td>Date</td><td class="sep">:</td><td>{{ $fmtDate($order->delivery_date) }}</td></tr>
                    <tr><td style="height:8px" colspan="3"></td></tr>
                    <tr><td>PO/SPK/ No</td><td class="sep">:</td><td>{{ $order->customer_ref }}</td></tr>
                    <tr><td>Date</td><td class="sep">:</td><td>{{ $fmtDate($order->customer_ref_date) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th>Description</th>
                <th style="width:11%">Qty</th>
                <th style="width:28%">Serial Number/Part Number</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
            <tr class="{{ $i === 0 ? 'first' : '' }}">
                <td class="c">{{ $i + 1 }}</td>
                <td style="white-space:pre-line">{{ $item->description }}</td>
                <td class="c">{{ $fmtQty($item->qty) }} {{ $item->unit }}</td>
                <td class="c">{{ $item->serialLabel() }}</td>
            </tr>
            @endforeach
            <tr class="last"><td></td><td></td><td></td><td></td></tr>
        </tbody>
    </table>

    <table class="foot">
        <tr>
            <td style="width:64%">
                <div>Note: Good has been Purchased can not be Returned, Refund or Exchange</div>
                <table style="margin-top:4px">
                    <tr><td style="width:80px">Cek By</td><td>:</td></tr>
                    <tr><td></td><td style="padding:4px 0 10px"><span class="line">{{ $order->checked_by_name }}</span></td></tr>
                    <tr><td>Acknowledge</td><td>:</td></tr>
                    <tr><td></td><td style="padding:4px 0 10px"><span class="line">{{ $order->acknowledged_by_name }}</span></td></tr>
                    <tr><td>Send by</td><td>:</td></tr>
                    <tr><td></td><td style="padding:12px 0 10px"><span class="line">{{ $order->sent_by_name }}</span></td></tr>
                </table>
            </td>
            <td style="width:36%;padding-top:10px">
                <table class="recv">
                    <tr><td style="height:95px;vertical-align:top;border-bottom:1.5px solid #111">Received in good Condition<br>By</td></tr>
                    <tr><td style="height:32px;vertical-align:top">Please sign and Stamp Here</td></tr>
                </table>
            </td>
        </tr>
    </table>
    <div style="margin-top:12px">PS : Please Fax to 021 - 4264624 after receipt and sign. Thank you</div>
</body>
</html>

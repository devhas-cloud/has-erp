<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Biaya Quotation {{ $quotation->quotation_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #111;
            padding: 8mm 14mm;
            line-height: 1.45;
        }

        .doc-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 6mm 14mm 2mm;
            border-bottom: 2px solid #111;
        }
        .doc-header .company {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .doc-header .addr,
        .doc-header .contact {
            font-size: 9.5px;
            color: #333;
        }
        .doc-header .npwp {
            font-size: 9.5px;
            color: #333;
            margin-top: 1px;
        }

        .doc-title {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            margin: 14px 0 12px;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-decoration: underline;
        }

        table.info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.info td {
            padding: 1.5px 4px;
            font-size: 10.5px;
            vertical-align: top;
        }
        table.info td.k {
            width: 55px;
            font-weight: 600;
        }
        table.info td.right {
            text-align: right;
        }
        table.info td.kr {
            width: 80px;
            font-weight: 600;
            text-align: right;
        }

        .salutation {
            margin: 10px 0 8px;
            font-size: 10.5px;
        }

        table.parts {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.parts th {
            background: #e8e8e8;
            border: 1px solid #999;
            padding: 5px 6px;
            font-size: 10px;
            text-align: left;
        }
        table.parts td {
            border: 1px solid #999;
            padding: 4px 6px;
            font-size: 10px;
            vertical-align: top;
        }
        .cat-row td {
            background: #f2f2f2;
            font-weight: 700;
            padding: 4px 6px;
            border: 1px solid #999;
            font-size: 10px;
        }
        .no-col { width: 26px; text-align: center; }
        .qty-col { width: 55px; text-align: center; }
        .price-col { width: 85px; text-align: right; }
        .amount-col { width: 90px; text-align: right; }
        table.parts tr { page-break-inside: avoid; }

        .catatan {
            margin: 8px 0;
            font-size: 9.5px;
        }
        .catatan .catatan-title { font-weight: 700; }

        .sign {
            width: 100%;
            margin-top: 48px;
            page-break-inside: avoid;
        }
        .sign table {
            width: 100%;
            border-collapse: collapse;
        }
        .sign td {
            text-align: center;
            font-size: 10px;
            padding: 4px;
            vertical-align: top;
        }
        .sign .label { font-weight: 600; }
        .sign .name { margin-top: 52px; font-weight: 700; }
        .sign .phone { font-size: 9.5px; }
        .sign .rule {
            border-top: 1px solid #111;
            width: 190px;
            margin: 0 auto;
        }

        @page { margin: 34mm 14mm 12mm 14mm; }
    </style>
</head>
<body>

    <div class="doc-header">
        <div class="company">PT. HAS ENVIRONMENTAL</div>
        <div class="addr">Ruko Mega Grosir Cempaka Mas Blok I/12</div>
        <div class="addr">Jl. Letjen Suprapto Cempaka Putih, Jakarta Pusat 10640</div>
        <div class="contact">Phone : 62 - 21- 42900007, 42900008 , Fax : 021 - 4264624</div>
        <div class="contact">email : info@has-environmental.com</div>
        <div class="npwp">NPWP : 02.593.153.6 027.000</div>
    </div>

    <div class="doc-title">COST / BIAYA</div>

    <table class="info">
        <tr>
            <td class="k">To</td>
            <td>{{ $quotation->to_name ?? '—' }}</td>
            <td class="kr">Date</td>
            <td class="right">{{ $quotation->date?->format('d F Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Attn.</td>
            <td>{{ $quotation->attn_name ?? '—' }}</td>
            <td class="kr">Our Ref</td>
            <td class="right">{{ $quotation->quotation_number ?? '—' }}</td>
        </tr>
    </table>

    @php $costRows = $quotation->flattenCostTree(); @endphp

    <table class="parts">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th>Description</th>
                <th class="qty-col">Qty</th>
                <th class="price-col">Unit Price</th>
                <th class="amount-col">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($costRows as $row)
                @php
                    $citem = $row['item'];
                    $cdepth = $row['depth'];
                    $isTitle = (bool) $citem->title;
                    $desc = $isTitle ? $citem->title : $citem->description;
                @endphp
                <tr>
                    <td class="no-col">{{ $citem->item_no }}</td>
                    <td>
                        <div style="padding-left:{{ $cdepth * 14 }}px;{{ $isTitle ? 'font-weight:700;' : '' }}">
                            {!! \App\Models\Quotation::renderDescription($desc) !!}
                        </div>
                    </td>
                    <td class="qty-col">{{ $citem->qty ?: '' }} {{ $citem->qty ? $citem->unit : '' }}</td>
                    <td class="price-col">{{ $citem->price ? \App\Models\Quotation::formatMoney($citem->price) : '' }}</td>
                    <td class="amount-col">{{ $citem->qty && $citem->price ? \App\Models\Quotation::formatMoney($citem->qty * $citem->price) : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:#999">Belum ada biaya.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            @php $costTotal = $quotation->costItems->reduce(fn ($c, $i) => $c + (($i->qty ?? 0) * ($i->price ?? 0)), 0); @endphp
            <tr class="cat-row">
                <td colspan="4" style="text-align:right">Total Price Biaya</td>
                <td style="text-align:right">{{ \App\Models\Quotation::formatMoney($costTotal) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($quotation->cost_notes)
        <div class="catatan">
            <span class="catatan-title">Catatan :</span><br>
            {!! nl2br(e($quotation->cost_notes)) !!}
        </div>
    @endif

    <div class="sign">
        <table>
            <tr>
                <td>
                    <div class="label">Signature</div>
                </td>
                <td>
                    <div class="label">Contact Person</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="name">
                        Computer generated<br>
                        {{ $quotation->creator?->username ?? '________' }}
                    </div>
                </td>
                <td>
                    <div class="name">
                        {{ $quotation->from_name ?? '________' }}<br>
                        <span class="phone">{{ $quotation->contact_phone ?? '' }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>

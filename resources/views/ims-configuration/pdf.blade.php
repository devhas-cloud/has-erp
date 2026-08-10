<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>IMS Configuration #{{ $quotation->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #111;
            padding: 28px 36px;
            line-height: 1.45;
        }
        .header {
            text-align: center;
            margin-bottom: 6px;
        }
        .header .company {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .header .addr {
            font-size: 10.5px;
            color: #333;
        }
        .header .contact {
            font-size: 10.5px;
            color: #333;
        }
        .header .npwp {
            font-size: 10.5px;
            color: #333;
            margin-top: 2px;
        }
        .doc-title {
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            margin: 14px 0 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        table.info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.info td {
            padding: 1.5px 4px;
            font-size: 11.5px;
            vertical-align: top;
        }
        table.info td.k {
            width: 90px;
            font-weight: 600;
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
            font-size: 11px;
            text-align: left;
        }
        table.parts td {
            border: 1px solid #999;
            padding: 4px 6px;
            font-size: 11px;
            vertical-align: top;
        }
        .cat-row td {
            background: #f2f2f2;
            font-weight: 700;
            padding: 4px 6px;
            border: 1px solid #999;
            font-size: 11px;
        }
        .no-col { width: 34px; text-align: center; }
        .pn-col { width: 130px; }
        .qty-col { width: 48px; text-align: center; }
        .parameter {
            font-weight: 700;
            margin: 8px 0 8px;
        }
        .note {
            margin-top: 12px;
            font-size: 10.5px;
            white-space: pre-line;
        }
        .sign {
            width: 100%;
            margin-top: 60px;
        }
        .sign table {
            width: 100%;
            border-collapse: collapse;
        }
        .sign td {
            text-align: center;
            font-size: 11px;
            padding: 4px;
            vertical-align: top;
        }
        .sign .label { font-weight: 600; }
        .sign .name { margin-top: 64px; font-weight: 700; }
        .sign .rule {
            border-top: 1px solid #111;
            width: 200px;
            margin: 0 auto;
        }
        .page-break { page-break-before: always; }
        table.parts tr { page-break-inside: avoid; }
        .sign { page-break-inside: avoid; }
        @media print {
            body { padding: 10mm 12mm; }
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="company">PT. HAS ENVIRONMENTAL</div>
        <div class="addr">Ruko Mega Grosir Cempaka Mas Blok I/12</div>
        <div class="addr">Jl. Letjen Suprapto Cempaka Putih, Jakarta Pusat 10640</div>
        <div class="contact">Phone : 62 - 21- 42900007, 42900008 , Fax : 021 - 4264624</div>
        <div class="contact">email : info@has-environmental.com</div>
        <div class="npwp">NPWP : 02.593.153.6 027.000</div>
    </div>

    <div class="doc-title">IMS Configuration</div>

    <table class="info">
        <tr>
            <td class="k">Task</td>
            <td>#{{ $quotation->task_id }} — {{ $quotation->task?->title }}</td>
            <td class="k">Sales</td>
            <td>{{ $quotation->sales_name }}</td>
        </tr>
        <tr>
            <td class="k">To</td>
            <td>{{ $quotation->location }}</td>
            <td class="k">Date</td>
            <td>{{ $quotation->date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="k">Address</td>
            <td>{{ $quotation->address }}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="k">PIC</td>
            <td>{{ $quotation->pic_name }}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="k">Phone</td>
            <td>{{ $quotation->pic_phone }}</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="k">Email</td>
            <td>{{ $quotation->pic_email }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <div class="parameter">Parameter {{ $quotation->parameter_note }}</div>

    @php
        $groups = $quotation->itemsGroupedByCategory();
        $no = 1;
        $totalItems = $quotation->items->count();
    @endphp

    <table class="parts">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th class="pn-col">Part Number</th>
                <th>List Part Instrument</th>
                <th class="qty-col">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $category => $items)
                <tr class="cat-row">
                    <td colspan="4">{{ $category }}</td>
                </tr>
                @foreach($items as $item)
                    <tr>
                        <td class="no-col">{{ $no++ }}</td>
                        <td class="pn-col">{{ $item->part_number }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="qty-col">{{ $item->qty }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    @if($quotation->notes)
        <div class="note">Note :<br>{{ $quotation->notes }}</div>
    @endif

    <div class="sign">
        <table>
            <tr>
                <td>
                    <div class="label">Created by :</div>
                </td>
                <td>
                    <div class="label">Final Checked by:</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="name">
                        ({{ $quotation->creator?->username ?? '________' }})
                    </div>
                </td>
                <td>
                    <div class="name">
                        ({{ $quotation->finalChecker?->username ?? '________' }})
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

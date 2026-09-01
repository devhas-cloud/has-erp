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
        /* Judul biaya (cost_title) — terpisah di luar tabel */
        .cost-title {
            background: #ddebf7;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 6px 8px;
            border: 1px solid #999;
            margin-bottom: 6px;
        }
        /* Baris header (depth 0 / parent) */
        .parent-row td {
            background: #f2f2f2;
            font-weight: 700;
            padding: 4px 6px;
            border: 1px solid #999;
            font-size: 10px;
        }
        .parent-row td.parent-desc-cell {
            padding: 0;
        }
        .parent-row .parent-info {
            width: 266pt;
            table-layout: fixed;
            border-collapse: collapse;
        }
        .parent-row .parent-info td {
            border: none;
            background: transparent;
            padding: 4px 0;
            vertical-align: top;
        }
        .parent-row .parent-info .parent-info-desc {
            width: 234pt;
        }
        .parent-row .parent-info .parent-info-val {
            width: 32pt;
            text-align: right;
            white-space: nowrap;
        }
        table.parts th.no-col { width: 26px; }
        table.parts th.qty-col { width: 40px; }
        table.parts th.unit-col { width: 50px; }
        table.parts th.price-col { width: 80px; }
        table.parts th.amount-col { width: 90px; }
        .no-col { text-align: center; }
        .qty-col { text-align: center; }
        .unit-col { text-align: center; }
        .price-col { text-align: right; }
        .amount-col { text-align: right; }
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

    {{-- <div class="doc-header">
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
    </table> --}}

    @php
        $costRows = $quotation->flattenCostTree();
        // Set item yang punya anak (parent) — dipakai utk deteksi leaf saat hitung total.
        $parentIds = $quotation->costItems->pluck('parent_id')->filter()->unique()->all();
        // Total = jumlah qty x price dari baris leaf saja (parent tidak dihitung 2x).
        $costTotal = $quotation->costItems
            ->filter(fn ($i) => ! in_array($i->id, $parentIds))
            ->reduce(fn ($c, $i) => $c + (($i->qty ?? 0) * ($i->price ?? 0)), 0);
    @endphp

    @if($quotation->cost_title)
        <div class="cost-title">{!! \App\Models\Quotation::renderDescription($quotation->cost_title) !!}</div>
    @endif

    <table class="parts">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th>Description</th>
                <th class="qty-col">Qty</th>
                <th class="unit-col">Unit</th>
                <th class="price-col">Unit Price</th>
                <th class="amount-col">Amount</th>
            </tr>
        </thead>
        <tbody>
        @forelse($costRows as $row)
            @php
                $item = $row['item'];
                $depth = $row['depth'];
            @endphp

            @if($depth === 0)
                {{-- Baris header (parent): No + deskripsi, ": qty unit" rata kanan di batas kolom Qty. --}}
                <tr class="parent-row">
                    <td class="no-col">{{ $item->item_no }}</td>
                    <td class="parent-desc-cell">
                        <table class="parent-info">
                            <tr>
                                <td class="parent-info-desc">{!! \App\Models\Quotation::renderDescription($item->description) !!}</td>
                                @if($item->qty)
                                    <td>: {{ $item->qty }} {{ $item->unit }}</td>
                                @endif
                            </tr>
                        </table>
                    </td>
                    <td class="qty-col"></td>
                    <td class="unit-col"></td>
                    <td class="price-col"></td>
                    <td class="amount-col"></td>
                </tr>
            @else
                {{-- Baris data (leaf/child): kolom lengkap. --}}
               <tr>
                    <td class="no-col"></td>
                    <td> {{ $item->item_no }} &emsp; {!! \App\Models\Quotation::renderDescription($item->description) !!}</td>
                    <td class="qty-col">{{ $item->qty ?: '' }}</td>
                    <td class="unit-col">{{ $item->qty ? $item->unit : '' }}</td>
                    <td class="price-col">{{ $item->price ? \App\Models\Quotation::formatMoney($item->price) : '' }}</td>
                    <td class="amount-col">{{ $item->qty && $item->price ? \App\Models\Quotation::formatMoney($item->qty * $item->price) : '' }}</td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="6" style="text-align:center;color:#999">Belum ada biaya.</td>
            </tr>
        @endforelse
            <tr class="cat-row">
                <td colspan="5" style="text-align:right">Total Price Biaya</td>
                <td style="text-align:right">{{ \App\Models\Quotation::formatMoney($costTotal) }}</td>
            </tr>
        </tbody>
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

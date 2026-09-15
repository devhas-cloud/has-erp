<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $purchaseOrder->po_number ?: $purchaseOrder->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10.5px;
            color: #111;
            line-height: 1.4;
        }

        /* Header diulang otomatis di setiap halaman oleh DomPDF. */
        .doc-header {
            position: fixed;
            top: -34mm;
            left: 0;
            right: 0;
            height: 32mm;
            overflow: hidden;
        }
        .doc-header .logo { position: absolute; top: 0; left: 0; width: 90px; height: auto; }
        .doc-header .banner {
            position: absolute;
            top: 0;
            right: 0;
            background: #111;
            color: #fff;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 1px;
            padding: 6px 14px;
        }
        .doc-header .company-block { margin-left: 100px; padding-top: 2px; }
        .doc-header .company { font-size: 13px; font-weight: 800; font-style: italic; }
        .doc-header .addr, .doc-header .contact { font-size: 8.5px; color: #333; }
        .doc-header .contact-right {
            position: absolute;
            top: 26px;
            right: 0;
            text-align: right;
            font-size: 8.5px;
            color: #333;
        }
        .doc-header .notice {
            margin-left: 100px;
            margin-top: 4px;
            font-size: 7.5px;
            color: #333;
            width: 260px;
        }

        .doc-footer {
            position: fixed;
            bottom: -11mm;
            left: 0;
            right: 0;
            height: 6mm;
            font-size: 8px;
            color: #666;
            border-top: 0.5px solid #bbb;
            padding-top: 1.5mm;
        }
        .doc-footer table { width: 100%; border-collapse: collapse; }
        .doc-footer td { font-size: 8px; color: #666; padding: 0; }
        .doc-footer td.pg { text-align: right; }
        .doc-footer td.pg:after { content: counter(page); }

        @page { margin: 40mm 12mm 16mm 12mm !important; }

        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.meta td { vertical-align: top; padding: 0; font-size: 10px; }
        table.meta .po-number-label { font-weight: 700; }
        table.meta .po-number-value { font-weight: 700; font-size: 11px; }
        table.meta .meta-right { text-align: right; width: 45%; }
        table.meta .meta-right .row { margin-bottom: 1px; }
        table.meta .meta-right .k { display: inline-block; width: 62px; font-weight: 600; text-align: left; }

        .to-box {
            border: 1px solid #111;
            padding: 6px 8px;
            width: 60%;
            font-size: 10px;
            margin-bottom: 10px;
        }
        .to-box .to-label { font-weight: 700; margin-bottom: 2px; }
        .to-box .to-name { font-weight: 700; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.items thead { display: table-header-group; }
        table.items tbody { display: table-row-group; }
        table.items th {
            background: #e8e8e8;
            border: 1px solid #999;
            padding: 4px 5px;
            font-size: 9.5px;
            text-align: left;
        }
        table.items td {
            border: 1px solid #999;
            padding: 3px 5px;
            font-size: 9.5px;
            vertical-align: top;
        }
        table.items tr { page-break-inside: avoid; }
        table.items .project-row td {
            background: #ffe066;
            font-weight: 700;
            text-align: center;
            padding: 3px 5px;
        }
        .qty-col { width: 32px; text-align: center; }
        .unit-col { width: 45px; text-align: center; }
        .price-col { width: 65px; text-align: right; }
        .amount-col { width: 70px; text-align: right; }

        .total-row { width: 100%; margin-bottom: 10px; }
        .total-row table { width: 220px; margin-left: auto; border-collapse: collapse; }
        .total-row td { border: 1px solid #999; padding: 4px 6px; font-size: 10.5px; font-weight: 700; }
        .total-row td.label { text-align: right; background: #f2f2f2; }
        .total-row td.value { text-align: right; width: 100px; }

        .freight-note { font-size: 9.5px; margin-bottom: 14px; }

        table.sign { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.sign td { vertical-align: top; font-size: 9.5px; padding: 0; width: 50%; }
        .sign-line { margin-top: 22px; border-top: 1px solid #111; width: 160px; padding-top: 2px; font-weight: 700; }
        .sign-block + .sign-block { margin-top: 14px; }
        .send-to-title { font-weight: 700; text-decoration: underline; margin-bottom: 3px; }
        .send-to .company { font-weight: 700; }
    </style>
</head>
<body>

    <div class="doc-header">
        <img class="logo" src="{{ public_path('img/has.jpg') }}" />
        <div class="banner">PURCHASE ORDER</div>
        <div class="company-block">
            <div class="company">PT. HAS ENVIRONMENTAL</div>
            <div class="addr">RUKO MEGA CEMPAKA MAS BLOK I - 12</div>
            <div class="addr">JL. LETJEND. SUPRAPTO CEMPAKA PUTIH, JAKARTA PUSAT 10640</div>
            <div class="contact">Email : info@has-environmental.com</div>
        </div>
        <div class="contact-right">
            <div>Phone : 62 - 21 - 42900007, 42900008</div>
            <div>Fax : 62 - 21 - 4264624</div>
        </div>
        <div class="notice">
            The following number must appear on all invoices, bills of lading and acknowledgements, relating to this PO :
        </div>
    </div>

    <div class="doc-footer">
        <table>
            <tr>
                <td class="ref">PO {{ $purchaseOrder->po_number ?: '#'.$purchaseOrder->id }}</td>
                <td class="pg">Hal. </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td>
                <span class="po-number-label">Purchase Order :</span>
                <span class="po-number-value">{{ $purchaseOrder->po_number ?: '#'.$purchaseOrder->id }}</span>
            </td>
            <td class="meta-right">
                <div class="row"><span class="k">PO DATE</span> : {{ $purchaseOrder->date?->format('M, d Y') ?? '—' }}</div>
                <div class="row"><span class="k">TERMS</span> : {{ $purchaseOrder->terms ?: '—' }}</div>
                <div class="row">
                    <span class="k">PROJECT</span> :
                    @foreach($purchaseOrder->projectLabels() as $label)
                        {{ $label }}@if(!$loop->last)<br><span style="margin-left:66px"></span>@endif
                    @endforeach
                </div>
            </td>
        </tr>
    </table>

    <div class="to-box">
        <div class="to-label">To :</div>
        <div class="to-name">{{ $purchaseOrder->supplier_name }}</div>
        @if($purchaseOrder->supplier_address)
            <div>{!! nl2br(e($purchaseOrder->supplier_address)) !!}</div>
        @endif
        @if($purchaseOrder->supplier_phone || $purchaseOrder->supplier_fax)
            <div>
                @if($purchaseOrder->supplier_phone) Phone : {{ $purchaseOrder->supplier_phone }} @endif
                @if($purchaseOrder->supplier_fax) , Fax : {{ $purchaseOrder->supplier_fax }} @endif
            </div>
        @endif
        @if($purchaseOrder->supplier_attn)
            <div>Attn : {{ $purchaseOrder->supplier_attn }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th class="qty-col">QTY</th>
                <th class="unit-col">UNIT</th>
                <th>D E S C R I P T I O N</th>
                <th class="price-col">UNIT PRICE</th>
                <th class="amount-col">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $printCurrencyMatches = $purchaseOrder->printCurrency() !== null;
            @endphp
            @forelse($purchaseOrder->projectGroups() as $group)
                <tr class="project-row">
                    <td colspan="5">{{ $group['label'] }}</td>
                </tr>
                @foreach($group['items'] as $item)
                    @php
                        $qty = $item->qty;
                        $unitPrice = $printCurrencyMatches ? $item->price_currency : $item->price;
                        $amount = $qty !== null && $unitPrice !== null ? $qty * $unitPrice : null;
                    @endphp
                    <tr>
                        <td class="qty-col">{{ $qty ?? '' }}</td>
                        <td class="unit-col">{{ $item->unit ?: '' }}</td>
                        <td>{{ $item->part_number ? $item->part_number.' ' : '' }}{!! $item->description ?: '' !!}</td>
                        <td class="price-col">{{ $unitPrice !== null ? $currencySymbol.' '.number_format($unitPrice, 2, ',', '.') : '' }}</td>
                        <td class="amount-col">{{ $amount !== null ? $currencySymbol.' '.number_format($amount, 2, ',', '.') : '' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="5" style="text-align:center;color:#999">Belum ada item.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($purchaseOrder->notes)
        <div class="freight-note">{!! nl2br(e($purchaseOrder->notes)) !!}</div>
    @endif

    <div class="total-row">
        <table>
            <tr>
                <td class="label">Total</td>
                <td class="value">{{ $currencySymbol }} {{ number_format($purchaseOrder->printTotal(), 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <table class="sign">
        <tr>
            <td>
                <div class="sign-block">
                    Request By :
                    <div class="sign-line">{{ $purchaseOrder->request_by_name ?: '' }}</div>
                </div>
                <div class="sign-block">
                    Finance Dept :
                    <div class="sign-line">{{ $purchaseOrder->finance_name ?: '' }}</div>
                </div>
                <div class="sign-block">
                    Accounting Dept :
                    <div class="sign-line">{{ $purchaseOrder->accounting_name ?: '' }}</div>
                </div>
            </td>
            <td class="send-to">
                <div class="send-to-title">Send To :</div>
                <div class="company">PT. HAS ENVIRONMENTAL</div>
                <div>RUKO MEGA CEMPAKA MAS BLOK I - 12</div>
                <div>JL. LETJEND. SUPRAPTO CEMPAKA PUTIH,</div>
                <div>JAKARTA PUSAT 10640</div>
                <div>PHONE : 62-21-42900007</div>
                <div>FAX : 62-21-4264624</div>
            </td>
        </tr>
    </table>

</body>
</html>

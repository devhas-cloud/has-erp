<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $purchaseOrder->po_number ?: $purchaseOrder->id }}</title>
    @php
        // Angka gaya Indonesia: 1.125.000 (desimal hanya bila ada pecahan).
        $money = function ($value) {
            $value = (float) $value;
            $decimals = abs($value - round($value)) > 0.004 ? 2 : 0;

            return number_format($value, $decimals, ',', '.');
        };
        $percent = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        $printCurrencyMatches = $purchaseOrder->printCurrency() !== null;
        $totals = $purchaseOrder->printTotals();
        $groups = $purchaseOrder->hierarchyGroups();
        // Baris judul project hanya dicetak bila PO menggabungkan lebih dari satu project.
        $showGroupRows = count($groups) > 1;
        $itemCount = collect($groups)->sum(fn ($group) => count($group['items']));

        // Grid kolom bersama agar tabel item dan tabel total sejajar. Lebar dipasang
        // langsung di sel karena DomPDF mengabaikan <colgroup> pada tabel ini.
        $w = ['qty' => 7, 'unit' => 10, 'desc' => 49, 'price' => 16, 'amount' => 18];
    @endphp
    <style>
        @page { margin: 12mm 14mm 14mm 14mm !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #111;
            line-height: 1.35;
        }
        table { border-collapse: collapse; }

        .doc-footer {
            position: fixed;
            bottom: -9mm;
            left: 0;
            right: 0;
            font-size: 7.5px;
            color: #777;
        }
        .doc-footer td { font-size: 7.5px; color: #777; }
        .doc-footer td.pg { text-align: right; }
        .doc-footer td.pg:after { content: counter(page); }

        /* Header */
        table.head { width: 100%; }
        table.head td { vertical-align: top; }
        .logo { width: 118px; height: auto; display: block; }
        .company {
            font-size: 15px;
            font-weight: 700;
            font-style: italic;
            margin-top: 2px;
            padding-bottom: 1px;
            border-bottom: 3px double #111;
            width: 340px;
        }
        .addr { font-size: 9.5px; margin-top: 3px; }
        .addr div { line-height: 1.4; }
        .email { font-size: 9.5px; margin-top: 4px; }
        .notice { font-size: 9.5px; margin-top: 8px; width: 300px; }
        .banner {
            background: #3a3a3a;
            color: #fff;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: .5px;
            text-align: center;
            padding: 4px 10px;
            margin-top: 56px;
        }
        table.contact { margin-top: 10px; }
        table.contact td { font-size: 9.5px; padding: 1px 0; }
        table.contact td.k { width: 70px; }
        table.contact td.sep { width: 16px; }

        /* Nomor PO, kepada & meta */
        table.meta { width: 100%; margin-top: 12px; }
        table.meta > tbody > tr > td { vertical-align: top; }
        .po-line { font-size: 10px; margin-bottom: 6px; }
        .po-line .k { display: inline-block; width: 180px; }
        .po-line .v { font-weight: 700; font-size: 11px; }
        .to-box { border: 1.5px solid #111; padding: 3px 5px; width: 100%; }
        .to-box .name { font-weight: 700; }
        .to-box .bold { font-weight: 700; }
        table.meta-right { width: 100%; }
        table.meta-right td { font-size: 10px; padding: 1.5px 0; vertical-align: top; }
        table.meta-right td.k { text-align: right; width: 78px; }
        table.meta-right td.sep { width: 14px; text-align: center; }

        /* Item */
        table.items, table.bottom { width: 100%; table-layout: fixed; }
        table.items { margin-top: 12px; border: 1.5px solid #111; }
        table.items th {
            border: 1.5px solid #111;
            background: #d9d9d9;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 4px;
            text-align: center;
        }
        table.items td {
            border-left: 1.5px solid #111;
            border-right: 1.5px solid #111;
            padding: 2px 4px;
            font-size: 10px;
            vertical-align: top;
        }
        table.items thead { display: table-header-group; }
        table.items tr { page-break-inside: avoid; }
        table.items td.c { text-align: center; }
        table.items td.desc { text-transform: uppercase; }
        table.items tr.group td { font-weight: 700; background: #f0f0f0; border-bottom: 1px solid #111; border-top: 1px solid #111; }
        table.items tr.filler td { height: 52px; }
        .amt .rp { float: left; }
        .amt .val { text-align: right; }
        .notes { font-size: 9px; margin-top: 4px; }

        /* Total, tanda tangan & alamat */
        /* Tabel total menempel ke tabel item: garis atasnya memakai garis bawah tabel item. */
        table.bottom { margin-top: -4.5px; }
        table.bottom td { vertical-align: top; padding: 0; }
        table.totals { width: 100%; table-layout: fixed; }
        table.totals td {
            border: 1.5px solid #111;
            border-top: 0;
            padding: 1px 4px;
            font-size: 10px;
            font-weight: 700;
        }
        table.totals td.k { text-align: right; }
        table.totals tr.grand td { border-bottom-width: 2px; }

        .sign-box { border: 1.5px solid #111; margin-top: 18px; }
        table.sign { width: 100%; }
        table.sign td { vertical-align: bottom; font-size: 10px; padding: 0 6px; }
        table.sign td.k { width: 120px; font-weight: 700; padding-bottom: 14px; }
        table.sign td.line { height: 52px; }
        table.sign .name { border-top: 1px solid #111; padding: 1px 0 4px; font-weight: 700; }

        .send-to { margin-top: 16px; padding-left: 12px; font-size: 9.5px; line-height: 1.45; }
        .send-to .company-name { font-weight: 700; }
    </style>
</head>
<body>

    <div class="doc-footer">
        <table style="width:100%">
            <tr>
                <td>PO {{ $purchaseOrder->po_number ?: '#'.$purchaseOrder->id }}</td>
                <td class="pg">Hal. </td>
            </tr>
        </table>
    </div>

    <table class="head">
        <tr>
            <td style="width:62%">
                <img class="logo" src="{{ public_path('img/has.jpg') }}" alt="HAS">
                <div class="company">PT. HAS ENVIRONMENTAL</div>
                <div class="addr">
                    <div>RUKO MEGA CEMPAKA MAS BLOK I - 12</div>
                    <div>JL. LETJEND. SUPRAPTO CEMPAKA PUTIH,</div>
                    <div>JAKARTA PUSAT 10640</div>
                </div>
                <div class="email">Email &nbsp;&nbsp;: &nbsp;info@has-environmental.com</div>
                <div class="notice">The following number must appear on all invoices, bills of lading and acknowledgements, relating to this PO :</div>
            </td>
            <td style="width:38%">
                <div class="banner">PURCHASE ORDER</div>
                <table class="contact">
                    <tr><td class="k">Phone</td><td class="sep">:</td><td>62 - 21 - 42900007, 42900008</td></tr>
                    <tr><td class="k">Fax</td><td class="sep">:</td><td>62 - 21 - 4264624</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td style="width:58%">
                <div class="po-line">
                    <span class="k">Purchase Order</span>: &nbsp;&nbsp;<span class="v">{{ $purchaseOrder->po_number ?: '#'.$purchaseOrder->id }}</span>
                </div>
                <div class="to-box">
                    <div>To :</div>
                    <div class="name">{{ $purchaseOrder->supplier_name }}</div>
                    @if($purchaseOrder->supplier_address)
                        <div>{!! nl2br(e($purchaseOrder->supplier_address)) !!}</div>
                    @endif
                    @if($purchaseOrder->supplier_phone)
                        <div>Phone: <span class="bold">{{ $purchaseOrder->supplier_phone }}</span></div>
                    @endif
                    @if($purchaseOrder->supplier_fax)
                        <div>Fax: <span class="bold">{{ $purchaseOrder->supplier_fax }}</span></div>
                    @endif
                    @if($purchaseOrder->supplier_attn)
                        <div class="bold">Attn: {{ $purchaseOrder->supplier_attn }}</div>
                    @endif
                </div>
            </td>
            <td style="width:42%;padding-left:16px">
                <table class="meta-right">
                    <tr><td class="k">PO DATE</td><td class="sep">:</td><td>{{ $purchaseOrder->date?->format('M, d Y') ?? '' }}</td></tr>
                    <tr><td class="k">TERMS</td><td class="sep">:</td><td>{{ $purchaseOrder->terms }}</td></tr>
                    <tr>
                        <td class="k">PROJECT</td><td class="sep">:</td>
                        <td>
                            @foreach($purchaseOrder->projectLabels() as $label)
                                <div>{{ $label }}</div>
                            @endforeach
                        </td>
                    </tr>
                    <tr><td class="k">DELIVERY</td><td class="sep">:</td><td>{{ $purchaseOrder->delivery }}</td></tr>
                    <tr><td class="k">YOUR REF</td><td class="sep">:</td><td>{{ $purchaseOrder->your_ref }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:{{ $w['qty'] }}%">QTY</th>
                <th style="width:{{ $w['unit'] }}%">UNIT</th>
                <th style="width:{{ $w['desc'] }}%;letter-spacing:3px">DESCRIPTION</th>
                <th style="width:{{ $w['price'] }}%">UNIT PRICE</th>
                <th style="width:{{ $w['amount'] }}%">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $group)
                @if($showGroupRows)
                    <tr class="group"><td colspan="5">{{ $group['label'] }}</td></tr>
                @endif
                @foreach($group['items'] as $item)
                    @php
                        $unitPrice = $printCurrencyMatches ? $item->selling_price_currency : $item->selling_price;
                        $amount = $item->qty !== null && $unitPrice !== null ? $item->qty * $unitPrice : null;
                        $description = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $item->description)), ENT_QUOTES | ENT_HTML5));
                        $partNumber = trim((string) $item->part_number);
                        if ($partNumber !== '' && stripos($description, $partNumber) === false) {
                            $description = trim($description.' / '.$partNumber, ' /');
                        }
                    @endphp
                    <tr>
                        <td class="c">{{ $item->qty }}</td>
                        <td class="c">{{ $item->unit }}</td>
                        <td class="desc">{!! nl2br(e($description)) !!}</td>
                        <td class="amt">@if($unitPrice !== null)<span class="rp">{{ $currencySymbol }}</span><div class="val">{{ $money($unitPrice) }}</div>@endif</td>
                        <td class="amt">@if($amount !== null)<span class="rp">{{ $currencySymbol }}</span><div class="val">{{ $money($amount) }}</div>@endif</td>
                    </tr>
                @endforeach
            @endforeach
            @if($itemCount === 0)
                <tr><td colspan="5" class="c" style="color:#999">Belum ada item.</td></tr>
            @endif
            <tr class="filler">
                @if($purchaseOrder->notes)
                    <td></td><td></td>
                    <td class="notes">{!! nl2br(e($purchaseOrder->notes)) !!}</td>
                    <td></td><td></td>
                @else
                    <td></td><td></td><td></td><td></td><td></td>
                @endif
            </tr>
        </tbody>
    </table>

    {{-- Kiri 52%: kotak tanda tangan. Kanan 48%: tabel total (lebar 34% halaman,
         rata kanan agar sejajar kolom UNIT PRICE & AMOUNT) lalu alamat perusahaan. --}}
    <table class="bottom">
        <tr>
            <td style="width:52%">
                <div class="sign-box">
                    <table class="sign">
                        @foreach(['Request By :' => $purchaseOrder->request_by_name, 'Finance Dept :' => $purchaseOrder->finance_name, 'Accounting Dept :' => $purchaseOrder->accounting_name] as $label => $name)
                            <tr>
                                <td class="k">{{ $label }}</td>
                                <td class="line"><div class="name">{{ $name ?: ' ' }}</div></td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </td>
            <td style="width:48%">
                <table style="width:100%;table-layout:fixed">
                    <tr>
                        <td style="width:29.17%"></td>
                        <td style="width:70.83%">
                            <table class="totals">
                                {{-- Sub Total hanya dicetak bila ada diskon atau PPN; tanpa keduanya cukup Total. --}}
                                @if($totals['discount'] > 0 || $totals['ppn'] > 0)
                                <tr>
                                    <td class="k" style="width:47.06%">Sub Total</td>
                                    <td class="amt" style="width:52.94%"><span class="rp">{{ $currencySymbol }}</span><div class="val">{{ $money($totals['subtotal']) }}</div></td>
                                </tr>
                                @endif
                                @if($totals['discount'] > 0)
                                    <tr>
                                        <td class="k">Diskon {{ $percent($purchaseOrder->discount_percent) }}%</td>
                                        <td class="amt"><span class="rp">{{ $currencySymbol }}</span><div class="val">{{ $money($totals['discount']) }}</div></td>
                                    </tr>
                                    <tr>
                                        <td class="k">Sub Total 1</td>
                                        <td class="amt"><span class="rp">{{ $currencySymbol }}</span><div class="val">{{ $money($totals['after_discount']) }}</div></td>
                                    </tr>
                                @endif
                                @if($totals['ppn'] > 0)
                                    <tr>
                                        <td class="k">PPN</td>
                                        <td class="amt"><span class="rp">{{ $currencySymbol }}</span><div class="val">{{ $money($totals['ppn']) }}</div></td>
                                    </tr>
                                @endif
                                <tr class="grand">
                                    <td class="k" style="width:47.06%">Total</td>
                                    <td class="amt" style="width:52.94%"><span class="rp">{{ $currencySymbol }}</span><div class="val">{{ $money($totals['total']) }}</div></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <div class="send-to">
                    <div class="company-name">PT HAS ENVIRONMENTAL</div>
                    <div>RUKO MEGA CEMPAKA MAS BLOK I - 12</div>
                    <div>JL. LETJEND. SUPRAPTO CEMPAKA PUTIH,</div>
                    <div>JAKARTA PUSAT 10640</div>
                    <div>PHONE : 62-21-42900007</div>
                    <div>FAX : 62-21-4264624</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>

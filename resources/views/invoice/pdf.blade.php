@php
    $project = $invoice->project;
    $company = $project?->company;
    $salesPerson = $project?->quotation?->opportunity?->owner?->display_name;

    $money = function ($value) {
        $value = round((float) $value, 2);
        $decimals = fmod($value, 1) == 0 ? 0 : 2;

        return number_format($value, $decimals, ',', '.');
    };

    $companyAddress = collect([
        $company?->address_billing_street,
        trim(collect([$company?->address_billing_city, $company?->address_billing_postal_code])->filter()->implode(' ')),
        $company?->address_billing_province,
        $company?->address_billing_country,
    ])->filter()->implode("\n");

    $doNumbers = collect(preg_split('/[\r\n,;]+/', (string) $invoice->do_number))->map(fn ($v) => trim($v))->filter();

    $percentLabel = \App\Models\Invoice::formatNumber($invoice->percent_payment).'%';
    $paymentLabel = ($invoice->noted ?: 'Pembayaran').' ('.$percentLabel.')';
    $ppnAmount = $invoice->total_payment - $invoice->amount_payment;

    $signerName = $invoice->signer_name ?: \App\Models\Invoice::DEFAULT_SIGNER_NAME;
    $signerPosition = $invoice->signer_position ?: \App\Models\Invoice::DEFAULT_SIGNER_POSITION;
@endphp
@php
    // Grid kolom bersama agar tabel info, item, dan ringkasan sejajar.
    $cols = '<colgroup><col style="width:9%"><col style="width:21%"><col style="width:19%"><col style="width:13%"><col style="width:19%"><col style="width:19%"></colgroup>';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 12mm 14mm 12mm 14mm !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #111;
            line-height: 1.35;
        }
        table { border-collapse: collapse; }

        .header { width: 100%; }
        .header td { vertical-align: top; }
        .header .logo { width: 80px; height: auto; }
        .header .company-name {
            font-family: 'DejaVu Serif', 'Times New Roman', serif;
            font-size: 17px;
            font-weight: 700;
            padding-bottom: 2px;
            border-bottom: 3px double #111;
        }
        .header .title {
            font-family: 'DejaVu Serif', 'Times New Roman', serif;
            font-size: 18px;
            font-weight: 700;
            text-align: right;
            letter-spacing: 1px;
        }

        .info { width: 100%; margin-top: 6px; }
        .info td { vertical-align: top; }
        .info .addr { padding-left: 88px; font-size: 9.5px; }
        .meta td { padding: 0 0 1px 0; font-size: 10px; }
        .meta .k { width: 80px; font-weight: 700; text-decoration: underline; }
        .meta .k-sub { font-weight: 700; }
        .meta .sep { width: 10px; }
        .meta .gap td { height: 9px; }

        .to { width: 100%; margin-top: 10px; }
        .to td { vertical-align: top; font-size: 10px; }
        .to .label { width: 32px; text-decoration: underline; }
        .to .name { font-size: 11px; font-weight: 700; }
        .to .attn { font-weight: 700; }

        table.grid, table.items, table.summary { width: 100%; table-layout: fixed; }
        table.grid { margin-top: 10px; border: 1px solid #111; }
        table.grid th, table.grid td { border: 1px solid #111; padding: 3px 5px; font-size: 10px; }
        table.grid th { text-align: center; font-weight: 700; vertical-align: top; }
        table.grid th .en { text-decoration: underline; display: block; }
        table.grid th .id { font-weight: 400; font-size: 9px; display: block; }
        table.grid td { text-align: center; vertical-align: top; }

        table.items { margin-top: 8px; border: 1px solid #111; }
        table.items th { border: 1px solid #111; padding: 3px 5px; font-size: 10px; text-align: center; vertical-align: top; }
        table.items th .en { text-decoration: underline; display: block; font-weight: 700; }
        table.items th .id { font-weight: 400; font-size: 9px; display: block; }
        table.items td {
            border-left: 1px solid #111;
            border-right: 1px solid #111;
            padding: 3px 5px;
            font-size: 10.5px;
            vertical-align: top;
        }
        table.items td.desc { text-transform: uppercase; }
        table.items tr.filler td { height: 30px; border-bottom: 1px solid #111; }

        table.summary { border-collapse: collapse; }
        table.summary td { font-size: 10.5px; padding: 2px 5px; vertical-align: top; }
        table.summary .bank .u { text-decoration: underline; }
        table.summary .label { text-align: right; }
        table.summary .amount { border-left: 1px solid #111; border-right: 1px solid #111; border-bottom: 1px solid #111; padding: 2px 5px; }
        table.summary tr.after td { border: 0; }
        table.summary .amount .rp { float: left; }
        table.summary .amount .val { text-align: right; }
        table.summary .bold { font-weight: 700; }
        table.summary .amount.total { border-bottom-width: 2px; }

        .sign { width: 100%; margin-top: 50px; }
        .sign td { vertical-align: bottom; }
        .sign .company { font-size: 11px; font-weight: 700; text-align: center; }
        .sign .space { height: 70px; }
        .sign .name { font-size: 11px; font-weight: 700; text-decoration: underline; text-align: center; }
        .sign .role { font-size: 10.5px; text-align: center; }
        .notes div { font-size: 9px; }
        .notes .u { text-decoration: underline; }

        .penalty {
            margin-top: 8px;
            border: 3px double #111;
            padding: 5px 8px;
            text-align: center;
            font-size: 9px;
            font-weight: 700;
            width: 62%;
        }
        .watermark {
            position: fixed;
            top: 45%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 72px;
            font-weight: 700;
            letter-spacing: 6px;
            color: #eef2f7;
            transform: rotate(-30deg);
        }
    </style>
</head>
<body>
@if($invoice->status === \App\Models\Invoice::STATUS_CANCELLED)
    <div class="watermark">CANCELLED</div>
@elseif(! $invoice->isApproved())
    <div class="watermark">{{ strtoupper($invoice->approval_status_label) }}</div>
@endif
    <table class="header">
        <tr>
            <td style="width:95px"><img class="logo" src="{{ public_path('img/has.jpg') }}" /></td>
            <td style="padding-top:4px"><div class="company-name">PT. HAS ENVIRONMENTAL</div></td>
            <td class="title" style="width:150px;padding-top:4px">INVOICE</td>
        </tr>
    </table>

    <table class="info">
        <tr>
            <td class="addr">
                <div>Ruko Mega Grosir Cempaka Mas Blok I/12</div>
                <div>Jakarta 10640, INDONESIA</div>
                <div>Telp : (62-21) 42900007, 42900008</div>
                <div>Fax : (62-21) 4264624</div>
            </td>
            <td style="width:235px">
                <table class="meta">
                    <tr><td class="k">Invoice No.</td><td class="sep">:</td><td>{{ $invoice->invoice_number }}</td></tr>
                    <tr><td class="k-sub">No. Invoice</td><td class="sep">:</td><td></td></tr>
                    <tr class="gap"><td colspan="3"></td></tr>
                    <tr><td class="k">Date</td><td class="sep">:</td><td>{{ $invoice->invoice_date?->format('j F Y') }}</td></tr>
                    <tr><td class="k-sub">Tanggal</td><td class="sep">:</td><td></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="to">
        <tr>
            <td class="label">To</td>
            <td style="width:14px">:</td>
            <td>
                <div class="name">{{ $company?->account_name ?: '—' }}</div>
                @if($companyAddress)
                    <div>{!! nl2br(e($companyAddress)) !!}</div>
                @endif
            </td>
            <td style="width:235px">
                <table class="meta">
                    <tr><td class="k">Ship To</td><td class="sep">:</td><td>{!! nl2br(e($invoice->ship_to)) !!}</td></tr>
                    <tr><td class="k-sub">Dikirim ke</td><td class="sep">:</td><td></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid">
        {!! $cols !!}
        <tr>
            <th><span class="en">SALES PERSON</span></th>
            <th><span class="en">PO. NUMBER</span><span class="id">NOMOR PO/SPK</span></th>
            <th><span class="en">DATE SHIPPED</span><span class="id">TGL KIRIM</span></th>
            <th><span class="en">SHIPPED VIA</span><span class="id">MELALUI</span></th>
            <th><span class="en">DO NUMBER</span><span class="id">NOMOR DO</span></th>
            <th><span class="en">TERMS</span><span class="id">TEMPO</span></th>
        </tr>
        <tr>
            <td>{{ $salesPerson ?: '-' }}</td>
            <td>{{ $project?->po_number_company ?: '-' }}</td>
            <td>{{ $invoice->date_shipped?->format('j-M-y') ?? '-' }}</td>
            <td>{{ $invoice->shipping_method ?: '-' }}</td>
            <td>
                @forelse($doNumbers as $do)
                    <div>{{ $do }}</div>
                @empty
                    -
                @endforelse
            </td>
            <td>{{ $invoice->terms ?: '-' }}</td>
        </tr>
    </table>

    <table class="items">
        {!! $cols !!}
        <thead>
            <tr>
                <th><span class="en">NO.</span><span class="id">NO.</span></th>
                <th colspan="2"><span class="en">DESCRIPTION</span><span class="id">KETERANGAN</span></th>
                <th><span class="en">QUANTITY</span><span class="id">BANYAKNYA</span></th>
                <th><span class="en">UNIT PRICE</span><span class="id">HARGA/UNIT</span></th>
                <th><span class="en">AMOUNT</span><span class="id">JUMLAH</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $i => $item)
            <tr>
                <td style="text-align:center">{{ $i + 1 }}</td>
                <td class="desc" colspan="2">
                    @if($item->description)
                        {!! \App\Models\Quotation::renderDescription($item->description) !!}
                    @else
                        <div style="font-weight:700">{{ $item->product_name }}</div>
                    @endif
                </td>
                <td style="text-align:center">{{ trim(\App\Models\Invoice::formatNumber($item->qty).' '.strtoupper((string) $item->unit)) }}</td>
                <td style="text-align:right">Rp {{ $money($item->unit_price) }}</td>
                <td style="text-align:right">Rp {{ $money($item->total_price) }}</td>
            </tr>
            @endforeach
            <tr class="filler"><td></td><td colspan="2"></td><td></td><td></td><td></td></tr>
        </tbody>
    </table>

    @php
        // Info bank di kiri dipasangkan baris demi baris dengan ringkasan di kanan.
        $bankLines = [
            '<span class="u">PEMBAYARAN MOHON DITRANSFER KE :</span>',
            'Bank Mandiri Cabang Cempaka Mas Jakarta',
            '<span class="u">NO. REKENING :</span>',
            '123-0004666881 (IDR)',
            '<span class="u">ATAS NAMA :</span>',
            'Has Environmental',
        ];
        $summaryRows = [['Sub Total', $invoice->subtotal, false]];
        if ((float) $invoice->discount_amount > 0) {
            $summaryRows[] = ['Discount'.($invoice->discount_percent ? ' ('.\App\Models\Invoice::formatNumber($invoice->discount_percent).'%)' : ''), -$invoice->discount_amount, false];
            $summaryRows[] = ['Total Setelah Discount', $invoice->net_subtotal, false];
        }
        $summaryRows[] = [$paymentLabel, $invoice->amount_payment, false];
        $summaryRows[] = ['PPN', $ppnAmount, false];
        $summaryRows[] = ['Total '.$paymentLabel, $invoice->total_payment, true];
        $summaryCount = max(count($bankLines), count($summaryRows));
    @endphp
    <table class="summary">
        {!! $cols !!}
        @for ($i = 0; $i < $summaryCount; $i++)
        @php $row = $summaryRows[$i] ?? null; @endphp
        <tr>
            <td class="bank" colspan="3">{!! $bankLines[$i] ?? '' !!}</td>
            @if ($row)
            <td class="label{{ $row[2] ? ' bold' : '' }}" colspan="2">{{ $row[0] }}</td>
            <td class="amount{{ $row[2] ? ' total bold' : '' }}"><span class="rp">Rp</span><div class="val">{{ $row[1] < 0 ? '('.$money(-$row[1]).')' : $money($row[1]) }}</div></td>
            @else
            <td class="blank" colspan="3"></td>
            @endif
        </tr>
        @endfor
    </table>

    <table class="sign">
        <tr>
            <td class="notes" style="width:62%">
                <div class="u">* Goods has been Purchased can not be Returned, Refund or Exchange.</div>
                <div>* Barang yang sudah dibeli tidak dapat dikembalikan, direfund atau ditukar</div>
                <div class="u" style="margin-top:3px">* All bank charges are paid by the buyer</div>
                <div>* Semua biaya bank ditanggung oleh Pembeli</div>
            </td>
            <td>
                <div class="company">PT. HAS ENVIRONMENTAL</div>
                <div class="space"></div>
                <div class="name">{{ $signerName }}</div>
                <div class="role">{{ $signerPosition }}</div>
            </td>
        </tr>
    </table>

    <div class="penalty">
        KETERLAMBATAN PEMBAYARAN H+7 TANPA ADA KONFIRMASI AKAN<br>
        DIKENAKAN DENDA 1.5% / BULAN DARI NILAI INVOICE
    </div>
</body>
</html>

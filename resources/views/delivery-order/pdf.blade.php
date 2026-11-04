<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan {{ $order->do_number }}</title>
    <style>
        @page { margin: 12mm 14mm 14mm 14mm !important; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; line-height: 1.35; }
        table { border-collapse: collapse; }

        .doc-footer { position: fixed; bottom: -9mm; left: 0; right: 0; }
        .doc-footer td { font-size: 7.5px; color: #777; }
        .doc-footer td.pg { text-align: right; }
        .doc-footer td.pg:after { content: counter(page); }

        table.head { width: 100%; }
        table.head td { vertical-align: top; }
        .logo { width: 118px; height: auto; display: block; }
        .company { font-size: 15px; font-weight: 700; font-style: italic; margin-top: 2px; padding-bottom: 1px; border-bottom: 3px double #111; width: 340px; }
        .addr { font-size: 9.5px; margin-top: 3px; }
        .banner { background: #3a3a3a; color: #fff; font-size: 17px; font-weight: 700; letter-spacing: .5px; text-align: center; padding: 4px 10px; margin-top: 30px; }
        table.meta-right { width: 100%; margin-top: 8px; }
        table.meta-right td { font-size: 10px; padding: 1.5px 0; vertical-align: top; }
        table.meta-right td.k { width: 78px; }
        table.meta-right td.sep { width: 12px; }

        .to-box { border: 1.5px solid #111; padding: 5px 7px; margin-top: 14px; width: 62%; }
        .to-box .label { font-size: 9px; color: #444; }
        .to-box .name { font-weight: 700; font-size: 11px; }

        table.items { width: 100%; margin-top: 12px; border: 1.5px solid #111; }
        table.items th { border: 1.5px solid #111; background: #d9d9d9; font-size: 10px; font-weight: 700; padding: 3px 4px; text-align: center; }
        table.items td { border: 1px solid #111; padding: 3px 4px; font-size: 10px; vertical-align: top; }
        table.items thead { display: table-header-group; }
        table.items tr { page-break-inside: avoid; }
        table.items td.c { text-align: center; }

        .notes { font-size: 9px; margin-top: 6px; }
        table.sign { width: 100%; margin-top: 26px; page-break-inside: avoid; }
        table.sign td { width: 33.33%; text-align: center; font-size: 10px; vertical-align: top; }
        table.sign .space { height: 60px; }
        table.sign .name { border-top: 1px solid #111; margin: 0 18px; padding-top: 2px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="doc-footer">
        <table style="width:100%"><tr><td>Surat Jalan {{ $order->do_number }}</td><td class="pg">Hal. </td></tr></table>
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
            </td>
            <td style="width:38%">
                <div class="banner">SURAT JALAN</div>
                <table class="meta-right">
                    <tr><td class="k">No. DO</td><td class="sep">:</td><td><strong>{{ $order->do_number }}</strong></td></tr>
                    <tr><td class="k">Tanggal</td><td class="sep">:</td><td>{{ $order->delivery_date?->format('d/m/Y') }}</td></tr>
                    <tr><td class="k">Project</td><td class="sep">:</td><td>{{ $order->project?->project_number }} — {{ $order->project?->project_name }}</td></tr>
                    @if($order->project?->po_number_company)
                    <tr><td class="k">PO Customer</td><td class="sep">:</td><td>{{ $order->project->po_number_company }}</td></tr>
                    @endif
                    @if($order->courier)
                    <tr><td class="k">Ekspedisi</td><td class="sep">:</td><td>{{ $order->courier }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="to-box">
        <div class="label">Kepada Yth.</div>
        <div class="name">{{ $order->recipient_name }}</div>
        @if($order->project?->company && $order->project->company->account_name !== $order->recipient_name)
            <div>{{ $order->project->company->account_name }}</div>
        @endif
        <div style="white-space:pre-line">{{ $order->shipping_address }}</div>
        @if($order->recipient_phone)<div>Telp: {{ $order->recipient_phone }}</div>@endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:6%">NO</th>
                <th style="width:18%">KODE</th>
                <th style="width:38%">NAMA BARANG</th>
                <th style="width:8%">QTY</th>
                <th style="width:10%">SATUAN</th>
                <th style="width:20%">KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $item->masterProduct?->code }}</td>
                <td>{{ $item->masterProduct?->name }}</td>
                <td class="c">{{ $item->qty }}</td>
                <td class="c">{{ $item->unit }}</td>
                <td>{{ $item->remark }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($order->note)
        <div class="notes"><strong>Catatan:</strong> {{ $order->note }}</div>
    @endif
    <div class="notes">Barang telah diterima dalam keadaan baik dan lengkap.</div>

    <table class="sign">
        <tr>
            <td>Disiapkan oleh,</td>
            <td>Pengirim,</td>
            <td>Diterima oleh,</td>
        </tr>
        <tr><td class="space"></td><td class="space"></td><td class="space"></td></tr>
        <tr>
            <td><div class="name">{{ $order->creator?->display_name ?? 'Gudang' }}</div></td>
            <td><div class="name">&nbsp;</div></td>
            <td><div class="name">&nbsp;</div></td>
        </tr>
    </table>
</body>
</html>

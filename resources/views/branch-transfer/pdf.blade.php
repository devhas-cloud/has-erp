<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan {{ $transfer->transfer_number }}</title>
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
        .banner { background: #3a3a3a; color: #fff; font-size: 15px; font-weight: 700; letter-spacing: .5px; text-align: center; padding: 4px 10px; margin-top: 30px; }
        table.meta-right { width: 100%; margin-top: 8px; }
        table.meta-right td { font-size: 10px; padding: 1.5px 0; vertical-align: top; }
        table.meta-right td.k { width: 82px; }
        table.meta-right td.sep { width: 12px; }

        table.route { width: 100%; margin-top: 14px; }
        table.route td { width: 50%; vertical-align: top; padding: 0 4px 0 0; }
        .box { border: 1.5px solid #111; padding: 5px 7px; }
        .box .label { font-size: 9px; color: #444; }
        .box .name { font-weight: 700; font-size: 11px; }

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
        <table style="width:100%"><tr><td>Surat Jalan Antar Branch {{ $transfer->transfer_number }}</td><td class="pg">Hal. </td></tr></table>
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
                <div class="banner">SURAT JALAN ANTAR BRANCH</div>
                <table class="meta-right">
                    <tr><td class="k">No. Dokumen</td><td class="sep">:</td><td><strong>{{ $transfer->transfer_number }}</strong></td></tr>
                    <tr><td class="k">Tanggal</td><td class="sep">:</td><td>{{ $transfer->transfer_date?->format('d/m/Y') }}</td></tr>
                    @if($transfer->courier)
                        <tr><td class="k">Ekspedisi</td><td class="sep">:</td><td>{{ $transfer->courier }}</td></tr>
                    @endif
                    @if($transfer->vehicle_no)
                        <tr><td class="k">Kendaraan</td><td class="sep">:</td><td>{{ $transfer->vehicle_no }}</td></tr>
                    @endif
                    @if($transfer->driver_name)
                        <tr><td class="k">Pembawa</td><td class="sep">:</td><td>{{ $transfer->driver_name }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="route">
        <tr>
            <td>
                <div class="box">
                    <div class="label">Dari</div>
                    <div class="name">{{ $transfer->fromBranch?->name }}</div>
                    <div>Gudang {{ $transfer->fromWarehouse?->code }} — {{ $transfer->fromWarehouse?->name }}</div>
                </div>
            </td>
            <td>
                <div class="box">
                    <div class="label">Kepada</div>
                    <div class="name">{{ $transfer->toBranch?->name }}</div>
                    <div>&nbsp;</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:6%">NO</th>
                <th style="width:18%">KODE</th>
                <th style="width:42%">NAMA BARANG</th>
                <th style="width:10%">QTY</th>
                <th style="width:10%">SATUAN</th>
                <th style="width:14%">DITERIMA</th>
            </tr>
        </thead>
        <tbody>
            @php
                // Satu baris per produk; SN / batch dicantumkan di bawah nama barang.
                $lines = $transfer->items->groupBy(fn ($item) => $item->stock?->master_product_id ?? 'x'.$item->id)->values();
            @endphp
            @foreach($lines as $i => $group)
                @php
                    $product = $group->first()->stock?->masterProduct;
                    $serials = $group->map(fn ($item) => $item->stock?->serial_number)->filter();
                    $batches = $group->map(fn ($item) => $item->stock?->batch_number)->filter()->unique();
                @endphp
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $product?->code }}</td>
                    <td>{{ $product?->name }}
                        @if($serials->isNotEmpty())<div style="font-size:9px">SN: {{ $serials->implode(', ') }}</div>@endif
                        @if($batches->isNotEmpty())<div style="font-size:9px">Batch / MFG: {{ $batches->implode(', ') }}</div>@endif
                    </td>
                    <td class="c">{{ \App\Support\StockCost::qty($group->sum('qty')) }}</td>
                    <td class="c">{{ $group->first()->unit }}</td>
                    <td>&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($transfer->note)
        <div class="notes"><strong>Catatan:</strong> {{ $transfer->note }}</div>
    @endif
    <div class="notes">Penerima memeriksa jumlah dan kondisi setiap barang. Barang yang tidak sesuai dicatat di kolom Diterima dan dilaporkan sebelum penerimaan diselesaikan di sistem.</div>

    <table class="sign">
        <tr>
            <td>Pengirim,</td>
            <td>Pembawa,</td>
            <td>Penerima,</td>
        </tr>
        <tr><td class="space"></td><td class="space"></td><td class="space"></td></tr>
        <tr>
            <td><div class="name">{{ $transfer->shippedBy?->display_name ?? $transfer->creator?->display_name ?? ' ' }}</div></td>
            <td><div class="name">{{ $transfer->driver_name ?: ' ' }}</div></td>
            <td><div class="name">&nbsp;</div></td>
        </tr>
    </table>
</body>
</html>

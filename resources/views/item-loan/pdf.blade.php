<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Peminjaman {{ $loan->loan_number }}</title>
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
        .banner { background: #3a3a3a; color: #fff; font-size: 14px; font-weight: 700; letter-spacing: .5px; text-align: center; padding: 4px 10px; margin-top: 30px; }
        table.meta-right { width: 100%; margin-top: 8px; }
        table.meta-right td { font-size: 10px; padding: 1.5px 0; vertical-align: top; }
        table.meta-right td.k { width: 86px; }
        table.meta-right td.sep { width: 12px; }

        table.parties { width: 100%; margin-top: 14px; }
        table.parties td { width: 50%; vertical-align: top; padding: 0 4px 0 0; }
        .box { border: 1.5px solid #111; padding: 5px 7px; min-height: 58px; }
        .box .label { font-size: 9px; color: #444; }
        .box .name { font-weight: 700; font-size: 11px; }

        table.items { width: 100%; margin-top: 12px; border: 1.5px solid #111; }
        table.items th { border: 1.5px solid #111; background: #d9d9d9; font-size: 9.5px; font-weight: 700; padding: 3px 4px; text-align: center; }
        table.items td { border: 1px solid #111; padding: 3px 4px; font-size: 9.5px; vertical-align: top; }
        table.items thead { display: table-header-group; }
        table.items tr { page-break-inside: avoid; }
        table.items td.c { text-align: center; }
        .sn { font-family: 'DejaVu Sans Mono', monospace; }

        .notes { font-size: 9px; margin-top: 6px; }
        .terms { margin-top: 10px; font-size: 9px; border: 1px solid #999; padding: 5px 7px; }
        .terms ol { margin-left: 14px; }
        .section { margin-top: 18px; font-size: 10px; font-weight: 700; }
        table.sign { width: 100%; margin-top: 8px; page-break-inside: avoid; }
        table.sign td { width: 33.33%; text-align: center; font-size: 10px; vertical-align: top; }
        table.sign .space { height: 56px; }
        table.sign .name { border-top: 1px solid #111; margin: 0 18px; padding-top: 2px; font-weight: 700; }
        table.sign .role { font-size: 8.5px; color: #555; }
    </style>
</head>
<body>
    @php
        $returnLabels = ['good' => 'Baik', 'damaged' => 'Rusak', 'lost' => 'Hilang'];
        $borrowerOrg = $loan->isExternal() ? $loan->borrower_company : ($loan->borrowerUser?->division?->division_name ? 'Divisi '.$loan->borrowerUser->division->division_name : null);
    @endphp
    <div class="doc-footer">
        <table style="width:100%"><tr><td>Bukti Peminjaman Barang {{ $loan->loan_number }} · dicetak {{ now()->format('d/m/Y H:i') }}</td><td class="pg">Hal. </td></tr></table>
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
                <div class="banner">BUKTI PEMINJAMAN BARANG</div>
                <table class="meta-right">
                    <tr><td class="k">No. Dokumen</td><td class="sep">:</td><td><strong>{{ $loan->loan_number }}</strong></td></tr>
                    <tr><td class="k">Tanggal Pinjam</td><td class="sep">:</td><td>{{ $loan->loan_date->format('d/m/Y') }}</td></tr>
                    <tr><td class="k">Wajib Kembali</td><td class="sep">:</td><td><strong>{{ $loan->due_date->format('d/m/Y') }}</strong>{{ $loan->extensions->isNotEmpty() ? ' (diperpanjang)' : '' }}</td></tr>
                    <tr><td class="k">Gudang</td><td class="sep">:</td><td>{{ $loan->warehouse?->code }} — {{ $loan->warehouse?->name }}</td></tr>
                    @if($loan->status === \App\Models\ItemLoan::STATUS_CANCELLED)
                        <tr><td class="k">Status</td><td class="sep">:</td><td><strong>DIBATALKAN</strong></td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="box">
                    <div class="label">Peminjam {{ $loan->isExternal() ? '(pihak luar)' : '(internal)' }}</div>
                    <div class="name">{{ $loan->borrowerLabel() }}</div>
                    @if($borrowerOrg)<div>{{ $borrowerOrg }}</div>@endif
                    @if($loan->borrowerPhone())<div>Telp. {{ $loan->borrowerPhone() }}</div>@endif
                </div>
            </td>
            <td>
                <div class="box">
                    <div class="label">Keperluan</div>
                    <div class="name" style="font-weight:400;font-size:10px">{{ $loan->purpose }}</div>
                    @if($loan->project)<div>Project {{ $loan->project->project_number }} — {{ $loan->project->project_name }}</div>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">NO</th>
                <th style="width:15%">KODE</th>
                <th style="width:30%">NAMA BARANG</th>
                <th style="width:16%">SERIAL NUMBER</th>
                <th style="width:17%">KONDISI SAAT DIPINJAM</th>
                <th style="width:17%">KONDISI SAAT KEMBALI</th>
            </tr>
        </thead>
        <tbody>
            @foreach($loan->items as $i => $item)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $item->masterProduct?->code }}</td>
                    <td>{{ $item->masterProduct?->display_name ?? '—' }}</td>
                    <td class="sn">{{ $item->stock?->serial_number ?? '—' }}</td>
                    <td>{{ $item->condition_out ?: 'Baik' }}</td>
                    <td>
                        @if($item->return_status)
                            {{ $returnLabels[$item->return_status] ?? $item->return_status }} · {{ $item->returned_at?->format('d/m/Y') }}
                            @if($item->return_note)<div style="font-size:8.5px">{{ $item->return_note }}</div>@endif
                        @else
                            &nbsp;
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="notes">Jumlah: {{ $loan->items->count() }} unit.@if($loan->note) <strong>Catatan:</strong> {{ $loan->note }}@endif</div>

    <div class="terms">
        <strong>Ketentuan peminjaman</strong>
        <ol>
            <li>Barang tetap milik PT. HAS Environmental dan tercatat di inventory selama dipinjam.</li>
            <li>Peminjam bertanggung jawab menjaga barang dan mengembalikannya lengkap paling lambat tanggal wajib kembali.</li>
            <li>Kerusakan atau kehilangan wajib segera dilaporkan ke tim inventory dan dicatat atas nama peminjam.</li>
            <li>Perpanjangan waktu pinjam hanya berlaku bila dicatat oleh tim inventory.</li>
        </ol>
    </div>

    <div class="section">Penyerahan barang — {{ $loan->loan_date->format('d/m/Y') }}</div>
    <table class="sign">
        <tr>
            <td>Diserahkan oleh,</td>
            <td>Diterima oleh (Peminjam),</td>
            <td>Mengetahui,</td>
        </tr>
        <tr><td class="space"></td><td class="space"></td><td class="space"></td></tr>
        <tr>
            <td><div class="name">{{ $loan->creator?->display_name ?? ' ' }}</div><div class="role">Inventory</div></td>
            <td><div class="name">{{ $loan->borrowerLabel() }}</div><div class="role">{{ $borrowerOrg ?: ' ' }}</div></td>
            <td><div class="name">&nbsp;</div><div class="role">Atasan / PIC</div></td>
        </tr>
    </table>

    <div class="section">Pengembalian barang — tanggal: ......................</div>
    <table class="sign">
        <tr>
            <td>Dikembalikan oleh,</td>
            <td>Diterima kembali oleh,</td>
            <td></td>
        </tr>
        <tr><td class="space"></td><td class="space"></td><td class="space"></td></tr>
        <tr>
            <td><div class="name">{{ $loan->borrowerLabel() }}</div><div class="role">Peminjam</div></td>
            <td><div class="name">&nbsp;</div><div class="role">Inventory</div></td>
            <td></td>
        </tr>
    </table>
</body>
</html>

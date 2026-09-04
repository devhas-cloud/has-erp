<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Quotation {{ $quotation->quotation_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #111;
            line-height: 1.45;
        }

        /* Header diulang otomatis di setiap halaman oleh DomPDF */
        .doc-header {
            position: fixed;
            top: -28mm;      /* mulai ~8mm dari tepi atas kertas */
            left: 0;
            right: 0;
            height: 25mm;    /* tinggi tetap agar jarak ke konten selalu sama */
            overflow: hidden;
            text-align: center;
            padding-bottom: 2mm;
            border-bottom: 2px solid #111;
            line-height: 1.25;
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
        }

        /* Footer diulang di setiap halaman, berada di dalam margin bawah */
        .doc-footer {
            position: fixed;
            bottom: -11mm;
            left: 0;
            right: 0;
            height: 6mm;
            font-size: 8.5px;
            color: #666;
            border-top: 0.5px solid #bbb;
            padding-top: 1.5mm;
        }
        /*
         * Pakai tabel, JANGAN float: float di dalam elemen fixed ini ikut
         * terbaca oleh alur dokumen di DomPDF sehingga paragraf biasa
         * (salutation, terms) tergeser ke kanan sebesar lebar float.
         */
        .doc-footer table { width: 100%; border-collapse: collapse; }
        .doc-footer td { font-size: 8.5px; color: #666; padding: 0; }
        .doc-footer td.pg { text-align: right; }
        .doc-footer td.pg:after { content: counter(page); }

        .doc-title {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 12px;
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
        /* Baris judul tabel ikut tercetak ulang di tiap halaman */
        table.parts thead { display: table-header-group; }
        table.parts tbody { display: table-row-group; }
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

        .totals {
            width: 260px;
            margin-left: auto;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals td {
            padding: 3px 6px;
            font-size: 10.5px;
            border: 1px solid #999;
        }
        .totals td.l { font-weight: 600; }
        .totals td.v { text-align: right; }
        .totals tr.grand td {
            background: #e8e8e8;
            font-weight: 800;
            font-size: 11.5px;
        }

        .terms {
            margin: 12px 0 8px;
            font-size: 9.5px;
        }
        .terms .terms-title {
            font-weight: 700;
            text-decoration: underline;
            margin-bottom: 4px;
            page-break-after: avoid;
        }
        .terms .closing {
            margin-top: 8px;
            font-weight: 700;
        }

        .sign {
            width: 100%;
            margin-top: 36px;
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

        /*
         * Layout halaman:
         * - @page menyisakan ruang kosong 38mm di atas & 16mm di bawah pada SETIAP halaman.
         * - Header/footer position:fixed diletakkan DI DALAM ruang kosong itu memakai
         *   offset negatif, karena DomPDF menghitung top/bottom elemen fixed relatif
         *   terhadap area konten (sesudah margin @page), bukan terhadap tepi kertas.
         *   Tanpa offset negatif, header menumpuk dengan isi dokumen.
         * - !important WAJIB dipertahankan: di DomPDF style @page dipakai sebagai style
         *   dasar elemen root, sehingga "* { margin: 0 }" di atas akan menimpanya dan
         *   membuat margin halaman hilang (header/footer ikut terdorong keluar kertas).
         */
        @page { margin: 38mm 14mm 16mm 14mm !important; }
    </style>
</head>
<body>

    <img src="{{ public_path('img/has.jpg') }}" style="position:fixed;top:-80;left:0;width:120px;height:auto;" />
    <div class="doc-header">
        <div class="company">PT. HAS ENVIRONMENTAL</div>
        <div class="addr">Ruko Mega Grosir Cempaka Mas Blok I/12</div>
        <div class="addr">Jl. Letjen Suprapto Cempaka Putih, Jakarta Pusat 10640</div>
        <div class="contact">Phone : 62 - 21- 42900007, 42900008 , Fax : 021 - 4264624</div>
        <div class="contact">email : info@has-environmental.com</div>
        <div class="npwp">NPWP : 02.593.153.6 027.000</div>
    </div>
    <img src="{{ public_path('img/smk3.png') }}" style="position:fixed;top:-80;right:0;width:70px;height:auto;" />

    <div class="doc-footer">
        <table>
            <tr>
                <td class="ref">{{ $quotation->quotation_number ?? '' }}</td>
                <td class="pg">Hal. </td>
            </tr>
        </table>
    </div>

    {{-- $pageCount = jumlah halaman hasil cetak sebenarnya, diisi oleh
         QuotationController::renderPdfWithPageCount(). Nilai no_of_pages dari
         database hanya dipakai kalau view dirender di luar controller itu. --}}
    @php $totalPages = (int) ($pageCount ?? $quotation->no_of_pages ?? 1); @endphp

    <table class="info">
        <tr>
            <td class="k">To</td>
            <td>{{ $quotation->to_name ?? '—' }}</td>
            <td class="kr">From</td>
            <td class="right">{{ $quotation->from_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Address</td>
            <td>{!! nl2br(e($quotation->address ?? '—')) !!}</td>
            <td class="kr">Date</td>
            <td class="right">{{ $quotation->date?->format('d F Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Attn.</td>
            <td>{{ $quotation->attn_name ?? '—' }}</td>
            <td class="kr">Our Ref</td>
            <td class="right">{{ $quotation->quotation_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Telp</td>
            <td>{{ $quotation->attn_phone ?? '—' }}</td>
            <td class="kr">Currency</td>
            <td class="right">{{ $quotation->currency ?? 'Rupiah' }}</td>
        </tr>
        <tr>
            <td class="k">Email</td>
            <td>{{ $quotation->attn_email ?? '—' }}</td>
            <td class="kr">Your Ref</td>
            <td class="right">{{ $quotation->your_ref ?? '' }}</td>
        </tr>
        <tr>
            <td class="k"></td>
            <td></td>
            <td class="kr">No of Pages</td>
            <td class="right">{{ $totalPages }} {{ $totalPages > 1 ? 'Pages' : 'Page' }}</td>
        </tr>
    </table>

    <div class="salutation">
        Dear Customer,<br>
        Thank you for your inquiry &amp; we are pleased to quote as follow :
        <br>
        <br>
        <h4>Sistem Pemantauan Kualitas Air Secara Terus Menerus dan Dalam Jaringa (SPARING) <br> Sesuai PERMENLHK Nomor P.80/MENLHK/SETJEN/KUM.1/10/2019</h4>
        <h4>Parameter : {{ $quotation->parameter_note }} </h4>
    </div>


    @php $rows = $quotation->flattenTree(); @endphp

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
            @forelse($rows as $row)
                @php
                    $item = $row['item'];
                    $depth = $row['depth'];
                @endphp
                <tr>
                    <td>
                        <!-- jika item_no parent -->
                        @if(!$depth)
                        <span style="font-weight:bold;">{{ $item->item_no }}</span>
                        @else
                        <span style="font-weight:bold; padding-left:{{ $depth * 14 }}px">{{ $item->item_no }}</span>
                        @endif
                    </td>
                    <td>
                        <div>
                            {!! \App\Models\Quotation::renderDescription($item->description) !!}
                            @if($item->part_number)
                                <div style="font-size:9px;color:#444">Part Number : {{ $item->part_number }}</div>
                            @endif
                        </div>
                    </td>
                    <td class="qty-col">{{ $item->qty ?: '' }} {{ $item->qty ? $item->unit : '' }}</td>
                    <td class="price-col">{{ $item->price ? \App\Models\Quotation::formatMoney($item->price) : '' }}</td>
                    <td class="amount-col">{{ $item->qty && $item->price ? \App\Models\Quotation::formatMoney($item->qty * $item->price) : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;color:#999">Tidak ada item.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"></td>
                <td class="l">Subtotal</td>
                <td class="price-col">{{ \App\Models\Quotation::formatMoney($quotation->subtotal) }}</td>
            </tr>
            @if($quotation->discount_amount > 0)
            <tr>
                <td colspan="3"></td>
                <td class="l">Discount</td>
                <td class="v">({{ \App\Models\Quotation::formatMoney($quotation->discount_amount) }})</td>
            </tr>
            @endif
            <tr>
                <td colspan="3"></td>
                <td class="l">DPP Pajak</td>
                <td class="price-col">{{ \App\Models\Quotation::formatMoney($quotation->dpp) }}</td>
            </tr>
            <tr>
                <td colspan="3"></td>
                <td class="l">PPN </td>
                <td class="price-col">{{ \App\Models\Quotation::formatMoney($quotation->ppn) }}</td>
            </tr>
            <tr class="grand">
                <td colspan="3"></td>
                <td class="l">Full Amount</td>
                <td class="price-col">{{ \App\Models\Quotation::formatMoney($quotation->grand_total) }}</td>
            </tr>
        </tfoot>
    </table>


    @if($quotation->terms)
        <div class="terms">
            <div class="terms-title">Term &amp; Conditions :</div>
             <div style="font-family:monospace;font-size:10px; white-space: pre-wrap;">{!! e($quotation->terms) !!}</div>
            <div class="closing">Goods has been Purchased can not be Returned, Refunded or Exchanged <br>
            Signature</div>
        </div>
    @endif

    <div class="sign">
        <table>
            <tr>
                <td>
                    <div class="label"></div>
                </td>
                <td>
                    <div class="label">Contact Person</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="name">
                        Computer generated<br>
                        {{ ucfirst($quotation->creator?->username ?? '________') }}
                    </div>
                </td>
                <td>
                    <div class="name">
                        {{ ucfirst($quotation->from_name ?? '________') }}<br>
                        <span class="phone">{{ $quotation->contact_phone ?? '' }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>

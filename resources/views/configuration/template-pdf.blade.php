<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Template {{ $template->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #111;
            padding: 28px 36px;
            line-height: 1.45;
        }
        .header { text-align: center; margin-bottom: 6px; }
        .header .company { font-size: 17px; font-weight: 800; letter-spacing: 1px; }
        .header .addr { font-size: 10.5px; color: #333; }
        .header .contact { font-size: 10.5px; color: #333; }
        .header .npwp { font-size: 10.5px; color: #333; margin-top: 2px; }
        .doc-title {
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            margin: 14px 0 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.info td { padding: 1.5px 4px; font-size: 11.5px; vertical-align: top; }
        table.info td.k { width: 90px; font-weight: 600; }
        table.parts { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.parts th { background: #e8e8e8; border: 1px solid #999; padding: 5px 6px; font-size: 11px; text-align: left; }
        table.parts td { border: 1px solid #999; padding: 4px 6px; font-size: 11px; vertical-align: top; }
        .cat-row td { background: #f2f2f2; font-weight: 700; padding: 4px 6px; border: 1px solid #999; font-size: 11px; text-align: center; }
        .no-col { width: 34px; text-align: center; }
        .pn-col { width: 130px; }
        .qty-col { width: 48px; text-align: center; }
        .u-col { width: 70px; }
        .p-col { width: 120px; text-align: right; }
        table.parts tr { page-break-inside: avoid; }
        @media print { body { padding: 10mm 12mm; } }
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

    <div class="doc-title">Template Configuration</div>
@php
    $isIms = strtoupper((string) optional($template->division)->division_name) === 'IMS';
@endphp

    <table class="info">
        <tr>
            <td class="k">Nama</td>
            <td>{{ $template->name }}</td>
            <td class="k">Dibuat Oleh</td>
            <td>{{ $template->creator?->display_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Deskripsi</td>
            <td>{!! $template->description ? str_replace('<br>', ' · ', strip_tags(\App\Models\Quotation::renderDescription($template->description), '<br>')) : '—' !!}</td>
            <td class="k">Tanggal</td>
            <td>{{ $template->created_at?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="k">Jumlah Item</td>
            <td>{{ $template->items->count() }} item (total qty {{ $template->items->sum('qty') }})</td>
            <td></td>
            <td></td>
        </tr>
    </table>

@php
    $all = $template->items->keyBy('id');
    $children = $all->groupBy(fn ($i) => $i->parent_id ?: '_root');

    // Kelompokkan per root (parent) berdasarkan kategori, urut kemunculan pertama.
    $groupMap = [];
    $groupOrder = [];
    foreach ($children['_root'] ?? [] as $root) {
        $cat = $root->category ?: 'Lainnya';
        if (! isset($groupMap[$cat])) {
            $groupMap[$cat] = [];
            $groupOrder[] = $cat;
        }
        $groupMap[$cat][] = $root;
    }

    $colspan = $isIms ? 6 : 4;

    $groups = [];
    foreach ($groupOrder as $cat) {
        $catRows = [];
        $walk = function ($parentId, $depth) use (&$walk, &$catRows, $children) {
            foreach ($children[$parentId] ?? [] as $item) {
                $catRows[] = ['item' => $item, 'depth' => $depth];
                $walk($item->id, $depth + 1);
            }
        };
        foreach ($groupMap[$cat] as $root) {
            $kids = $children[$root->id] ?? collect();
            if ($kids->isEmpty()) {
                $catRows[] = ['item' => $root, 'depth' => 0];
            } else {
                $walk($root->id, 1);
            }
        }
        $groups[] = ['category' => $cat, 'rows' => $catRows];
    }
@endphp

    <table class="parts">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th class="pn-col">Part Number</th>
                <th>List Part Instrument</th>
                <th class="qty-col">Qty</th>
                @if($isIms)
                    <th class="u-col">Unit</th>
                    <th class="p-col">Harga</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                <tr class="cat-row">
                    <td colspan="{{ $colspan }}">{{ $group['category'] }}</td>
                </tr>
                @foreach($group['rows'] as $row)
                    @php
                        $item = $row['item'];
                        $depth = $row['depth'];
                        $price = $item->price;
                    @endphp
                    <tr>
                        <td class="no-col" style="padding-left:{{ 6 + $depth * 12 }}px">{{ $item->item_no }}</td>
                        <td class="pn-col" style="padding-left:{{ 4 + $depth * 12 }}px">{{ $item->part_number }}</td>
                        <td>
                            <div style="padding-left:{{ $depth * 12 }}px">{!! \App\Models\Quotation::renderDescription($item->description) ?: '' !!}</div>
                        </td>
                        <td class="qty-col">{{ $item->qty }}</td>
                        @if($isIms)
                            <td class="u-col">{{ $item->unit ?: '—' }}</td>
                            <td class="p-col">{{ $price ? 'Rp '.number_format($price, 0, '.', ',') : '—' }}</td>
                        @endif
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" style="text-align:center;color:#999">Tidak ada item.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
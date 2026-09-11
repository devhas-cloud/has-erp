<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Estimasi PL {{ $estimate->quotation?->quotation_number }}</title>
    @php
        use App\Models\ProfitEstimate as PL;
        $rates = $estimate->rates ?? [];
        $fx = $estimate->foreignCurrenciesUsed();
        $products = $estimate->linesOf(PL::SECTION_PRODUCT);
        $hpp = $estimate->linesOf(PL::SECTION_HPP);
        $spent = $estimate->linesOf(PL::SECTION_COST_SPENT);
        $planned = $estimate->linesOf(PL::SECTION_COST_PLANNED);
        $vendors = $estimate->vendorNames();
        $costLines = $estimate->lines->where('section', '!=', PL::SECTION_PRODUCT);
        $fxTotal = fn (string $code) => $costLines->where('currency', $code)->sum('amount');
        $fxCell = function ($line) use ($fx) {
            $out = '';
            foreach ($fx as $code) {
                $out .= '<td class="num">'.($line->currency === $code ? PL::formatMoney($line->amount) : '').'</td>';
            }
            return $out;
        };
        $fxCount = count($fx);
        // Simbol mata uang dari master currency; fallback bila tidak dikirim.
        $symbol = array_merge(['IDR' => 'Rp', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'], $currencySymbols ?? []);
        $money = function ($amount, ?string $code) use ($symbol) {
            $code = strtoupper($code ?: 'IDR');
            $sym = $symbol[$code] ?? $code;
            return $sym.' '.($code === 'IDR' ? PL::formatMoney($amount, 0) : PL::formatMoney($amount));
        };
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5px; color: #111; line-height: 1.35; }
        @page { margin: 15mm 16mm 15mm 16mm; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 1.8px 5px; vertical-align: middle; }
        .b { font-weight: 700; }
        .num { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        .box td, .box th { border: 1px solid #111; }
        .title { border: 1px solid #111; text-align: center; font-size: 13px; font-weight: 700; padding: 3px; margin: 3px 0 6px; }
        .top td { padding: 1px 4px; }
        .top .kv-k { width: 70px; font-weight: 700; }
        .top .kv-v { border: 1px solid #111; text-align: right; width: 110px; }
        .summ td { padding: 2px 4px; }
        .summ .k { width: 105px; font-weight: 700; }
        .summ .cur { width: 26px; }
        .summ .v { text-align: right; font-weight: 700; font-size: 10.5px; }
        .summ .pct { width: 48px; text-align: right; font-weight: 700; }
        .items td { border: 1px solid #111; font-weight: 700; }
        .grey { background: #d9d9d9; }
        .blue { background: #ddebf7; }
        .sect { font-weight: 700; }
        .sub { padding-left: 14px; }
        .bottom td { padding: 2px 5px; }
        .bottom .k { width: 190px; font-weight: 700; }
        .bottom .p { width: 45px; text-align: right; }
        .bottom .v { width: 150px; text-align: right; font-weight: 700; border: 1px solid #fff; }
        .bottom .lbl { width: 90px; padding-left: 10px; }
        .sign { page-break-inside: avoid; }
        .sign td { text-align: center; padding-top: 10px; }
        .sign .name { margin-top: 14px; font-weight: 700; text-decoration: underline; }
    </style>
</head>
<body>

<table class="top">
    <tr>
        <td style="width:55%;vertical-align:top">
            <img src="{{ public_path('img/has.jpg') }}" style="width:95px;height:auto;" />
        </td>
        <td style="width:45%;vertical-align:top">
            <table class="top">
                <tr><td class="kv-k">Date</td><td style="width:8px">:</td><td class="kv-v">{{ PL::formatDateId($estimate->date) }}</td></tr>
                @foreach($rates as $code => $rate)
                    <tr><td class="kv-k">Kurs {{ $code }}</td><td>:</td><td class="kv-v">{{ PL::formatMoney($rate) }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

<div class="title">Estimasi Perhitungan Pendapatan</div>

<table>
    <tr>
        <td style="width:58%;vertical-align:top;padding:0 6px 0 0">
            <table class="summ">
                <tr><td class="k">Project / Company</td><td class="cur">:</td><td colspan="2" class="b">{{ $estimate->project_name ?? '—' }}</td></tr>
                <tr><td class="k">Nilai Awal</td><td class="cur">:</td><td class="cur">IDR</td><td class="v">{{ PL::formatMoney($estimate->nilai_awal) }}</td></tr>
                <tr><td class="k">PPN</td><td class="cur">:</td><td class="cur">IDR</td><td class="v" style="border-bottom:1px solid #111">{{ PL::formatMoney($estimate->ppn_amount) }}</td></tr>
                <tr><td class="k">Sub Total I</td><td class="cur">:</td><td class="cur">IDR</td><td class="v">{{ PL::formatMoney($estimate->subtotal_1) }}</td></tr>
                <tr><td class="k">Discount</td><td class="cur">:</td><td class="cur">IDR</td><td class="v">{{ $estimate->discount_amount > 0 ? PL::formatMoney($estimate->discount_amount) : '-' }}</td></tr>
                <tr><td class="k">Sub Total II</td><td class="cur">:</td><td class="cur">IDR</td><td class="v">{{ PL::formatMoney($estimate->subtotal_2) }}</td></tr>
                <tr><td class="k">Referen <span style="float:right">{{ number_format($estimate->referral_percent, 2) }}%</span></td><td class="cur">:</td><td class="cur">IDR</td><td class="v">{{ PL::formatMoney($estimate->referral_amount) }}</td></tr>
                <tr><td class="k">Nilai Real Project</td><td class="cur">:</td><td class="cur">IDR</td><td class="v">{{ PL::formatMoney($estimate->real_project_value) }}</td></tr>
            </table>
        </td>
        <td style="width:42%;vertical-align:top;padding:0">
            <table class="items">
                @foreach($products as $p)
                    <tr>
                        <td style="width:40%">{{ $p->label }}</td>
                        <td class="c" style="width:26%;white-space:nowrap">{{ $p->qty + 0 }} {{ $p->unit }} x</td>
                        <td class="num">{{ $p->amount ? $money($p->amount, $p->currency) : '' }}</td>
                    </tr>
                @endforeach
                @if($products->isEmpty())
                    <tr><td style="width:40%">&nbsp;</td><td style="width:26%"></td><td></td></tr>
                @endif
                <tr><td>&nbsp;</td><td></td><td></td></tr>
                {{-- Vendor + nilai HPP-nya (per vendor & mata uang); fallback nama vendor saja bila HPP kosong. --}}
                @forelse($hpp as $h)
                    <tr>
                        <td>Vendor</td>
                        <td class="c" style="white-space:nowrap">{{ $h->vendor ?: $h->label }}</td>
                        <td class="num">{{ $money($h->is_up ? $h->amount - PL::HPP_UP_AMOUNT : $h->amount, $h->currency) }}</td>
                    </tr>
                @empty
                    @foreach($vendors as $v)
                        <tr><td>Vendor</td><td class="c" colspan="2">{{ $v }}</td></tr>
                    @endforeach
                @endforelse
            </table>
        </td>
    </tr>
</table>

<table class="box" style="margin-top:6px">
    <thead>
        <tr>
            <th rowspan="2" style="width:45%">Deskripsi</th>
            <th colspan="{{ $fxCount + 1 }}">Mata Uang</th>
        </tr>
        <tr>
            @foreach($fx as $code)<th>{{ $code }}</th>@endforeach
            <th>Rp</th>
        </tr>
    </thead>
    <tbody>
        @foreach($hpp as $l)
            <tr>
                <td>
                    @if($loop->first)<span class="b">Harga Pokok Product ( FOB, TT )</span>@endif
                    <span class="b" style="float:right;width:45%">{{ $l->vendor ?: $l->label }}</span>
                </td>
                {!! $fxCell($l) !!}
                <td class="num">{{ PL::formatMoney($l->amount_idr) }}</td>
            </tr>
        @endforeach
        @if($hpp->isEmpty())
            <tr><td class="b">Harga Pokok Product ( FOB, TT )</td>{!! str_repeat('<td></td>', $fxCount) !!}<td></td></tr>
        @endif

        <tr><td class="sect">Operasional Cost</td>{!! str_repeat('<td></td>', $fxCount) !!}<td></td></tr>
        <tr><td class="sect">1. Yang Telah Dikeluarkan</td>{!! str_repeat('<td></td>', $fxCount) !!}<td></td></tr>
        @foreach($spent as $l)
            <tr>
                <td class="sub">{{ chr(97 + $loop->index) }} . {{ $l->label }}</td>
                {!! $fxCell($l) !!}
                <td class="num">{{ PL::formatMoney($l->amount_idr) }}</td>
            </tr>
        @endforeach

        <tr><td class="sect">2. Yang Akan dikeluarkan</td>{!! str_repeat('<td></td>', $fxCount) !!}<td></td></tr>
        @foreach($planned as $l)
            <tr>
                <td class="sub">{{ $l->label }}</td>
                {!! $fxCell($l) !!}
                <td class="num">{{ PL::formatMoney($l->amount_idr) }}</td>
            </tr>
        @endforeach

        <tr><td>&nbsp;</td>{!! str_repeat('<td></td>', $fxCount) !!}<td></td></tr>
        <tr>
            <td class="b">Company Investment &amp; Development <span style="float:right">{{ number_format($estimate->investment_percent, 2) }}%</span></td>
            {!! str_repeat('<td></td>', $fxCount) !!}
            <td class="num">{{ PL::formatMoney($estimate->investment_amount) }}</td>
        </tr>
        <tr class="grey">
            <td class="b">Total Biaya Yang dikeluarkan</td>
            @foreach($fx as $code)<td class="num b">{{ ($symbol[$code] ?? $code).' '.PL::formatMoney($fxTotal($code)) }}</td>@endforeach
            <td class="num b">{{ PL::formatMoney($estimate->total_cost) }}</td>
        </tr>
    </tbody>
</table>

<table class="bottom" style="margin-top:6px">
    <tr><td class="k">Nilai Real Project</td><td class="p"></td><td class="c" style="width:10px">:</td><td class="v grey">{{ PL::formatMoney($estimate->real_project_value) }}</td><td class="lbl"></td><td></td><td></td></tr>
    <tr><td class="k">Nilai Biaya Yang dikeluarkan</td><td class="p"></td><td class="c">:</td><td class="v grey">{{ PL::formatMoney($estimate->total_cost) }}</td><td class="lbl"></td><td></td><td></td></tr>
    <tr><td class="k">Estimasi Nilai Profit</td><td class="p"></td><td class="c">:</td><td class="v blue">{{ PL::formatMoney($estimate->estimated_profit) }}</td><td class="lbl b">Prosentase %</td><td class="c">:</td><td class="v grey">{{ number_format($estimate->profit_percent, 2) }}</td></tr>
    <tr><td class="k">Fee Marketing</td><td class="p">{{ number_format($estimate->marketing_fee_percent, 2) }}%</td><td class="c">:</td><td class="v grey">{{ PL::formatMoney($estimate->marketing_fee_amount) }}</td><td class="lbl"></td><td></td><td></td></tr>
    <tr><td class="k">Fee PM</td><td class="p">{{ number_format($estimate->pm_fee_percent, 2) }}%</td><td class="c">:</td><td class="v grey">{{ PL::formatMoney($estimate->pm_fee_amount) }}</td><td class="lbl"></td><td></td><td></td></tr>
    <tr><td class="k">Real Profit</td><td class="p"></td><td class="c">:</td><td class="v blue">{{ PL::formatMoney($estimate->real_profit) }}</td><td class="lbl">Prosentase %</td><td class="c">:</td><td class="v grey">{{ number_format($estimate->real_profit_percent, 2) }}%</td></tr>
</table>

@if($estimate->notes)
    <div style="margin-top:6px;font-size:9px"><span class="b">Catatan :</span><br>{!! nl2br(e($estimate->notes)) !!}</div>
@endif

<div style="margin-top:4px;padding-left:55%;font-size:9px">Jakarta, ...........................................</div>

<table class="sign">
    <tr>
        <td style="width:34%"><div>Sales Person</div><div class="name">{{ $estimate->sales_person_name ?? '' }}</div></td>
        <td style="width:33%"><div>Finance Dept</div><div class="name">{{ $estimate->finance_name ?? '' }}</div></td>
        <td style="width:33%"><div>Accounting</div><div class="name">{{ $estimate->accounting_name ?? '' }}</div></td>
    </tr>
</table>

</body>
</html>

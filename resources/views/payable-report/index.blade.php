@extends('layouts.app')

@section('title', 'Laporan Hutang')
@section('page-title', 'Laporan Hutang')

@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
@endphp

@section('styles')
<style>
    .pr-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (max-width: 991.98px) { .pr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .pr-kpi { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 14px 16px; }
    .pr-kpi .lbl { font-size: 12px; color: var(--text-muted); }
    .pr-kpi .val { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .pr-kpi.danger .val { color: #b91c1c; }
    .pr-kpi.warn .val { color: #b45309; }
    .pr-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid var(--card-border); margin-bottom: 14px; }
    .pr-tabs a { padding: 8px 14px; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; }
    .pr-tabs a.active { color: var(--accent); border-bottom-color: var(--accent); }
    .pr-tabs a:hover { color: var(--text-primary); }
    .pr-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .pr-muted { font-size: 12px; color: var(--text-muted); }
    .pr-bar { display: flex; height: 6px; border-radius: 99px; overflow: hidden; background: #eef2f7; min-width: 120px; }
    .pr-bar span { display: block; height: 100%; }
    .pr-group-row td { background: var(--accent-subtle) !important; font-weight: 600; }
    .pr-group-row.overdue td { background: #fef2f2 !important; color: #7f1d1d; }
    table.pr-table tfoot td { font-weight: 700; border-top: 2px solid var(--card-border); }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Laporan Hutang</h1>
        <p class="page-header-sub">Posisi hutang supplier per {{ now()->format('d/m/Y') }}. Nilai dalam {{ $base }}; tagihan valas dinilai dengan kurs tanggal invoice supplier (nilai buku). Selisih terhadap kurs terbaru ada di tab Selisih Kurs.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('payable-report.export', array_merge(['tab' => $tab], $tab === 'fx' ? $period : [])) }}" class="btn btn-secondary btn-sm"><i class="fa fa-file-excel me-1"></i> Export Excel</a>
    </div>
</div>

<div class="pr-kpis">
    <div class="pr-kpi"><div class="lbl">Sisa hutang</div><div class="val">{{ $money($totals['outstanding']) }}</div><div class="pr-muted">{{ $totals['open_count'] }} tagihan terbuka</div></div>
    <div class="pr-kpi danger"><div class="lbl">Lewat jatuh tempo</div><div class="val">{{ $money($totals['overdue']) }}</div><div class="pr-muted"><a href="{{ route('supplier-bill.index', ['overdue' => 1]) }}">Lihat tagihan terlambat</a></div></div>
    <div class="pr-kpi warn"><div class="lbl">Jatuh tempo minggu ini</div><div class="val">{{ $money($totals['due_week']) }}</div><div class="pr-muted">s/d {{ now()->endOfWeek()->format('d/m/Y') }}</div></div>
    <div class="pr-kpi"><div class="lbl">Belum jatuh tempo</div><div class="val">{{ $money($totals['outstanding'] - $totals['overdue']) }}</div><div class="pr-muted">sisa hutang yang belum terlambat</div></div>
</div>

<div class="card-custom fade-in">
    <div class="card-body-custom">
        <nav class="pr-tabs" aria-label="Jenis laporan">
            @foreach($tabs as $key => $label)
                <a href="{{ route('payable-report.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="table-responsive">
        @switch($tab)
            @case('aging')
                @php $agingColors = ['current' => '#10b981', 'd1_30' => '#f59e0b', 'd31_60' => '#f97316', 'd61_90' => '#ef4444', 'd90' => '#991b1b']; @endphp
                <table class="table table-custom align-middle mb-0 pr-table">
                    <thead><tr><th>Supplier</th>@foreach(\App\Support\PayableReport::AGING_BUCKETS as $label)<th class="pr-num">{{ $label }}</th>@endforeach<th class="pr-num">Total</th><th style="width:140px">Komposisi</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr>
                                <td><a href="{{ route('supplier-bill.index', ['supplier_id' => $r['supplier']?->id]) }}" class="fw-semibold">{{ $r['supplier']?->name }}</a></td>
                                @foreach($r['buckets'] as $key => $value)<td class="pr-num {{ $key !== 'current' && $value > 0 ? 'text-danger' : '' }}">{{ $value > 0 ? $money($value) : '—' }}</td>@endforeach
                                <td class="pr-num fw-semibold">{{ $money($r['total']) }}</td>
                                <td><div class="pr-bar" title="Komposisi umur hutang">@foreach($r['buckets'] as $key => $value)@if($value > 0)<span style="width:{{ $r['total'] > 0 ? $value / $r['total'] * 100 : 0 }}%;background:{{ $agingColors[$key] }}"></span>@endif @endforeach</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 pr-muted">Tidak ada hutang terbuka.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    <tfoot><tr><td>Total</td>@foreach(array_keys(\App\Support\PayableReport::AGING_BUCKETS) as $key)<td class="pr-num">{{ $money($rows->sum(fn ($r) => $r['buckets'][$key])) }}</td>@endforeach<td class="pr-num">{{ $money($rows->sum('total')) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
                @break

            @case('schedule')
                <table class="table table-custom align-middle mb-0 pr-table">
                    <thead><tr><th>Tagihan</th><th>Supplier</th><th>Jatuh Tempo</th><th class="pr-num">Sisa</th><th class="pr-num">Sisa ({{ $base }})</th></tr></thead>
                    <tbody>
                        @forelse($rows as $group)
                            <tr class="pr-group-row {{ $group['key'] === '0_overdue' ? 'overdue' : '' }}"><td colspan="4">{{ $group['label'] }} <span class="pr-muted">({{ $group['bills']->count() }} tagihan)</span></td><td class="pr-num">{{ $money($group['total']) }}</td></tr>
                            @foreach($group['bills'] as $bill)
                                <tr>
                                    <td><a href="{{ route('supplier-bill.show', $bill->id) }}" class="fw-semibold">{{ $bill->bill_number }}</a>@if($bill->isAdvance()) <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px">Uang Muka</span>@endif<div class="pr-muted">Inv. {{ $bill->supplier_invoice_number }} · {{ $bill->sourceNumber() }}</div></td>
                                    <td>{{ $bill->supplier?->name }}</td>
                                    <td>{{ $bill->due_date?->format('d/m/Y') }}@if($report->daysOverdue($bill) > 0)<div class="pr-muted text-danger">terlambat {{ $report->daysOverdue($bill) }} hari</div>@endif</td>
                                    <td class="pr-num">{{ $bill->currency }} {{ \App\Support\Number::money($bill->outstanding()) }}</td>
                                    <td class="pr-num">{{ $money($bill->outstandingBase()) }}@if($bill->isForeign())<div class="pr-muted">kurs {{ \App\Support\Number::rate($bill->exchange_rate) }}</div>@endif</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="5" class="text-center py-4 pr-muted">Tidak ada tagihan yang perlu dibayar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @break

            @case('fx')
                @php $fxMoney = fn ($v) => ($v > 0 ? '' : ($v < 0 ? '−' : '')).$money(abs($v)); @endphp
                <form class="d-flex flex-wrap align-items-end gap-2 mb-3" method="GET" action="{{ route('payable-report.index') }}">
                    <input type="hidden" name="tab" value="fx">
                    <div><label class="form-label pr-muted mb-1" for="pr-fx-from">Dari</label><input type="date" id="pr-fx-from" name="from" class="form-control form-control-sm" value="{{ $period['from'] }}"></div>
                    <div><label class="form-label pr-muted mb-1" for="pr-fx-to">Sampai</label><input type="date" id="pr-fx-to" name="to" class="form-control form-control-sm" value="{{ $period['to'] }}"></div>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="fa fa-filter me-1"></i> Tampilkan</button>
                </form>
                <p class="pr-muted mb-2">
                    <strong>Terealisasi</strong> = pembayaran / refund valas yang sudah diposting: nilai aktual (kurs bank) dibanding nilai buku (kurs tagihan / kurs uang muka).
                    Angka positif = <span class="text-danger">rugi kurs</span>, negatif = <span style="color:#15803d">laba kurs</span>.
                </p>
                <table class="table table-custom align-middle mb-4 pr-table">
                    <thead><tr><th>Tanggal</th><th>Dokumen</th><th>Supplier</th><th class="pr-num">Nominal</th><th class="pr-num">Kurs Buku → Aktual</th><th class="pr-num">Nilai Buku</th><th class="pr-num">Nilai Aktual</th><th class="pr-num">Selisih</th></tr></thead>
                    <tbody>
                        @forelse($rows['realized'] as $r)
                            <tr>
                                <td>{{ $r['date']?->format('d/m/Y') }}</td>
                                <td><a href="{{ $r['url'] }}" class="fw-semibold">{{ $r['number'] }}</a><div class="pr-muted">{{ $r['reference'] }}</div></td>
                                <td>{{ $r['supplier'] }}</td>
                                <td class="pr-num">{{ $r['currency'] }} {{ \App\Support\Number::money($r['amount']) }}</td>
                                <td class="pr-num">{{ \App\Support\Number::money($r['book_rate']) }} → {{ \App\Support\Number::money($r['actual_rate']) }}</td>
                                <td class="pr-num">{{ $money($r['book']) }}</td>
                                <td class="pr-num">{{ $money($r['actual']) }}</td>
                                <td class="pr-num fw-semibold {{ $r['difference'] > 0 ? 'text-danger' : ($r['difference'] < 0 ? 'text-success' : '') }}">{{ $fxMoney($r['difference']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 pr-muted">Belum ada pembayaran / refund valas pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows['realized']->isNotEmpty())
                    <tfoot><tr><td colspan="7">Total selisih kurs terealisasi ({{ $rows['realized_total'] > 0 ? 'rugi' : ($rows['realized_total'] < 0 ? 'laba' : 'nihil') }})</td><td class="pr-num {{ $rows['realized_total'] > 0 ? 'text-danger' : 'text-success' }}">{{ $fxMoney($rows['realized_total']) }}</td></tr></tfoot>
                    @endif
                </table>

                <p class="pr-muted mb-2"><strong>Belum terealisasi</strong> = tagihan valas yang masih terbuka: nilai buku dibanding nilai bila dibayar dengan kurs master saat ini. Hanya informasi, belum dicatat.</p>
                <table class="table table-custom align-middle mb-0 pr-table">
                    <thead><tr><th>Tagihan</th><th>Supplier</th><th class="pr-num">Sisa</th><th class="pr-num">Kurs Tagihan → Terbaru</th><th class="pr-num">Nilai Buku</th><th class="pr-num">Nilai Kini</th><th class="pr-num">Selisih</th></tr></thead>
                    <tbody>
                        @forelse($rows['unrealized'] as $u)
                            <tr>
                                <td><a href="{{ route('supplier-bill.show', $u['bill']->id) }}" class="fw-semibold">{{ $u['bill']->bill_number }}</a>@if($u['bill']->rate_estimated) <span class="badge" style="background:#fef3c7;color:#92400e;font-size:10px" title="Kurs diisi otomatis dari kurs master">kurs perkiraan</span>@endif<div class="pr-muted">Inv. {{ $u['bill']->supplier_invoice_number }}</div></td>
                                <td>{{ $u['bill']->supplier?->name }}</td>
                                <td class="pr-num">{{ $u['bill']->currency }} {{ \App\Support\Number::money($u['outstanding']) }}</td>
                                <td class="pr-num">{{ \App\Support\Number::money($u['book_rate']) }} → {{ \App\Support\Number::money($u['current_rate']) }}</td>
                                <td class="pr-num">{{ $money($u['book']) }}</td>
                                <td class="pr-num">{{ $money($u['current']) }}</td>
                                <td class="pr-num fw-semibold {{ $u['difference'] > 0 ? 'text-danger' : ($u['difference'] < 0 ? 'text-success' : '') }}">{{ $fxMoney($u['difference']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 pr-muted">Tidak ada tagihan valas terbuka.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows['unrealized']->isNotEmpty())
                    <tfoot><tr><td colspan="6">Total selisih belum terealisasi</td><td class="pr-num">{{ $fxMoney($rows['unrealized_total']) }}</td></tr></tfoot>
                    @endif
                </table>
                @break

            @case('project')
                <p class="pr-muted mb-2">Nilai sebelum pajak. Komitmen = PO & Direct Purchase untuk project; Terbayar = porsi tagihan yang sudah dibayar / dipotong uang muka / PPh. Alokasi stok = stok bebas yang dialokasikan ke project (harga rata-rata). Pembelian untuk stok belum dihitung sampai dialokasikan.</p>
                <table class="table table-custom align-middle mb-0 pr-table">
                    <thead><tr><th>Project</th><th class="pr-num">Komitmen</th><th class="pr-num">Diterima</th><th class="pr-num">Ditagih</th><th class="pr-num">Terbayar</th><th class="pr-num">Alokasi Stok</th><th class="pr-num">Total Biaya</th><th class="pr-num">Estimasi (PL)</th><th class="pr-num">Selisih</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr>
                                <td><a href="{{ route('project.show', $r['project']->id) }}" class="fw-semibold">{{ $r['project']->project_number }}</a><div class="pr-muted">{{ $r['project']->project_name }}</div></td>
                                <td class="pr-num">{{ $money($r['commitment']) }}</td>
                                <td class="pr-num">{{ $money($r['received']) }}</td>
                                <td class="pr-num">{{ $money($r['billed']) }}</td>
                                <td class="pr-num">{{ $money($r['paid']) }}</td>
                                <td class="pr-num">{{ $r['stock_allocated'] ? $money($r['stock_allocated']) : '—' }}</td>
                                <td class="pr-num fw-semibold">{{ $money($r['actual']) }}</td>
                                <td class="pr-num">{{ $r['estimate'] !== null ? $money($r['estimate']) : '—' }}</td>
                                <td class="pr-num {{ $r['variance'] !== null && $r['variance'] < 0 ? 'text-danger fw-semibold' : '' }}">{{ $r['variance'] !== null ? $money($r['variance']) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-4 pr-muted">Belum ada pembelian untuk project.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @break

            @default
                <table class="table table-custom align-middle mb-0 pr-table">
                    <thead><tr><th>Supplier</th><th class="pr-num">Nilai PO Aktif</th><th class="pr-num">Kas Keluar Bersih</th><th class="pr-num">Uang Muka Belum Terpakai</th><th class="pr-num">Sisa Hutang</th><th class="pr-num" title="Selisih bila sisa hutang valas dibayar dengan kurs master saat ini (+ rugi / − laba)">Selisih Kurs*</th><th class="pr-num">Terlambat</th><th>Jatuh Tempo Terdekat</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr>
                                <td><a href="{{ route('supplier-bill.index', ['supplier_id' => $r['supplier']->id]) }}" class="fw-semibold">{{ $r['supplier']->name }}</a><div class="pr-muted">{{ $r['open_count'] }} tagihan terbuka</div></td>
                                <td class="pr-num">{{ $money($r['po_value']) }}</td>
                                <td class="pr-num">{{ $money($r['paid']) }}</td>
                                <td class="pr-num">{{ $r['advance_balance'] > 0 ? $money($r['advance_balance']) : '—' }}</td>
                                <td class="pr-num fw-semibold">{{ $r['outstanding'] > 0 ? $money($r['outstanding']) : '—' }}</td>
                                <td class="pr-num {{ $r['unrealized_fx'] > 0 ? 'text-danger' : ($r['unrealized_fx'] < 0 ? 'text-success' : '') }}">{{ abs($r['unrealized_fx']) >= 0.01 ? ($r['unrealized_fx'] < 0 ? '−' : '').$money(abs($r['unrealized_fx'])) : '—' }}</td>
                                <td class="pr-num {{ $r['overdue'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $r['overdue'] > 0 ? $money($r['overdue']).' ('.$r['overdue_count'].')' : '—' }}</td>
                                <td>{{ $r['next_due']?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 pr-muted">Belum ada data supplier.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    <tfoot><tr><td>Total</td><td class="pr-num">{{ $money($rows->sum('po_value')) }}</td><td class="pr-num">{{ $money($rows->sum('paid')) }}</td><td class="pr-num">{{ $money($rows->sum('advance_balance')) }}</td><td class="pr-num">{{ $money($rows->sum('outstanding')) }}</td><td class="pr-num">{{ $money($rows->sum('unrealized_fx')) }}</td><td class="pr-num">{{ $money($rows->sum('overdue')) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
        @endswitch
        </div>
    </div>
</div>

{{-- Status pengingat WhatsApp; pengaturannya di Master Data → Notifikasi Finance --}}
<div class="d-flex align-items-center flex-wrap gap-2 mt-3 pr-muted">
    <i class="fa-brands fa-whatsapp" style="color:#16a34a"></i>
    <span>Pengingat Jatuh Tempo (WhatsApp):</span>
    <span class="status-badge" style="{{ $reminder['enabled'] ? 'background:#dcfce7;color:#166534' : 'background:#f3f4f6;color:#4b5563' }}">{{ $reminder['enabled'] ? 'Aktif · setiap hari '.$reminder['send_time'] : 'Nonaktif' }}</span>
    @if($reminder['last_sent_date'])<span>· terakhir terkirim {{ \Illuminate\Support\Carbon::parse($reminder['last_sent_date'])->format('d/m/Y') }}</span>@endif
    @if($canManageNotification)<a href="{{ route('finance-notification.index') }}" class="ms-1"><i class="fa fa-gear me-1"></i>Atur</a>@endif
</div>
@endsection

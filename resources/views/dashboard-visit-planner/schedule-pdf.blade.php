@php
    $days = $vs['days'];
    $first = $days[0];
    $last = end($days);
    $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    // "23 – 27 November 2026" / "26 November – 4 Desember 2026" / lintas tahun lengkap.
    $period = $first->year !== $last->year
        ? $first->day.' '.$months[$first->month].' '.$first->year.' – '.$last->day.' '.$months[$last->month].' '.$last->year
        : ($first->month === $last->month
            ? $first->day.' – '.$last->day.' '.$months[$last->month].' '.$last->year
            : $first->day.' '.$months[$first->month].' – '.$last->day.' '.$months[$last->month].' '.$last->year);
    $time = fn ($t) => $t ? str_replace(':', '.', $t) : '-';
    $people = $vs['groups']->flatMap(fn ($g) => $g['rows']->map(fn ($r) => $r + ['division' => $g['name']]))->values();
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Jadwal Visit Sales {{ $period }}</title>
<style>
    @page { margin: 34px 40px 36px; }
    * { font-family: Helvetica, Arial, sans-serif; }
    body { font-size: 10.5px; color: #111827; }
    .title { text-align: center; margin-bottom: 18px; }
    .title h1 { font-size: 19px; font-weight: bold; margin: 0 0 3px; letter-spacing: .3px; }
    .title .sub { font-size: 12px; color: #4b5563; }
    .title .filter { font-size: 9px; color: #6b7280; margin-top: 3px; }
    .person { page-break-inside: avoid; margin-bottom: 18px; }
    .person h2 { font-size: 14px; font-weight: bold; color: #1f4e3d; margin: 0 0 6px; padding-bottom: 3px; border-bottom: 1.5px solid #1f4e3d; text-transform: uppercase; }
    .person h2 small { font-size: 9px; font-weight: normal; color: #6b7280; text-transform: none; margin-left: 6px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 0.7px solid #9ca3af; padding: 5px 7px; vertical-align: middle; }
    th { background: #fff200; font-weight: bold; text-align: center; font-size: 10.5px; }
    td.c { text-align: center; }
    td.merged { background: #e8f3ea; font-weight: bold; text-align: center; }
    td.none { color: #9ca3af; text-align: center; font-style: italic; }
    .col-date { width: 13%; }
    .col-time { width: 8%; }
    .col-company { width: 20%; }
    .col-pic { width: 13%; }
    .col-title { width: 16%; }
    .empty { text-align: center; color: #6b7280; margin-top: 30px; }
    .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
    <div class="title">
        <h1>JADWAL VISIT SALES</h1>
        <div class="sub">PT HAS Environmental &nbsp;|&nbsp; {{ $period }}</div>
        @if($activeFilters)<div class="filter">Filter: {{ implode(' · ', $activeFilters) }}</div>@endif
    </div>

    @forelse($people as $i => $row)
        <div class="person">
            <h2>{{ $i + 1 }}. {{ $row['user']->display_name }} <small>{{ $row['division'] }}</small></h2>
            <table>
                <thead>
                    <tr>
                        <th class="col-date">Tanggal</th>
                        <th class="col-time">Waktu<br>(WIB)</th>
                        <th class="col-company">Nama Perusahaan</th>
                        <th class="col-pic">Nama PIC</th>
                        <th class="col-title">Jabatan</th>
                        <th>Topic</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($days as $day)
                        @php
                            $items = $row['days'][$day->toDateString()];
                            $dateLabel = $dayNames[$day->dayOfWeekIso].', '.$day->format('d/m/Y');
                        @endphp
                        @forelse($items as $k => $item)
                            <tr>
                                @if($k === 0)<td @if(count($items) > 1) rowspan="{{ count($items) }}" @endif>{{ $dateLabel }}</td>@endif
                                <td class="c">{{ $time($item['time']) }}</td>
                                @if(! $item['company'] && ! $item['pic'])
                                    {{-- Tanpa customer / PIC (mis. internal): topik digabung satu baris --}}
                                    <td colspan="4" class="merged">{{ $item['title'] }}</td>
                                @else
                                    <td>{{ $item['company'] ?: '-' }}</td>
                                    <td>{{ $item['pic'] ?: '-' }}</td>
                                    <td>{{ $item['pic_title'] ?: '-' }}</td>
                                    <td>{{ $item['title'] }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td>{{ $dateLabel }}</td>
                                <td class="c">-</td>
                                <td colspan="4" class="none">Tidak ada jadwal visit</td>
                            </tr>
                        @endforelse
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="empty">Tidak ada jadwal visit sales pada {{ $period }}{{ $activeFilters ? ' dengan filter ini' : '' }}.</p>
    @endforelse

    <div class="footer">Dicetak oleh {{ $printedBy }} · {{ now()->format('d/m/Y H:i') }} · HAS ERP</div>
</body>
</html>

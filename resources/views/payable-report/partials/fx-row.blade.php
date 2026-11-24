@php
    $baseMoney = fn ($v) => \App\Support\Number::moneyWithCode((float) $v, $base);
    $diff = (float) $r['difference'];
    $projects = collect($r['projects']);
@endphp
<td class="text-center pr-muted">{{ $i + 1 }}</td>
<td class="text-center">{{ $r['date']?->format('d/m/Y') ?? '—' }}</td>
<td>
    @forelse($projects as $project)
        <div><a href="{{ route('project.show', $project->id) }}" class="fw-semibold">{{ $project->project_number }}</a> <span class="pr-muted">{{ $project->project_name }}</span></div>
    @empty
        <span class="pr-muted">Stock / Internal</span>
    @endforelse
</td>
<td>
    <a href="{{ $r['url'] }}" class="fw-semibold">{{ $r['number'] }}</a>
    <div class="pr-muted">{{ $r['reference'] }}@if($r['remaining'] > 0.005) · remaining {{ \App\Support\Number::moneyWithCode($r['remaining'], $r['currency']) }}@endif</div>
</td>
<td>{{ $r['supplier'] ?? '—' }}</td>
<td class="pr-num">{{ \App\Support\Number::moneyWithCode($r['amount'], $r['currency']) }}</td>
<td class="pr-num">{{ \App\Support\Number::rate($r['book_rate']) }} → {{ \App\Support\Number::rate($r['actual_rate']) }}</td>
<td class="pr-num">{{ $baseMoney($r['book']) }}</td>
<td class="pr-num">{{ $baseMoney($r['actual']) }}</td>
<td class="pr-num fw-semibold {{ $diff > 0 ? 'text-danger' : ($diff < 0 ? 'text-success' : '') }}" title="{{ $diff > 0 ? 'Exchange loss' : ($diff < 0 ? 'Exchange gain' : 'No difference') }}">{{ $baseMoney($diff) }}</td>

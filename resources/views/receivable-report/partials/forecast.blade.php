@php
    $money = fn ($v) => \App\Support\Number::money((float) $v);
    $positionStyles = [
        'unscheduled' => 'background:#e5e7eb;color:#374151',
        'locked' => 'background:#f1f5f9;color:#475569',
        'ready' => 'background:#e0f2fe;color:#0369a1',
        'in_process' => 'background:#ede9fe;color:#6d28d9',
        'receivable' => 'background:#fef3c7;color:#92400e',
        'paid' => 'background:#dcfce7;color:#166534',
    ];
@endphp
@forelse($groups as $group)
    @php $groupTint = $group['key'] === '0_overdue' ? 'overdue' : ($group['key'] === '0_late_billing' ? 'late' : ''); @endphp
    <tbody class="rr-group">
        <tr class="rr-group-row rr-group-head {{ $groupTint }}" title="Klik untuk membuka/menutup kelompok">
            <td colspan="5"><i class="fa fa-chevron-down rr-chev me-1"></i>{{ $group['label'] }} <span class="rr-muted">({{ $group['rows']->count() }})</span></td>
            <td class="rr-num">{{ $money($group['certain'] + $group['estimate']) }}@if($group['certain'] > 0 && $group['estimate'] > 0)<div class="rr-muted" style="font-weight:400">pasti {{ $money($group['certain']) }} · estimasi {{ $money($group['estimate']) }}</div>@endif</td>
        </tr>
        @foreach($group['rows'] as $r)
            @php $overdueDays = $r['kind'] === 'invoice' ? $report->daysOverdue($r['invoice']) : 0; @endphp
            <tr class="rr-group-item {{ $overdueDays > 0 ? 'rr-row-overdue' : '' }}">
                <td class="text-center rr-muted">{{ $loop->iteration }}</td>
                <td>{{ $r['project']?->company?->account_name ?? '—' }}@if($r['project'])<div class="rr-muted"><a href="{{ route('project.show', $r['project']->id) }}#timeline-penagihan">{{ $r['project']->project_number }}</a> {{ $r['project']->project_name }}</div>@endif</td>
                <td>{{ $r['term'] ? $r['term']->name.' · '.\App\Models\Invoice::formatNumber($r['term']->percent).'%' : ($r['invoice']?->noted ?: 'Di luar timeline') }}@if($r['invoice'])<div class="rr-muted"><a href="{{ route('invoice.show', $r['invoice']->id) }}">{{ $r['invoice']->invoice_number }}</a></div>@endif</td>
                <td>{{ $r['date']?->format('d/m/Y') ?? '—' }}@if($overdueDays > 0)<div class="rr-muted text-danger">terlambat {{ $overdueDays }} hari</div>@endif</td>
                <td><span class="rr-tag {{ $r['kind'] === 'invoice' ? 'certain' : 'estimate' }}">{{ $r['kind'] === 'invoice' ? 'Pasti' : 'Estimasi' }}</span> <span class="status-badge" style="{{ $positionStyles[$r['position']] ?? '' }}">{{ \App\Support\ReceivableReport::POSITIONS[$r['position']] }}</span></td>
                <td class="rr-num">{{ $money($r['amount']) }}</td>
            </tr>
        @endforeach
    </tbody>
@empty
    <tbody><tr><td colspan="6"><div class="rr-empty"><i class="fa-solid fa-inbox"></i>Tidak ada piutang maupun termin yang cocok dengan filter.</div></td></tr></tbody>
@endforelse

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
        <tr class="rr-group-row rr-group-head {{ $groupTint }}" title="Click to expand / collapse the group">
            <td colspan="6"><i class="fa fa-chevron-down rr-chev me-1"></i>{{ $group['label'] }} <span class="rr-muted">({{ $group['rows']->count() }})</span></td>
            <td class="rr-num">{{ $money($group['certain'] + $group['estimate']) }}@if($group['certain'] > 0 && $group['estimate'] > 0)<div class="rr-muted" style="font-weight:400">confirmed {{ $money($group['certain']) }} · estimate {{ $money($group['estimate']) }}</div>@endif</td>
        </tr>
        @foreach($group['rows'] as $r)
            @php
                $overdueDays = $r['kind'] === 'invoice' ? $report->daysOverdue($r['invoice']) : 0;
                // Sumber tanggal: jatuh tempo invoice -> Billing Target termin (perkiraan invoice dibuat) -> PO due date project.
                $invoiceDue = $r['invoice']?->due_date;
                $targetDate = $r['term']?->target_date;
                [$dueDate, $dueSource] = match (true) {
                    (bool) $invoiceDue => [$invoiceDue, 'invoice'],
                    (bool) $targetDate => [$targetDate, 'target'],
                    (bool) $r['project']?->po_due_date => [$r['project']->po_due_date, 'po'],
                    default => [null, 'none'],
                };
                $lateTarget = $dueSource === 'target' && $targetDate->copy()->startOfDay()->lt(now()->startOfDay())
                    ? (int) $targetDate->copy()->startOfDay()->diffInDays(now()->startOfDay()) : 0;
            @endphp
            <tr class="rr-group-item {{ $overdueDays > 0 ? 'rr-row-overdue' : '' }}">
                <td class="text-center rr-muted">{{ $loop->iteration }}</td>
                <td>{{ $r['project']?->company?->account_name ?? '—' }}</td>
                <td>
                    @if($r['project'])
                        <a href="{{ route('project.show', $r['project']->id) }}#timeline-penagihan" class="fw-semibold">{{ $r['project']->project_number }}</a>
                        <div class="rr-muted">{{ $r['project']->project_name }}</div>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $r['term'] ? $r['term']->name.' · '.\App\Models\Invoice::formatNumber($r['term']->percent).'%' : ($r['invoice']?->noted ?: 'Outside billing timeline') }}@if($r['invoice'])<div class="rr-muted"><a href="{{ route('invoice.show', $r['invoice']->id) }}">{{ $r['invoice']->invoice_number }}</a></div>@endif</td>
                <td class="text-center">
                    {{ $dueDate?->format('d/m/Y') ?? '—' }}
                    @switch($dueSource)
                        @case('invoice')
                            @if($r['kind'] === 'estimate')<div class="rr-muted">invoice due date (in process)</div>@endif
                            @break
                        @case('target')
                            <div class="rr-muted" title="No invoice yet: expected invoice date from the Billing Target on the billing timeline">Billing Target · expected invoice</div>
                            @break
                        @case('po')
                            <div class="rr-muted" title="The term has no Billing Target yet: the project PO due date is used">PO due date · estimate</div>
                            @break
                        @default
                            <div class="rr-muted">no Billing Target yet</div>
                    @endswitch
                    @if($overdueDays > 0)<div class="rr-muted text-danger">{{ $overdueDays }} day(s) overdue</div>@endif
                    @if($lateTarget > 0)<div class="rr-muted" style="color:#b45309">{{ $lateTarget }} day(s) past target, not billed yet</div>@endif
                </td>
                <td class="text-center"><span class="rr-tag {{ $r['kind'] === 'invoice' ? 'certain' : 'estimate' }}">{{ $r['kind'] === 'invoice' ? 'Confirmed' : 'Estimate' }}</span> <span class="status-badge" style="{{ $positionStyles[$r['position']] ?? '' }}">{{ \App\Support\ReceivableReport::POSITIONS_EN[$r['position']] }}</span></td>
                <td class="rr-num">{{ $money($r['amount']) }}</td>
            </tr>
        @endforeach
    </tbody>
@empty
    <tbody><tr><td colspan="7"><div class="rr-empty">No receivables or terms match the filters.</div></td></tr></tbody>
@endforelse

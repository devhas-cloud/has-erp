<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectBillingTerm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Piutang Customer (Finance, piutang Tahap C) — semua angka diturunkan
 * dari dokumen (invoice, penerimaan, timeline termin project).
 *
 *  - Piutang = invoice Approved yang belum dibayar (nilai termasuk PPN).
 *    Umurnya dihitung dari jatuh tempo invoice.
 *  - Termin yang belum menjadi piutang diukur terhadap PO due date project:
 *    perkiraan tanggal = target tanggal termin, bila kosong = PO due date;
 *    "terlambat ditagih" bila tanggal itu sudah lewat tetapi termin belum diinvoice.
 *    Nilainya sebelum PPN (nilai kontrak x persen) karena PPN baru pasti saat invoice dibuat.
 */
class ReceivableReport
{
    public const AGING_BUCKETS = PayableReport::AGING_BUCKETS;

    /** Posisi termin pada timeline penagihan project. */
    public const POSITIONS = [
        'unscheduled' => 'Belum dijadwalkan',
        'locked' => 'Terkunci',
        'ready' => 'Siap ditagih',
        'in_process' => 'Dalam proses',
        'receivable' => 'Piutang',
        'paid' => 'Lunas',
    ];

    private Carbon $today;

    private ?Collection $projects = null;

    public function __construct(?Carbon $today = null)
    {
        $this->today = ($today ?? now())->copy()->startOfDay();
    }

    public function today(): Carbon
    {
        return $this->today->copy();
    }

    /** Invoice Approved & Unpaid (piutang aktif), urut jatuh tempo. */
    public function openInvoices(): Collection
    {
        return Invoice::with(['project.company', 'billingTerm', 'receipts'])
            ->where('approval_status', Invoice::APPROVAL_APPROVED)
            ->where('status', Invoice::STATUS_UNPAID)
            ->orderBy('due_date')
            ->get();
    }

    public function daysOverdue(Invoice $invoice): int
    {
        return $invoice->due_date && $invoice->due_date->lt($this->today) ? (int) $invoice->due_date->diffInDays($this->today) : 0;
    }

    public function agingBucket(Invoice $invoice): string
    {
        $days = $this->daysOverdue($invoice);

        return match (true) {
            $days <= 0 => 'current',
            $days <= 30 => 'd1_30',
            $days <= 60 => 'd31_60',
            $days <= 90 => 'd61_90',
            default => 'd90',
        };
    }

    /** Project yang punya timeline termin atau invoice (selain project batal). */
    public function projects(): Collection
    {
        return $this->projects ??= Project::with(['company', 'quotation', 'billingTerms.invoices', 'invoices'])
            ->where('status', '!=', Project::STATUS_CANCELLED)
            ->where(fn ($q) => $q->has('billingTerms')->orHas('invoices'))
            ->orderBy('project_number')
            ->get();
    }

    /**
     * Posisi termin: tanpa invoice aktif -> Terkunci / Siap ditagih; invoice
     * Draft/Waiting/Rejected -> Dalam proses; Approved -> Piutang / Lunas.
     */
    public function termPosition(ProjectBillingTerm $term): string
    {
        $invoice = $term->activeInvoice();

        return match (true) {
            ! $invoice => $term->isUnlocked() ? 'ready' : 'locked',
            $invoice->status === Invoice::STATUS_PAID => 'paid',
            $invoice->isApproved() => 'receivable',
            default => 'in_process',
        };
    }

    /** Nilai termin sebelum PPN: dari invoice bila sudah ada, selain itu nilai kontrak x persen. */
    public function termAmount(Project $project, ProjectBillingTerm $term): float
    {
        $invoice = $term->activeInvoice();

        return $invoice
            ? round((float) $invoice->amount_payment, 2)
            : round($project->billingBaseAmount() * $term->percent / 100, 2);
    }

    /** Perkiraan tanggal termin belum ditagih: target tanggal, bila kosong PO due date. */
    public function termExpectedDate(Project $project, ProjectBillingTerm $term): ?Carbon
    {
        return ($term->target_date ?? $project->po_due_date)?->copy()->startOfDay();
    }

    /** Termin yang belum diinvoice (Terkunci / Siap ditagih) dan perkiraan tanggalnya sudah lewat. */
    public function isLateBilling(Project $project, ProjectBillingTerm $term): bool
    {
        $date = $this->termExpectedDate($project, $term);

        return in_array($this->termPosition($term), ['locked', 'ready'], true) && $date && $date->lt($this->today);
    }

    /**
     * Ringkasan per customer: sudah ditagih / diterima / sisa piutang (termasuk
     * PPN), terlambat, jatuh tempo terdekat, dan termin belum ditagih (sebelum PPN).
     */
    public function customerSummary(): Collection
    {
        $open = $this->openInvoices();
        $billed = Invoice::with('project')
            ->where('approval_status', Invoice::APPROVAL_APPROVED)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->get();

        $unbilled = [];
        foreach ($this->projects() as $project) {
            foreach ($project->billingTerms as $term) {
                if (in_array($this->termPosition($term), ['locked', 'ready', 'in_process'], true)) {
                    $unbilled[$project->company_id] = ($unbilled[$project->company_id] ?? 0) + $this->termAmount($project, $term);
                }
            }
        }

        $companyIds = $billed->pluck('project.company_id')->merge(array_keys($unbilled))->filter()->unique();
        $companies = $this->projects()->pluck('company')->merge($billed->pluck('project.company'))->filter()->unique('id')->keyBy('id');

        return $companyIds->map(function ($companyId) use ($open, $billed, $unbilled, $companies) {
            $mine = $open->filter(fn (Invoice $i) => (int) $i->project?->company_id === (int) $companyId);
            $overdue = $mine->filter(fn (Invoice $i) => $this->daysOverdue($i) > 0);
            $billedMine = $billed->filter(fn (Invoice $i) => (int) $i->project?->company_id === (int) $companyId);

            return [
                'company' => $companies->get($companyId),
                'billed' => round($billedMine->sum('total_payment'), 2),
                'received' => round($billedMine->where('status', Invoice::STATUS_PAID)->sum('total_payment'), 2),
                'outstanding' => round($mine->sum('total_payment'), 2),
                'overdue' => round($overdue->sum('total_payment'), 2),
                'overdue_count' => $overdue->count(),
                'open_count' => $mine->count(),
                'next_due' => $mine->sortBy('due_date')->first()?->due_date,
                'unbilled' => round($unbilled[$companyId] ?? 0, 2),
            ];
        })->filter(fn ($row) => $row['company'])
            ->sortBy(fn ($row) => [-$row['overdue'], -$row['outstanding']])
            ->values();
    }

    /** Umur piutang per customer: [company, buckets => [key => nilai], total]. */
    public function aging(): Collection
    {
        return $this->openInvoices()->groupBy(fn (Invoice $i) => $i->project?->company_id)->map(function (Collection $invoices) {
            $buckets = array_fill_keys(array_keys(self::AGING_BUCKETS), 0.0);
            foreach ($invoices as $invoice) {
                $buckets[$this->agingBucket($invoice)] += (float) $invoice->total_payment;
            }

            return [
                'company' => $invoices->first()->project?->company,
                'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
                'total' => round(array_sum($buckets), 2),
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Proyeksi penerimaan per minggu. Baris pasti = invoice piutang (menurut
     * jatuh tempo, termasuk PPN); baris estimasi = termin Terkunci / Siap ditagih /
     * Dalam proses (menurut perkiraan tanggal, sebelum PPN; invoice dalam proses
     * memakai jatuh tempo & total invoicenya).
     */
    public function forecast(): Collection
    {
        $items = collect();

        foreach ($this->openInvoices() as $invoice) {
            $items->push([
                'kind' => 'invoice',
                'date' => $invoice->due_date?->copy()->startOfDay(),
                'amount' => (float) $invoice->total_payment,
                'invoice' => $invoice,
                'project' => $invoice->project,
                'term' => $invoice->billingTerm,
                'position' => 'receivable',
            ]);
        }

        foreach ($this->projects() as $project) {
            foreach ($project->billingTerms as $term) {
                $position = $this->termPosition($term);
                if (! in_array($position, ['locked', 'ready', 'in_process'], true)) {
                    continue;
                }
                $invoice = $position === 'in_process' ? $term->activeInvoice() : null;
                $items->push([
                    'kind' => 'estimate',
                    'date' => $invoice?->due_date?->copy()->startOfDay() ?? $this->termExpectedDate($project, $term),
                    'amount' => $invoice ? (float) $invoice->total_payment : $this->termAmount($project, $term),
                    'invoice' => $invoice,
                    'project' => $project,
                    'term' => $term,
                    'position' => $position,
                ]);
            }
        }

        $weekStart = $this->today->copy()->startOfWeek();

        return $items->groupBy(function (array $item) use ($weekStart) {
            if (! $item['date']) {
                return '9_nodate';
            }
            if ($item['date']->lt($this->today)) {
                return $item['kind'] === 'invoice' ? '0_overdue' : '0_late_billing';
            }
            $weeks = (int) floor($weekStart->diffInDays($item['date']) / 7);

            return $weeks >= 8 ? '8_later' : '1_w'.$weeks;
        })->sortKeys()->map(function (Collection $rows, string $key) use ($weekStart) {
            $label = match (true) {
                $key === '0_overdue' => 'Invoice lewat jatuh tempo',
                $key === '0_late_billing' => 'Terlambat ditagih (lewat target / PO due date)',
                $key === '8_later' => 'Lebih dari 8 minggu',
                $key === '9_nodate' => 'Tanpa tanggal',
                default => (function () use ($key, $weekStart) {
                    $w = (int) substr($key, 3);
                    $from = $weekStart->copy()->addWeeks($w);

                    return ($w === 0 ? 'Minggu ini' : ($w === 1 ? 'Minggu depan' : 'Minggu +'.$w)).' ('.$from->format('d/m').'–'.$from->copy()->addDays(6)->format('d/m').')';
                })(),
            };
            $rows = $rows->sortBy(fn ($r) => $r['date']?->timestamp ?? PHP_INT_MAX)->values();

            return [
                'key' => $key,
                'label' => $label,
                'rows' => $rows,
                'certain' => round($rows->where('kind', 'invoice')->sum('amount'), 2),
                'estimate' => round($rows->where('kind', 'estimate')->sum('amount'), 2),
            ];
        })->values();
    }

    /**
     * Piutang per project (sebelum PPN): nilai kontrak dibagi menurut posisi
     * termin — Belum dijadwalkan / Terkunci / Siap ditagih / Dalam proses /
     * Piutang / Lunas — ditambah tagihan di luar timeline dan status PO due date.
     */
    public function projectSummary(): Collection
    {
        return $this->projects()->map(function (Project $project) {
            $base = $project->billingBaseAmount();
            $positions = array_fill_keys(array_keys(self::POSITIONS), 0.0);
            $lateBilling = 0;

            foreach ($project->billingTerms as $term) {
                $positions[$this->termPosition($term)] += $this->termAmount($project, $term);
                if ($this->isLateBilling($project, $term)) {
                    $lateBilling++;
                }
            }

            $scheduledPercent = (float) $project->billingTerms->sum('percent');
            $positions['unscheduled'] = $scheduledPercent < 100 ? round($base * (100 - $scheduledPercent) / 100, 2) : 0.0;

            $extra = $project->invoices->whereNull('billing_term_id')->where('status', '!=', Invoice::STATUS_CANCELLED);
            $dueDays = $project->po_due_date ? (int) $this->today->diffInDays($project->po_due_date->copy()->startOfDay(), false) : null;

            return [
                'project' => $project,
                'contract' => round($base, 2),
                'scheduled_percent' => round($scheduledPercent, 2),
                'positions' => array_map(fn ($v) => round($v, 2), $positions),
                'outside_timeline' => round($extra->sum('amount_payment'), 2),
                'outside_timeline_count' => $extra->count(),
                'late_billing' => $lateBilling,
                'po_due_date' => $project->po_due_date,
                'due_days' => $dueDays,
            ];
        })->sortBy(fn ($row) => [-$row['late_billing'], -$row['positions']['receivable']])->values();
    }

    /** Angka utama di atas laporan. */
    public function headline(): array
    {
        $open = $this->openInvoices();
        $ready = 0.0;
        $late = 0;
        foreach ($this->projects() as $project) {
            foreach ($project->billingTerms as $term) {
                if ($this->termPosition($term) === 'ready') {
                    $ready += $this->termAmount($project, $term);
                }
                if ($this->isLateBilling($project, $term)) {
                    $late++;
                }
            }
        }

        return [
            'outstanding' => round($open->sum('total_payment'), 2),
            'open_count' => $open->count(),
            'overdue' => round($open->filter(fn ($i) => $this->daysOverdue($i) > 0)->sum('total_payment'), 2),
            'overdue_count' => $open->filter(fn ($i) => $this->daysOverdue($i) > 0)->count(),
            'due_week' => round($open->filter(fn ($i) => $this->daysOverdue($i) === 0 && $i->due_date && $i->due_date->lte($this->today->copy()->endOfWeek()))->sum('total_payment'), 2),
            'ready' => round($ready, 2),
            'late_billing' => $late,
        ];
    }
}

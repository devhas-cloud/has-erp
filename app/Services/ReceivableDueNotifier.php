<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Services\Concerns\SendsWhatsAppDigest;
use App\Support\Number;
use App\Support\ReceivableReport;
use Illuminate\Support\Collection;

/**
 * Pengingat piutang customer via WhatsApp (piutang Tahap C). Satu ringkasan
 * per hari: invoice lewat jatuh tempo, jatuh tempo hari ini, jatuh tempo dalam
 * N hari, dan termin yang terlambat ditagih (lewat target / PO due date tetapi
 * belum diinvoice). Tujuan: grup WA Finance dan/atau nomor pribadi user
 * Penerimaan Customer. Pengiriman terjadwal tidak dobel di hari yang sama.
 */
class ReceivableDueNotifier
{
    use SendsWhatsAppDigest;

    public const SETTING_KEY = 'finance.receivable_reminder';

    public const DEFAULTS = [
        'enabled' => false,
        'days_ahead' => 3,
        'send_time' => '08:10',
        'whatsapp_group_id' => null,
        'notify_receipt_users' => true,
        'last_sent_date' => null,
    ];

    /** Maksimal baris yang dirinci per kelompok agar pesan tetap ringkas. */
    private const MAX_LINES = 10;

    public function __construct(private WhatsAppService $whatsapp) {}

    /**
     * @return array{overdue: Collection, today: Collection, upcoming: Collection, late_billing: Collection}
     */
    public function dueItems(int $daysAhead): array
    {
        $report = new ReceivableReport;
        $today = $report->today();
        $limit = $today->copy()->addDays($daysAhead);
        $open = $report->openInvoices()->filter(fn (Invoice $i) => $i->due_date);

        $late = collect();
        foreach ($report->projects() as $project) {
            foreach ($project->billingTerms as $term) {
                if ($report->isLateBilling($project, $term)) {
                    $late->push(['project' => $project, 'term' => $term, 'date' => $report->termExpectedDate($project, $term), 'amount' => $report->termAmount($project, $term)]);
                }
            }
        }

        return [
            'overdue' => $open->filter(fn (Invoice $i) => $i->due_date->lt($today))->sortBy('due_date')->values(),
            'today' => $open->filter(fn (Invoice $i) => $i->due_date->isSameDay($today))->values(),
            'upcoming' => $open->filter(fn (Invoice $i) => $i->due_date->gt($today) && $i->due_date->lte($limit))->sortBy('due_date')->values(),
            'late_billing' => $late->sortBy(fn ($row) => $row['date']->timestamp)->values(),
        ];
    }

    public function buildMessage(?int $daysAhead = null): ?string
    {
        $daysAhead ??= (int) self::settings()['days_ahead'];
        $groups = $this->dueItems($daysAhead);
        if (collect($groups)->every(fn (Collection $items) => $items->isEmpty())) {
            return null;
        }

        $report = new ReceivableReport;
        $money = fn (float $v) => Number::money($v);

        $lines = ['*Pengingat Piutang Customer* — '.now()->format('d/m/Y'), ''];
        $sections = [
            'overdue' => '🔴 *Invoice lewat jatuh tempo*',
            'today' => '🟠 *Jatuh tempo hari ini*',
            'upcoming' => '🟡 *Jatuh tempo '.$daysAhead.' hari ke depan*',
        ];

        foreach ($sections as $key => $title) {
            $invoices = $groups[$key];
            if ($invoices->isEmpty()) {
                continue;
            }
            $lines[] = $title.' — '.$invoices->count().' invoice, Rp '.$money($invoices->sum('total_payment'));
            foreach ($invoices->take(self::MAX_LINES) as $invoice) {
                $when = $key === 'overdue'
                    ? 'lewat '.$report->daysOverdue($invoice).' hari'
                    : ($key === 'today' ? 'hari ini' : $invoice->due_date->format('d/m'));
                $lines[] = '• '.($invoice->project?->company?->account_name ?? '—').' · '.$invoice->invoice_number.' · Rp '.$money($invoice->total_payment).' ('.$when.')';
            }
            if ($invoices->count() > self::MAX_LINES) {
                $lines[] = '• … dan '.($invoices->count() - self::MAX_LINES).' invoice lain';
            }
            $lines[] = '';
        }

        if ($groups['late_billing']->isNotEmpty()) {
            $late = $groups['late_billing'];
            $lines[] = '⚪ *Terlambat ditagih* — '.$late->count().' termin, Rp '.$money($late->sum('amount')).' (sebelum PPN)';
            foreach ($late->take(self::MAX_LINES) as $row) {
                $lines[] = '• '.$row['project']->project_number.' · '.$row['term']->name.' '.Number::percent($row['term']->percent).'% · target '.$row['date']->format('d/m/Y');
            }
            if ($late->count() > self::MAX_LINES) {
                $lines[] = '• … dan '.($late->count() - self::MAX_LINES).' termin lain';
            }
            $lines[] = '';
        }

        $lines[] = 'Detail: '.route('receivable-report.index', ['tab' => 'forecast']);

        return implode("\n", $lines);
    }

    /**
     * Penerima pribadi: user dengan akses buat/approve Penerimaan Customer yang punya nomor WhatsApp.
     */
    public function receiptUsers(): Collection
    {
        $ids = UserAccessControl::whereHas('module', fn ($q) => $q->where('route_name', 'customer-receipt'))
            ->where(fn ($q) => $q->where('can_create', true)->orWhere('can_approve', true))
            ->pluck('user_id');

        return User::whereIn('id', $ids)->whereNotNull('phone_number')->where('phone_number', '!=', '')->get();
    }

    public function personalRecipients(): Collection
    {
        return $this->receiptUsers();
    }

    public static function personalFlag(): string
    {
        return 'notify_receipt_users';
    }
}

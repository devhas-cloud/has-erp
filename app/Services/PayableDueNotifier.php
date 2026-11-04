<?php

namespace App\Services;

use App\Models\SupplierBill;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Services\Concerns\SendsWhatsAppDigest;
use App\Support\PayableReport;
use Illuminate\Support\Collection;

/**
 * Pengingat jatuh tempo hutang supplier via WhatsApp (Finance Tahap 6).
 * Satu ringkasan per hari (bukan per tagihan): tagihan terlambat, jatuh tempo
 * hari ini, dan jatuh tempo dalam N hari. Tujuan: grup WA Finance dan/atau
 * nomor pribadi user yang menangani Pembayaran Supplier. Pengiriman terjadwal
 * tidak dikirim dua kali di hari yang sama (last_sent_date).
 */
class PayableDueNotifier
{
    use SendsWhatsAppDigest;

    public const SETTING_KEY = 'finance.due_reminder';

    public const DEFAULTS = [
        'enabled' => false,
        'days_ahead' => 3,
        'send_time' => '08:05',
        'whatsapp_group_id' => null,
        'notify_payment_users' => true,
        'last_sent_date' => null,
    ];

    /** Maksimal tagihan yang dirinci per kelompok agar pesan tetap ringkas. */
    private const MAX_LINES = 10;

    public function __construct(private WhatsAppService $whatsapp) {}

    /**
     * Tagihan terbuka yang perlu diingatkan: [overdue, today, upcoming].
     *
     * @return array<string, Collection>
     */
    public function dueBills(int $daysAhead): array
    {
        $report = new PayableReport;
        $today = now()->startOfDay();
        $limit = $today->copy()->addDays($daysAhead);
        $open = $report->openBills();

        return [
            'overdue' => $open->filter(fn (SupplierBill $b) => $b->due_date->lt($today))->sortBy('due_date')->values(),
            'today' => $open->filter(fn (SupplierBill $b) => $b->due_date->isSameDay($today))->values(),
            'upcoming' => $open->filter(fn (SupplierBill $b) => $b->due_date->gt($today) && $b->due_date->lte($limit))->sortBy('due_date')->values(),
        ];
    }

    public function buildMessage(?int $daysAhead = null): ?string
    {
        $daysAhead ??= (int) self::settings()['days_ahead'];
        $groups = $this->dueBills($daysAhead);
        if ($groups['overdue']->isEmpty() && $groups['today']->isEmpty() && $groups['upcoming']->isEmpty()) {
            return null;
        }

        $report = new PayableReport;
        $base = $report->baseCurrency();
        $money = fn (float $v) => number_format($v, 0, ',', '.');
        $sum = fn (Collection $bills) => $bills->sum(fn (SupplierBill $b) => $b->outstandingBase());

        $lines = ['*Pengingat Hutang Supplier* — '.now()->format('d/m/Y'), ''];
        $sections = [
            'overdue' => '🔴 *Terlambat*',
            'today' => '🟠 *Jatuh tempo hari ini*',
            'upcoming' => '🟡 *Jatuh tempo '.$daysAhead.' hari ke depan*',
        ];

        foreach ($sections as $key => $title) {
            $bills = $groups[$key];
            if ($bills->isEmpty()) {
                continue;
            }
            $lines[] = $title.' — '.$bills->count().' tagihan, '.$base.' '.$money($sum($bills));
            foreach ($bills->take(self::MAX_LINES) as $bill) {
                $when = $key === 'overdue'
                    ? 'lewat '.$report->daysOverdue($bill).' hari'
                    : ($key === 'today' ? 'hari ini' : $bill->due_date->format('d/m'));
                $lines[] = '• '.$bill->supplier?->name.' · '.$bill->bill_number.' · '.$bill->currency.' '.$money($bill->outstanding()).' ('.$when.')';
            }
            if ($bills->count() > self::MAX_LINES) {
                $lines[] = '• … dan '.($bills->count() - self::MAX_LINES).' tagihan lain';
            }
            $lines[] = '';
        }

        $lines[] = 'Detail: '.route('payable-report.index', ['tab' => 'schedule']);

        return implode("\n", $lines);
    }

    /**
     * Penerima pribadi: user (selain non-aktif) dengan akses buat/approve
     * Pembayaran Supplier yang punya nomor WhatsApp.
     */
    public function paymentUsers(): Collection
    {
        $ids = UserAccessControl::whereHas('module', fn ($q) => $q->where('route_name', 'supplier-payment'))
            ->where(fn ($q) => $q->where('can_create', true)->orWhere('can_approve', true))
            ->pluck('user_id');

        return User::whereIn('id', $ids)->whereNotNull('phone_number')->where('phone_number', '!=', '')->get();
    }

    public function personalRecipients(): Collection
    {
        return $this->paymentUsers();
    }

    public static function personalFlag(): string
    {
        return 'notify_payment_users';
    }
}

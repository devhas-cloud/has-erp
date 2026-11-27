<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ItemLoan;
use App\Models\WhatsAppGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Pengingat WhatsApp jatuh tempo peminjaman barang (Item Loan), sekali sehari
 * pada / setelah jam kirim:
 *  - grup inventory: rekap pinjaman lewat jatuh tempo, jatuh tempo hari ini,
 *    dan jatuh tempo dalam N hari (termasuk nomor peminjam luar);
 *  - peminjam (internal maupun luar yang punya nomor): pesan pribadi bila
 *    pinjamannya jatuh tempo dalam N hari atau sudah lewat. Tiap pinjaman
 *    paling banyak satu pesan per hari (last_reminded_on).
 */
class ItemLoanDueNotifier
{
    public const SETTING_KEY = 'inventory.loan_reminder';

    public const DEFAULTS = [
        'enabled' => false,
        'days_ahead' => 1,
        'send_time' => '08:10',
        'whatsapp_group_id' => null,
        'notify_borrowers' => true,
        'last_sent_date' => null,
    ];

    private const MAX_LINES = 15;

    public function __construct(private WhatsAppService $whatsapp) {}

    public static function settings(): array
    {
        return AppSetting::get(self::SETTING_KEY, self::DEFAULTS);
    }

    /**
     * Pinjaman aktif yang perlu diingatkan: [overdue, today, upcoming].
     *
     * @return array<string, Collection>
     */
    public function dueLoans(int $daysAhead): array
    {
        $today = now()->startOfDay();
        $loans = ItemLoan::with(['borrowerUser', 'items' => fn ($q) => $q->whereNull('return_status')->with(['masterProduct:id,code', 'stock:id,serial_number'])])
            ->where('status', ItemLoan::STATUS_ON_LOAN)
            ->whereDate('due_date', '<=', $today->copy()->addDays($daysAhead)->toDateString())
            ->orderBy('due_date')
            ->get();

        return [
            'overdue' => $loans->filter(fn (ItemLoan $l) => $l->due_date->lt($today))->values(),
            'today' => $loans->filter(fn (ItemLoan $l) => $l->due_date->isSameDay($today))->values(),
            'upcoming' => $loans->filter(fn (ItemLoan $l) => $l->due_date->gt($today))->values(),
        ];
    }

    /** Rekap untuk grup inventory (null bila tidak ada yang perlu diingatkan). */
    public function buildDigest(?int $daysAhead = null): ?string
    {
        $daysAhead ??= (int) self::settings()['days_ahead'];
        $groups = $this->dueLoans($daysAhead);
        if ($groups['overdue']->isEmpty() && $groups['today']->isEmpty() && $groups['upcoming']->isEmpty()) {
            return null;
        }

        $lines = ['*Pengingat Peminjaman Barang* — '.now()->format('d/m/Y'), ''];
        $sections = [
            'overdue' => '*Lewat jatuh tempo*',
            'today' => '*Kembali hari ini*',
            'upcoming' => '*Kembali dalam '.$daysAhead.' hari*',
        ];
        foreach ($sections as $key => $title) {
            $loans = $groups[$key];
            if ($loans->isEmpty()) {
                continue;
            }
            $lines[] = $title.' — '.$loans->count().' pinjaman';
            foreach ($loans->take(self::MAX_LINES) as $loan) {
                $when = $key === 'overdue' ? 'lewat '.abs($loan->daysToDue()).' hari' : ($key === 'today' ? 'hari ini' : $loan->due_date->format('d/m'));
                $phone = $loan->isExternal() && $loan->borrower_phone ? ' · '.$loan->borrower_phone : '';
                $lines[] = '• '.$loan->loan_number.' · '.$loan->borrowerLabel().($loan->isExternal() && $loan->borrower_company ? ' ('.$loan->borrower_company.')' : '')
                    .$phone.' · '.$loan->items->count().' unit ('.$when.')';
            }
            if ($loans->count() > self::MAX_LINES) {
                $lines[] = '• … dan '.($loans->count() - self::MAX_LINES).' pinjaman lain';
            }
            $lines[] = '';
        }
        $lines[] = 'Detail: '.route('item-loan.index');

        return implode("\n", $lines);
    }

    /** Pesan pribadi ke peminjam. */
    public function borrowerMessage(ItemLoan $loan): string
    {
        $days = $loan->daysToDue();
        $when = $days < 0 ? 'sudah lewat '.abs($days).' hari dari tanggal kembali' : ($days === 0 ? 'harus dikembalikan hari ini' : 'harus dikembalikan '.$loan->due_date->format('d/m/Y'));
        $units = $loan->items->map(fn ($i) => '• '.($i->masterProduct?->code ?? 'Barang').' · SN '.($i->stock?->serial_number ?? '—'))->implode("\n");

        return 'Halo '.$loan->borrowerLabel().",\n\n"
            .'Barang pinjaman '.$loan->loan_number.' '.$when.":\n".$units."\n\n"
            .'Mohon dikembalikan ke gudang atau hubungi tim inventory bila perlu perpanjangan. Terima kasih.';
    }

    /**
     * @return array{status: string, sent: int, failed: int, message: ?string}
     */
    public function send(bool $force = false): array
    {
        $settings = self::settings();
        if (! $force && ! $settings['enabled']) {
            return ['status' => 'disabled', 'sent' => 0, 'failed' => 0, 'message' => null];
        }
        if (! $force && $settings['last_sent_date'] === now()->toDateString()) {
            return ['status' => 'already_sent', 'sent' => 0, 'failed' => 0, 'message' => null];
        }
        if (! $force && now()->format('H:i') < $settings['send_time']) {
            return ['status' => 'not_yet', 'sent' => 0, 'failed' => 0, 'message' => null];
        }

        $daysAhead = (int) $settings['days_ahead'];
        $digest = $this->buildDigest($daysAhead);
        if ($digest === null) {
            return ['status' => 'nothing_due', 'sent' => 0, 'failed' => 0, 'message' => null];
        }
        if (! $this->whatsapp->isConfigured()) {
            return ['status' => 'not_configured', 'sent' => 0, 'failed' => 0, 'message' => $digest];
        }

        $sent = $failed = 0;
        $pause = fn () => sleep(app()->runningUnitTests() ? 0 : 3);

        $groupJid = $settings['whatsapp_group_id'] ? WhatsAppGroup::whereKey($settings['whatsapp_group_id'])->value('group_id') : null;
        if ($groupJid) {
            $this->whatsapp->sendToGroup(trim($groupJid), $digest) ? $sent++ : $failed++;
        }

        if ($settings['notify_borrowers']) {
            $today = now()->toDateString();
            foreach (collect($this->dueLoans($daysAhead))->flatten(1) as $loan) {
                $phone = $loan->borrowerPhone();
                if (! $phone || $loan->last_reminded_on?->toDateString() === $today) {
                    continue;
                }
                if ($sent + $failed > 0) {
                    $pause();
                }
                if ($this->whatsapp->sendText($phone, $this->borrowerMessage($loan))) {
                    $sent++;
                    $loan->update(['last_reminded_on' => $today]);
                } else {
                    $failed++;
                }
            }
        }

        if ($sent === 0 && $failed === 0) {
            return ['status' => 'no_recipient', 'sent' => 0, 'failed' => 0, 'message' => $digest];
        }
        if ($sent > 0) {
            AppSetting::put(self::SETTING_KEY, array_merge($settings, ['last_sent_date' => now()->toDateString()]));
        }
        Log::info('ItemLoanDueNotifier sent', ['sent' => $sent, 'failed' => $failed]);

        return ['status' => $failed > 0 ? 'partial' : 'sent', 'sent' => $sent, 'failed' => $failed, 'message' => $digest];
    }
}

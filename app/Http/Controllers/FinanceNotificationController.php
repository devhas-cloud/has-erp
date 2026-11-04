<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Log;
use App\Models\WhatsAppGroup;
use App\Services\PayableDueNotifier;
use App\Services\ReceivableDueNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Notifikasi Finance (grup Master Data): satu tempat mengatur pengingat
 * WhatsApp hutang supplier dan piutang customer, terpisah dari laporan.
 * Lihat = can_read, simpan (PATCH finance-notification/{type}) = can_update,
 * kirim sekarang (POST finance-notification/{type}/send) = can_create.
 */
class FinanceNotificationController extends Controller
{
    /**
     * Jenis pengingat: notifier, label, penerima pribadi, tautan laporan.
     */
    private function types(): array
    {
        return [
            'payable' => [
                'notifier' => PayableDueNotifier::class,
                'title' => 'Pengingat Hutang Supplier',
                'description' => 'Tagihan supplier yang terlambat, jatuh tempo hari ini, dan yang akan jatuh tempo.',
                'recipient_label' => 'user Pembayaran Supplier',
                'report_route' => 'payable-report.index',
                'report_label' => 'Laporan Hutang',
            ],
            'receivable' => [
                'notifier' => ReceivableDueNotifier::class,
                'title' => 'Pengingat Piutang Customer',
                'description' => 'Invoice customer yang lewat jatuh tempo, jatuh tempo hari ini, yang akan jatuh tempo, dan termin yang terlambat ditagih.',
                'recipient_label' => 'user Penerimaan Customer',
                'report_route' => 'receivable-report.index',
                'report_label' => 'Laporan Piutang',
            ],
        ];
    }

    public function index()
    {
        $cards = collect($this->types())->map(function (array $type, string $key) {
            $notifier = app($type['notifier']);

            return $type + [
                'key' => $key,
                'settings' => $type['notifier']::settings(),
                'flag' => $type['notifier']::personalFlag(),
                'recipients' => $notifier->personalRecipients(),
                'preview' => $notifier->buildMessage(),
            ];
        });

        return view('finance-notification.index', [
            'cards' => $cards,
            'waGroups' => WhatsAppGroup::where('status', 'Active')->orderBy('group_name')->get(['id', 'group_name']),
        ]);
    }

    public function update(Request $request, string $type): JsonResponse
    {
        $config = $this->types()[$type];
        $notifier = $config['notifier'];
        $flag = $notifier::personalFlag();

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'days_ahead' => 'required|integer|min:0|max:30',
            'send_time' => 'required|date_format:H:i',
            'whatsapp_group_id' => 'nullable|integer|exists:whatsapp_groups,id',
            $flag => 'required|boolean',
        ], [
            'send_time.date_format' => 'Jam kirim harus berformat JJ:MM, contoh 08:05.',
        ]);

        if ($validated['enabled'] && ! $validated['whatsapp_group_id'] && ! $validated[$flag]) {
            return response()->json(['success' => false, 'message' => 'Pilih minimal satu tujuan: grup WhatsApp atau '.$config['recipient_label'].'.'], 422);
        }

        AppSetting::put($notifier::SETTING_KEY, array_merge($notifier::settings(), [
            'enabled' => (bool) $validated['enabled'],
            'days_ahead' => (int) $validated['days_ahead'],
            'send_time' => $validated['send_time'],
            'whatsapp_group_id' => $validated['whatsapp_group_id'] ?? null,
            $flag => (bool) $validated[$flag],
        ]));

        Log::record(
            'update_finance_notification',
            $config['title'].' diubah oleh '.Auth::user()->display_name.' ('.($validated['enabled'] ? 'aktif, jam '.$validated['send_time'] : 'nonaktif').')',
            'MOD_FINANCE_NOTIFICATION'
        );

        return response()->json([
            'success' => true,
            'message' => $config['title'].' disimpan.',
            'preview' => app($notifier)->buildMessage((int) $validated['days_ahead']),
        ]);
    }

    public function send(string $type): JsonResponse
    {
        $config = $this->types()[$type];
        $result = app($config['notifier'])->send(true);

        $message = match ($result['status']) {
            'sent' => 'Pengingat terkirim ke '.$result['sent'].' tujuan.',
            'partial' => 'Terkirim '.$result['sent'].', gagal '.$result['failed'].'. Cek log WhatsApp.',
            'nothing_due' => 'Tidak ada yang perlu diingatkan saat ini — tidak ada yang dikirim.',
            'not_configured' => 'Gateway WhatsApp belum dikonfigurasi di server.',
            'no_recipient' => 'Belum ada tujuan: pilih grup WhatsApp atau pastikan '.$config['recipient_label'].' punya nomor WA.',
            default => 'Tidak terkirim.',
        };

        return response()->json(
            ['success' => in_array($result['status'], ['sent', 'partial'], true), 'message' => $message],
            in_array($result['status'], ['sent', 'partial', 'nothing_due'], true) ? 200 : 422
        );
    }
}

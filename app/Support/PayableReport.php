<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\DirectPurchase;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\SupplierRefund;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Hutang Supplier (Finance Tahap 5) — semua angka diturunkan dari
 * dokumen (tagihan terverifikasi, pembayaran & refund Posted, PO, kartu stok),
 * tidak ada angka yang disimpan terpisah. Hutang valas dinilai dengan kurs
 * tagihan (nilai buku, kurs tanggal invoice supplier); kurs master terbaru hanya
 * dipakai untuk nilai kini & selisih kurs belum terealisasi, serta komitmen PO
 * yang belum ditagih.
 */
class PayableReport
{
    public const AGING_BUCKETS = [
        'current' => 'Belum jatuh tempo',
        'd1_30' => '1–30 hari',
        'd31_60' => '31–60 hari',
        'd61_90' => '61–90 hari',
        'd90' => '> 90 hari',
    ];

    private Carbon $today;

    private array $rates = [];

    public function __construct(?Carbon $today = null)
    {
        $this->today = ($today ?? now())->copy()->startOfDay();
    }

    public function baseCurrency(): string
    {
        return Currency::baseName();
    }

    public function toBase(float $amount, ?string $currency): float
    {
        $code = strtoupper((string) $currency);
        $this->rates[$code] ??= Currency::rateOf($code);

        return round($amount * $this->rates[$code], 2);
    }

    /** Tagihan terverifikasi yang masih punya sisa hutang. */
    public function openBills(?int $supplierId = null): Collection
    {
        return SupplierBill::with(['supplier', 'purchaseOrder', 'directPurchase', 'allocations.payment'])
            ->whereIn('status', SupplierBill::OPEN_STATUSES)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderBy('due_date')
            ->get()
            ->filter(fn (SupplierBill $bill) => $bill->outstanding() > 0)
            ->values();
    }

    public function daysOverdue(SupplierBill $bill): int
    {
        return $bill->due_date && $bill->due_date->lt($this->today) ? (int) $bill->due_date->diffInDays($this->today) : 0;
    }

    public function agingBucket(SupplierBill $bill): string
    {
        $days = $this->daysOverdue($bill);

        return match (true) {
            $days <= 0 => 'current',
            $days <= 30 => 'd1_30',
            $days <= 60 => 'd31_60',
            $days <= 90 => 'd61_90',
            default => 'd90',
        };
    }

    /**
     * Ringkasan per supplier (dalam mata uang dasar): nilai PO aktif, kas keluar
     * bersih (pembayaran - refund), uang muka belum terpakai, sisa hutang,
     * terlambat, jatuh tempo terdekat.
     */
    public function supplierSummary(): Collection
    {
        $openBills = $this->openBills()->groupBy('supplier_id');

        $pos = PurchaseOrder::with(['supplier', 'items', 'supplierBills.allocations.payment', 'supplierRefunds'])
            ->whereNotNull('supplier_id')
            ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED])
            ->get()->groupBy('supplier_id');

        $payments = SupplierPayment::where('status', SupplierPayment::STATUS_POSTED)->get()->groupBy('supplier_id');
        $refunds = SupplierRefund::where('status', SupplierRefund::STATUS_POSTED)->get()->groupBy('supplier_id');

        $supplierIds = collect([$openBills->keys(), $pos->keys(), $payments->keys()])->flatten()->unique();

        return Supplier::whereIn('id', $supplierIds)->orderBy('name')->get()->map(function (Supplier $supplier) use ($openBills, $pos, $payments, $refunds) {
            $bills = $openBills->get($supplier->id, collect());
            $supplierPos = $pos->get($supplier->id, collect());
            $outstanding = $bills->sum(fn (SupplierBill $b) => $b->outstandingBase());
            $current = $bills->sum(fn (SupplierBill $b) => $this->toBase($b->outstanding(), $b->currency));
            $overdueBills = $bills->filter(fn (SupplierBill $b) => $this->daysOverdue($b) > 0);

            return [
                'supplier' => $supplier,
                'po_value' => round($supplierPos->where('status', '!=', PurchaseOrder::STATUS_CANCELLED)->sum(fn (PurchaseOrder $po) => $this->toBase($po->netTotal(), $po->currencyCode())), 2),
                'paid' => round($payments->get($supplier->id, collect())->sum('amount_base') - $refunds->get($supplier->id, collect())->sum('amount_base'), 2),
                'advance_balance' => round($supplierPos->sum(fn (PurchaseOrder $po) => $this->toBase($po->advanceBalance(), $po->currencyCode())), 2),
                'outstanding' => round($outstanding, 2),
                'unrealized_fx' => round($current - $outstanding, 2),
                'overdue' => round($overdueBills->sum(fn (SupplierBill $b) => $b->outstandingBase()), 2),
                'overdue_count' => $overdueBills->count(),
                'open_count' => $bills->count(),
                'next_due' => $bills->sortBy('due_date')->first()?->due_date,
            ];
        })->filter(fn ($row) => $row['po_value'] > 0 || $row['outstanding'] > 0 || $row['paid'] != 0 || $row['advance_balance'] > 0)->values();
    }

    /** Umur hutang per supplier: [supplier, buckets => [key => nilai dasar], total]. */
    public function aging(): Collection
    {
        return $this->openBills()->groupBy('supplier_id')->map(function (Collection $bills) {
            $buckets = array_fill_keys(array_keys(self::AGING_BUCKETS), 0.0);
            foreach ($bills as $bill) {
                $buckets[$this->agingBucket($bill)] += $bill->outstandingBase();
            }

            return [
                'supplier' => $bills->first()->supplier,
                'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
                'total' => round(array_sum($buckets), 2),
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Jadwal pembayaran: tagihan terbuka dikelompokkan per minggu jatuh tempo
     * (Terlambat, Minggu ini, +1..+7 minggu, Lebih dari 8 minggu).
     */
    public function schedule(): Collection
    {
        $weekStart = $this->today->copy()->startOfWeek();

        return $this->openBills()->groupBy(function (SupplierBill $bill) use ($weekStart) {
            if ($this->daysOverdue($bill) > 0) {
                return '0_overdue';
            }
            $weeks = (int) floor($weekStart->diffInDays($bill->due_date->copy()->startOfDay()) / 7);

            return $weeks >= 8 ? '9_later' : '1_w'.$weeks;
        })->sortKeys()->map(function (Collection $bills, string $key) use ($weekStart) {
            $label = match (true) {
                $key === '0_overdue' => 'Terlambat',
                $key === '9_later' => 'Lebih dari 8 minggu',
                default => (function () use ($key, $weekStart) {
                    $w = (int) substr($key, 3);
                    $from = $weekStart->copy()->addWeeks($w);

                    return ($w === 0 ? 'Minggu ini' : ($w === 1 ? 'Minggu depan' : 'Minggu +'.$w)).' ('.$from->format('d/m').'–'.$from->copy()->addDays(6)->format('d/m').')';
                })(),
            };

            return [
                'key' => $key,
                'label' => $label,
                'bills' => $bills->values(),
                'total' => round($bills->sum(fn (SupplierBill $b) => $b->outstandingBase()), 2),
            ];
        })->values();
    }

    /**
     * Biaya pembelian per project (sebelum pajak, mata uang dasar):
     *  - komitmen  : baris PO aktif & DP disetujui untuk project (PO batal: yang sudah diterima);
     *  - diterima  : qty diterima x harga;
     *  - ditagih   : baris tagihan terverifikasi ber-project;
     *  - terbayar  : porsi baris tersebut dari bagian tagihan yang sudah diselesaikan
     *                (pembayaran + potongan uang muka + PPh dipotong);
     *  - alokasi stok: stok bebas yang dialokasikan/ditransfer ke project (harga rata-rata).
     * Pembelian untuk stok belum menjadi biaya project sampai dialokasikan.
     */
    public function projectCosts(?int $projectId = null): Collection
    {
        $rows = [];
        $row = function (int $id) use (&$rows) {
            $rows[$id] ??= ['commitment' => 0.0, 'received' => 0.0, 'billed' => 0.0, 'paid' => 0.0, 'stock_allocated' => 0.0];

            return $id;
        };

        PurchaseOrderItem::with(['purchaseOrder', 'goodsRequestItem.goodsRequest', 'goodsReceiptItems.goodsReceipt', 'returnItems'])
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED]))
            ->get()
            ->each(function (PurchaseOrderItem $item) use (&$rows, $row, $projectId) {
                $project = $item->projectId();
                if (! $project || ($projectId && $project !== $projectId)) {
                    return;
                }
                $row($project);
                $cancelled = $item->purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED;
                $qty = $cancelled ? $item->receivedQty() : (int) $item->qty;
                $rows[$project]['commitment'] += $this->toBase($qty * (float) $item->price, $item->currency);
                $rows[$project]['received'] += $this->toBase($item->receivedQty() * (float) $item->price, $item->currency);
            });

        DirectPurchase::with('items')
            ->where('status', DirectPurchase::STATUS_APPROVED)
            ->where('purpose', DirectPurchase::PURPOSE_PROJECT)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereNotNull('project_id')
            ->get()
            ->each(function (DirectPurchase $dp) use (&$rows, $row) {
                $row($dp->project_id);
                $amount = $this->toBase($dp->grandTotal(), $dp->currencyCode());
                $rows[$dp->project_id]['commitment'] += $amount;
                $rows[$dp->project_id]['received'] += $amount;
            });

        SupplierBillItem::with('bill.allocations.payment')
            ->whereNotNull('project_id')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereHas('bill', fn ($q) => $q->whereIn('status', [SupplierBill::STATUS_VERIFIED, SupplierBill::STATUS_PARTIALLY_PAID, SupplierBill::STATUS_PAID]))
            ->get()
            ->each(function (SupplierBillItem $item) use (&$rows, $row) {
                $bill = $item->bill;
                $row($item->project_id);
                $base = $bill->subtotal + $bill->other_charges;
                $settledRatio = $bill->total > 0 ? min(1, ($bill->paidAmount() + $bill->advance_deduction + $bill->pph_amount) / $bill->total) : 0;
                // Ditagih & terbayar memakai kurs tagihan (historis), bukan kurs hari ini.
                $rows[$item->project_id]['billed'] += $bill->toBook($item->amount);
                $rows[$item->project_id]['paid'] += $bill->toBook($base > 0 ? $item->amount * $settledRatio : 0);
            });

        StockMovement::whereNotNull('project_id')
            ->whereIn('type', [StockMovement::TYPE_ALLOCATE_IN, StockMovement::TYPE_TRANSFER_IN, StockMovement::TYPE_TRANSFER_OUT])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->get()
            ->each(function (StockMovement $m) use (&$rows, $row) {
                $row($m->project_id);
                $sign = $m->type === StockMovement::TYPE_TRANSFER_OUT ? -1 : 1;
                $rows[$m->project_id]['stock_allocated'] += $sign * $m->qty * (float) $m->unit_cost;
            });

        $projects = Project::with('quotation.profitEstimate')->whereIn('id', array_keys($rows))->get()->keyBy('id');

        return collect($rows)->map(function (array $values, int $id) use ($projects) {
            $values = array_map(fn ($v) => round($v, 2), $values);
            $estimate = $projects[$id]?->quotation?->profitEstimate?->total_cost;
            $actual = round($values['commitment'] + $values['stock_allocated'], 2);

            return $values + [
                'project' => $projects[$id] ?? null,
                'actual' => $actual,
                'estimate' => $estimate !== null ? round((float) $estimate, 2) : null,
                'variance' => $estimate !== null ? round((float) $estimate - $actual, 2) : null,
                'outstanding' => round($values['billed'] - $values['paid'], 2),
            ];
        })->filter(fn ($r) => $r['project'])->sortByDesc('actual')->values();
    }

    /**
     * Selisih kurs hutang valas:
     *  - realized: per alokasi pembayaran Posted (bayar - buku) dan refund uang muka
     *    Posted (buku - diterima) dalam rentang tanggal; positif = rugi kurs;
     *  - unrealized: tagihan valas terbuka, nilai buku vs nilai kini (kurs master).
     *
     * @return array{realized: Collection, unrealized: Collection, realized_total: float, unrealized_total: float}
     */
    public function fx(?string $from = null, ?string $to = null): array
    {
        $payments = SupplierPaymentAllocation::with(['payment', 'bill.supplier'])
            ->whereHas('payment', fn ($q) => $q->where('status', SupplierPayment::STATUS_POSTED)
                ->where('currency', '!=', $this->baseCurrency())
                ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to)))
            ->get()
            ->map(fn (SupplierPaymentAllocation $a) => [
                'kind' => 'payment',
                'date' => $a->payment->payment_date,
                'number' => $a->payment->payment_number,
                'url' => route('supplier-payment.show', $a->supplier_payment_id),
                'supplier' => $a->bill?->supplier?->name,
                'reference' => 'Tagihan '.$a->bill?->bill_number,
                'currency' => $a->payment->currency,
                'amount' => $a->amount,
                'book_rate' => (float) $a->bill?->exchange_rate,
                'actual_rate' => (float) $a->payment->exchange_rate,
                'book' => $a->amount_base_book,
                'actual' => $a->amount_base_paid,
                'difference' => $a->fx_difference,
            ]);

        $refunds = SupplierRefund::with('supplier')
            ->where('status', SupplierRefund::STATUS_POSTED)
            ->where('currency', '!=', $this->baseCurrency())
            ->when($from, fn ($q) => $q->whereDate('refund_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('refund_date', '<=', $to))
            ->get()
            ->map(fn (SupplierRefund $r) => [
                'kind' => 'refund',
                'date' => $r->refund_date,
                'number' => $r->refund_number,
                'url' => route('supplier-refund.show', $r->id),
                'supplier' => $r->supplier?->name,
                'reference' => 'Refund uang muka',
                'currency' => $r->currency,
                'amount' => $r->amount,
                'book_rate' => $r->amount > 0 ? round($r->amount_base_book / $r->amount, 6) : 0,
                'actual_rate' => (float) $r->exchange_rate,
                'book' => $r->amount_base_book,
                'actual' => $r->amount_base,
                'difference' => $r->fx_difference,
            ]);

        $realized = $payments->concat($refunds)->sortBy(fn ($row) => $row['date']?->timestamp)->values();

        $unrealized = $this->openBills()->filter(fn (SupplierBill $b) => $b->isForeign())->map(fn (SupplierBill $b) => [
            'bill' => $b,
            'outstanding' => $b->outstanding(),
            'book_rate' => (float) $b->exchange_rate,
            'current_rate' => $this->toBase(1, $b->currency),
            'book' => $b->outstandingBase(),
            'current' => $this->toBase($b->outstanding(), $b->currency),
            'difference' => round($this->toBase($b->outstanding(), $b->currency) - $b->outstandingBase(), 2),
        ])->values();

        return [
            'realized' => $realized,
            'unrealized' => $unrealized,
            'realized_total' => round($realized->sum('difference'), 2),
            'unrealized_total' => round($unrealized->sum('difference'), 2),
        ];
    }
}

<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\DirectPurchase;
use App\Models\GoodsReceipt;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLog;
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

    /** Daftar mata uang yang muncul pada tagihan (untuk opsi filter). */
    public function currencies(): Collection
    {
        return SupplierBill::whereIn('status', [SupplierBill::STATUS_VERIFIED, SupplierBill::STATUS_PARTIALLY_PAID, SupplierBill::STATUS_PAID])
            ->distinct()->pluck('currency')
            ->map(fn ($c) => strtoupper((string) $c))->filter()->sort()->values();
    }

    public function toBase(float $amount, ?string $currency): float
    {
        $code = strtoupper((string) $currency);
        $this->rates[$code] ??= Currency::rateOf($code);

        return round($amount * $this->rates[$code], 2);
    }

    /**
     * Harga modal rata-rata (base) per unit yang diterima untuk baris PO: kurs penerimaan
     * tiap baris GRN posted; penerimaan lama tanpa kurs memakai kurs PO. Qty dan nilai
     * retur supplier dikurangkan agar barang yang dikembalikan tidak ikut merata-ratakan
     * harga (retur memakai harga modal baris stok asal; fallback kurs PO bila tanpa stok).
     */
    private function receiptUnitCost(PurchaseOrderItem $item, float $discount, float $poRate): float
    {
        $posted = $item->goodsReceiptItems->filter(fn ($gr) => $gr->goodsReceipt?->status === GoodsReceipt::STATUS_POSTED);
        $qty = (float) $posted->sum('qty');
        $value = (float) $posted->sum(fn ($gr) => $gr->qty * StockCost::toBase((float) $item->price, $item->currency, $discount, $gr->exchange_rate ?: $poRate));

        $returns = $item->relationLoaded('returnItems')
            ? $item->returnItems
            : $item->returnItems()->with('stock')->get();

        $returnedQty = 0.0;
        $returnedValue = 0.0;
        foreach ($returns as $return) {
            $returnQty = (float) $return->qty;
            if ($returnQty <= 0) {
                continue;
            }

            $unitCost = $return->stock?->unit_cost
                ?? StockCost::toBase((float) $item->price, $item->currency, $discount, $poRate);
            $returnedQty += $returnQty;
            $returnedValue += $returnQty * (float) $unitCost;
        }

        $qty -= $returnedQty;
        if ($qty <= 0) {
            return StockCost::toBase((float) $item->price, $item->currency, $discount, $poRate);
        }

        return max(0, $value - $returnedValue) / $qty;
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
     * Ringkasan hutang per (supplier × mata uang), murni dari tagihan
     * (verified / sebagian lunas / lunas). Total = amountDue, Bayar = paidAmount,
     * Sisa = outstanding — semuanya dalam mata uang tagihan.
     */
    public function supplierByCurrency(): Collection
    {
        return SupplierBill::with(['supplier', 'allocations.payment'])
            ->whereIn('status', [SupplierBill::STATUS_VERIFIED, SupplierBill::STATUS_PARTIALLY_PAID, SupplierBill::STATUS_PAID])
            ->get()
            ->groupBy(fn (SupplierBill $b) => $b->supplier_id.'|'.strtoupper((string) $b->currency))
            ->map(function (Collection $bills) {
                $first = $bills->first();
                $open = $bills->filter(fn (SupplierBill $b) => $b->outstanding() > 0);
                $overdue = $open->filter(fn (SupplierBill $b) => $this->daysOverdue($b) > 0);

                return [
                    'supplier' => $first->supplier,
                    'currency' => strtoupper((string) $first->currency),
                    'total' => round($bills->sum(fn (SupplierBill $b) => $b->amountDue()), 2),
                    'paid' => round($bills->sum(fn (SupplierBill $b) => $b->paidAmount()), 2),
                    'sisa' => round($bills->sum(fn (SupplierBill $b) => $b->outstanding()), 2),
                    'open_count' => $open->count(),
                    'overdue' => round($overdue->sum(fn (SupplierBill $b) => $b->outstanding()), 2),
                    'next_due' => $open->sortBy('due_date')->first()?->due_date,
                ];
            })
            ->sortByDesc('sisa')->values();
    }

    /**
     * Daftar tagihan ber-project per (bill × project), dalam mata uang tagihan:
     * total = porsi amountDue (setelah diskon, charge, PPN, advance, PPh),
     * bayar = porsi paidAmount, sisa = total − bayar. Porsi dihitung dari nilai
     * baris project dibagi nilai barang + charge tagihan, sehingga total antar
     * project menjumlah ke amountDue dan rekonsiliasi dengan laporan hutang.
     */
    public function projectBills(): Collection
    {
        return SupplierBillItem::with(['bill.supplier', 'bill.allocations.payment', 'project'])
            ->whereNotNull('project_id')
            ->whereHas('bill', fn ($q) => $q->whereIn('status', [SupplierBill::STATUS_VERIFIED, SupplierBill::STATUS_PARTIALLY_PAID, SupplierBill::STATUS_PAID]))
            ->get()
            ->groupBy(fn (SupplierBillItem $item) => $item->supplier_bill_id.'|'.$item->project_id)
            ->map(function (Collection $items) {
                $bill = $items->first()->bill;
                $project = $items->first()->project;
                $lineBase = round((float) $items->sum('amount'), 2);
                $billBase = round((float) $bill->subtotal + (float) $bill->other_charges, 2);
                $ratio = $billBase > 0 ? min(1, $lineBase / $billBase) : 0;
                $total = round($bill->amountDue() * $ratio, 2);
                $paid = round($bill->paidAmount() * $ratio, 2);

                return [
                    'project' => $project,
                    'bill' => $bill,
                    'currency' => strtoupper((string) $bill->currency),
                    'date' => $bill->bill_date,
                    'total' => $total,
                    'paid' => $paid,
                    'sisa' => round($total - $paid, 2),
                ];
            })
            ->sortBy(fn (array $r) => ($r['project']?->project_number ?? '').'|'.($r['date']?->format('Y-m-d') ?? ''))
            ->values();
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
     *  - diterima  : qty diterima x harga setelah diskon PO x kurs penerimaan (= nilai stok);
     *  - komitmen  : diterima + sisa PO belum diterima (kurs PO) + DP disetujui
     *                (PO batal: hanya yang sudah diterima);
     *  - ditagih   : baris tagihan terverifikasi ber-project;
     *  - terbayar  : porsi baris tersebut dari bagian tagihan yang sudah diselesaikan
     *                (pembayaran + potongan uang muka + PPh dipotong);
     *  - alokasi stok: stok bebas yang dialokasikan ke project (harga modal baris stok);
     *  - selisih kurs: terima -> tagihan (kurs tagihan - kurs penerimaan) dan tagihan -> bayar
     *                (fx_difference pembayaran Posted), porsi baris tagihan ber-project; positif = rugi.
     * Biaya aktual = komitmen + alokasi stok + selisih kurs (Rupiah riil). Nilai stok tidak diubah.
     * Pembelian untuk stok belum menjadi biaya project sampai dialokasikan.
     */
    public function projectCosts(?int $projectId = null): Collection
    {
        $rows = [];
        $row = function (int $id) use (&$rows) {
            $rows[$id] ??= ['commitment' => 0.0, 'received' => 0.0, 'billed' => 0.0, 'paid' => 0.0, 'stock_allocated' => 0.0, 'fx_receipt' => 0.0, 'fx_payment' => 0.0];

            return $id;
        };

        PurchaseOrderItem::with(['purchaseOrder', 'goodsRequestItem.goodsRequest', 'goodsReceiptItems.goodsReceipt', 'returnItems.stock'])
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED]))
            ->get()
            ->each(function (PurchaseOrderItem $item) use (&$rows, $row, $projectId) {
                $project = $item->projectId();
                if (! $project || ($projectId && $project !== $projectId)) {
                    return;
                }
                $row($project);
                $cancelled = $item->purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED;
                $discount = (float) ($item->purchaseOrder->discount_percent ?? 0);
                $receivedQty = $item->receivedQty();
                $poRate = $item->purchaseOrder->exchangeRate();
                $received = $receivedQty * $this->receiptUnitCost($item, $discount, $poRate);
                $pending = $cancelled ? 0 : max(0, (int) $item->qty - $receivedQty) * StockCost::toBase((float) $item->price, $item->currency, $discount, $poRate);
                $rows[$project]['received'] += $received;
                $rows[$project]['commitment'] += $received + $pending;
            });

        DirectPurchase::with('items')
            ->where('status', DirectPurchase::STATUS_APPROVED)
            ->where('purpose', DirectPurchase::PURPOSE_PROJECT)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->whereNotNull('project_id')
            ->get()
            ->each(function (DirectPurchase $dp) use (&$rows, $row) {
                $row($dp->project_id);
                // Kurs yang ditetapkan di DP (DP lama tanpa kurs -> kurs master).
                $amount = round($dp->grandTotal() * $dp->exchangeRate(), 2);
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
                if ($bill->isForeign() && $base > 0) {
                    // Barang: nilai setelah diskon x (kurs tagihan - kurs penerimaan); biaya tambahan tidak punya kurs terima.
                    if ($bill->receipt_rate && $item->line_type === SupplierBillItem::TYPE_ITEM) {
                        $rows[$item->project_id]['fx_receipt'] += $item->amount * (1 - (float) $bill->discount_percent / 100) * ((float) $bill->exchange_rate - (float) $bill->receipt_rate);
                    }
                    $rows[$item->project_id]['fx_payment'] += $bill->realizedFx() * $item->amount / $base;
                }
            });

        // Stok bebas yang dialokasikan ke project (dan dilepas kembali): log bertanda
        // pada sisi project. Transfer antar gudang dalam project yang sama saling meniadakan.
        StockLog::whereNotNull('project_id')
            ->whereIn('type', [StockLog::TYPE_ALLOCATE, StockLog::TYPE_TRANSFER_IN, StockLog::TYPE_TRANSFER_OUT])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->groupBy('project_id')
            ->selectRaw('project_id, SUM(qty * unit_cost) AS total_value')
            ->get()
            ->each(function ($sum) use (&$rows, $row) {
                $row((int) $sum->project_id);
                $rows[(int) $sum->project_id]['stock_allocated'] += (float) $sum->total_value;
            });

        $projects = Project::with('quotation.profitEstimate')->whereIn('id', array_keys($rows))->get()->keyBy('id');

        return collect($rows)->map(function (array $values, int $id) use ($projects) {
            $values = array_map(fn ($v) => round($v, 2), $values);
            $estimate = $projects[$id]?->quotation?->profitEstimate?->total_cost;
            $values['fx'] = round($values['fx_receipt'] + $values['fx_payment'], 2);
            $actual = round($values['commitment'] + $values['stock_allocated'] + $values['fx'], 2);

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
        $payments = SupplierPaymentAllocation::with(['payment', 'bill.supplier', 'bill.items.project', 'bill.allocations.payment'])
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
                'supplier_id' => $a->bill?->supplier_id,
                // Project dari baris tagihan yang dibayar (satu tagihan bisa beberapa project).
                'projects' => $a->bill ? $a->bill->items->pluck('project')->filter()->unique('id')->values() : collect(),
                'remaining' => $a->bill ? $a->bill->outstanding() : 0.0,
                'reference' => 'Bill '.$a->bill?->bill_number,
                'currency' => $a->payment->currency,
                'amount' => $a->amount,
                'book_rate' => (float) $a->bill?->exchange_rate,
                'actual_rate' => (float) $a->payment->exchange_rate,
                'book' => $a->amount_base_book,
                'actual' => $a->amount_base_paid,
                'difference' => $a->fx_difference,
            ]);

        $refunds = SupplierRefund::with(['supplier', 'purchaseOrder.items.goodsRequestItem.goodsRequest.project'])
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
                'supplier_id' => $r->supplier_id,
                'projects' => $r->purchaseOrder
                    ? $r->purchaseOrder->items->map(fn ($item) => $item->goodsRequest()?->isForProject() ? $item->goodsRequest()->project : null)->filter()->unique('id')->values()
                    : collect(),
                // Refund menyelesaikan uang muka: tidak ada sisa tagihan.
                'remaining' => 0.0,
                'reference' => 'Advance refund',
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

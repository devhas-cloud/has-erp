<?php

namespace App\Support;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DirectPurchase;
use App\Models\DirectPurchaseItem;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\Invoice;
use App\Models\MaterialHandoverItem;
use App\Models\MaterialRequest;
use App\Models\PackingList;
use App\Models\Production;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QuotationItem;
use App\Models\Stock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tracking proses project dalam 7 tahap, dihitung dari dokumen yang sudah ada
 * (tanpa input manual, tanpa tabel baru). Data dimuat sekaligus untuk banyak
 * project (satu query per jenis dokumen), lalu dihitung per project.
 *
 * Status tahap: done (Selesai), run (Berjalan), late (Terlambat / tertahan),
 * idle (Belum mulai), na (Tidak dipakai project ini).
 */
class ProjectTracker
{
    public const DONE = 'done';

    public const RUN = 'run';

    public const LATE = 'late';

    public const IDLE = 'idle';

    public const NA = 'na';

    public const STAGES = [
        1 => 'Project',
        2 => 'Pembelian',
        3 => 'Terima Barang',
        4 => 'Produksi',
        5 => 'Packing List',
        6 => 'Delivery Order',
        7 => 'Invoice',
    ];

    public const STATUS_LABELS = [
        self::DONE => 'Selesai',
        self::RUN => 'Berjalan',
        self::LATE => 'Terlambat',
        self::IDLE => 'Belum mulai',
        self::NA => 'Tidak dipakai',
    ];

    private const EPS = 0.00005;

    private Carbon $today;

    private int $alertDays;

    public function __construct(?Carbon $today = null, ?int $alertDays = null)
    {
        $this->today = ($today ?? now())->copy()->startOfDay();
        $this->alertDays = $alertDays ?? (int) config('project_tracking.alert_days', 14);
    }

    public function alertDays(): int
    {
        return $this->alertDays;
    }

    /** Tracking satu project. */
    public function forProject(Project $project): array
    {
        return $this->track(collect([$project]))->first();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, array> keyBy project id
     */
    public function track(Collection $projects): Collection
    {
        if ($projects->isEmpty()) {
            return collect();
        }
        $projects = new \Illuminate\Database\Eloquent\Collection($projects->all());
        $projects->loadMissing(['company', 'quotation.profitEstimate', 'quotation.opportunity', 'poSupplierApprovals', 'invoices']);
        $ids = $projects->pluck('id')->all();
        $data = $this->load($ids, $projects);

        return $projects->mapWithKeys(fn (Project $project) => [$project->id => $this->build($project, $data)]);
    }

    private function load(array $ids, Collection $projects): array
    {
        $requests = GoodsRequest::with(['items' => fn ($q) => $q->with(GoodsRequestItem::FULFILLMENT_RELATIONS)])
            ->where('purpose', GoodsRequest::PURPOSE_PROJECT)
            ->whereIn('project_id', $ids)
            ->where('status', '!=', GoodsRequest::STATUS_REJECTED)
            ->get()->groupBy('project_id');

        $poItems = PurchaseOrderItem::with(['purchaseOrder', 'goodsRequestItem.goodsRequest', 'goodsReceiptItems.goodsReceipt', 'returnItems'])
            ->whereHas('goodsRequestItem.goodsRequest', fn ($q) => $q->whereIn('project_id', $ids))
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED]))
            ->get()->groupBy(fn (PurchaseOrderItem $item) => $item->projectId());

        $directPurchases = DirectPurchase::with('items.goodsReceiptItems.goodsReceipt')
            ->where('purpose', DirectPurchase::PURPOSE_PROJECT)
            ->whereIn('project_id', $ids)
            ->where('status', '!=', DirectPurchase::STATUS_REJECTED)
            ->get()->groupBy('project_id');

        $materialRequests = MaterialRequest::with('items.handoverItems')
            ->whereIn('project_id', $ids)
            ->where('status', '!=', MaterialRequest::STATUS_REJECTED)
            ->get()->groupBy('project_id');

        $poolLeft = MaterialHandoverItem::withUsedQty()
            ->with('materialHandover.materialRequest:id,project_id')
            ->whereHas('materialHandover.materialRequest', fn ($q) => $q->whereIn('project_id', $ids))
            ->get()
            ->groupBy(fn (MaterialHandoverItem $item) => $item->materialHandover->materialRequest->project_id)
            ->map(fn ($items) => $items->filter(fn (MaterialHandoverItem $item) => $item->remainingQty() > 0)->count());

        $productions = Production::whereIn('project_id', $ids)->where('status', Production::STATUS_POSTED)
            ->get(['id', 'project_id', 'production_number'])->groupBy('project_id');

        $packingLists = PackingList::whereIn('project_id', $ids)->get(['id', 'project_id', 'packing_number', 'status'])->groupBy('project_id');

        $deliveryOrders = DeliveryOrder::whereIn('project_id', $ids)->get(['id', 'project_id', 'do_number', 'status', 'uses_packing_list'])->groupBy('project_id');

        $stock = Stock::open()->whereIn('project_id', $ids)
            ->get(['id', 'project_id', 'is_material', 'serial_number', 'created_at'])
            ->groupBy('project_id');

        $quotationIds = $projects->pluck('quotation_id')->filter()->all();
        $quotationItems = QuotationItem::whereIn('quotation_id', $quotationIds ?: [0])->get(['id', 'quotation_id', 'parent_id', 'qty'])->groupBy('quotation_id');
        $delivered = DeliveryOrderItem::whereNotNull('quotation_item_id')
            ->whereHas('deliveryOrder', fn ($q) => $q->where('status', DeliveryOrder::STATUS_POSTED)->whereIn('project_id', $ids))
            ->groupBy('quotation_item_id')
            ->selectRaw('quotation_item_id, SUM(qty) AS total')
            ->pluck('total', 'quotation_item_id');

        $costs = (new PayableReport)->projectCosts(count($ids) === 1 ? $ids[0] : null)->keyBy(fn ($row) => $row['project']->id);

        return compact('requests', 'poItems', 'directPurchases', 'materialRequests', 'poolLeft', 'productions', 'packingLists', 'deliveryOrders', 'stock', 'quotationItems', 'delivered', 'costs');
    }

    private function build(Project $project, array $d): array
    {
        $id = $project->id;
        $old = fn (?Carbon $date) => $date && $date->copy()->startOfDay()->lt($this->today->copy()->subDays($this->alertDays));
        $alerts = [];

        // 1 · Project
        $quotation = $project->quotation;
        $stages[1] = $this->stage(self::DONE, 100, collect([
            $quotation ? 'Quotation '.$quotation->quotation_number : null,
            $project->po_number_company ? 'PO customer '.$project->po_number_company.($project->po_date ? ' ('.$project->po_date->format('d/m/Y').')' : '') : null,
            $quotation?->opportunity?->close_won_date ? 'Close won '.Carbon::parse($quotation->opportunity->close_won_date)->format('d/m/Y') : null,
        ])->filter()->implode(' · '), array_filter([
            $quotation ? [$quotation->quotation_number, $this->url('quotation.show', $quotation->id)] : null,
        ]));

        // 2 · Pembelian
        $approvals = $project->poSupplierApprovals->count();
        $requests = $d['requests'][$id] ?? collect();
        $dps = $d['directPurchases'][$id] ?? collect();
        $poItems = $d['poItems'][$id] ?? collect();
        $prItems = $requests->flatMap->items;
        $needQty = (float) $prItems->sum('qty');
        $metQty = (float) $prItems->sum(fn (GoodsRequestItem $item) => min((int) $item->qty, $item->fulfilledQty()));
        $approvalStuck = $approvals < 2 && $old($project->created_at);
        // Tanpa pembelian tapi tahap berikutnya sudah berjalan (mis. dikirim dari
        // stok yang sudah ada) -> pembelian & terima barang tidak dipakai.
        $laterActivity = ($d['materialRequests'][$id] ?? collect())->isNotEmpty()
            || ($d['packingLists'][$id] ?? collect())->isNotEmpty()
            || ($d['deliveryOrders'][$id] ?? collect())->isNotEmpty()
            || $project->invoices->where('status', '!=', Invoice::STATUS_CANCELLED)->isNotEmpty();
        $noPurchase = $requests->isEmpty() && $dps->isEmpty();
        if ($noPurchase) {
            $status2 = $laterActivity ? self::NA : ($approvalStuck ? self::LATE : self::IDLE);
            $progress2 = $laterActivity ? null : 0;
        } else {
            $allApproved = $requests->every(fn ($gr) => $gr->status === GoodsRequest::STATUS_APPROVED)
                && $dps->every(fn ($dp) => $dp->status === DirectPurchase::STATUS_APPROVED);
            $progress2 = $needQty > 0 ? (int) floor(min(1, $metQty / $needQty) * 100) : ($allApproved ? 100 : 0);
            $status2 = $allApproved && $metQty >= $needQty - self::EPS ? self::DONE : self::RUN;
        }
        if ($approvalStuck && $status2 !== self::NA) {
            $alerts[] = ['warn', 'Approval PO Supplier '.$approvals.'/2 lebih dari '.$this->alertDays.' hari'];
        }
        $purchaseOrders = $poItems->pluck('purchaseOrder')->unique('id');
        $cost = $d['costs'][$id] ?? null;
        $stages[2] = $this->stage($status2, $progress2, collect([
            'Approval '.min($approvals, 2).'/2',
            $requests->count() ? $requests->count().' PR' : null,
            $purchaseOrders->count() ? $purchaseOrders->count().' PO' : null,
            $dps->count() ? $dps->count().' DP' : null,
            $cost ? 'komitmen '.StockCost::format($cost['commitment']) : null,
        ])->filter()->implode(' · '), array_merge(
            $requests->map(fn ($gr) => [$gr->request_number ?: '#'.$gr->id, $this->url('goods-request.show', $gr->id)])->all(),
            $purchaseOrders->map(fn ($po) => [$po->po_number ?: '#'.$po->id, $this->url('purchase-order.show', $po->id)])->all(),
            $dps->map(fn ($dp) => [$dp->dp_number ?: '#'.$dp->id, $this->url('direct-purchase.show', $dp->id)])->all(),
        ));

        // 3 · Terima Barang
        $approvedDps = $dps->where('status', DirectPurchase::STATUS_APPROVED);
        $ordered = (float) $poItems->sum(fn (PurchaseOrderItem $item) => $item->committedQty())
            + (float) $approvedDps->flatMap->items->sum('qty');
        $received = (float) $poItems->sum(fn (PurchaseOrderItem $item) => $item->receivedQty())
            + (float) $approvedDps->flatMap->items->sum(fn (DirectPurchaseItem $item) => $item->receivedQty());
        $lateReceipt = $poItems->contains(fn (PurchaseOrderItem $item) => $item->purchaseOrder->status !== PurchaseOrder::STATUS_CANCELLED
                && $item->remainingReceiptQty() > 0 && $old($item->purchaseOrder->approved_at))
            || $approvedDps->contains(fn ($dp) => $old($dp->approved_at) && $dp->items->contains(fn ($item) => $item->remainingReceiptQty() > 0));
        if ($lateReceipt) {
            $alerts[] = ['warn', 'Barang PO / DP belum datang lebih dari '.$this->alertDays.' hari'];
        }
        $status3 = match (true) {
            $ordered <= 0 && $noPurchase && $laterActivity => self::NA,
            $ordered <= 0 => self::IDLE,
            $received >= $ordered - self::EPS => self::DONE,
            $lateReceipt => self::LATE,
            default => self::RUN,
        };
        $receipts = $poItems->flatMap->goodsReceiptItems->merge($approvedDps->flatMap->items->flatMap->goodsReceiptItems)
            ->pluck('goodsReceipt')->filter()->unique('id');
        $stages[3] = $this->stage($status3, $ordered > 0 ? (int) floor(min(1, $received / $ordered) * 100) : 0,
            $ordered > 0 ? StockCost::qty($received).' dari '.StockCost::qty($ordered).' diterima' : 'Belum ada PO / DP disetujui',
            $receipts->map(fn ($gr) => [$gr->receipt_number ?: '#'.$gr->id, $this->url('goods-receipt.show', $gr->id)])->values()->all());

        // 4 · Produksi
        $mrs = $d['materialRequests'][$id] ?? collect();
        $productions = $d['productions'][$id] ?? collect();
        $poolLeft = (int) ($d['poolLeft'][$id] ?? 0);
        if ($mrs->isEmpty()) {
            $stages[4] = $this->stage(self::NA, null, 'Tanpa Material Request', []);
        } else {
            $requested = (float) $mrs->flatMap->items->sum('qty');
            $handed = (float) $mrs->flatMap->items->sum(fn ($item) => min($item->qty, $item->handedQty()));
            $allClosed = $mrs->every(fn ($mr) => $mr->status === MaterialRequest::STATUS_COMPLETED);
            $stages[4] = $this->stage($allClosed && $poolLeft === 0 ? self::DONE : self::RUN,
                $requested > 0 ? (int) floor(min(1, $handed / $requested) * 100) : 0,
                collect([$mrs->count().' MR', $productions->count().' produksi', $poolLeft ? $poolLeft.' baris material belum dipakai' : null])->filter()->implode(' · '),
                array_merge(
                    $mrs->map(fn ($mr) => [$mr->request_number, $this->url('material-request.show', $mr->id)])->all(),
                    $productions->map(fn ($p) => [$p->production_number, $this->url('production.show', $p->id)])->all(),
                ));
        }

        // 5 · Packing List & 6 · Delivery Order
        $pkls = $d['packingLists'][$id] ?? collect();
        $dos = $d['deliveryOrders'][$id] ?? collect();
        $openPkls = $pkls->whereIn('status', [PackingList::STATUS_DRAFT, PackingList::STATUS_READY]);
        $postedDos = $dos->where('status', DeliveryOrder::STATUS_POSTED);
        $draftDos = $dos->where('status', DeliveryOrder::STATUS_DRAFT);
        if ($pkls->isEmpty()) {
            $stages[5] = $this->stage($postedDos->isNotEmpty() ? self::NA : self::IDLE, null, $postedDos->isNotEmpty() ? 'DO Langsung tanpa Packing List' : 'Belum ada Packing List', []);
        } else {
            $shipped = $pkls->where('status', PackingList::STATUS_SHIPPED)->count();
            $stages[5] = $this->stage($openPkls->isEmpty() ? self::DONE : self::RUN, (int) floor($shipped / $pkls->count() * 100),
                collect(PackingList::STATUS_LABELS)->map(fn ($label, $key) => ($n = $pkls->where('status', $key)->count()) ? $n.' '.$label : null)->filter()->implode(' · '),
                $pkls->map(fn ($pl) => [$pl->packing_number, $this->url('packing-list.show', $pl->id)])->values()->all());
        }

        $stockRows = $d['stock'][$id] ?? collect();
        [$quotationDone, $quotationProgress] = $this->quotationDelivery($d['quotationItems'][$project->quotation_id] ?? collect(), $d['delivered']);
        $stockEmptied = $postedDos->isNotEmpty() && $stockRows->isEmpty() && $openPkls->isEmpty() && $draftDos->isEmpty();
        $dueDays = $project->po_due_date ? (int) $this->today->diffInDays($project->po_due_date->copy()->startOfDay(), false) : null;
        $done6 = $quotationDone || $stockEmptied;
        $overdue = $dueDays !== null && $dueDays < 0 && ! $done6;
        if ($overdue) {
            $alerts[] = ['danger', 'Lewat jatuh tempo '.abs($dueDays).' hari'];
        }
        $status6 = match (true) {
            $done6 => self::DONE,
            $overdue => self::LATE,
            $dos->isEmpty() => self::IDLE,
            default => self::RUN,
        };
        $stages[6] = $this->stage($status6, $quotationProgress,
            collect([
                $postedDos->count() ? $postedDos->count().' DO terkirim' : null,
                $draftDos->count() ? $draftDos->count().' DO draft' : null,
                $quotationProgress !== null ? $quotationProgress.'% item Quotation terkirim' : null,
                $stockRows->count() ? $stockRows->count().' baris stok project belum dikirim' : null,
            ])->filter()->implode(' · ') ?: 'Belum ada Delivery Order',
            $dos->map(fn ($do) => [$do->do_number, $this->url('delivery-order.show', $do->id)])->values()->all());

        // Peringatan stok
        $missingSn = $stockRows->filter(fn ($s) => ! $s->is_material && ! $s->serial_number)->count();
        if ($missingSn) {
            $alerts[] = ['warn', $missingSn.' unit stok project belum ber-SN'];
        }
        $oldest = $stockRows->min('created_at');
        if ($stockRows->isNotEmpty() && $openPkls->isEmpty() && $draftDos->isEmpty() && $old($oldest ? Carbon::parse($oldest) : null)) {
            $alerts[] = ['warn', 'Barang project siap tapi belum dikirim lebih dari '.$this->alertDays.' hari'];
        }

        // 7 · Invoice
        $base = $project->billingBaseAmount();
        $invoices = $project->invoices->where('status', '!=', Invoice::STATUS_CANCELLED);
        $approvedInvoices = $invoices->filter(fn (Invoice $inv) => $inv->isApproved());
        $billed = round((float) $approvedInvoices->sum('amount_payment'), 2);
        $paid = round((float) $approvedInvoices->where('status', Invoice::STATUS_PAID)->sum('amount_payment'), 2);
        $overdueInvoices = $approvedInvoices->filter(fn (Invoice $inv) => $inv->status === Invoice::STATUS_UNPAID && $inv->due_date && $inv->due_date->copy()->startOfDay()->lt($this->today));
        if ($overdueInvoices->isNotEmpty()) {
            $alerts[] = ['danger', $overdueInvoices->count().' invoice lewat jatuh tempo belum dibayar'];
        }
        $billedPct = $base > 0 ? (int) floor(min(1, $billed / $base) * 100) : 0;
        $paidPct = $base > 0 ? (int) floor(min(1, $paid / $base) * 100) : 0;
        $status7 = match (true) {
            $invoices->isEmpty() => self::IDLE,
            $base > 0 && $billed >= $base - 1 && $paid >= $billed - 1 => self::DONE,
            $overdueInvoices->isNotEmpty() => self::LATE,
            default => self::RUN,
        };
        $stages[7] = $this->stage($status7, $billedPct, 'Ditagih '.$billedPct.'% · diterima '.$paidPct.'%',
            $invoices->map(fn ($inv) => [$inv->invoice_number ?: '#'.$inv->id, $this->url('invoice.show', $inv->id)])->values()->all());

        // Biaya
        $estimate = $quotation?->profitEstimate?->total_cost !== null ? (float) $quotation->profitEstimate->total_cost : null;
        $actual = $cost ? (float) $cost['actual'] : 0.0;
        if ($estimate !== null && $estimate > 0 && $actual > $estimate + 1) {
            $alerts[] = ['danger', 'Biaya aktual melewati estimasi'];
        }

        $current = collect($stages)->filter(fn ($s) => ! in_array($s['status'], [self::DONE, self::NA], true))->keys()->first();

        return [
            'project' => $project,
            'stages' => $stages,
            'current' => $current,
            'current_label' => $current ? self::STAGES[$current] : 'Selesai',
            'alerts' => $alerts,
            'due_date' => $project->po_due_date,
            'due_days' => $dueDays,
            'finance' => [
                'contract' => round($base, 2),
                'estimate' => $estimate,
                'actual' => round($actual, 2),
                'billed' => $billed,
                'paid' => $paid,
                'billed_pct' => $billedPct,
                'paid_pct' => $paidPct,
            ],
        ];
    }

    /**
     * Item Quotation ber-qty yang terkirim (baris DO terposting). Item paket
     * terhitung terkirim lewat anak-anaknya. Kembalikan [semua terkirim, % rata-rata].
     */
    private function quotationDelivery(Collection $items, Collection $delivered): array
    {
        $children = $items->whereNotNull('parent_id')->groupBy('parent_id');
        $ratio = fn ($item) => (float) $item->qty > 0 ? min(1, (float) ($delivered[$item->id] ?? 0) / (float) $item->qty) : null;

        $ratios = $items->whereNull('parent_id')->map(function ($item) use ($children, $ratio) {
            $own = $ratio($item);
            $kids = ($children[$item->id] ?? collect())->map($ratio)->filter(fn ($r) => $r !== null);
            $viaKids = $kids->isNotEmpty() ? $kids->min() : null;
            if ($own === null && $viaKids === null) {
                return null;
            }

            return max($own ?? 0, $viaKids ?? 0);
        })->filter(fn ($r) => $r !== null);

        if ($ratios->isEmpty()) {
            return [false, null];
        }

        return [$ratios->every(fn ($r) => $r >= 1 - self::EPS), (int) floor($ratios->avg() * 100)];
    }

    private function stage(string $status, ?int $progress, string $summary, array $docs): array
    {
        return [
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'progress' => $progress,
            'summary' => $summary,
            'docs' => array_values(array_filter($docs, fn ($doc) => $doc && $doc[1])),
        ];
    }

    private function url(string $route, int $id): ?string
    {
        return \Illuminate\Support\Facades\Route::has($route) ? route($route, $id) : null;
    }
}

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
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\SupplierPayment;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tracking proses project dalam 9 tahap (Project, Purchasing, Purchase Payment,
 * Goods Receipt, Incoming Quality, Production, Delivery, Installation, Invoice),
 * dihitung dari dokumen & task yang sudah ada
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
        2 => 'Purchasing',
        3 => 'Purchase Payment',
        4 => 'Goods Receipt',
        5 => 'Incoming Quality',
        6 => 'Production',
        7 => 'Delivery',
        8 => 'Installation',
        9 => 'Invoice',
    ];

    /** Penjelasan singkat tiap tahap (tooltip / halaman detail). */
    public const STAGE_HINTS = [
        1 => 'Project start — customer PO received',
        2 => 'Purchase requests, supplier POs and direct purchases',
        3 => 'Supplier bills for project items and their payments',
        4 => 'Goods received into the warehouse',
        5 => 'Project tasks in the "Incoming Quality" category',
        6 => 'Material requests, handovers and production',
        7 => 'Packing lists and delivery orders to the customer',
        8 => 'Project tasks in the "Installasi" category',
        9 => 'Customer invoices and collections',
    ];

    /** Nama kategori task yang menjadi dasar tahap Incoming Quality & Installation. */
    public const TASK_STAGE_CATEGORIES = [5 => 'Incoming Quality', 8 => 'Installasi'];

    public const STATUS_LABELS = [
        self::DONE => 'Done',
        self::RUN => 'In progress',
        self::LATE => 'Delayed',
        self::IDLE => 'Not started',
        self::NA => 'Not used',
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
            ->withReturnedQty()
            ->with('materialHandover.materialRequest:id,project_id')
            ->whereHas('materialHandover.materialRequest', fn ($q) => $q->whereIn('project_id', $ids))
            ->get()
            ->groupBy(fn (MaterialHandoverItem $item) => $item->materialHandover->materialRequest->project_id)
            ->map(fn ($items) => $items->filter(fn (MaterialHandoverItem $item) => $item->remainingQty() > 0)->count());

        $productions = Production::whereIn('project_id', $ids)->where('status', Production::STATUS_POSTED)
            ->get(['id', 'project_id', 'production_number', 'production_date', 'created_at'])->groupBy('project_id');

        $packingLists = PackingList::whereIn('project_id', $ids)->get(['id', 'project_id', 'packing_number', 'status', 'packing_date'])->groupBy('project_id');

        $deliveryOrders = DeliveryOrder::whereIn('project_id', $ids)->get(['id', 'project_id', 'do_number', 'status', 'uses_packing_list', 'delivery_date', 'posted_at'])->groupBy('project_id');

        // Tahap Purchase Payment: baris tagihan supplier ber-project (terverifikasi) + alokasi pembayaran.
        $billItems = SupplierBillItem::with('bill.allocations.payment')
            ->whereIn('project_id', $ids)
            ->whereHas('bill', fn ($q) => $q->whereIn('status', [SupplierBill::STATUS_VERIFIED, SupplierBill::STATUS_PARTIALLY_PAID, SupplierBill::STATUS_PAID]))
            ->get()->groupBy('project_id');

        // Tahap Incoming Quality & Installation: task project per kategori (task batal diabaikan).
        $tasks = Task::with('category:id,name')
            ->whereIn('project_id', $ids)
            ->where('status', '!=', Task::STATUS_CANCELLED)
            ->whereHas('category', fn ($q) => $q->whereIn('name', array_values(self::TASK_STAGE_CATEGORIES)))
            ->get(['id', 'project_id', 'category_id', 'title', 'status', 'due_date', 'created_at', 'updated_at'])
            ->groupBy('project_id');

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

        return compact('requests', 'poItems', 'directPurchases', 'materialRequests', 'poolLeft', 'productions', 'packingLists', 'deliveryOrders', 'stock', 'quotationItems', 'delivered', 'costs', 'billItems', 'tasks');
    }

    private function build(Project $project, array $d): array
    {
        $id = $project->id;
        $old = fn (?Carbon $date) => $date && $date->copy()->startOfDay()->lt($this->today->copy()->subDays($this->alertDays));
        $alerts = [];

        $min = fn ($dates) => collect($dates)->filter()->map(fn ($v) => Carbon::parse($v))->sort()->first();
        $max = fn ($dates) => collect($dates)->filter()->map(fn ($v) => Carbon::parse($v))->sort()->last();

        // 1 · Project — titik awal: tanggal PO customer (fallback tanggal dibuat).
        $quotation = $project->quotation;
        $projectStart = $project->po_date ?? $project->created_at;
        $stages[1] = $this->stage(self::DONE, 100, collect([
            $quotation ? 'Quotation '.$quotation->quotation_number : null,
            $project->po_number_company ? 'Customer PO '.$project->po_number_company : null,
            $quotation?->opportunity?->close_won_date ? 'Closed won '.Carbon::parse($quotation->opportunity->close_won_date)->format('d/m/Y') : null,
        ])->filter()->implode(' · ') ?: 'Project created', array_filter([
            $quotation ? [$quotation->quotation_number, $this->url('quotation.show', $quotation->id)] : null,
        ]), $projectStart, $projectStart);

        // 2 · Purchasing
        $approvals = $project->poSupplierApprovals->count();
        $requests = $d['requests'][$id] ?? collect();
        $dps = $d['directPurchases'][$id] ?? collect();
        $poItems = $d['poItems'][$id] ?? collect();
        $prItems = $requests->flatMap->items;
        $needQty = (float) $prItems->sum('qty');
        $metQty = (float) $prItems->sum(fn (GoodsRequestItem $item) => min((int) $item->qty, $item->fulfilledQty()));
        $approvalStuck = $approvals < 2 && $old($project->created_at);
        // Tanpa pembelian tapi tahap berikutnya sudah berjalan (mis. dikirim dari
        // stok yang sudah ada) -> pembelian, pembayaran & terima barang tidak dipakai.
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
            $alerts[] = ['warn', 'Supplier PO approval '.$approvals.'/2 for over '.$this->alertDays.' days'];
        }
        $purchaseOrders = $poItems->pluck('purchaseOrder')->unique('id');
        $cost = $d['costs'][$id] ?? null;
        $stages[2] = $this->stage($status2, $progress2, collect([
            'Approval '.min($approvals, 2).'/2',
            $requests->count() ? $requests->count().' PR' : null,
            $purchaseOrders->count() ? $purchaseOrders->count().' PO' : null,
            $dps->count() ? $dps->count().' DP' : null,
            $cost ? 'committed '.StockCost::format($cost['commitment']) : null,
        ])->filter()->implode(' · '), array_merge(
            $requests->map(fn ($gr) => [$gr->request_number ?: '#'.$gr->id, $this->url('goods-request.show', $gr->id)])->all(),
            $purchaseOrders->map(fn ($po) => [$po->po_number ?: '#'.$po->id, $this->url('purchase-order.show', $po->id)])->all(),
            $dps->map(fn ($dp) => [$dp->dp_number ?: '#'.$dp->id, $this->url('direct-purchase.show', $dp->id)])->all(),
        ), $min(array_merge($requests->pluck('created_at')->all(), $dps->pluck('created_at')->all())),
            $status2 === self::DONE ? $max(array_merge($purchaseOrders->pluck('approved_at')->all(), $dps->pluck('approved_at')->all())) : null);

        // 4 · Goods Receipt (dihitung dulu: dipakai tahap 3 & 5)
        $approvedDps = $dps->where('status', DirectPurchase::STATUS_APPROVED);
        $ordered = (float) $poItems->sum(fn (PurchaseOrderItem $item) => $item->committedQty())
            + (float) $approvedDps->flatMap->items->sum('qty');
        $received = (float) $poItems->sum(fn (PurchaseOrderItem $item) => $item->receivedQty())
            + (float) $approvedDps->flatMap->items->sum(fn (DirectPurchaseItem $item) => $item->receivedQty());
        $lateReceipt = $poItems->contains(fn (PurchaseOrderItem $item) => $item->purchaseOrder->status !== PurchaseOrder::STATUS_CANCELLED
                && $item->remainingReceiptQty() > 0 && $old($item->purchaseOrder->approved_at))
            || $approvedDps->contains(fn ($dp) => $old($dp->approved_at) && $dp->items->contains(fn ($item) => $item->remainingReceiptQty() > 0));
        if ($lateReceipt) {
            $alerts[] = ['warn', 'PO / DP goods not arrived for over '.$this->alertDays.' days'];
        }
        $status4 = match (true) {
            $ordered <= 0 && $noPurchase && $laterActivity => self::NA,
            $ordered <= 0 => self::IDLE,
            $received >= $ordered - self::EPS => self::DONE,
            $lateReceipt => self::LATE,
            default => self::RUN,
        };
        $receipts = $poItems->flatMap->goodsReceiptItems->merge($approvedDps->flatMap->items->flatMap->goodsReceiptItems)
            ->pluck('goodsReceipt')->filter()->unique('id');
        $postedReceipts = $receipts->where('status', \App\Models\GoodsReceipt::STATUS_POSTED);
        $stages[4] = $this->stage($status4, $ordered > 0 ? (int) floor(min(1, $received / $ordered) * 100) : 0,
            $ordered > 0 ? StockCost::qty($received).' of '.StockCost::qty($ordered).' received' : 'No approved PO / DP yet',
            $receipts->map(fn ($gr) => [$gr->receipt_number ?: '#'.$gr->id, $this->url('goods-receipt.show', $gr->id)])->values()->all(),
            $min($postedReceipts->pluck('received_date')->all()),
            $status4 === self::DONE ? $max($postedReceipts->pluck('received_date')->all()) : null);

        // 3 · Purchase Payment — tagihan supplier ber-project & pembayarannya (nilai buku, mata uang dasar).
        $billItems = $d['billItems'][$id] ?? collect();
        $bills = $billItems->pluck('bill')->filter()->unique('id');
        $billedBase = round((float) $billItems->sum(fn (SupplierBillItem $item) => $item->bill->toBook((float) $item->amount)), 2);
        $paidBase = round((float) $billItems->sum(function (SupplierBillItem $item) {
            $bill = $item->bill;
            $base = (float) $bill->subtotal + (float) $bill->other_charges;
            $settled = $bill->total > 0 ? min(1, ($bill->paidAmount() + $bill->advance_deduction + $bill->pph_amount) / $bill->total) : 0;

            return $base > 0 ? $bill->toBook((float) $item->amount * $settled) : 0;
        }), 2);
        $receivedValue = (float) ($cost['received'] ?? 0);
        $paymentDates = $bills->flatMap(fn (SupplierBill $bill) => $bill->allocations
            ->filter(fn ($a) => $a->payment?->status === SupplierPayment::STATUS_POSTED)
            ->map(fn ($a) => $a->payment->payment_date));
        $overdueBills = $bills->filter(fn (SupplierBill $bill) => $bill->outstanding() > 0.005 && $bill->due_date && $bill->due_date->copy()->startOfDay()->lt($this->today));
        if ($overdueBills->isNotEmpty()) {
            $alerts[] = ['warn', $overdueBills->count().' supplier bill(s) past due'];
        }
        $target3 = max($billedBase, $receivedValue);
        $status3 = match (true) {
            $noPurchase && $laterActivity && $bills->isEmpty() => self::NA,
            $bills->isEmpty() => self::IDLE,
            $paidBase >= $billedBase - 1 && $billedBase >= $receivedValue - 1 => self::DONE,
            $overdueBills->isNotEmpty() => self::LATE,
            default => self::RUN,
        };
        $stages[3] = $this->stage($status3, $target3 > 0 ? (int) floor(min(1, $paidBase / $target3) * 100) : ($status3 === self::DONE ? 100 : 0),
            $bills->isEmpty()
                ? ($received > 0 ? 'Goods received, no supplier bill yet' : 'No supplier bill yet')
                : collect([
                    $bills->count().' bill(s)',
                    'billed '.StockCost::format($billedBase),
                    'paid '.StockCost::format($paidBase),
                    $receivedValue > $billedBase + 1 ? 'unbilled '.StockCost::format($receivedValue - $billedBase) : null,
                ])->filter()->implode(' · '),
            $bills->map(fn (SupplierBill $bill) => [$bill->bill_number ?: '#'.$bill->id, $this->url('supplier-bill.show', $bill->id)])->values()->all(),
            $min($paymentDates->all()) ?? $min($bills->pluck('bill_date')->all()),
            $status3 === self::DONE ? $max($paymentDates->all()) : null);

        // 5 · Incoming Quality & 8 · Installation — task project per kategori.
        $tasks = $d['tasks'][$id] ?? collect();
        $taskStage = function (string $category, bool $required) use ($tasks) {
            $group = $tasks->filter(fn (Task $t) => $t->category?->name === $category)->values();
            if ($group->isEmpty()) {
                return null;
            }
            $done = $group->where('status', 'done');
            $late = $group->contains(fn (Task $t) => $t->status !== 'done' && $t->due_date && $t->due_date->copy()->startOfDay()->lt($this->today));
            $status = match (true) {
                $done->count() === $group->count() => self::DONE,
                $late => self::LATE,
                $group->contains(fn (Task $t) => $t->status !== 'todo') => self::RUN,
                default => self::IDLE,
            };

            return $this->stage($status, (int) floor($done->count() / $group->count() * 100),
                $done->count().' of '.$group->count().' task(s) done'
                    .(($next = $group->where('status', '!=', 'done')->sortBy('due_date')->first())?->due_date ? ' · next due '.$next->due_date->format('d/m/Y') : ''),
                $group->map(fn (Task $t) => [$t->title ?: 'Task #'.$t->id, $this->url('task-planner.show', $t->id)])->all(),
                $group->min('created_at') ? Carbon::parse($group->min('created_at')) : null,
                $status === self::DONE ? Carbon::parse($done->max('updated_at')) : null);
        };
        $stages[5] = $taskStage(self::TASK_STAGE_CATEGORIES[5], true)
            ?? $this->stage($noPurchase && $laterActivity ? self::NA : self::IDLE, null,
                $received > 0 ? 'No Incoming Quality task yet' : 'Waiting for goods receipt', []);
        if ($received > 0 && ! $taskStage(self::TASK_STAGE_CATEGORIES[5], true)) {
            $alerts[] = ['warn', 'Goods received but no Incoming Quality task yet'];
        }

        // 6 · Production
        $mrs = $d['materialRequests'][$id] ?? collect();
        $productions = $d['productions'][$id] ?? collect();
        $poolLeft = (int) ($d['poolLeft'][$id] ?? 0);
        if ($mrs->isEmpty()) {
            $stages[6] = $this->stage(self::NA, null, 'No material request', []);
        } else {
            $requested = (float) $mrs->flatMap->items->sum('qty');
            $handed = (float) $mrs->flatMap->items->sum(fn ($item) => min($item->qty, $item->handedQty()));
            $allClosed = $mrs->every(fn ($mr) => $mr->status === MaterialRequest::STATUS_COMPLETED);
            $status6p = $allClosed && $poolLeft === 0 ? self::DONE : self::RUN;
            $stages[6] = $this->stage($status6p,
                $requested > 0 ? (int) floor(min(1, $handed / $requested) * 100) : 0,
                collect([$mrs->count().' MR', $productions->count().' production(s)', $poolLeft ? $poolLeft.' material line(s) not used yet' : null])->filter()->implode(' · '),
                array_merge(
                    $mrs->map(fn ($mr) => [$mr->request_number, $this->url('material-request.show', $mr->id)])->all(),
                    $productions->map(fn ($p) => [$p->production_number, $this->url('production.show', $p->id)])->all(),
                ),
                $min($mrs->map(fn ($mr) => $mr->request_date ?? $mr->created_at)->all()),
                $status6p === self::DONE ? $max($productions->map(fn ($p) => $p->production_date ?? $p->created_at)->all()) : null);
        }

        // 7 · Delivery — Packing List + Delivery Order.
        $pkls = $d['packingLists'][$id] ?? collect();
        $dos = $d['deliveryOrders'][$id] ?? collect();
        $openPkls = $pkls->whereIn('status', [PackingList::STATUS_DRAFT, PackingList::STATUS_READY]);
        $postedDos = $dos->where('status', DeliveryOrder::STATUS_POSTED);
        $draftDos = $dos->where('status', DeliveryOrder::STATUS_DRAFT);
        $stockRows = $d['stock'][$id] ?? collect();
        [$quotationDone, $quotationProgress] = $this->quotationDelivery($d['quotationItems'][$project->quotation_id] ?? collect(), $d['delivered']);
        $stockEmptied = $postedDos->isNotEmpty() && $stockRows->isEmpty() && $openPkls->isEmpty() && $draftDos->isEmpty();
        $dueDays = $project->po_due_date ? (int) $this->today->diffInDays($project->po_due_date->copy()->startOfDay(), false) : null;
        $done7 = $quotationDone || $stockEmptied;
        $overdue = $dueDays !== null && $dueDays < 0 && ! $done7;
        if ($overdue) {
            $alerts[] = ['danger', 'Past due date by '.abs($dueDays).' days'];
        }
        $status7 = match (true) {
            $done7 => self::DONE,
            $overdue => self::LATE,
            $dos->isEmpty() && $pkls->isEmpty() => self::IDLE,
            default => self::RUN,
        };
        $stages[7] = $this->stage($status7, $quotationProgress,
            collect([
                $pkls->count() ? $pkls->count().' packing list(s)'.($openPkls->count() ? ' ('.$openPkls->count().' open)' : '') : null,
                $postedDos->count() ? $postedDos->count().' DO delivered' : null,
                $draftDos->count() ? $draftDos->count().' DO draft' : null,
                $quotationProgress !== null ? $quotationProgress.'% of quotation items delivered' : null,
                $stockRows->count() ? $stockRows->count().' project stock row(s) not delivered' : null,
            ])->filter()->implode(' · ') ?: 'No packing list / delivery order yet',
            array_merge(
                $pkls->map(fn ($pl) => [$pl->packing_number, $this->url('packing-list.show', $pl->id)])->values()->all(),
                $dos->map(fn ($do) => [$do->do_number, $this->url('delivery-order.show', $do->id)])->values()->all(),
            ),
            $min(array_merge($pkls->pluck('packing_date')->all(), $postedDos->map(fn ($do) => $do->delivery_date ?? $do->posted_at)->all())),
            $status7 === self::DONE ? $max($postedDos->map(fn ($do) => $do->delivery_date ?? $do->posted_at)->all()) : null);

        // Peringatan stok
        $missingSn = $stockRows->filter(fn ($s) => ! $s->is_material && ! $s->serial_number)->count();
        if ($missingSn) {
            $alerts[] = ['warn', $missingSn.' project stock unit(s) without SN'];
        }
        $oldest = $stockRows->min('created_at');
        if ($stockRows->isNotEmpty() && $openPkls->isEmpty() && $draftDos->isEmpty() && $old($oldest ? Carbon::parse($oldest) : null)) {
            $alerts[] = ['warn', 'Project goods ready but not delivered for over '.$this->alertDays.' days'];
        }

        $stages[8] = $taskStage(self::TASK_STAGE_CATEGORIES[8], false)
            ?? $this->stage(self::NA, null, 'No Installasi task for this project', []);

        // 9 · Invoice
        $base = $project->billingBaseAmount();
        $invoices = $project->invoices->where('status', '!=', Invoice::STATUS_CANCELLED);
        $approvedInvoices = $invoices->filter(fn (Invoice $inv) => $inv->isApproved());
        $billed = round((float) $approvedInvoices->sum('amount_payment'), 2);
        $paid = round((float) $approvedInvoices->where('status', Invoice::STATUS_PAID)->sum('amount_payment'), 2);
        $overdueInvoices = $approvedInvoices->filter(fn (Invoice $inv) => $inv->status === Invoice::STATUS_UNPAID && $inv->due_date && $inv->due_date->copy()->startOfDay()->lt($this->today));
        if ($overdueInvoices->isNotEmpty()) {
            $alerts[] = ['danger', $overdueInvoices->count().' invoice(s) past due and unpaid'];
        }
        $billedPct = $base > 0 ? (int) floor(min(1, $billed / $base) * 100) : 0;
        $paidPct = $base > 0 ? (int) floor(min(1, $paid / $base) * 100) : 0;
        $status9 = match (true) {
            $invoices->isEmpty() => self::IDLE,
            $base > 0 && $billed >= $base - 1 && $paid >= $billed - 1 => self::DONE,
            $overdueInvoices->isNotEmpty() => self::LATE,
            default => self::RUN,
        };
        $stages[9] = $this->stage($status9, $billedPct, 'Billed '.$billedPct.'% · collected '.$paidPct.'%',
            $invoices->map(fn ($inv) => [$inv->invoice_number ?: '#'.$inv->id, $this->url('invoice.show', $inv->id)])->values()->all(),
            $min($invoices->map(fn ($inv) => $inv->invoice_date ?? $inv->created_at)->all()),
            $status9 === self::DONE ? $max($invoices->map(fn ($inv) => $inv->invoice_date ?? $inv->created_at)->all()) : null);

        ksort($stages);

        // Biaya
        $estimate = $quotation?->profitEstimate?->total_cost !== null ? (float) $quotation->profitEstimate->total_cost : null;
        $actual = $cost ? (float) $cost['actual'] : 0.0;
        if ($estimate !== null && $estimate > 0 && $actual > $estimate + 1) {
            $alerts[] = ['danger', 'Actual cost exceeds the estimate'];
        }

        $current = collect($stages)->filter(fn ($s) => ! in_array($s['status'], [self::DONE, self::NA], true))->keys()->first();

        return [
            'project' => $project,
            'stages' => $stages,
            'current' => $current,
            'current_label' => $current ? self::STAGES[$current] : 'Completed',
            'current_since' => $current ? $stages[$current]['start'] : null,
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

    /** Satu tahap: status, progres, ringkasan, dokumen terkait, dan tanggal mulai / selesai. */
    private function stage(string $status, ?int $progress, string $summary, array $docs, ?Carbon $start = null, ?Carbon $end = null): array
    {
        return [
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'progress' => $progress,
            'summary' => $summary,
            'docs' => array_values(array_filter($docs, fn ($doc) => $doc && $doc[1])),
            'start' => $start,
            'end' => $end,
        ];
    }

    private function url(string $route, int $id): ?string
    {
        return \Illuminate\Support\Facades\Route::has($route) ? route($route, $id) : null;
    }
}

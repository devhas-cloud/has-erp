<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPurchaseDocuments;
use App\Models\Currency;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Support\ModuleAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul "Purchase Order": PO barang ke supplier, HANYA dari item Permintaan
 * Barang berstatus Approved (pembelian tanpa permintaan lewat Direct Purchase).
 * Satu item permintaan boleh dipecah ke beberapa PO / supplier; qty baris PO
 * tidak boleh melebihi sisa kebutuhan item (GoodsRequestItem::remainingQty()).
 * Ketersediaan stok hanya informasi — user tetap boleh membeli.
 *
 * Status: draft -> waiting_approval -> approved -> partially_received -> received,
 * plus rejected (melepas seluruh qty ke permintaan) dan cancelled (oleh
 * pemegang can_approve, alasan wajib; hanya sisa yang belum diterima dilepas).
 * Barang diterima lewat modul Penerimaan Barang.
 *
 * Nomor PO otomatis (YYMM + 4 digit, urutan bersama lintas jenis) + suffix jenis.
 * PO Rejected bisa direvisi: disalin menjadi draft baru bernomor dasar sama +
 * REV (26110005REV, 26110005REV2-L, ...) — lihat revise().
 * Satu PO satu mata uang (mengikuti item permintaan, dikunci), tanpa kurs.
 */
class PurchaseOrderController extends Controller
{
    use ManagesPurchaseDocuments;

    public function index()
    {
        return view('purchase-order.index', [
            'suppliers' => PurchaseOrder::whereNotNull('supplier_name')->distinct()->orderBy('supplier_name')->pluck('supplier_name'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = PurchaseOrder::withCount('items')->with(['creator', 'items']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('supplier_name', 'like', "%{$searchValue}%")
                    ->orWhere('po_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('items', fn ($i) => $i->where('part_number', 'like', "%{$searchValue}%"))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%")->orWhere('full_name', 'like', "%{$searchValue}%"));
            });
        }

        // Filter bar: status, supplier, tanggal (tanggal dokumen, fallback tanggal dibuat), milik saya.
        $query->when($request->input('supplier'), fn ($q, $supplier) => $q->where('supplier_name', $supplier))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereRaw('DATE(COALESCE(date, created_at)) >= ?', [$from]))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereRaw('DATE(COALESCE(date, created_at)) <= ?', [$to]))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()))
            ->when($request->input('po_type'), fn ($q, $type) => $q->where('po_type', $type))
            ->when($request->input('pay_status'), fn ($q, $pay) => $this->filterPayStatus($q, $pay));

        $statusCounts = (clone $query)->reorder()->toBase()
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $orders = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($orders as $i => $po) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $po->id,
                'supplier_name' => $po->supplier_name,
                'po_number' => $po->po_number ?: '—',
                'po_type' => $po->po_type,
                'type_badge' => $po->typeBadgeHtml(),
                'date' => $po->date?->format('d/m/Y') ?? '—',
                'item_count' => $po->items_count,
                'grand_total_label' => $po->currencyCode().' '.number_format($po->netTotal(), 2, '.', ','),
                'creator_name' => $po->creator?->display_name ?? '—',
                'status' => $po->status,
                'status_label' => $po->status_label,
                'status_badge' => $po->statusBadgeHtml(),
                'is_creator' => (int) $po->created_by === (int) Auth::id(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => collect(PurchaseOrder::STATUS_LABELS)->map(fn ($label, $key) => (int) ($statusCounts[$key] ?? 0)),
            'data' => $data,
        ]);
    }

    public function create()
    {
        return view('purchase-order.form', [
            'purchaseOrder' => null,
            'items' => [],
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePurchaseOrder($request);

        $purchaseOrder = $this->createWithNumber(fn () => DB::transaction(function () use ($validated) {
            $po = PurchaseOrder::create([
                'supplier_name' => $validated['supplier_name'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_address' => $validated['supplier_address'] ?? null,
                'supplier_phone' => $validated['supplier_phone'] ?? null,
                'supplier_fax' => $validated['supplier_fax'] ?? null,
                'supplier_attn' => $validated['supplier_attn'] ?? null,
                'po_number' => PurchaseOrder::generatePoNumber($validated['po_type']),
                'po_type' => $validated['po_type'],
                'date' => $validated['date'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'payment_term_days' => $validated['payment_term_days'],
                'delivery' => $validated['delivery'] ?? null,
                'your_ref' => $validated['your_ref'] ?? null,
                'discount_percent' => $validated['discount_percent'] ?? null,
                'ppn_percent' => $validated['ppn_percent'] ?? null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => $validated['notes'] ?? null,
                'request_by_name' => $validated['request_by_name'] ?? null,
                'finance_name' => $validated['finance_name'] ?? null,
                'accounting_name' => $validated['accounting_name'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($po, $validated['items'] ?? []);

            return $po;
        }));

        return response()->json([
            'success' => true,
            'message' => 'Purchase order '.$purchaseOrder->po_number.' berhasil dibuat.',
            'id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
        ]);
    }

    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'items.goodsRequestItem.goodsRequest.division', 'items.goodsRequestItem.goodsRequest.project',
            'items.goodsReceiptItems.goodsReceipt', 'supplier', 'creator', 'finalChecker', 'canceller',
            'revisionOf', 'revisions', 'items.billItems.bill', 'items.returnItems', 'supplierBills.allocations.payment', 'supplierRefunds',
            'goodsReceipts' => fn ($q) => $q->with('warehouse')->withSum('items', 'qty'),
        ])->findOrFail($id);

        return view('purchase-order.show', [
            'purchaseOrder' => $purchaseOrder,
            'poGroups' => $purchaseOrder->hierarchyGroups(),
            'documents' => $this->documentList($purchaseOrder),
            'canCreateBill' => ModuleAccess::for()->module('MOD_SUPPLIER_BILL')->canCreate(),
            'canCreateRefund' => ModuleAccess::for()->module('MOD_SUPPLIER_REFUND')->canCreate(),
            'canCreateReturn' => ModuleAccess::for()->module('MOD_SUPPLIER_RETURN')->canCreate(),
        ]);
    }

    public function edit($id)
    {
        $purchaseOrder = PurchaseOrder::with(['items.goodsRequestItem' => fn ($q) => $q->with(array_merge(['goodsRequest.project', 'goodsRequest.division'], GoodsRequestItem::FULFILLMENT_RELATIONS))])->findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return redirect()->route('purchase-order.index')
                ->with('error', 'Hanya purchase order berstatus Draft yang bisa diedit.');
        }

        return view('purchase-order.form', [
            'purchaseOrder' => $purchaseOrder,
            'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item) => $this->lineForForm($item))->all(),
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function pdf($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'items.goodsRequestItem.goodsRequest.project',
            'supplier',
            'creator',
        ])->findOrFail($id);

        $printCurrency = $purchaseOrder->printCurrency();
        $currencySymbol = $printCurrency
            ? (Currency::where('name', $printCurrency)->value('symbol') ?: $printCurrency)
            : 'Rp';

        $pdf = Pdf::loadView('purchase-order.pdf', compact('purchaseOrder', 'currencySymbol'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('PO-'.($purchaseOrder->po_number ?: $purchaseOrder->id).'.pdf');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order berstatus Draft yang bisa diubah.'], 422);
        }

        $validated = $this->validatePurchaseOrder($request);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $purchaseOrder->update([
                'supplier_name' => $validated['supplier_name'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'supplier_address' => $validated['supplier_address'] ?? null,
                'supplier_phone' => $validated['supplier_phone'] ?? null,
                'supplier_fax' => $validated['supplier_fax'] ?? null,
                'supplier_attn' => $validated['supplier_attn'] ?? null,
                'po_number' => $purchaseOrder->po_number
                    ? PurchaseOrder::numberWithType($purchaseOrder->po_number, $validated['po_type'])
                    : PurchaseOrder::generatePoNumber($validated['po_type'], $purchaseOrder->created_at?->toDateString()),
                'po_type' => $validated['po_type'],
                'date' => $validated['date'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'payment_term_days' => $validated['payment_term_days'],
                'delivery' => $validated['delivery'] ?? null,
                'your_ref' => $validated['your_ref'] ?? null,
                'discount_percent' => $validated['discount_percent'] ?? null,
                'ppn_percent' => $validated['ppn_percent'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'request_by_name' => $validated['request_by_name'] ?? null,
                'finance_name' => $validated['finance_name'] ?? null,
                'accounting_name' => $validated['accounting_name'] ?? null,
            ]);

            $this->syncItems($purchaseOrder, $validated['items'] ?? []);
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase order berhasil diperbarui.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order berstatus Draft yang bisa dihapus.'], 422);
        }

        // Middleware (DELETE -> can_delete) sudah menjaga izin hapus; tidak perlu
        // dicek ulang di sini — itu justru salah menolak user yang hanya diberi can_delete.

        $purchaseOrder->delete();

        return response()->json(['success' => true, 'message' => 'Purchase order dihapus.']);
    }

    public function submit($id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order berstatus Draft yang bisa di-submit.'], 422);
        }

        if ($purchaseOrder->items()->count() === 0) {
            return response()->json(['success' => false, 'message' => 'Tambahkan minimal satu item sebelum submit.'], 422);
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_WAITING_APPROVAL]);
        $this->log($purchaseOrder, 'submit_purchase_order', 'diajukan approval');

        return response()->json(['success' => true, 'message' => 'Purchase order dikirim untuk approval.']);
    }

    public function approve($id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Purchase order ini tidak sedang menunggu approval.'], 422);
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'final_checked_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        $this->log($purchaseOrder, 'approve_purchase_order', 'disetujui');

        return response()->json(['success' => true, 'message' => 'Purchase order disetujui. Barang siap diterima lewat Penerimaan Barang.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Purchase order ini tidak sedang menunggu approval.'], 422);
        }

        $validated = $request->validate([
            'approval_note' => 'required|string',
        ]);

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_REJECTED,
            'final_checked_by' => Auth::id(),
            'approval_note' => $validated['approval_note'],
            'rejected_at' => now(),
        ]);
        $this->log($purchaseOrder, 'reject_purchase_order', 'ditolak: '.$validated['approval_note']);

        return response()->json(['success' => true, 'message' => 'Purchase order ditolak. Item kembali tersedia di Permintaan Barang.']);
    }

    /**
     * Revisi PO Rejected: salin header & item ke PO draft baru bernomor dasar
     * sama + REV. Qty tiap item mengikuti sisa kebutuhan Permintaan Barang saat
     * ini (item yang sudah di-PO di tempat lain dikurangi / dilewati).
     * Route purchase-order.revise -> middleware mewajibkan can_update.
     */
    public function revise($id): JsonResponse
    {
        $source = PurchaseOrder::with(['items.goodsRequestItem' => fn ($q) => $q->with(array_merge(['goodsRequest'], GoodsRequestItem::FULFILLMENT_RELATIONS))])
            ->findOrFail($id);

        if ($source->status !== PurchaseOrder::STATUS_REJECTED) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order Rejected yang bisa direvisi.'], 422);
        }
        if ($existing = $source->revisions()->first()) {
            return response()->json(['success' => false, 'message' => 'PO ini sudah direvisi menjadi '.$existing->po_number.'.', 'id' => $existing->id], 422);
        }

        $rows = [];
        $skipped = 0;
        foreach ($source->items as $item) {
            $requestItem = $item->goodsRequestItem;
            $qty = $requestItem && $requestItem->goodsRequest?->status === GoodsRequest::STATUS_APPROVED
                ? min((int) $item->qty, $requestItem->remainingQty())
                : 0;
            if ($qty < 1) {
                $skipped++;

                continue;
            }
            $rows[] = [
                'goods_request_item_id' => $requestItem->id,
                'description' => $item->description,
                'qty' => $qty,
                'price' => $item->price,
            ];
        }

        if (! $rows) {
            return response()->json(['success' => false, 'message' => 'Semua item PO ini sudah diproses di PO lain atau sisa kebutuhannya ditutup — tidak ada yang bisa direvisi.'], 422);
        }

        $revision = $this->createWithNumber(fn () => DB::transaction(function () use ($source, $rows) {
            $po = $source->replicate(['po_number', 'status', 'final_checked_by', 'approval_note', 'approved_at', 'rejected_at', 'cancel_reason', 'cancelled_by', 'cancelled_at']);
            $po->fill([
                'po_number' => PurchaseOrder::revisionNumber($source->po_number ?: PurchaseOrder::generatePoNumber($source->po_type), $source->po_type),
                'revision_of_id' => $source->id,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ])->save();

            $this->syncItems($po, $rows);

            return $po;
        }));

        $this->log($revision, 'revise_purchase_order', 'dibuat sebagai revisi dari PO '.($source->po_number ?: '#'.$source->id));

        return response()->json([
            'success' => true,
            'message' => 'Revisi '.$revision->po_number.' dibuat sebagai draft.'.($skipped ? ' '.$skipped.' item dilewati karena sudah diproses di PO lain.' : ''),
            'id' => $revision->id,
            'redirect' => route('purchase-order.edit', $revision->id),
        ]);
    }

    /**
     * Batalkan PO Approved yang belum diterima penuh — hanya pemegang can_approve
     * PO, alasan wajib. Barang yang sudah diterima tetap tercatat; sisa yang
     * belum diterima dilepas kembali ke Permintaan Barang.
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        // Route purchase-order.void -> middleware mewajibkan can_approve.
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if (! $purchaseOrder->canBeCancelled()) {
            return response()->json(['success' => false, 'message' => 'Hanya PO Approved yang belum diterima penuh yang bisa dibatalkan.'], 422);
        }

        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:1000',
        ], ['cancel_reason.required' => 'Alasan pembatalan wajib diisi.']);

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'cancel_reason' => $validated['cancel_reason'],
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
        ]);
        $this->log($purchaseOrder, 'cancel_purchase_order', 'dibatalkan: '.$validated['cancel_reason']);

        return response()->json(['success' => true, 'message' => 'Purchase order dibatalkan. Sisa yang belum diterima kembali ke Permintaan Barang.']);
    }

    /**
     * Item Permintaan Barang Approved yang masih punya sisa kebutuhan (setelah
     * stok, PO berlaku, dan tutup sisa) — untuk modal "Ambil dari Permintaan
     * Barang". Stok bebas produk ikut dikirim sebagai INFORMASI (user tetap
     * boleh membeli). Filter opsional project_id / purpose internal.
     */
    public function fetchAvailableItems(Request $request): JsonResponse
    {
        $exclude = array_filter((array) $request->input('exclude_goods_request_item_ids', []));

        $requests = GoodsRequest::with([
            'items' => fn ($q) => $q->with(array_merge(['masterProduct.currency'], GoodsRequestItem::FULFILLMENT_RELATIONS))
                ->whereNotNull('master_product_id')
                ->when($exclude, fn ($q) => $q->whereNotIn('id', $exclude)),
            'division', 'project',
        ])
            ->where('status', GoodsRequest::STATUS_APPROVED)
            ->orderByDesc('id')
            ->get()
            ->map(function (GoodsRequest $gr) {
                $gr->setRelation('items', $gr->items->filter(fn (GoodsRequestItem $item) => $item->remainingQty() > 0)->values());

                return $gr;
            })
            ->filter(fn (GoodsRequest $gr) => $gr->items->isNotEmpty())
            ->values();

        $projects = $requests->filter->isForProject()->map(fn (GoodsRequest $gr) => $gr->project)->filter()->unique('id')
            ->sortBy('project_number')
            ->map(fn ($project) => [
                'id' => $project->id,
                'text' => $project->project_number.' — '.$project->project_name,
            ])->values();

        $projectFilter = $request->input('project_id');
        if ($projectFilter === 'internal') {
            $requests = $requests->reject->isForProject()->values();
        } elseif ($projectFilter) {
            $requests = $requests->where('project_id', (int) $projectFilter)->values();
        }

        $freeStock = $this->freeStockByProduct($requests->flatMap->items->pluck('master_product_id')->unique()->all());

        $data = $requests->map(function (GoodsRequest $gr) use ($freeStock) {
            $groupLabel = $gr->isForProject() ? ($gr->project?->project_name ?? 'Project') : GoodsRequest::PURPOSE_LABELS[GoodsRequest::PURPOSE_INTERNAL];

            return [
                'goods_request_id' => $gr->id,
                'request_number' => $gr->number_label,
                'purpose' => $gr->purpose,
                'project_id' => $gr->isForProject() ? $gr->project_id : null,
                'project_number' => $gr->isForProject() ? $gr->project?->project_number : null,
                'project_name' => $groupLabel,
                'division_name' => $gr->division?->division_name,
                'items' => $gr->items->map(fn (GoodsRequestItem $item) => [
                    'goods_request_item_id' => $item->id,
                    'goods_request_id' => $gr->id,
                    'master_product_id' => $item->master_product_id,
                    'product_name' => $item->masterProduct?->name,
                    'project_name' => $groupLabel,
                    'part_number' => $item->part_number,
                    'description' => Quotation::renderDescription($item->description),
                    'qty' => $item->remainingQty(),
                    'remaining_qty' => $item->remainingQty(),
                    'requested_qty' => (int) $item->qty,
                    'unit' => $item->unit,
                    'currency' => GoodsRequestItem::normalizeCurrency($item->currency),
                    'price' => $this->defaultPrice($item),
                    'free_stock' => $freeStock[$item->master_product_id] ?? 0,
                ])->values(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data, 'projects' => $projects]);
    }

    private function validatePurchaseOrder(Request $request): array
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:200',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_address' => 'nullable|string',
            'supplier_phone' => 'nullable|string|max:50',
            'supplier_fax' => 'nullable|string|max:50',
            'supplier_attn' => 'nullable|string|max:150',
            'po_type' => 'nullable|in:'.implode(',', array_keys(PurchaseOrder::TYPE_LABELS)),
            'date' => 'nullable|date',
            'terms' => 'nullable|string|max:100',
            'payment_term_days' => 'nullable|integer|min:0|max:365',
            'delivery' => 'nullable|string|max:150',
            'your_ref' => 'nullable|string|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'ppn_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'request_by_name' => 'nullable|string|max:150',
            'finance_name' => 'nullable|string|max:150',
            'accounting_name' => 'nullable|string|max:150',
            'items' => 'nullable|array',
            'items.*.goods_request_item_id' => 'required|integer|exists:goods_request_items,id',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
        ], [
            'items.*.goods_request_item_id.required' => 'Setiap baris PO harus berasal dari item Permintaan Barang.',
            'items.*.qty.min' => 'Qty baris PO minimal 1.',
        ]);

        $validated['po_type'] ??= PurchaseOrder::TYPE_MANUFACTURE;
        // Termin bayar (hari) = dasar jatuh tempo tagihan; kosong -> termin supplier.
        $validated['payment_term_days'] ??= isset($validated['supplier_id'])
            ? Supplier::whereKey($validated['supplier_id'])->value('payment_term_days')
            : null;

        return $validated;
    }

    /**
     * Buat PO dengan nomor otomatis. Bila dua request mendapat nomor yang sama
     * bersamaan, unique index po_number menolak salah satunya — dicoba ulang
     * dengan nomor berikutnya.
     */
    private function createWithNumber(callable $create, int $attempts = 3): PurchaseOrder
    {
        for ($i = 1; ; $i++) {
            try {
                return $create();
            } catch (QueryException $e) {
                $duplicateNumber = str_contains($e->getMessage(), 'po_number');
                if (! $duplicateNumber || $i >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Simpan baris PO dari item Permintaan Barang. Divalidasi ulang di server:
     * permintaan Approved, item punya master product, qty <= sisa kebutuhan
     * (qty baris PO ini sendiri dikembalikan dulu saat edit), dan satu mata uang.
     * Produk, unit, dan mata uang dikunci mengikuti item permintaan.
     */
    private function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $items = array_values($items);
        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));

        $requestItems = GoodsRequestItem::with(array_merge(['goodsRequest'], GoodsRequestItem::FULFILLMENT_RELATIONS))
            ->whereIn('id', collect($items)->pluck('goods_request_item_id'))
            ->get()
            ->keyBy('id');

        // Qty baris PO ini sendiri tidak dihitung sebagai pemakai sisa (saat edit).
        $ownQty = $purchaseOrder->exists
            ? $purchaseOrder->items()->get()->groupBy('goods_request_item_id')->map->sum('qty')
            : collect();

        $requestedQty = [];
        $payload = [];
        $now = now();

        foreach ($items as $i => $row) {
            $requestItem = $requestItems->get((int) $row['goods_request_item_id']);
            $label = 'Baris '.($i + 1);

            if (! $requestItem || $requestItem->goodsRequest?->status !== GoodsRequest::STATUS_APPROVED) {
                $fail($label.': item Permintaan Barang tidak valid atau belum Approved.');
            }
            if (! $requestItem->master_product_id) {
                $fail($label.': item permintaan belum terhubung master produk. Petakan produknya dulu di Permintaan Barang.');
            }

            $requestedQty[$requestItem->id] = ($requestedQty[$requestItem->id] ?? 0) + (int) $row['qty'];
            $available = $requestItem->remainingQty() + (int) ($ownQty[$requestItem->id] ?? 0);
            if ($requestedQty[$requestItem->id] > $available) {
                $fail($label.' ('.($requestItem->part_number ?: 'item').'): qty melebihi sisa kebutuhan ('.$available.').');
            }

            $price = isset($row['price']) && $row['price'] !== '' ? round((float) $row['price'], 2) : $this->defaultPrice($requestItem);

            $payload[] = [
                'purchase_order_id' => $purchaseOrder->id,
                'goods_request_item_id' => $requestItem->id,
                'master_product_id' => $requestItem->master_product_id,
                'part_number' => $requestItem->part_number,
                'description' => Quotation::sanitizeDescription($row['description'] ?? $requestItem->description ?? ''),
                'qty' => (int) $row['qty'],
                'unit' => $requestItem->unit,
                'price' => $price,
                'currency' => GoodsRequestItem::normalizeCurrency($requestItem->currency),
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Satu PO hanya boleh satu mata uang (mis. tidak boleh campur IDR dan USD).
        $currencies = collect($payload)->pluck('currency')->unique()->values();
        if ($currencies->count() > 1) {
            $fail('Semua item dalam satu PO harus memakai mata uang yang sama. Ditemukan: '.$currencies->implode(', ').'. Pisahkan ke PO berbeda.');
        }

        $purchaseOrder->items()->delete();
        if ($payload) {
            PurchaseOrderItem::insert($payload);
        }
    }

    /**
     * Harga default = harga beli master product, hanya bila mata uang produk sama
     * dengan mata uang item permintaan (tanpa kurs). Selain itu dikosongkan.
     */
    private function defaultPrice(GoodsRequestItem $item): ?float
    {
        $product = $item->relationLoaded('masterProduct') ? $item->masterProduct : $item->masterProduct()->with('currency')->first();
        if (! $product) {
            return null;
        }

        $productCurrency = GoodsRequestItem::normalizeCurrency($product->currency?->name);

        return $productCurrency === GoodsRequestItem::normalizeCurrency($item->currency)
            ? round((float) $product->purchase_price, 2)
            : null;
    }

    /**
     * Stok bebas (semua gudang) per produk — hanya informasi di picker.
     *
     * @return array<int,int>
     */
    private function freeStockByProduct(array $productIds): array
    {
        if (! $productIds) {
            return [];
        }

        return StockMovement::whereIn('master_product_id', $productIds)
            ->whereNull('project_id')
            ->get(['master_product_id', 'type', 'qty'])
            ->groupBy('master_product_id')
            ->map(fn ($rows) => max(0, $rows->sum(fn (StockMovement $m) => $m->isIn() ? $m->qty : -$m->qty)))
            ->all();
    }

    private function lineForForm(PurchaseOrderItem $item): array
    {
        $requestItem = $item->goodsRequestItem;
        $request = $requestItem?->goodsRequest;

        return [
            'goods_request_item_id' => $item->goods_request_item_id,
            'goods_request_id' => $request?->id,
            'request_number' => $request?->number_label,
            'master_product_id' => $item->master_product_id,
            'project_name' => $request && $request->isForProject() ? ($request->project?->project_name ?? 'Project') : GoodsRequest::PURPOSE_LABELS[GoodsRequest::PURPOSE_INTERNAL],
            'part_number' => $item->part_number,
            'description' => Quotation::renderDescription($item->description),
            'qty' => $item->qty,
            // Batas qty saat edit = sisa kebutuhan + qty baris ini sendiri.
            'remaining_qty' => ($requestItem?->remainingQty() ?? 0) + (int) $item->qty,
            'requested_qty' => (int) ($requestItem?->qty ?? 0),
            'unit' => $item->unit,
            'price' => $item->price,
            'currency' => $item->currency,
            'source_label' => $request ? 'Permintaan '.$request->number_label.' / '.($request->division?->division_name ?? '-') : null,
        ];
    }

    /**
     * Filter status bayar: unbilled (belum ada tagihan terverifikasi), unpaid (ada
     * tagihan terbuka), paid (semua tagihan terverifikasi lunas).
     */
    private function filterPayStatus($query, string $status)
    {
        $active = fn ($q) => $q->whereNotIn('status', [SupplierBill::STATUS_DRAFT, SupplierBill::STATUS_VOID]);

        return match ($status) {
            'unbilled' => $query->whereDoesntHave('supplierBills', $active),
            'unpaid' => $query->whereHas('supplierBills', fn ($q) => $q->whereIn('status', SupplierBill::OPEN_STATUSES)),
            'paid' => $query->whereHas('supplierBills', fn ($q) => $q->where('status', SupplierBill::STATUS_PAID))
                ->whereDoesntHave('supplierBills', fn ($q) => $q->whereIn('status', SupplierBill::OPEN_STATUSES)),
            default => $query,
        };
    }

    protected function documentOwner($id): Model
    {
        return PurchaseOrder::findOrFail($id);
    }

    protected function documentRoutePrefix(): string
    {
        return 'purchase-order';
    }

    protected function documentModuleCode(): string
    {
        return 'MOD_PURCHASE_ORDER';
    }

    private function log(PurchaseOrder $purchaseOrder, string $action, string $what): void
    {
        Log::record(
            $action,
            'Purchase order '.($purchaseOrder->po_number ?: '#'.$purchaseOrder->id).' '.$what.' oleh '.Auth::user()->display_name,
            'MOD_PURCHASE_ORDER',
            $purchaseOrder
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\Module;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\UserAccessControl;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul "Purchase Order": PO barang ke supplier, disusun dari item Permintaan
 * Barang yang sudah Approved. Satu PO boleh menggabungkan item dari beberapa
 * Permintaan Barang berbeda — termasuk lintas divisi — selama tujuannya
 * supplier yang sama. Satu item Permintaan Barang hanya boleh masuk ke SATU
 * PO manapun (dijaga unique constraint di goods_request_items.id pada
 * purchase_order_items, plus dicek ulang di controller). Harga baru muncul
 * di modul ini — sesuai keputusan bahwa Permintaan Barang tidak perlu tahu
 * harga, harga ditentukan saat PO ke supplier.
 */
class PurchaseOrderController extends Controller
{
    private const MODULE_CODE = 'MOD_PURCHASE_ORDER';

    public function index()
    {
        return view('purchase-order.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = PurchaseOrder::withCount('items')->with('creator');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('supplier_name', 'like', "%{$searchValue}%")
                    ->orWhere('po_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $orders = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $isApprover = $this->isApprover();

        $data = [];
        foreach ($orders as $i => $po) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $po->id,
                'supplier_name' => $po->supplier_name,
                'po_number' => $po->po_number ?: '—',
                'date' => $po->date?->format('d/m/Y') ?? '—',
                'item_count' => $po->items_count,
                'grand_total_label' => number_format($po->grandTotal(), 0, '.', ','),
                'creator_name' => $po->creator?->username ?? '—',
                'status' => $po->status,
                'status_label' => $po->status_label,
                'status_badge' => $po->statusBadgeHtml(),
                'can_approve' => $isApprover && $po->status === PurchaseOrder::STATUS_WAITING_APPROVAL,
                'is_creator' => (int) $po->created_by === (int) Auth::id(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
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
        if (! $this->hasModuleAccess()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk membuat purchase order.'], 403);
        }

        $validated = $this->validatePurchaseOrder($request);

        $purchaseOrder = DB::transaction(function () use ($validated) {
            $po = PurchaseOrder::create([
                'supplier_name' => $validated['supplier_name'],
                'po_number' => $validated['po_number'] ?? null,
                'date' => $validated['date'] ?? null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($po, $validated['items'] ?? []);

            return $po;
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase order berhasil dibuat.',
            'id' => $purchaseOrder->id,
        ]);
    }

    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::with(['items.goodsRequest.division', 'items.goodsRequest.opportunity', 'items.goodsRequest.quotation', 'creator', 'finalChecker'])->findOrFail($id);

        return view('purchase-order.show', [
            'purchaseOrder' => $purchaseOrder,
            'canApprove' => $this->isApprover(),
            'canUpdate' => $this->hasModuleAccess(),
        ]);
    }

    public function edit($id)
    {
        $purchaseOrder = PurchaseOrder::with(['items'])->findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return redirect()->route('purchase-order.index')
                ->with('error', 'Hanya purchase order berstatus Draft yang bisa diedit.');
        }

        return view('purchase-order.form', [
            'purchaseOrder' => $purchaseOrder,
            'items' => $purchaseOrder->items->map(fn ($item) => [
                'id' => $item->id,
                'goods_request_item_id' => $item->goods_request_item_id,
                'goods_request_id' => $item->goods_request_id,
                'part_number' => $item->part_number,
                'description' => Quotation::renderDescription($item->description),
                'qty' => $item->qty,
                'unit' => $item->unit,
                'price' => $item->price,
                'price_currency' => $item->price_currency,
                'currency' => $item->currency,
            ])->all(),
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order berstatus Draft yang bisa diubah.'], 422);
        }

        if (! $this->hasModuleAccess()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk mengubah purchase order.'], 403);
        }

        $validated = $this->validatePurchaseOrder($request);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $purchaseOrder->update([
                'supplier_name' => $validated['supplier_name'],
                'po_number' => $validated['po_number'] ?? null,
                'date' => $validated['date'] ?? null,
                'notes' => $validated['notes'] ?? null,
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
        // dicek ulang lewat hasModuleAccess() (can_create/can_update) di sini —
        // itu justru salah menolak user yang hanya diberi can_delete.

        $purchaseOrder->delete();

        return response()->json(['success' => true, 'message' => 'Purchase order dihapus.']);
    }

    public function submit($id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya purchase order berstatus Draft yang bisa di-submit.'], 422);
        }

        if (! $this->hasModuleAccess()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk submit purchase order.'], 403);
        }

        if ($purchaseOrder->items()->count() === 0) {
            return response()->json(['success' => false, 'message' => 'Tambahkan minimal satu item sebelum submit.'], 422);
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_WAITING_APPROVAL]);

        return response()->json(['success' => true, 'message' => 'Purchase order dikirim untuk approval.']);
    }

    public function approve($id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Purchase order ini tidak sedang menunggu approval.'], 422);
        }

        if (! $this->isApprover()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak approve pada modul ini.'], 403);
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'final_checked_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Purchase order disetujui.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        if ($purchaseOrder->status !== PurchaseOrder::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Purchase order ini tidak sedang menunggu approval.'], 422);
        }

        if (! $this->isApprover()) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak approve pada modul ini.'], 403);
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

        return response()->json(['success' => true, 'message' => 'Purchase order ditolak.']);
    }

    /**
     * Item dari SEMUA Permintaan Barang berstatus Approved yang belum pernah
     * dipakai di Purchase Order manapun — lintas divisi/opportunity, untuk
     * modal "Ambil dari Permintaan Barang". Dikelompokkan per Permintaan
     * Barang saat dikirim balik supaya jelas asalnya.
     */
    public function fetchAvailableItems(Request $request): JsonResponse
    {
        $excludeGoodsRequestItemIds = [];
        if ($request->filled('exclude_goods_request_item_ids')) {
            $excludeGoodsRequestItemIds = array_filter((array) $request->input('exclude_goods_request_item_ids'));
        }

        $requests = GoodsRequest::with(['items' => function ($q) use ($excludeGoodsRequestItemIds) {
            $q->whereDoesntHave('purchaseOrderItem');
            if (! empty($excludeGoodsRequestItemIds)) {
                $q->whereNotIn('id', $excludeGoodsRequestItemIds);
            }
        }, 'division', 'opportunity', 'quotation'])
            ->where('status', GoodsRequest::STATUS_APPROVED)
            ->get()
            ->filter(fn (GoodsRequest $gr) => $gr->items->isNotEmpty())
            ->values();

        $data = $requests->map(fn (GoodsRequest $gr) => [
            'goods_request_id' => $gr->id,
            'division_name' => $gr->division?->division_name,
            'opportunity_name' => $gr->opportunity?->opportunity_name,
            'quotation_number' => $gr->quotation?->quotation_number,
            'items' => $gr->items->map(fn (GoodsRequestItem $item) => [
                'goods_request_item_id' => $item->id,
                'part_number' => $item->part_number,
                'description' => Quotation::renderDescription($item->description),
                'qty' => $item->qty,
                'unit' => $item->unit,
            ])->values(),
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function validatePurchaseOrder(Request $request): array
    {
        return $request->validate([
            'supplier_name' => 'required|string|max:200',
            'po_number' => 'nullable|string|max:50',
            'date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.goods_request_item_id' => 'nullable|integer|exists:goods_request_items,id',
            'items.*.goods_request_id' => 'nullable|integer|exists:goods_requests,id',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.price_currency' => 'nullable|numeric|min:0',
            'items.*.currency' => 'nullable|string|max:10',
        ]);
    }

    /**
     * Simpan item PO. goods_request_item_id divalidasi ulang di sini (selain
     * unique constraint di DB) supaya pesan error jelas kalau ternyata sudah
     * dipakai PO lain sejak form dibuka (race condition).
     */
    private function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $goodsRequestItemIds = collect($items)->pluck('goods_request_item_id')->filter()->values();

        if ($goodsRequestItemIds->isNotEmpty()) {
            $alreadyOrdered = PurchaseOrderItem::whereIn('goods_request_item_id', $goodsRequestItemIds)
                ->where('purchase_order_id', '!=', $purchaseOrder->id)
                ->exists();

            if ($alreadyOrdered) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Salah satu item sudah dipakai di Purchase Order lain. Muat ulang halaman dan coba lagi.',
                ], 422));
            }
        }

        $purchaseOrder->items()->delete();

        $payload = [];
        foreach (array_values($items) as $i => $item) {
            $payload[] = [
                'purchase_order_id' => $purchaseOrder->id,
                'goods_request_item_id' => $item['goods_request_item_id'] ?? null,
                'goods_request_id' => $item['goods_request_id'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => Quotation::sanitizeDescription($item['description'] ?? ''),
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'unit' => $item['unit'] ?? null,
                'price' => $item['price'] ?? null,
                'price_currency' => $item['price_currency'] ?? null,
                'currency' => $item['currency'] ?? null,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($payload)) {
            return;
        }

        try {
            PurchaseOrderItem::insert($payload);
        } catch (QueryException $e) {
            abort(response()->json([
                'success' => false,
                'message' => 'Salah satu item sudah dipakai di Purchase Order lain. Muat ulang halaman dan coba lagi.',
            ], 422));
        }
    }

    /**
     * User berhak create/update modul Purchase Order (can_create/can_update atau Admin).
     */
    private function hasModuleAccess(): bool
    {
        $user = Auth::user();

        if ($user->role === 'Admin') {
            return true;
        }

        $module = Module::where('module_code', self::MODULE_CODE)->first();
        if (! $module) {
            return false;
        }

        return UserAccessControl::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where(fn ($q) => $q->where('can_create', true)->orWhere('can_update', true))
            ->exists();
    }

    /**
     * User berhak approve modul Purchase Order (can_approve atau Admin).
     */
    private function isApprover(): bool
    {
        $user = Auth::user();

        if ($user->role === 'Admin') {
            return true;
        }

        $module = Module::where('module_code', self::MODULE_CODE)->first();
        if (! $module) {
            return false;
        }

        return UserAccessControl::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->where('can_approve', true)
            ->exists();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\GoodsRequest;
use App\Models\GoodsRequestItem;
use App\Models\MasterProduct;
use App\Models\QuoteConfiguration;
use App\Models\Quotation;
use App\Support\ModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul "Permintaan Barang": pengajuan permintaan barang ke purchasing.
 * Terikat pada Opportunity (satu opportunity bisa punya banyak configuration
 * lintas divisi), yang mana opportunity itu terikat pada Quotation yang PO
 * Supplier Approval-nya sudah disetujui 2 approver
 * (Quotation::isReadyForSupplierPo()). opportunity_id selalu diturunkan dari
 * quotation yang dipilih (bukan input terpisah), dan dipakai untuk memfilter
 * item configuration yang boleh diambil ke Permintaan Barang — hanya
 * configuration approved milik opportunity yang sama. Satu Quotation boleh
 * punya lebih dari satu Permintaan Barang. Approval di sini satu approver
 * saja, cukup berdasarkan hak can_approve pada modul ini (tidak dibatasi
 * divisi).
 */
class GoodsRequestController extends Controller
{
    private const MODULE_CODE = 'MOD_GOODS_REQUEST';

    public function index()
    {
        return view('goods-request.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = GoodsRequest::with(['quotation', 'opportunity', 'division', 'creator']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->whereHas('quotation', fn ($qq) => $qq->where('quotation_number', 'like', "%{$searchValue}%")
                    ->orWhere('to_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('opportunity', fn ($o) => $o->where('opportunity_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('division', fn ($d) => $d->where('division_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $requests = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $canApprove = ModuleAccess::for()->module(self::MODULE_CODE)->canApprove();

        $data = [];
        foreach ($requests as $i => $gr) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $gr->id,
                'quotation_number' => $gr->quotation?->quotation_number ?? '—',
                'to_name' => $gr->quotation?->to_name ?? '—',
                'opportunity_name' => $gr->opportunity?->opportunity_name ?? '—',
                'division_name' => $gr->division?->division_name ?? '—',
                'item_count' => $gr->items()->count(),
                'creator_name' => $gr->creator?->username ?? '—',
                'status' => $gr->status,
                'status_label' => $gr->status_label,
                'status_badge' => $gr->statusBadgeHtml(),
                'can_approve' => $canApprove && $gr->status === GoodsRequest::STATUS_WAITING_APPROVAL,
                'is_creator' => (int) $gr->created_by === (int) Auth::id(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $quotations = $this->eligibleQuotations();

        $preselected = null;
        if ($request->filled('quotation_id')) {
            $preselected = $quotations->firstWhere('id', (int) $request->input('quotation_id'));
        }

        return view('goods-request.form', [
            'goodsRequest' => null,
            'quotations' => $quotations,
            'preselected' => $preselected,
            'items' => [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateGoodsRequest($request);

        $quotation = Quotation::findOrFail($validated['quotation_id']);
        if (! $this->quotationIsEligible($quotation)) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation ini belum boleh diajukan permintaan barang (PO Supplier Approval belum disetujui 2 approver, atau quotation belum terikat opportunity).',
            ], 422);
        }

        $goodsRequest = DB::transaction(function () use ($validated, $quotation) {
            $gr = GoodsRequest::create([
                'opportunity_id' => $quotation->opportunity_id,
                'quotation_id' => $validated['quotation_id'],
                'division_id' => Auth::user()->division_id,
                'status' => GoodsRequest::STATUS_DRAFT,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($gr, $validated['items'] ?? []);

            return $gr;
        });

        return response()->json([
            'success' => true,
            'message' => 'Permintaan barang berhasil dibuat.',
            'id' => $goodsRequest->id,
        ]);
    }

    public function show($id)
    {
        $goodsRequest = GoodsRequest::with(['items', 'quotation', 'opportunity', 'division', 'creator', 'finalChecker'])->findOrFail($id);

        return view('goods-request.show', [
            'goodsRequest' => $goodsRequest,
            'canApprove' => ModuleAccess::for()->module(self::MODULE_CODE)->canApprove(),
            'canUpdate' => ModuleAccess::for()->module(self::MODULE_CODE)->canManage(),
        ]);
    }

    public function edit($id)
    {
        $goodsRequest = GoodsRequest::with(['items', 'quotation.opportunity'])->findOrFail($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_DRAFT) {
            return redirect()->route('goods-request.index')
                ->with('error', 'Hanya permintaan barang berstatus Draft yang bisa diedit.');
        }

        $quotations = $this->eligibleQuotations();
        if (! $quotations->contains('id', $goodsRequest->quotation_id) && $goodsRequest->quotation) {
            $quotations->prepend($goodsRequest->quotation);
        }

        return view('goods-request.form', [
            'goodsRequest' => $goodsRequest,
            'quotations' => $quotations,
            'preselected' => $goodsRequest->quotation,
            'items' => $goodsRequest->items->map(fn ($item) => [
                'id' => $item->id,
                'quote_configuration_item_id' => $item->quote_configuration_item_id,
                'master_product_id' => $item->master_product_id,
                'part_number' => $item->part_number,
                'description' => Quotation::renderDescription($item->description),
                'qty' => $item->qty,
                'unit' => $item->unit,
            ])->all(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $goodsRequest = GoodsRequest::findOrFail($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan barang berstatus Draft yang bisa diubah.'], 422);
        }

        $validated = $this->validateGoodsRequest($request);

        // Quotation (dan opportunity turunannya) tidak bisa diganti setelah dibuat —
        // UI menonaktifkan field ini; nilai yang dikirim client diabaikan di sini.
        DB::transaction(function () use ($goodsRequest, $validated) {
            $goodsRequest->update([
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncItems($goodsRequest, $validated['items'] ?? []);
        });

        return response()->json([
            'success' => true,
            'message' => 'Permintaan barang berhasil diperbarui.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $goodsRequest = GoodsRequest::findOrFail($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan barang berstatus Draft yang bisa dihapus.'], 422);
        }

        // Middleware (DELETE -> can_delete) sudah menjaga izin hapus; tidak perlu
        // dicek ulang di sini — itu justru salah menolak user yang hanya diberi can_delete.

        $goodsRequest->delete();

        return response()->json(['success' => true, 'message' => 'Permintaan barang dihapus.']);
    }

    public function submit($id): JsonResponse
    {
        $goodsRequest = GoodsRequest::findOrFail($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan barang berstatus Draft yang bisa di-submit.'], 422);
        }

        if ($goodsRequest->items()->count() === 0) {
            return response()->json(['success' => false, 'message' => 'Tambahkan minimal satu item sebelum submit.'], 422);
        }

        $goodsRequest->update(['status' => GoodsRequest::STATUS_WAITING_APPROVAL]);

        return response()->json(['success' => true, 'message' => 'Permintaan barang dikirim untuk approval.']);
    }

    public function approve($id): JsonResponse
    {
        $goodsRequest = GoodsRequest::findOrFail($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Permintaan barang ini tidak sedang menunggu approval.'], 422);
        }

        $goodsRequest->update([
            'status' => GoodsRequest::STATUS_APPROVED,
            'final_checked_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Permintaan barang disetujui.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $goodsRequest = GoodsRequest::findOrFail($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Permintaan barang ini tidak sedang menunggu approval.'], 422);
        }

        $validated = $request->validate([
            'approval_note' => 'required|string',
        ]);

        $goodsRequest->update([
            'status' => GoodsRequest::STATUS_REJECTED,
            'final_checked_by' => Auth::id(),
            'approval_note' => $validated['approval_note'],
            'rejected_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Permintaan barang ditolak.']);
    }

    /**
     * Item configuration (live master data) milik SEMUA configuration approved
     * di bawah satu opportunity — lintas divisi (Water, IMS, dst), bukan
     * dibatasi ke satu quotation — untuk modal "Ambil dari Configuration"
     * pada form Permintaan Barang. Urutan mengikuti hierarki parent -> children
     * (QuoteConfiguration::flattenTree(), sama seperti tampilan configuration
     * aslinya), bukan dikelompokkan per kategori.
     */
    public function fetchConfigItems(Request $request): JsonResponse
    {
        $request->validate(['opportunity_id' => 'required|integer|exists:opportunities,id']);

        $configs = QuoteConfiguration::with(['items', 'division'])
            ->where('opportunity_id', $request->input('opportunity_id'))
            ->where('status', QuoteConfiguration::STATUS_APPROVED)
            ->where('is_current', true)
            ->orderBy('division_id')
            ->get();

        $items = collect();
        foreach ($configs as $config) {
            foreach ($config->flattenTree() as $row) {
                $item = $row['item'];
                $items->push([
                    'quote_configuration_item_id' => $item->id,
                    'quote_configuration_id' => $config->id,
                    'parent_id' => $item->parent_id,
                    'depth' => $row['depth'],
                    'item_no' => $item->item_no,
                    'part_number' => $item->part_number,
                    'description' => Quotation::renderDescription($item->description),
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'division_name' => $config->division?->division_name,
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $items->values()]);
    }

    /**
     * Katalog Master Product (aktif saja) untuk modal "Tambah Baris Manual"
     * pada form Permintaan Barang — hanya part number, nama/deskripsi, dan
     * divisi yang ditampilkan; harga sengaja TIDAK disertakan (harga baru
     * ditentukan saat Purchase Order ke supplier, bukan di tahap ini).
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $searchValue = $request->input('search.value', '');

        $query = MasterProduct::query()
            ->with('division')
            ->where('division_id', Auth::user()->division_id)
            ->where('status', 'Active')
            ->orderBy('name');

        if ($searchValue) {
            $query->where(function ($builder) use ($searchValue) {
                $builder->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhere('code', 'like', "%{$searchValue}%")
                    ->orWhere('brand', 'like', "%{$searchValue}%")
                    ->orWhere('category', 'like', "%{$searchValue}%");
            });
        }

        $products = $query->limit(100)->get(['id', 'name', 'code', 'division_id', 'description']);

        $data = $products->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'description' => Quotation::renderDescription($product->description),
            'division_name' => $product->division?->division_name,
        ])->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Quotation yang boleh diajukan Permintaan Barang: status Finish dan
     * kuorum PO Supplier Approval (2 approver berbeda) sudah terpenuhi.
     */
    private function eligibleQuotations(): \Illuminate\Database\Eloquent\Collection
    {
        return Quotation::with(['poSupplierApprovals', 'opportunity'])
            ->where('status', Quotation::STATUS_FINISH)
            ->whereNotNull('opportunity_id')
            ->orderBy('id', 'desc')
            ->get()
            ->filter(fn (Quotation $q) => $q->isReadyForSupplierPo())
            ->values();
    }

    private function quotationIsEligible(Quotation $quotation): bool
    {
        return $quotation->status === Quotation::STATUS_FINISH
            && $quotation->opportunity_id !== null
            && $quotation->isReadyForSupplierPo();
    }

    private function validateGoodsRequest(Request $request): array
    {
        return $request->validate([
            'quotation_id' => 'required|integer|exists:quotations,id',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.quote_configuration_item_id' => 'nullable|integer|exists:quote_configuration_items,id',
            'items.*.master_product_id' => 'nullable|integer|exists:master_products,id',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.unit' => 'nullable|string|max:50',
        ]);
    }

    private function syncItems(GoodsRequest $goodsRequest, array $items): void
    {
        $goodsRequest->items()->delete();

        $payload = [];
        foreach (array_values($items) as $i => $item) {
            $payload[] = [
                'goods_request_id' => $goodsRequest->id,
                'quote_configuration_item_id' => $item['quote_configuration_item_id'] ?? null,
                'master_product_id' => $item['master_product_id'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => Quotation::sanitizeDescription($item['description'] ?? ''),
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'unit' => $item['unit'] ?? null,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($payload)) {
            GoodsRequestItem::insert($payload);
        }
    }
}

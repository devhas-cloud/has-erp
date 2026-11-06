<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Division;
use App\Models\GoodsRequest;
use App\Models\GoodsRequestApproval;
use App\Models\GoodsRequestItem;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuoteConfiguration;
use App\Models\QuoteConfigurationItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul "Permintaan Barang" (Material Request), titik awal purchasing & inventori.
 * Tujuan:
 *   - project  : terikat Project aktif yang PO Supplier Approval-nya sudah
 *                disetujui 2 approver; barang nantinya dialokasikan ke project;
 *   - internal : stok / kebutuhan internal, tanpa project.
 * Setiap item WAJIB master product (agar bisa dipenuhi dari stok / PO / diterima
 * ke gudang). Quotation & opportunity diturunkan lewat project. Item configuration
 * dicocokkan ke master product lewat part number (= kode produk).
 *
 * Pemenuhan per item (setelah Approved) adalah pilihan user: dari stok (Keluar
 * Stok / alokasi) dan/atau PO — lihat GoodsRequestItem::remainingQty(). Sisa yang
 * tidak dibutuhkan lagi bisa "Tutup Sisa" oleh pembuat atau approver.
 *
 * Approval cukup 1 user can_approve (GoodsRequest::REQUIRED_APPROVALS), termasuk
 * pembuatnya sendiri; setelah itu status Approved dan item boleh diproses Purchase Order. Semua
 * akses per-ID memakai findVisible(): user tanpa can_approve hanya bisa
 * membuka permintaan divisinya.
 */
class GoodsRequestController extends Controller
{
    public function index()
    {
        $visible = GoodsRequest::visibleTo(Auth::user());

        return view('goods-request.index', [
            'projects' => Project::whereIn('id', (clone $visible)->whereNotNull('project_id')->select('project_id'))
                ->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
            'divisions' => Division::whereIn('id', (clone $visible)->whereNotNull('division_id')->select('division_id'))
                ->orderBy('division_name')->get(['id', 'division_name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = GoodsRequest::visibleTo($user)
            ->with(['project.company', 'project.quotation.opportunity', 'division', 'creator', 'approvals'])
            ->withCount('items')
            ->withSum('items', 'qty');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('request_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('project', fn ($pp) => $pp->where('project_number', 'like', "%{$searchValue}%")
                        ->orWhere('project_name', 'like', "%{$searchValue}%")
                        ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%")))
                    ->orWhereHas('division', fn ($d) => $d->where('division_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%")->orWhere('full_name', 'like', "%{$searchValue}%"));
            });
        }

        // Filter bar: tujuan, project, divisi, tanggal dibuat, milik saya.
        $query->when($request->input('purpose'), fn ($q, $purpose) => $q->where('purpose', $purpose))
            ->when($request->integer('project_id'), fn ($q, $projectId) => $q->where('project_id', $projectId))
            ->when($request->integer('division_id'), fn ($q, $divisionId) => $q->where('division_id', $divisionId))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', $user->id));

        // Jumlah per status mengikuti pencarian & filter, sebelum filter status diterapkan.
        $statusCounts = (clone $query)->reorder()->toBase()
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $status = $request->input('status');
        if ($status && array_key_exists($status, GoodsRequest::STATUS_LABELS)) {
            $query->where('status', $status);
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $requests = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();
        // Status pemenuhan hanya untuk permintaan Approved di halaman ini.
        $requests->where('status', GoodsRequest::STATUS_APPROVED)
            ->each(fn (GoodsRequest $gr) => $gr->load(['items' => fn ($q) => $q->with(GoodsRequestItem::FULFILLMENT_RELATIONS)]));

        $data = [];
        foreach ($requests as $i => $gr) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $gr->id,
                'request_number' => $gr->number_label,
                'purpose' => $gr->purpose,
                'purpose_label' => $gr->purpose_label,
                'project_number' => $gr->isForProject() ? ($gr->project?->project_number ?? '—') : 'Stok / Internal',
                'project_name' => $gr->isForProject() ? ($gr->project?->project_name ?? '—') : 'Kebutuhan stok / internal',
                'to_name' => $gr->project?->company?->account_name ?? $gr->quotation()?->to_name,
                'opportunity_name' => $gr->opportunity()?->opportunity_name,
                'fulfillment' => $gr->status === GoodsRequest::STATUS_APPROVED ? $gr->fulfillmentStatus() : null,
                'division_name' => $gr->division?->division_name ?? '—',
                'item_count' => (int) $gr->items_count,
                'total_qty' => (int) $gr->items_sum_qty,
                'creator_name' => $gr->creator?->display_name ?? '—',
                'created_at' => $gr->created_at?->format('d M Y'),
                'status' => $gr->status,
                'status_label' => $gr->status_label,
                'status_badge' => $gr->statusBadgeHtml(),
                'is_creator' => (int) $gr->created_by === (int) Auth::id(),
                'approval_count' => $gr->approvals->count(),
                'required_approvals' => GoodsRequest::REQUIRED_APPROVALS,
                'can_approve' => $gr->canBeApprovedBy($user),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => collect(GoodsRequest::STATUS_LABELS)
                ->map(fn ($label, $key) => (int) ($statusCounts[$key] ?? 0)),
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $projects = $this->eligibleProjects();

        $preselected = null;
        if ($request->filled('project_id')) {
            $preselected = $projects->firstWhere('id', (int) $request->input('project_id'));
        }

        return view('goods-request.form', [
            'goodsRequest' => null,
            'purpose' => $request->input('purpose') === GoodsRequest::PURPOSE_INTERNAL ? GoodsRequest::PURPOSE_INTERNAL : GoodsRequest::PURPOSE_PROJECT,
            'projects' => $projects,
            'preselected' => $preselected,
            'items' => [],
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateGoodsRequest($request, true);
        $forProject = $validated['purpose'] === GoodsRequest::PURPOSE_PROJECT;

        if ($duplicate = $this->rejectDuplicateConfigItems($validated['items'] ?? [])) {
            return $duplicate;
        }

        $project = $forProject ? Project::findOrFail($validated['project_id']) : null;
        if ($project && ! $this->projectIsEligible($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Project ini belum boleh diajukan permintaan barang (harus project aktif dan PO Supplier Approval sudah disetujui 2 approver).',
            ], 422);
        }

        $goodsRequest = DB::transaction(function () use ($validated, $project) {
            $gr = GoodsRequest::create([
                'request_number' => GoodsRequest::generateNumber(),
                'purpose' => $validated['purpose'],
                'project_id' => $project?->id,
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
            'message' => 'Permintaan barang '.$goodsRequest->request_number.' berhasil dibuat.',
            'id' => $goodsRequest->id,
        ]);
    }

    public function show($id)
    {
        $goodsRequest = $this->findVisible($id, [
            'items' => fn ($q) => $q->with(array_merge(['masterProduct', 'closer'], GoodsRequestItem::FULFILLMENT_RELATIONS)),
            'project.company', 'project.quotation.opportunity', 'division', 'creator', 'finalChecker', 'approvals.user',
        ]);

        // Saran pemetaan master product untuk item lama yang belum terhubung (cocokkan part number = kode).
        $unmapped = $goodsRequest->items->whereNull('master_product_id');
        $suggestions = $unmapped->isEmpty() ? collect() : MasterProduct::whereIn('code', $unmapped->pluck('part_number')->filter()->map(fn ($v) => trim($v)))
            ->get(['id', 'code', 'name'])->keyBy('code');

        return view('goods-request.show', [
            'goodsRequest' => $goodsRequest,
            'canApproveThis' => $goodsRequest->canBeApprovedBy(Auth::user()),
            'canCloseRemaining' => $this->canCloseRemaining($goodsRequest),
            'productSuggestions' => $unmapped->mapWithKeys(fn (GoodsRequestItem $item) => [
                $item->id => $suggestions->get(trim((string) $item->part_number)),
            ])->filter(),
        ]);
    }

    public function edit($id)
    {
        $goodsRequest = $this->findVisible($id, ['items.masterProduct', 'project']);

        if ($goodsRequest->status !== GoodsRequest::STATUS_DRAFT) {
            return redirect()->route('goods-request.index')
                ->with('error', 'Hanya permintaan barang berstatus Draft yang bisa diedit.');
        }

        $projects = $this->eligibleProjects();
        if (! $projects->contains('id', $goodsRequest->project_id) && $goodsRequest->project) {
            $projects->prepend($goodsRequest->project);
        }

        return view('goods-request.form', [
            'goodsRequest' => $goodsRequest,
            'purpose' => $goodsRequest->purpose,
            'projects' => $projects,
            'preselected' => $goodsRequest->project,
            'items' => $goodsRequest->items->map(fn ($item) => [
                'id' => $item->id,
                'quote_configuration_item_id' => $item->quote_configuration_item_id,
                'master_product_id' => $item->master_product_id,
                'product_name' => $item->masterProduct?->name,
                'part_number' => $item->part_number,
                'description' => Quotation::renderDescription($item->description),
                'qty' => $item->qty,
                'unit' => $item->unit,
                'currency' => $item->currency,
            ])->all(),
            'currencies' => Currency::formOptions(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $goodsRequest = $this->findVisible($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan barang berstatus Draft yang bisa diubah.'], 422);
        }

        $validated = $this->validateGoodsRequest($request, false);

        if ($duplicate = $this->rejectDuplicateConfigItems($validated['items'] ?? [], $goodsRequest->id)) {
            return $duplicate;
        }

        // Tujuan & project tidak bisa diganti setelah dibuat — nilai dari client diabaikan.
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
        $goodsRequest = $this->findVisible($id);

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
        $goodsRequest = $this->findVisible($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_DRAFT) {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan barang berstatus Draft yang bisa di-submit.'], 422);
        }

        if ($goodsRequest->items()->count() === 0) {
            return response()->json(['success' => false, 'message' => 'Tambahkan minimal satu item sebelum submit.'], 422);
        }

        $goodsRequest->update(['status' => GoodsRequest::STATUS_WAITING_APPROVAL]);

        return response()->json(['success' => true, 'message' => 'Permintaan barang dikirim untuk approval.']);
    }

    /**
     * Satu approval per user can_approve. Setelah REQUIRED_APPROVALS (1) approval
     * terkumpul, status menjadi Approved (boleh diproses PO).
     */
    public function approve($id): JsonResponse
    {
        $goodsRequest = $this->findVisible($id);

        if ($goodsRequest->status !== GoodsRequest::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Permintaan barang ini tidak sedang menunggu approval.'], 422);
        }

        if ($goodsRequest->hasApprovalFrom(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan approval untuk permintaan barang ini.'], 422);
        }

        try {
            DB::transaction(function () use ($goodsRequest) {
                GoodsRequestApproval::create([
                    'goods_request_id' => $goodsRequest->id,
                    'user_id' => Auth::id(),
                    'approved_at' => now(),
                ]);

                if ($goodsRequest->approvals()->count() >= GoodsRequest::REQUIRED_APPROVALS) {
                    $goodsRequest->update([
                        'status' => GoodsRequest::STATUS_APPROVED,
                        'final_checked_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);
                }
            });
        } catch (QueryException $e) {
            // Backstop unique (goods_request_id, user_id) jika request race/duplikat.
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan approval untuk permintaan barang ini.'], 422);
        }

        $count = $goodsRequest->approvals()->count();
        $approved = $goodsRequest->fresh()->status === GoodsRequest::STATUS_APPROVED;

        return response()->json([
            'success' => true,
            'message' => $approved
                ? 'Permintaan barang Approved dan siap diproses PO.'
                : 'Approval tercatat ('.$count.'/'.GoodsRequest::REQUIRED_APPROVALS.'). Menunggu approver berikutnya.',
            'approval_count' => $count,
            'approved' => $approved,
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $goodsRequest = $this->findVisible($id);

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
     * Tutup sisa kebutuhan item (atau semua item bila item_id kosong) yang tidak
     * diperlukan lagi — sisa tidak bisa dipenuhi / di-PO lagi. Hanya permintaan
     * Approved, oleh pembuat atau approver permintaan. Alasan wajib.
     */
    public function closeRemaining(Request $request, $id): JsonResponse
    {
        $goodsRequest = $this->findVisible($id, ['items' => fn ($q) => $q->with(GoodsRequestItem::FULFILLMENT_RELATIONS)]);

        if ($goodsRequest->status !== GoodsRequest::STATUS_APPROVED) {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan Approved yang bisa ditutup sisanya.'], 422);
        }

        if (! $this->canCloseRemaining($goodsRequest)) {
            return response()->json(['success' => false, 'message' => 'Hanya pembuat atau approver permintaan yang bisa menutup sisa.'], 403);
        }

        $validated = $request->validate([
            'item_id' => 'nullable|integer',
            'reason' => 'required|string|max:255',
        ], ['reason.required' => 'Alasan tutup sisa wajib diisi.']);

        $items = isset($validated['item_id'])
            ? $goodsRequest->items->where('id', (int) $validated['item_id'])
            : $goodsRequest->items;

        $closed = 0;
        foreach ($items as $item) {
            $remaining = $item->remainingQty();
            if ($remaining <= 0) {
                continue;
            }
            $item->update([
                'closed_qty' => (int) $item->closed_qty + $remaining,
                'closed_reason' => $validated['reason'],
                'closed_by' => Auth::id(),
                'closed_at' => now(),
            ]);
            $closed += $remaining;
        }

        if ($closed === 0) {
            return response()->json(['success' => false, 'message' => 'Tidak ada sisa kebutuhan yang bisa ditutup.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Sisa kebutuhan ditutup ('.$closed.' qty).']);
    }

    /**
     * Petakan item lama yang belum terhubung master product (wajib untuk
     * dipenuhi dari stok / PO). Boleh di status apa pun selama belum diproses.
     */
    public function mapProduct(Request $request, $id, $itemId): JsonResponse
    {
        $goodsRequest = $this->findVisible($id);
        $item = $goodsRequest->items()->with(GoodsRequestItem::FULFILLMENT_RELATIONS)->findOrFail($itemId);

        $validated = $request->validate([
            'master_product_id' => 'required|integer|exists:master_products,id',
        ]);

        if ($item->master_product_id && $item->fulfilledQty() > (int) $item->closed_qty) {
            return response()->json(['success' => false, 'message' => 'Item sudah diproses (stok/PO), produknya tidak bisa diganti.'], 422);
        }

        $product = MasterProduct::with('currency')->findOrFail($validated['master_product_id']);
        $item->update([
            'master_product_id' => $product->id,
            'part_number' => $item->part_number ?: $product->code,
            'currency' => $item->quote_configuration_item_id
                ? $item->currency
                : GoodsRequestItem::normalizeCurrency($product->currency?->name),
        ]);

        return response()->json(['success' => true, 'message' => 'Item dipetakan ke '.($product->code ? $product->code.' — ' : '').$product->name.'.']);
    }

    private function canCloseRemaining(GoodsRequest $goodsRequest): bool
    {
        $user = Auth::user();

        return $user->role === 'Admin'
            || (int) $goodsRequest->created_by === (int) $user->id
            || $goodsRequest->approvals->contains('user_id', $user->id)
            || (int) $goodsRequest->final_checked_by === (int) $user->id;
    }

    /**
     * Permintaan barang yang boleh dilihat user (lihat GoodsRequest::scopeVisibleTo);
     * di luar itu 404 agar tidak bisa dibuka lewat URL langsung.
     */
    private function findVisible($id, array $with = []): GoodsRequest
    {
        return GoodsRequest::visibleTo(Auth::user())->with($with)->findOrFail($id);
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
        $request->validate([
            'opportunity_id' => 'required|integer|exists:opportunities,id',
            'goods_request_id' => 'nullable|integer',
        ]);

        $configs = QuoteConfiguration::with(['items', 'division'])
            ->where('opportunity_id', $request->input('opportunity_id'))
            ->where('status', QuoteConfiguration::STATUS_APPROVED)
            ->where('is_current', true)
            ->orderBy('division_id')
            ->get();

        // Master product wajib: cocokkan part number item configuration dengan kode produk.
        $productsByCode = MasterProduct::whereIn('code', $configs->flatMap->items->pluck('part_number')->filter()->map(fn ($v) => trim($v))->unique())
            ->get(['id', 'code', 'name'])->keyBy('code');

        // Item yang sudah diminta di Permintaan Barang lain (selain Rejected)
        // disembunyikan agar tidak terjadi permintaan ganda.
        $used = $this->usedConfigItems($configs->flatMap->items->pluck('id')->all(), $request->integer('goods_request_id') ?: null);

        $items = collect();
        foreach ($configs as $config) {
            foreach ($config->flattenTree() as $row) {
                $item = $row['item'];
                if ($used->has($item->id)) {
                    continue;
                }
                $product = $productsByCode->get(trim((string) $item->part_number));
                $items->push([
                    'master_product_id' => $product?->id,
                    'product_name' => $product?->name,
                    'quote_configuration_item_id' => $item->id,
                    'quote_configuration_id' => $config->id,
                    'parent_id' => $item->parent_id,
                    'depth' => $row['depth'],
                    'item_no' => $item->item_no,
                    'part_number' => $item->part_number,
                    'description' => Quotation::renderDescription($item->description),
                    'qty' => $item->qty,
                    'unit' => $item->unit,
                    'currency' => GoodsRequestItem::normalizeCurrency($item->currency),
                    'division_name' => $config->division?->division_name,
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $items->values(), 'hidden_count' => $used->count()]);
    }

    /**
     * Item configuration yang sudah dipakai Permintaan Barang lain (status
     * selain Rejected): [quote_configuration_item_id => nomor permintaan].
     */
    private function usedConfigItems(array $configItemIds, ?int $exceptGoodsRequestId = null)
    {
        if (! $configItemIds) {
            return collect();
        }

        return GoodsRequestItem::whereIn('quote_configuration_item_id', $configItemIds)
            ->whereHas('goodsRequest', fn ($q) => $q->where('status', '!=', GoodsRequest::STATUS_REJECTED)
                ->when($exceptGoodsRequestId, fn ($q) => $q->whereKeyNot($exceptGoodsRequestId)))
            ->with('goodsRequest:id,request_number')
            ->get(['quote_configuration_item_id', 'goods_request_id'])
            ->mapWithKeys(fn (GoodsRequestItem $item) => [$item->quote_configuration_item_id => $item->goodsRequest->number_label]);
    }

    /**
     * Tolak item configuration yang sudah diminta di Permintaan Barang lain.
     */
    private function rejectDuplicateConfigItems(array $items, ?int $exceptGoodsRequestId = null): ?JsonResponse
    {
        $ids = collect($items)->pluck('quote_configuration_item_id')->filter();
        if ($ids->duplicates()->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'Item configuration yang sama tidak boleh muncul dua kali.'], 422);
        }

        $used = $this->usedConfigItems($ids->all(), $exceptGoodsRequestId);
        if ($used->isEmpty()) {
            return null;
        }

        $partNumbers = QuoteConfigurationItem::whereIn('id', $used->keys())->pluck('part_number')->filter()->implode(', ');

        return response()->json([
            'success' => false,
            'message' => 'Item configuration '.($partNumbers ?: '#'.$used->keys()->implode(', #')).' sudah diminta di Permintaan '.$used->unique()->implode(', ').'. Hapus baris tersebut agar tidak terjadi permintaan ganda.',
        ], 422);
    }

    /**
     * Katalog Master Product (aktif saja, SEMUA divisi) untuk modal "Tambah
     * Baris Manual" pada form Permintaan Barang — hanya part number,
     * nama/deskripsi, dan divisi yang ditampilkan; harga sengaja TIDAK
     * disertakan (harga baru ditentukan saat Purchase Order ke supplier).
     */
    /**
     * Katalog Master Product untuk "Tambah Baris Manual" — tanpa harga.
     * Mendukung pencarian, filter kategori/divisi, dan paging (per_page maks 50).
     * Daftar pilihan filter ikut dikirim bila with_filters=1.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $searchValue = trim((string) $request->input('search.value', $request->input('q', '')));
        $category = trim((string) $request->input('category', ''));
        $divisionId = $request->integer('division_id');
        $perPage = min(max($request->integer('per_page', 15), 5), 50);

        $query = MasterProduct::query()
            ->with(['division', 'currency'])
            ->where('status', 'Active')
            ->orderBy('name');

        if ($searchValue !== '') {
            $query->where(function ($builder) use ($searchValue) {
                $builder->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhere('code', 'like', "%{$searchValue}%")
                    ->orWhere('brand', 'like', "%{$searchValue}%")
                    ->orWhere('category', 'like', "%{$searchValue}%");
            });
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }

        $products = $query->paginate($perPage, ['id', 'name', 'code', 'brand', 'category', 'division_id', 'currency_id', 'description']);

        $response = [
            'success' => true,
            'data' => collect($products->items())->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'brand' => $product->brand,
                'category' => $product->category,
                'description' => Quotation::renderDescription($product->description),
                'division_name' => $product->division?->division_name,
                'currency' => GoodsRequestItem::normalizeCurrency($product->currency?->name),
            ])->values(),
            'meta' => [
                'page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ];

        if ($request->boolean('with_filters')) {
            $active = MasterProduct::where('status', 'Active');

            $response['filters'] = [
                'categories' => (clone $active)->whereNotNull('category')->where('category', '!=', '')
                    ->distinct()->orderBy('category')->pluck('category')->values(),
                'divisions' => Division::whereIn('id', (clone $active)->whereNotNull('division_id')->distinct()->pluck('division_id'))
                    ->orderBy('division_name')->get(['id', 'division_name'])
                    ->map(fn ($d) => ['id' => $d->id, 'name' => $d->division_name])->values(),
            ];
        }

        return response()->json($response);
    }

    /**
     * Project yang boleh diajukan Permintaan Barang: aktif dan kuorum PO
     * Supplier Approval (2 approver berbeda) sudah terpenuhi. Project retail
     * (tanpa quotation) ikut boleh — item hanya lewat "Tambah Baris Manual",
     * karena picker configuration butuh opportunity.
     */
    private function eligibleProjects(): Collection
    {
        return Project::with(['poSupplierApprovals', 'quotation.opportunity', 'company'])
            ->where('status', Project::STATUS_ACTIVE)
            ->orderBy('id', 'desc')
            ->get()
            ->filter(fn (Project $p) => $this->projectIsEligible($p))
            ->values();
    }

    private function projectIsEligible(Project $project): bool
    {
        return $project->status === Project::STATUS_ACTIVE
            && $project->isReadyForSupplierPo();
    }

    private function validateGoodsRequest(Request $request, bool $creating): array
    {
        return $request->validate([
            'purpose' => $creating ? 'required|in:'.implode(',', array_keys(GoodsRequest::PURPOSE_LABELS)) : 'nullable',
            'project_id' => $creating ? 'required_if:purpose,'.GoodsRequest::PURPOSE_PROJECT.'|nullable|integer|exists:projects,id' : 'nullable',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.quote_configuration_item_id' => 'nullable|integer|exists:quote_configuration_items,id',
            'items.*.master_product_id' => 'required|integer|exists:master_products,id',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'nullable|string',
            'items.*.qty' => 'nullable|integer|min:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.currency' => 'nullable|string|max:10',
        ], [
            'project_id.required_if' => 'Pilih project untuk permintaan bertujuan Project.',
            'items.*.master_product_id.required' => 'Setiap item wajib terhubung master produk. Pilih produk dari katalog.',
        ]);
    }

    /**
     * Mata uang item dikunci sesuai sumber (GoodsRequestItem::resolveCurrency());
     * nilai dari client hanya dipakai untuk baris manual.
     */
    private function syncItems(GoodsRequest $goodsRequest, array $items): void
    {
        $goodsRequest->items()->delete();

        $items = array_values($items);
        $configCurrencies = QuoteConfigurationItem::whereIn('id', collect($items)->pluck('quote_configuration_item_id')->filter())
            ->pluck('currency', 'id');
        $productCurrencies = MasterProduct::with('currency')
            ->whereIn('id', collect($items)->pluck('master_product_id')->filter())
            ->get(['id', 'currency_id'])
            ->mapWithKeys(fn (MasterProduct $product) => [$product->id => $product->currency?->name]);
        $allowedCurrencies = Currency::pluck('name')->map(fn ($name) => strtoupper($name))->all();

        $payload = [];
        foreach ($items as $i => $item) {
            $configItemId = $item['quote_configuration_item_id'] ?? null;
            $productId = $item['master_product_id'] ?? null;
            $manualCurrency = strtoupper(trim((string) ($item['currency'] ?? '')));

            $currency = match (true) {
                (bool) $configItemId => $configCurrencies[$configItemId] ?? null,
                (bool) $productId => $productCurrencies[$productId] ?? null,
                default => in_array($manualCurrency, $allowedCurrencies, true) ? $manualCurrency : null,
            };

            $payload[] = [
                'goods_request_id' => $goodsRequest->id,
                'quote_configuration_item_id' => $item['quote_configuration_item_id'] ?? null,
                'master_product_id' => $item['master_product_id'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => Quotation::sanitizeDescription($item['description'] ?? ''),
                'qty' => isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null,
                'unit' => $item['unit'] ?? null,
                'currency' => GoodsRequestItem::normalizeCurrency($currency),
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

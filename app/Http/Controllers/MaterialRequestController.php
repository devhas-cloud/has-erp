<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\MaterialHandoverItem;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\Project;
use App\Services\StockService;
use App\Support\ModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Material Request — kebutuhan material project untuk produksi di satu branch.
 *
 * Disimpan langsung Menunggu Approval (tanpa draft). Selama belum disetujui,
 * pembuat atau approver boleh mengubah / menghapus; MR Ditolak yang diubah
 * otomatis diajukan ulang. 1 approver (can_approve), pembuat ber-can_approve
 * boleh menyetujui sendiri. Disetujui -> gudang menyerahkan lewat Serah Terima
 * Material; Selesai otomatis bila semua diserahkan atau ditutup manual.
 */
class MaterialRequestController extends Controller
{
    public function index()
    {
        $visible = MaterialRequest::visibleTo(Auth::user());

        return view('material-request.index', [
            'projects' => Project::whereIn('id', (clone $visible)->select('project_id'))->orderBy('project_number')->get(['id', 'project_number', 'project_name']),
            'branches' => Branch::whereIn('id', (clone $visible)->select('branch_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = MaterialRequest::visibleTo(Auth::user())
            ->with(['project:id,project_number,project_name', 'branch:id,name', 'creator', 'items.handoverItems']);

        $recordsTotal = (clone $query)->count();

        $query
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->integer('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('request_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('request_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('request_number', 'like', "%{$search}%")
                ->orWhere('note', 'like', "%{$search}%")
                ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$search}%")->orWhere('project_name', 'like', "%{$search}%"))
                ->orWhereHas('items.masterProduct', fn ($p) => $p->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")));
        }

        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $rows = $query->orderByDesc('id')->offset($start)->limit($length)->get();
        $user = Auth::user();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(function (MaterialRequest $mr, $i) use ($start, $user) {
                $requested = $mr->items->sum('qty');
                $handed = $mr->items->sum(fn (MaterialRequestItem $item) => $item->handedQty());

                return [
                    'DT_RowIndex' => $start + $i + 1,
                    'id' => $mr->id,
                    'request_number' => $mr->request_number,
                    'request_date' => $mr->request_date?->format('d/m/Y'),
                    'project' => $mr->project ? $mr->project->project_number.' — '.$mr->project->project_name : '—',
                    'branch' => $mr->branch?->name ?? '—',
                    'item_count' => $mr->items->count(),
                    'progress' => $requested > 0 ? (int) floor(min(1, $handed / $requested) * 100) : 0,
                    'creator' => $mr->creator?->display_name ?? '—',
                    'status' => $mr->status,
                    'status_badge' => $mr->statusBadgeHtml(),
                    'can_edit' => $mr->isEditable() && $this->canManage($mr, $user),
                ];
            }),
        ]);
    }

    public function create()
    {
        return view('material-request.form', $this->formData(null));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request);

        $mr = DB::transaction(function () use ($validated) {
            $mr = MaterialRequest::create([
                'request_number' => MaterialRequest::generateNumber($validated['request_date']),
                'project_id' => $validated['project_id'],
                'branch_id' => $validated['branch_id'],
                'request_date' => $validated['request_date'],
                'note' => $validated['note'] ?? null,
                'status' => MaterialRequest::STATUS_WAITING_APPROVAL,
                'created_by' => Auth::id(),
            ]);
            $this->syncItems($mr, $validated['items']);

            return $mr;
        });

        Log::record('create_material_request', 'Material Request '.$mr->request_number.' diajukan oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE, $mr);

        return response()->json(['success' => true, 'message' => 'Material Request '.$mr->request_number.' diajukan untuk approval.', 'id' => $mr->id]);
    }

    public function show($id)
    {
        $mr = $this->find($id, [
            'project', 'branch', 'creator', 'approver', 'rejecter', 'closer',
            'items.masterProduct', 'items.handoverItems',
            'handovers.items.stock', 'handovers.creator',
        ]);
        $user = Auth::user();

        // Material di Produksi project + branch ini (gabungan semua MR).
        $pool = MaterialHandoverItem::pool($mr->project_id, $mr->branch_id)
            ->with(['stock.masterProduct', 'materialHandover'])
            ->orderBy('id')
            ->get()
            ->filter(fn (MaterialHandoverItem $item) => $item->remainingQty() > 0)
            ->values();

        return view('material-request.show', [
            'mr' => $mr,
            'pool' => $pool,
            'canManage' => $mr->isEditable() && $this->canManage($mr, $user),
            'canDecide' => $mr->status === MaterialRequest::STATUS_WAITING_APPROVAL && MaterialRequest::isApprover($user),
            'canClose' => $mr->isApproved() && $this->canManage($mr, $user),
            'canHandover' => $mr->isApproved() && ModuleAccess::for($user)->module('MOD_MATERIAL_HANDOVER')->canCreate(),
        ]);
    }

    public function edit($id)
    {
        $mr = $this->find($id, ['items.masterProduct']);
        abort_unless($mr->isEditable() && $this->canManage($mr, Auth::user()), 403, 'Material Request ini tidak bisa diubah.');

        return view('material-request.form', $this->formData($mr));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $mr = $this->find($id);
        if (! $mr->isEditable() || ! $this->canManage($mr, Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Hanya Material Request yang belum disetujui yang bisa diubah oleh pembuat atau approver.'], 422);
        }

        $validated = $this->validateRequest($request);
        $resubmit = $mr->status === MaterialRequest::STATUS_REJECTED;

        DB::transaction(function () use ($mr, $validated) {
            $mr->update([
                'project_id' => $validated['project_id'],
                'branch_id' => $validated['branch_id'],
                'request_date' => $validated['request_date'],
                'note' => $validated['note'] ?? null,
                'status' => MaterialRequest::STATUS_WAITING_APPROVAL,
                'rejected_by' => null,
                'rejected_at' => null,
                'reject_reason' => null,
            ]);
            $mr->items()->delete();
            $this->syncItems($mr, $validated['items']);
        });

        if ($resubmit) {
            Log::record('resubmit_material_request', 'Material Request '.$mr->request_number.' diperbaiki dan diajukan ulang oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE, $mr);
        }

        return response()->json([
            'success' => true,
            'message' => 'Material Request '.$mr->request_number.($resubmit ? ' diperbaiki dan diajukan ulang.' : ' diperbarui.'),
            'id' => $mr->id,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $mr = $this->find($id);
        if (! $mr->isEditable() || ! $this->canManage($mr, Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Hanya Material Request yang belum disetujui yang bisa dihapus oleh pembuat atau approver.'], 422);
        }

        $number = $mr->request_number;
        $mr->delete();
        Log::record('delete_material_request', 'Material Request '.$number.' dihapus oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE);

        return response()->json(['success' => true, 'message' => 'Material Request '.$number.' dihapus.']);
    }

    public function approve($id): JsonResponse
    {
        $mr = $this->find($id);
        if ($mr->status !== MaterialRequest::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Material Request ini tidak sedang menunggu approval.'], 422);
        }

        $mr->update(['status' => MaterialRequest::STATUS_APPROVED, 'approved_by' => Auth::id(), 'approved_at' => now()]);
        Log::record('approve_material_request', 'Material Request '.$mr->request_number.' disetujui oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE, $mr);

        return response()->json(['success' => true, 'message' => 'Material Request disetujui. Gudang bisa menyerahkan material.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255'], ['reason.required' => 'Alasan penolakan wajib diisi.']);
        $mr = $this->find($id);
        if ($mr->status !== MaterialRequest::STATUS_WAITING_APPROVAL) {
            return response()->json(['success' => false, 'message' => 'Material Request ini tidak sedang menunggu approval.'], 422);
        }

        $mr->update([
            'status' => MaterialRequest::STATUS_REJECTED,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'reject_reason' => $validated['reason'],
        ]);
        Log::record('reject_material_request', 'Material Request '.$mr->request_number.' ditolak oleh '.Auth::user()->display_name.': '.$validated['reason'], MaterialRequest::MODULE_CODE, $mr);

        return response()->json(['success' => true, 'message' => 'Material Request ditolak.']);
    }

    /** Tutup sisa permintaan yang tidak dilanjutkan. */
    public function close($id): JsonResponse
    {
        $mr = $this->find($id);
        if (! $mr->isApproved() || ! $this->canManage($mr, Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Hanya Material Request Disetujui yang bisa ditutup oleh pembuat atau approver.'], 422);
        }

        $mr->update(['status' => MaterialRequest::STATUS_COMPLETED, 'closed_by' => Auth::id(), 'closed_at' => now()]);
        Log::record('close_material_request', 'Material Request '.$mr->request_number.' ditutup oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE, $mr);

        return response()->json(['success' => true, 'message' => 'Material Request ditutup. Sisa permintaan tidak akan diserahkan.']);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $products = MasterProduct::where('status', 'Active')
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
            ->orderBy('code')
            ->limit(30)
            ->get(['id', 'code', 'name', 'unit', 'is_material']);

        return response()->json(['results' => $products->map(fn (MasterProduct $p) => [
            'id' => $p->id,
            'text' => $p->code.' — '.$p->name,
            'unit' => $p->unit,
            'is_material' => $p->isMaterial(),
        ])]);
    }

    private function find($id, array $with = []): MaterialRequest
    {
        return MaterialRequest::visibleTo(Auth::user())->with($with)->findOrFail($id);
    }

    /** Pembuat atau approver modul. */
    private function canManage(MaterialRequest $mr, $user): bool
    {
        return (int) $mr->created_by === (int) $user->id || MaterialRequest::isApprover($user);
    }

    private function formData(?MaterialRequest $mr): array
    {
        $user = Auth::user();

        return [
            'mr' => $mr,
            'projects' => Project::where('status', Project::STATUS_ACTIVE)
                ->when($mr, fn ($q) => $q->orWhere('id', $mr->project_id))
                ->orderBy('project_number')->get(['id', 'project_number', 'project_name']),
            'branches' => Branch::active()->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))->orderBy('name')->get(['id', 'name']),
            'defaultBranchId' => $mr?->branch_id ?? $user->branch_id,
        ];
    }

    private function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'request_date' => 'required|date',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.master_product_id' => 'required|integer|distinct|exists:master_products,id',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Tambahkan minimal satu material.',
            'items.*.master_product_id.distinct' => 'Material yang sama tidak boleh diminta dua kali dalam satu MR.',
        ]);

        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));

        $project = Project::find($validated['project_id']);
        if ($project->status !== Project::STATUS_ACTIVE) {
            $fail('Material Request hanya untuk project yang masih aktif.');
        }
        $branch = Branch::active()->find($validated['branch_id']);
        if (! $branch || ! Auth::user()->canAccessBranch($branch->id)) {
            $fail('Branch tidak aktif atau bukan branch Anda.');
        }

        $products = MasterProduct::whereIn('id', collect($validated['items'])->pluck('master_product_id'))->get()->keyBy('id');
        foreach ($validated['items'] as $row) {
            $product = $products[$row['master_product_id']];
            if (! $product->isMaterial() && abs($row['qty'] - round($row['qty'])) >= 0.00005) {
                $fail($product->code.' adalah barang ber-SN; qty harus bilangan bulat.');
            }
        }
        return $validated;
    }

    /** Satuan selalu dari master produk. */
    private function syncItems(MaterialRequest $mr, array $items): void
    {
        $products = MasterProduct::whereIn('id', collect($items)->pluck('master_product_id'))->get()->keyBy('id');
        foreach (array_values($items) as $i => $row) {
            $mr->items()->create([
                'master_product_id' => $row['master_product_id'],
                'qty' => StockService::round((float) $row['qty']),
                'unit' => $products[$row['master_product_id']]->unit,
                'note' => $row['note'] ?? null,
                'sort_order' => $i + 1,
            ]);
        }
    }
}

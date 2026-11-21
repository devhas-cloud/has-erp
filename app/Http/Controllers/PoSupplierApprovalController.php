<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Project;
use App\Models\ProjectPoSupplierApproval;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Modul "PO Supplier Approval": menandai project (PO dari customer sudah
 * terupload saat project dibuat) boleh dilanjutkan purchasing ke proses PO
 * barang ke supplier. Berbeda dari approval quotation (waiting_approval ->
 * approved) — di sini kuorum 2 approver berbeda hanya dicatat, TIDAK
 * mengubah status project.
 */
class PoSupplierApprovalController extends Controller
{
    /** Jumlah approver berbeda yang dibutuhkan sebelum purchasing boleh PO ke supplier. */
    private const REQUIRED = 2;

    public const STATUS_LABELS = [
        'waiting_me' => 'Needs my approval',
        'none' => 'No approvals yet (0/2)',
        'half' => 'Waiting for 2nd approver (1/2)',
        'ready' => 'Ready for supplier PO (2/2)',
    ];

    public function index()
    {
        $companyIds = Project::where('status', Project::STATUS_ACTIVE)->whereNotNull('company_id')->distinct()->pluck('company_id');

        return view('po-supplier-approval.index', [
            'companies' => \App\Models\AccountCompany::whereIn('id', $companyIds)->orderBy('account_name')->get(['id', 'account_name']),
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $query = Project::with(['company', 'creator', 'poSupplierApprovals.user'])
            ->withCount('poSupplierApprovals')
            ->where('status', Project::STATUS_ACTIVE);

        $recordsTotal = (clone $query)->count();

        $searchValue = trim((string) $request->input('search.value', ''));
        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('project_number', 'like', "%{$searchValue}%")
                    ->orWhere('project_name', 'like', "%{$searchValue}%")
                    ->orWhere('po_number_company', 'like', "%{$searchValue}%")
                    ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%")->orWhere('full_name', 'like', "%{$searchValue}%"));
            });
        }

        $query
            ->when($request->integer('company'), fn ($q, $id) => $q->where('company_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('po_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('po_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', $userId));

        // Jumlah per status (mengikuti cari & filter lain) untuk opsi filter Status.
        $counts = [];
        foreach (array_keys(self::STATUS_LABELS) as $status) {
            $counts[$status] = $this->applyStatus(clone $query, $status, $userId)->count();
        }

        $this->applyStatus($query, (string) $request->input('status', ''), $userId);

        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        // Yang masih perlu approval tampil lebih dulu, lalu terbaru.
        $projects = $query->orderByRaw('CASE WHEN po_supplier_approvals_count >= ? THEN 1 ELSE 0 END', [self::REQUIRED])
            ->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($projects as $i => $project) {
            $approvals = $project->poSupplierApprovals;

            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $project->id,
                'project_number' => $project->project_number,
                'project_name' => $project->project_name,
                'company_name' => $project->company?->account_name ?? '—',
                'po_number_company' => $project->po_number_company ?? '—',
                'po_date' => $project->po_date ? \Carbon\Carbon::parse($project->po_date)->format('d/m/Y') : null,
                'net_sale_label' => Project::formatMoney($project->net_sale_amount),
                'creator_name' => $project->creator?->display_name ?? '—',
                'approval_count' => $approvals->count(),
                'required' => self::REQUIRED,
                'ready_for_supplier_po' => $approvals->count() >= self::REQUIRED,
                'approvers' => $approvals->map(fn ($a) => [
                    'name' => $a->user?->display_name ?? '—',
                    'approved_at' => $a->approved_at?->format('d/m/Y H:i'),
                    'is_me' => (int) $a->user_id === (int) $userId,
                ])->values(),
                'already_approved_by_me' => $approvals->contains('user_id', $userId),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => $counts,
            'data' => $data,
        ]);
    }

    private function applyStatus($query, string $status, ?int $userId)
    {
        $approvedByMe = fn ($q) => $q->where('user_id', $userId);

        return match ($status) {
            'waiting_me' => $query->has('poSupplierApprovals', '<', self::REQUIRED)->whereDoesntHave('poSupplierApprovals', $approvedByMe),
            'none' => $query->doesntHave('poSupplierApprovals'),
            'half' => $query->has('poSupplierApprovals', '>', 0)->has('poSupplierApprovals', '<', self::REQUIRED),
            'ready' => $query->has('poSupplierApprovals', '>=', self::REQUIRED),
            default => $query,
        };
    }

    public function approve($id): JsonResponse
    {
        $project = Project::with('quotation.opportunity')->findOrFail($id);

        if ($project->status !== Project::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'This project is not active, so supplier PO approval is not needed.',
            ], 422);
        }

        if ($project->poSupplierApprovals()->where('user_id', Auth::id())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already approved this project.',
            ], 422);
        }

        try {
            ProjectPoSupplierApproval::create([
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'approved_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Backstop kuorum unik (project_id, user_id) jika request race/duplikat.
            return response()->json([
                'success' => false,
                'message' => 'You have already approved this project.',
            ], 422);
        }

        $project->refresh();

        // Kuorum terpenuhi: opportunity dari quotation project jadi Close Won.
        if ($project->isReadyForSupplierPo() && ($opportunity = $project->opportunity())) {
            $oldStageName = $opportunity->stage?->stage_name ?? '—';
            $opportunity->stage_id = 5; // stage_id 5 = "Close Won"
            $opportunity->close_won_date = now()->format('Y-m-d');
            $opportunity->close_date = now()->format('Y-m-d');
            $opportunity->save();

            Log::record('update_opportunity_stage', "Stage diubah dari {$oldStageName} ke Close Won", 'MOD_OPPORTUNITY_MANAGEMENT', $opportunity);
        }

        $ready = $project->isReadyForSupplierPo();

        return response()->json([
            'success' => true,
            'message' => $ready
                ? 'Approval recorded. Purchasing may now create the supplier PO (2 approvers reached).'
                : 'Approval recorded. Waiting for a second approver before purchasing can create the supplier PO.',
            'approval_count' => $project->poSupplierApprovals()->count(),
            'ready_for_supplier_po' => $ready,
        ]);
    }
}

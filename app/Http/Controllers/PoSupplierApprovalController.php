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
    public function index()
    {
        return view('po-supplier-approval.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Project::with(['company', 'creator', 'poSupplierApprovals.user'])
            ->where('status', Project::STATUS_ACTIVE);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('project_number', 'like', "%{$searchValue}%")
                    ->orWhere('project_name', 'like', "%{$searchValue}%")
                    ->orWhere('po_number_company', 'like', "%{$searchValue}%")
                    ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%")->orWhere('full_name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $projects = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $userId = Auth::id();

        $data = [];
        foreach ($projects as $i => $project) {
            $approvals = $project->poSupplierApprovals;
            $ready = $project->isReadyForSupplierPo();

            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $project->id,
                'project_number' => $project->project_number,
                'project_name' => $project->project_name,
                'company_name' => $project->company?->account_name ?? '—',
                'po_number_company' => $project->po_number_company ?? '—',
                'net_sale_label' => Project::formatMoney($project->net_sale_amount),
                'creator_name' => $project->creator?->display_name ?? '—',
                'approval_count' => $approvals->count(),
                'ready_for_supplier_po' => $ready,
                'approvers' => $approvals->map(fn ($a) => [
                    'name' => $a->user?->display_name ?? '—',
                    'approved_at' => $a->approved_at?->format('d/m/Y H:i'),
                ])->values(),
                'already_approved_by_me' => $approvals->contains('user_id', $userId),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function approve($id): JsonResponse
    {
        $project = Project::with('quotation.opportunity')->findOrFail($id);

        if ($project->status !== Project::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Project tidak aktif, approval PO Supplier tidak diperlukan.',
            ], 422);
        }

        if ($project->poSupplierApprovals()->where('user_id', Auth::id())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan approval untuk project ini.',
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
                'message' => 'Anda sudah melakukan approval untuk project ini.',
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
                ? 'Approval tercatat. Purchasing boleh melanjutkan PO barang ke supplier (2 approver terpenuhi).'
                : 'Approval tercatat. Menunggu approver kedua sebelum purchasing boleh lanjut PO ke supplier.',
            'approval_count' => $project->poSupplierApprovals()->count(),
            'ready_for_supplier_po' => $ready,
        ]);
    }
}

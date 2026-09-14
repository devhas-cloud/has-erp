<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Quotation;
use App\Models\QuotationPoSupplierApproval;
use App\Models\UserAccessControl;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Modul "PO Supplier Approval": menandai quotation berstatus Finish (PO dari
 * customer sudah terupload) boleh dilanjutkan purchasing ke proses PO barang
 * ke supplier. Berbeda dari QuotationController::approve() yang mengesahkan
 * quotation itu sendiri (waiting_approval -> approved) — di sini kuorum 2
 * approver berbeda hanya dicatat, TIDAK mengubah status quotation.
 */
class PoSupplierApprovalController extends Controller
{
    private const MODULE_CODE = 'MOD_PO_SUPPLIER_APPROVAL';

    public function index()
    {
        return view('po-supplier-approval.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Quotation::with(['creator', 'poSupplierApprovals.user'])
            ->where('status', Quotation::STATUS_FINISH);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('quotation_number', 'like', "%{$searchValue}%")
                    ->orWhere('to_name', 'like', "%{$searchValue}%")
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $quotations = $query->orderBy('id', 'desc')->offset($start)->limit($length)->get();

        $userId = Auth::id();
        $canApprove = $this->isPoSupplierApprover();

        $data = [];
        foreach ($quotations as $i => $quotation) {
            $approvals = $quotation->poSupplierApprovals;

            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number ?? '—',
                'to_name' => $quotation->to_name ?? '—',
                'grand_total_label' => Quotation::formatMoney($quotation->grand_total),
                'creator_name' => $quotation->creator?->username ?? '—',
                'approval_count' => $approvals->count(),
                'ready_for_supplier_po' => $quotation->isReadyForSupplierPo(),
                'requires_dp' => $quotation->requires_dp ? 'Ya' : 'Tidak',
                'approvers' => $approvals->map(fn ($a) => [
                    'name' => $a->user?->username ?? '—',
                    'approved_at' => $a->approved_at?->format('d/m/Y H:i'),
                ])->values(),
                'already_approved_by_me' => $approvals->contains('user_id', $userId),
                'can_approve' => $canApprove,
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
        $quotation = Quotation::findOrFail($id);

        if ($quotation->status !== Quotation::STATUS_FINISH) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation belum berstatus Finish (PO dari customer belum diupload).',
            ], 422);
        }

        if (! $this->isPoSupplierApprover()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak approve pada modul ini.',
            ], 403);
        }

        if ($quotation->poSupplierApprovals()->where('user_id', Auth::id())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan approval untuk quotation ini.',
            ], 422);
        }

        try {
            QuotationPoSupplierApproval::create([
                'quotation_id' => $quotation->id,
                'user_id' => Auth::id(),
                'approved_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Backstop kuorum unik (quotation_id, user_id) jika request race/duplikat.
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan approval untuk quotation ini.',
            ], 422);
        }

        $quotation->refresh();

        return response()->json([
            'success' => true,
            'message' => $quotation->isReadyForSupplierPo()
                ? 'Approval tercatat. Purchasing boleh melanjutkan PO barang ke supplier (2 approver terpenuhi).'
                : 'Approval tercatat. Menunggu approver kedua sebelum purchasing boleh lanjut PO ke supplier.',
            'approval_count' => $quotation->poSupplierApprovals()->count(),
            'ready_for_supplier_po' => $quotation->isReadyForSupplierPo(),
        ]);
    }

    /**
     * User berhak approve modul PO Supplier Approval (UAC can_approve atau Admin).
     */
    private function isPoSupplierApprover(): bool
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

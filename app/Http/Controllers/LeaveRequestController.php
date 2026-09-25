<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestLog;
use App\Models\LeaveType;
use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends Controller
{
    public function index()
    {
        return view('leave-request.index', [
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = LeaveRequest::query()->with(['employee', 'leaveType']);

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('request_no', 'like', "%{$searchValue}%")
                    ->orWhere('reason', 'like', "%{$searchValue}%")
                    ->orWhereHas('employee', function ($e) use ($searchValue) {
                        $e->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('employee_no', 'like', "%{$searchValue}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('month')) {
            $query->where('start_date', 'like', $request->input('month').'-%');
        }

        $recordsTotal = LeaveRequest::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $requests = $query->orderByDesc('id')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = [];
        foreach ($requests as $i => $lr) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $lr->id,
                'request_no' => $lr->request_no,
                'employee_no' => $lr->employee->employee_no ?? null,
                'employee' => $lr->employee->name ?? null,
                'leave_type' => $lr->leaveType->name ?? null,
                'period' => $lr->start_date?->format('d-m-Y').' s/d '.$lr->end_date?->format('d-m-Y'),
                'days' => $lr->days,
                'status' => $lr->status,
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show($id)
    {
        $leaveRequest = LeaveRequest::with(['employee', 'leaveType', 'approvedBy', 'submittedBy'])
            ->findOrFail($id);

        $balances = $this->getBalances($leaveRequest->employee_id);

        return view('leave-request.show', [
            'leaveRequest' => $leaveRequest,
            'balances' => $balances,
        ]);
    }

    /**
     * Saldo cuti per karyawan (semua jenis dengan quota) — widget & live info modal.
     */
    public function balances(Request $request): JsonResponse
    {
        $rows = $this->getBalances($request->input('employee_id'), $request->input('year', now()->year));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    private function getBalances(int $employeeId, int $year = null): array
    {
        $year = $year ?? now()->year;

        return LeaveBalance::with('leaveType')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get()
            ->map(fn (LeaveBalance $b) => [
                'leave_type_id' => $b->leave_type_id,
                'leave_type' => $b->leaveType->name,
                'entitlement' => $b->entitlement,
                'carried_over' => $b->carried_over,
                'used' => $b->used,
                'remaining' => max(0, $b->entitlement + $b->carried_over - $b->used),
            ])->all();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'leave_type_id' => 'required|integer|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
        ]);

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);

        $days = $this->workingDays($validated['start_date'], $validated['end_date']);
        if ($days <= 0) {
            return response()->json(['success' => false, 'message' => 'Periode tidak berisi hari kerja sama sekali.'], 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $request->validate(['attachment' => 'file|max:4096|mimes:pdf,jpg,jpeg,png']);

            $file = $request->file('attachment');
            $attachmentPath = $file->store("leave/{$validated['employee_id']}", 'public');
        } elseif ($leaveType->is_proof_required && $days > 1) {
            return response()->json(['success' => false, 'message' => 'Jenis cuti Sakit (>1 hari) memerlukan lampiran surat dokter.'], 422);
        }

        if ($leaveType->quota_days) {
            $remaining = $this->remainingQuota($validated['employee_id'], $leaveType->id, (int) \Carbon\Carbon::parse($validated['start_date'])->year);
            if ($days > $remaining) {
                return response()->json(['success' => false, 'message' => "Saldo cuti tidak cukup: butuh {$days} hari, tersisa {$remaining} hari."], 422);
            }
        }

        $leave = DB::transaction(function () use ($validated, $days, $attachmentPath) {
            return LeaveRequest::create([
                'request_no' => $this->nextRequestNo(),
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'days' => $days,
                'reason' => $validated['reason'],
                'attachment_path' => $attachmentPath,
                'status' => LeaveRequest::STATUS_SUBMITTED,
                'submitted_by' => auth()->id(),
            ]);
        });

        Log::record(
            'leave_submit',
            "Pengajuan cuti {$leave->request_no} — {$leave->employee->name} ({$days} hari) [{$leave->leaveType->name}]",
            'MOD_ER_LEAVE',
            $leave
        );

        return response()->json(['success' => true, 'message' => "Pengajuan cuti/izin diterima ({$days} hari)."]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);

        if (!in_array($leave->status, [LeaveRequest::STATUS_DRAFT, LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_REVISE], true)) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah dikunci.'], 422);
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
        ]);

        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $validated['start_date'] = $validated['start_date'] ?? $leave->start_date->toDateString();
            $validated['end_date'] = $validated['end_date'] ?? $leave->end_date->toDateString();
            $validated['days'] = $this->workingDays($validated['start_date'], $validated['end_date']);

            // re-check kuota setelah perpanjangan periode
            if ($leave->leaveType->quota_days) {
                $remaining = $this->remainingQuota(
                    $leave->employee_id,
                    $leave->leave_type_id,
                    (int) \Carbon\Carbon::parse($validated['start_date'])->year
                );
                if ($validated['days'] > $remaining) {
                    return response()->json(['success' => false, 'message' => "Saldo cuti tidak cukup: butuh {$validated['days']} hari, tersisa {$remaining} hari."], 422);
                }
            }
        }

        $leave->update(array_filter($validated, fn ($v) => !is_null($v)));

        Log::record('leave_update', "Pengajuan cuti {$leave->request_no} dikoreksi", 'MOD_ER_LEAVE', $leave);

        return response()->json(['success' => true, 'message' => 'Pengajuan cuti diperbarui.']);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $request->validate(['note' => 'nullable|string']);

        $leave = LeaveRequest::with('leaveType')->findOrFail($id);

        if (!in_array($leave->status, [LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_REVISE], true)) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah diproses.'], 422);
        }

        DB::transaction(function () use ($leave, $request) {
            $leave->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'decision_note' => $request->input('note'),
            ]);

            // potong saldo bila jenis cuti punya quota
            if ($leave->leaveType->quota_days) {
                LeaveBalance::firstOrCreate(
                    ['employee_id' => $leave->employee_id, 'year' => $leave->start_date->year, 'leave_type_id' => $leave->leave_type_id],
                    ['entitlement' => $leave->leaveType->quota_days]
                )->increment('used', $leave->days);
            }

            // integrasi attendance: kunci hari kerja dalam periode
            $this->syncAttendance($leave);
        });

        LeaveRequestLog::create([
            'leave_request_id' => $leave->id,
            'action' => LeaveRequestLog::ACTION_APPROVE,
            'note' => $request->input('note'),
            'actor_id' => auth()->id(),
        ]);

        Log::record('leave_approve', "Cuti {$leave->request_no} disetujui ({$leave->days} hari)", 'MOD_ER_LEAVE', $leave);

        return response()->json(['success' => true, 'message' => "Pengajuan cuti {$leave->request_no} disetujui."]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate(['note' => 'required|string']);

        $leave = LeaveRequest::findOrFail($id);

        if (!in_array($leave->status, [LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_REVISE], true)) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah diproses.'], 422);
        }

        $leave->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'decision_note' => $request->input('note'),
        ]);

        LeaveRequestLog::create([
            'leave_request_id' => $leave->id,
            'action' => LeaveRequestLog::ACTION_REJECT,
            'note' => $request->input('note'),
            'actor_id' => auth()->id(),
        ]);

        Log::record('leave_reject', "Cuti {$leave->request_no} ditolak: {$request->input('note')}", 'MOD_ER_LEAVE', $leave);

        return response()->json(['success' => true, 'message' => 'Pengajuan cuti ditolak.']);
    }

    public function revise(Request $request, $id): JsonResponse
    {
        $request->validate(['note' => 'required|string']);

        $leave = LeaveRequest::findOrFail($id);

        if (!in_array($leave->status, [LeaveRequest::STATUS_SUBMITTED], true)) {
            return response()->json(['success' => false, 'message' => 'Hanya pengajuan submitted yang bisa direvise.'], 422);
        }

        $leave->update(['status' => LeaveRequest::STATUS_REVISE, 'decision_note' => $request->input('note')]);

        LeaveRequestLog::create([
            'leave_request_id' => $leave->id,
            'action' => LeaveRequestLog::ACTION_REVISE,
            'note' => $request->input('note'),
            'actor_id' => auth()->id(),
        ]);

        Log::record('leave_revise', "Cuti {$leave->request_no} diminta revisi", 'MOD_ER_LEAVE', $leave);

        return response()->json(['success' => true, 'message' => 'Pengajuan dikembalikan untuk revisi.']);
    }

    public function cancel($id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);

        if (!in_array($leave->status, [LeaveRequest::STATUS_DRAFT, LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_REVISE], true)) {
            return response()->json(['success' => false, 'message' => 'Pengajuan approved tidak bisa dibatalkan.'], 422);
        }

        // Cancel adalah aksi PEMOHON — hanya pemilik pengajuan (submitted_by) atau admin
        if (auth()->user()->role !== 'Admin' && $leave->submitted_by !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Hanya pemohon yang dapat membatalkan pengajuannya.'], 403);
        }

        $leave->update(['status' => LeaveRequest::STATUS_CANCELLED]);

        LeaveRequestLog::create([
            'leave_request_id' => $leave->id,
            'action' => LeaveRequestLog::ACTION_CANCEL,
            'actor_id' => auth()->id(),
        ]);

        Log::record('leave_cancel', "Cuti {$leave->request_no} dibatalkan pemohon", 'MOD_ER_LEAVE', $leave);

        return response()->json(['success' => true, 'message' => 'Pengajuan cuti dibatalkan.']);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Pengajuan cuti tidak dihapus permanen — gunakan Cancel atau Reject.',
        ], 422);
    }

    // ---------------- Helper ----------------

    /**
     * Approve → auto-create attendance (leave/sick), skip weekend.
     */
    private function syncAttendance(LeaveRequest $leave): void
    {
        $status = $leave->leaveType->code === 'SCK' ? Attendance::STATUS_SICK : Attendance::STATUS_LEAVE;

        $period = new \DatePeriod(
            new \DateTime($leave->start_date->toDateString()),
            new \DateInterval('P1D'),
            (new \DateTime($leave->end_date->toDateString()))->modify('+1 day')
        );

        foreach ($period as $date) {
            if (in_array($date->format('N'), [6, 7], true)) {
                continue; // weekend
            }

            // manual entry dikunci dari integrasi
            Attendance::where('employee_id', $leave->employee_id)
                ->where('work_date', $date->format('Y-m-d'))
                ->whereNotIn('check_in_method', [Attendance::METHOD_MANUAL])
                ->delete();

            Attendance::firstOrCreate([
                'employee_id' => $leave->employee_id,
                'work_date' => $date->format('Y-m-d'),
            ], [
                'status' => $status,
                'check_in_method' => Attendance::METHOD_LEAVE_INTEGRATION,
                'source' => Attendance::SOURCE_AUTO,
                'note' => 'Auto dari pengajuan '.$leave->request_no,
                'created_by' => auth()->id(),
            ]);
        }
    }

    private function workingDays(string $start, string $end): int
    {
        $period = new \DatePeriod(
            new \DateTime($start),
            new \DateInterval('P1D'),
            (new \DateTime($end))->modify('+1 day')
        );

        $count = 0;
        foreach ($period as $date) {
            if (!in_array($date->format('N'), [6, 7], true)) {
                $count++;
            }
        }

        return $count;
    }

    private function remainingQuota(int $employeeId, int $leaveTypeId, int $year): int
    {
        $balance = LeaveBalance::where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        if (!$balance) {
            return (int) (LeaveType::find($leaveTypeId)->quota_days ?? 0);
        }

        return max(0, $balance->entitlement + $balance->carried_over - $balance->used);
    }

    private function nextRequestNo(): string
    {
        $count = LeaveRequest::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return 'LR-'.now()->format('Y-m').'-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}

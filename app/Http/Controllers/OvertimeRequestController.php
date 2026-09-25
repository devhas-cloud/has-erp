<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Log;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OvertimeRequestController extends Controller
{
    public function index()
    {
        return view('overtime-request.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = OvertimeRequest::query()->with(['employee', 'attendance']);

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
            $query->where('work_date', 'like', $request->input('month').'-%');
        }

        $recordsTotal = OvertimeRequest::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $requests = $query->orderByDesc('work_date')
            ->orderByDesc('id')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = [];
        foreach ($requests as $i => $ot) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $ot->id,
                'request_no' => $ot->request_no,
                'employee_no' => $ot->employee->employee_no ?? null,
                'employee' => $ot->employee->name ?? null,
                'work_date' => $ot->work_date?->format('d-m-Y'),
                'shift_end' => $ot->shift_end_time?->format('H:i'),
                'start_time' => $ot->start_time?->format('H:i'),
                'end_time' => $ot->end_time?->format('H:i'),
                'hours' => (float) $ot->hours,
                'multiplier' => (float) $ot->multiplier,
                'estimated_amount' => number_format((float) $ot->estimated_amount, 2),
                'status' => $ot->status,
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
        $overtime = OvertimeRequest::with(['employee', 'attendance'])
            ->findOrFail($id);

        return view('overtime-request.show', [
            'overtime' => $overtime,
            'divisor' => config('er.overtime.hour_divisor'),
        ]);
    }

    /**
     * Data absensi hari terpilih untuk modal pengajuan — basis hitung
     * jam lembur otomatis (start = clock_out, end = shift_end).
     */
    public function fetchAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'work_date' => 'required|date',
        ]);

        $attendance = Attendance::with('shift')
            ->where('employee_id', $validated['employee_id'])
            ->where('work_date', $validated['work_date'])
            ->first();

        $employee = Employee::find($validated['employee_id']);

        return response()->json([
            'success' => true,
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'shift_id' => $attendance->shift_id,
                'shift_name' => $attendance->shift?->name,
                'shift_end_time' => $attendance->shift?->clock_out_time?->format('H:i'),
                'clock_in' => $attendance->clock_in?->format('H:i'),
                'clock_out' => $attendance->clock_out?->format('H:i'),
            ] : null,
            'base_salary' => $employee->base_salary,
            'default_multiplier' => (float) config('er.overtime.default_multiplier'),
            'hour_divisor' => (int) config('er.overtime.hour_divisor'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id|unique:overtime_requests,employee_id,NULL,id,work_date,'.$request->input('work_date'),
            'work_date' => 'required|date',
            'attendance_id' => 'nullable|integer|exists:attendances,id',
            'shift_end_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'multiplier' => 'nullable|numeric|min:1|max:10',
            'reason' => 'required|string|max:500',
        ], [
            'employee_id.unique' => 'Karyawan ini sudah punya pengajuan lembur pada tanggal tersebut.',
        ]);

        $attendance = Attendance::with('shift')->find($validated['attendance_id'] ?? null);
        if (!$attendance) {
            $attendance = Attendance::with('shift')
                ->where('employee_id', $validated['employee_id'])
                ->where('work_date', $validated['work_date'])
                ->first();
        }

        $employee = Employee::findOrFail($validated['employee_id']);

        if (!$attendance && auth()->user()->role !== 'Admin') {
            // praktik: admin HR dapat buat manual bila absen belum ada;
            // pengajuan reguler wajib ada absensi (basis hitung otomatis)
            return response()->json([
                'success' => false,
                'message' => 'Absensi pada tanggal tersebut belum ada. Absen dahulu (basis hitung jam lembur berasal dari absensi).',
            ], 422);
        }

        $shiftEnd = $validated['shift_end_time']
            ?? $attendance?->shift?->clock_out_time?->format('H:i')
            ?? config('er.overtime.default_shift_end', '17:00');

        $startTime = $attendance?->clock_out?->format('H:i') ?? $shiftEnd;
        $endTime = $validated['end_time'] ?? $startTime;

        $hours = $this->hoursBetween($shiftEnd, $endTime);

        if ($hours <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Durasi lembur 0 jam — jam pulang tidak melebihi jam pulang shift (maksimal '.config('er.overtime.max_hours').' jam).',
            ], 422);
        }

        $maxHours = (float) config('er.overtime.max_hours', 8);
        if ($hours > $maxHours) {
            return response()->json([
                'success' => false,
                "message" => "Jam lembur melebihi batas maksimal {$maxHours} jam.",
            ], 422);
        }

        $multiplier = (float) ($validated['multiplier'] ?? config('er.overtime.default_multiplier', 1.5));

        $overtime = DB::transaction(function () use ($validated, $attendance, $employee, $shiftEnd, $startTime, $endTime, $hours, $multiplier) {
            return OvertimeRequest::create([
                'request_no' => $this->nextRequestNo(),
                'employee_id' => $validated['employee_id'],
                'work_date' => $validated['work_date'],
                'attendance_id' => $attendance?->id,
                'shift_end_time' => $shiftEnd,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hours' => round($hours, 2),
                'multiplier' => $multiplier,
                'estimated_amount' => $this->estimate($employee->base_salary, $hours, $multiplier),
                'reason' => $validated['reason'],
                'status' => OvertimeRequest::STATUS_SUBMITTED,
                'submitted_by' => auth()->id(),
            ]);
        });

        Log::record(
            'overtime_submit',
            "Pengajuan lembur {$overtime->request_no} — {$employee->name} ({$hours} jam × {$multiplier})",
            'MOD_ER_OVERTIME',
            $overtime
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan lembur diterima — '.number_format((float) $overtime->estimated_amount, 2),
        ]);
    }

    /**
     * Koreksi umum (PUT) — masih diperbolehkan selama belum approved/rejected.
     * Approve/pembacaan admin pakai endpoint approve (terikat can_approve).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $overtime = OvertimeRequest::findOrFail($id);

        if (in_array($overtime->status, [OvertimeRequest::STATUS_APPROVED, OvertimeRequest::STATUS_REJECTED], true)) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah dikunci.'], 422);
        }

        $validated = $request->validate([
            'end_time' => 'nullable|date_format:H:i',
            'multiplier' => 'nullable|numeric|min:1|max:10',
            'reason' => 'nullable|string|max:500',
        ]);

        $hours = isset($validated['end_time'])
            ? $this->hoursBetween($overtime->shift_end_time?->format('H:i'), $validated['end_time'])
            : (float) $overtime->hours;

        if (isset($validated['end_time']) && $hours <= 0) {
            return response()->json(['success' => false, 'message' => 'Durasi lembur tidak lebih dari 0 jam.'], 422);
        }

        $maxHours = (float) config('er.overtime.max_hours', 8);
        if ($hours > $maxHours) {
            return response()->json(['success' => false, 'message' => "Jam lembur melebihi batas maksimal {$maxHours} jam."], 422);
        }

        $multiplier = (float) ($validated['multiplier'] ?? $overtime->multiplier);
        $endTime = $validated['end_time'] ?? $overtime->end_time?->format('H:i');

        $overtime->update([
            ...array_filter($validated, fn ($v) => !is_null($v)),
            'hours' => round($hours, 2),
            'multiplier' => $multiplier,
            'estimated_amount' => $this->estimate($overtime->employee->base_salary, $hours, $multiplier),
        ]);

        Log::record(
            'overtime_update',
            "Koreksi pengajuan lembur {$overtime->request_no} ({$hours} jam × {$multiplier})",
            'MOD_ER_OVERTIME',
            $overtime
        );

        return response()->json(['success' => true, 'message' => 'Pengajuan lembur diperbarui.']);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $overtime = OvertimeRequest::findOrFail($id);

        if ($overtime->status !== OvertimeRequest::STATUS_SUBMITTED) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah diproses.'], 422);
        }

        $validated = $request->validate([
            'end_time' => 'nullable|date_format:H:i',
            'multiplier' => 'nullable|numeric|min:1|max:10',
        ]);

        $hours = isset($validated['end_time'])
            ? $this->hoursBetween($overtime->shift_end_time?->format('H:i'), $validated['end_time'])
            : (float) $overtime->hours;

        if (isset($validated['end_time']) && $hours <= 0) {
            return response()->json(['success' => false, 'message' => 'Durasi lembur tidak lebih dari 0 jam.'], 422);
        }

        $multiplier = (float) ($validated['multiplier'] ?? $overtime->multiplier);
        $endTime = $validated['end_time'] ?? $overtime->end_time?->format('H:i');

        $overtime->update([
            'hours' => round($hours, 2),
            'multiplier' => $multiplier,
            'end_time' => $endTime,
            'estimated_amount' => $this->estimate($overtime->employee->base_salary, $hours, $multiplier),
            'status' => OvertimeRequest::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'decision_note' => $request->input('note'),
        ]);

        Log::record(
            'overtime_approve',
            "Pengajuan lembur {$overtime->request_no} dipersetujui ({$hours} jam × {$multiplier})",
            'MOD_ER_OVERTIME',
            $overtime
        );

        return response()->json(['success' => true, 'message' => 'Pengajuan lembur disetujui.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $overtime = OvertimeRequest::findOrFail($id);

        $request->validate(['note' => 'required|string']);

        if ($overtime->status !== OvertimeRequest::STATUS_SUBMITTED) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah diproses.'], 422);
        }

        $overtime->update([
            'status' => OvertimeRequest::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'decision_note' => $request->input('note'),
        ]);

        Log::record(
            'overtime_reject',
            "Pengajuan lembur {$overtime->request_no} ditolak: {$request->input('note')}",
            'MOD_ER_OVERTIME',
            $overtime
        );

        return response()->json(['success' => true, 'message' => 'Pengajuan lembur ditolak.']);
    }

    public function cancel($id): JsonResponse
    {
        $overtime = OvertimeRequest::findOrFail($id);

        if ($overtime->status !== OvertimeRequest::STATUS_SUBMITTED) {
            return response()->json(['success' => false, 'message' => 'Pengajuan yang sudah diproses tidak bisa dibatalkan.'], 422);
        }

        // Cancel adalah aksi PEMOHON — hanya pemilik pengajuan (submitted_by) atau admin
        if (auth()->user()->role !== 'Admin' && $overtime->submitted_by !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Hanya pemohon yang dapat membatalkan pengajuannya.'], 403);
        }

        $overtime->update(['status' => OvertimeRequest::STATUS_CANCELLED]);

        Log::record(
            'overtime_cancel',
            "Pengajuan lembur {$overtime->request_no} dibatalkan pemohon",
            'MOD_ER_OVERTIME',
            $overtime
        );

        return response()->json(['success' => true, 'message' => 'Pengajuan lembur dibatalkan.']);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Pengajuan lembur tidak dihapus permanen — gunakan Cancel atau Reject.',
        ], 422);
    }

    // ---------------- Helper ----------------

    /**
     * hours = end_time − shift_end (floor per menit, desimal jam).
     * Time pakai format string sesuai catatan casts (bukan Carbon diff global).
     */
    private function hoursBetween(?string $shiftEnd, ?string $endTime): float
    {
        if (!$shiftEnd || !$endTime) {
            return 0.0;
        }

        $base = strtotime('2000-01-01 00:00:00');
        $diffMinutes = (int) floor(
            ((strtotime($endTime) - $base) - (strtotime($shiftEnd) - $base)) / 60
        );

        // shift malam: end_time lewat tengah malam (mis. shift end 22:00, pulang 01:00)
        if ($diffMinutes < 0) {
            $diffMinutes += 24 * 60;
        }

        if ($diffMinutes <= 0) {
            return 0.0;
        }

        return $diffMinutes / 60;
    }

    private function estimate($baseSalary, float $hours, float $multiplier): float
    {
        $divisor = max(1, (int) config('er.overtime.hour_divisor', 173));

        return round(($baseSalary / $divisor) * $hours * $multiplier, 2);
    }

    private function nextRequestNo(): string
    {
        $count = OvertimeRequest::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return 'OT-'.now()->format('Y-m').'-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}

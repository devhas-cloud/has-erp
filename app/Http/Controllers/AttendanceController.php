<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceApproval;
use App\Models\Employee;
use App\Models\Log;
use App\Models\Office;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index()
    {
        return view('attendance.index', [
            'shifts' => Shift::all(),
            'offices' => Office::where('is_active', true)->get(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Attendance::query()
            ->with(['employee', 'shift', 'office']);

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('work_date', 'like', "%{$searchValue}%")
                    ->orWhere('check_in_method', 'like', "%{$searchValue}%")
                    ->orWhereHas('employee', function ($e) use ($searchValue) {
                        $e->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('employee_no', 'like', "%{$searchValue}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('method')) {
            $query->where('check_in_method', $request->input('method'));
        }

        if ($request->filled('month')) {
            $query->where('work_date', 'like', $request->input('month').'-%');
        }

        $recordsTotal = Attendance::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $attendances = (clone $query)
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = [];
        foreach ($attendances as $i => $attendance) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $attendance->id,
                'work_date' => $attendance->work_date?->format('d-m-Y'),
                'employee_no' => $attendance->employee->employee_no ?? null,
                'employee' => $attendance->employee->name ?? null,
                'employee_id' => $attendance->employee_id,
                'shift' => $attendance->shift->name ?? null,
                'clock_in' => $attendance->clock_in?->format('H:i'),
                'clock_out' => $attendance->clock_out?->format('H:i'),
                'late_minutes' => $attendance->late_minutes,
                'status' => $attendance->status,
                'check_in_method' => $attendance->check_in_method,
                'office' => $attendance->office->name ?? null,
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show(Request $request, $id)
    {
        $attendance = Attendance::with(['employee', 'shift', 'office'])
            ->findOrFail($id);

        $approvals = AttendanceApproval::with('actor')
            ->where('attendance_id', $attendance->id)
            ->latest()
            ->get();

        return view('attendance.show', [
            'attendance' => $attendance,
            'approvals' => $approvals,
        ]);
    }

    // ---------------- Absen manual (HR/admin) ----------------

    public function manual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'work_date' => 'required|date|unique:attendances,work_date,NULL,id,employee_id,'.$request->input('employee_id'),
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:' . implode(',', [Attendance::STATUS_PRESENT, Attendance::STATUS_LATE, Attendance::STATUS_LEAVE, Attendance::STATUS_SICK, Attendance::STATUS_ABSENT]),
            'note' => 'nullable|string',
        ]);

        $attendance = DB::transaction(function () use ($validated) {
            $shift = $validated['shift_id'] ? Shift::find($validated['shift_id']) : null;

            $lateMinutes = 0;
            if ($shift && $validated['clock_in']) {
                $withTolerance = \Carbon\Carbon::parse($shift->clock_in_time)
                    ->copy()
                    ->addMinutes((int) $shift->tolerance_minutes);
                $clockIn = \Carbon\Carbon::parse($validated['clock_in']);

                if ($clockIn->gt($withTolerance)) {
                    $lateMinutes = (int) abs($withTolerance->diffInMinutes($clockIn));
                }
            }

            return Attendance::create([
                ...$validated,
                'late_minutes' => $lateMinutes,
                'check_in_method' => Attendance::METHOD_MANUAL,
                'source' => Attendance::SOURCE_WEB,
                'created_by' => auth()->id(),
            ]);
        });

        Log::record(
            'attendance_manual',
            'Absen manual untuk '.$attendance->employee->name.' pada '.$attendance->work_date->format('d-m-Y'),
            'MOD_ER_ATTENDANCE',
            $attendance
        );

        return response()->json([
            'success' => true,
            'message' => 'Absen manual berhasil direkam.',
        ]);
    }

    // ---------------- Koreksi absensi (pengajuan karyawan) ----------------

    public function correctionsData(Request $request): JsonResponse
    {
        $query = \App\Models\AttendanceCorrection::query()->with(['employee']);

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('work_date', 'like', "%{$searchValue}%")
                    ->orWhere('reason', 'like', "%{$searchValue}%")
                    ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', "%{$searchValue}%")
                        ->orWhere('employee_no', 'like', "%{$searchValue}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsTotal = \App\Models\AttendanceCorrection::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 5);

        $corrections = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($corrections as $i => $correction) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $correction->id,
                'employee_no' => $correction->employee->employee_no ?? null,
                'employee' => $correction->employee->name ?? null,
                'work_date' => $correction->work_date?->format('d-m-Y'),
                'proposed_clock_in' => $correction->proposed_clock_in,
                'proposed_clock_out' => $correction->proposed_clock_out,
                'reason' => $correction->reason,
                'status' => $correction->status,
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function storeCorrection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'work_date' => 'required|date',
            'proposed_clock_in' => 'nullable|date_format:H:i',
            'proposed_clock_out' => 'nullable|date_format:H:i',
            'reason' => 'required|string|max:500',
        ]);

        if (!$validated['proposed_clock_in'] && !$validated['proposed_clock_out']) {
            return response()->json(['success' => false, 'message' => 'Isi jam masuk dan/atau jam pulang koreksinya.'], 422);
        }

        $duplicate = \App\Models\AttendanceCorrection::where('employee_id', $validated['employee_id'])
            ->where('work_date', $validated['work_date'])
            ->where('status', \App\Models\AttendanceCorrection::STATUS_PENDING)
            ->exists();

        if ($duplicate) {
            return response()->json(['success' => false, 'message' => 'Sudah ada pengajuan koreksi pending untuk karyawan ini pada tanggal tersebut.'], 422);
        }

        $correction = \App\Models\AttendanceCorrection::create([
            ...$validated,
            'status' => \App\Models\AttendanceCorrection::STATUS_PENDING,
        ]);

        Log::record(
            'attendance_correction_submit',
            'Pengajuan koreksi absensi '.$correction->employee->name.' ('.$correction->work_date->format('d-m-Y').')',
            'MOD_ER_ATTENDANCE'
        );

        return response()->json(['success' => true, 'message' => 'Pengajuan koreksi absensi dikirim.']);
    }

    public function approveCorrection($correctionId): JsonResponse
    {
        $correction = \App\Models\AttendanceCorrection::with('employee')->findOrFail($correctionId);

        if ($correction->status !== \App\Models\AttendanceCorrection::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Koreksi sudah diproses.'], 422);
        }

        DB::transaction(function () use ($correction) {
            $attendance = Attendance::firstOrNew([
                'employee_id' => $correction->employee_id,
                'work_date' => $correction->work_date,
            ]);

            $shift = $attendance->shift_id
                ? Shift::find($attendance->shift_id)
                : null;
            $lateMinutes = 0;

            if ($shift && $correction->proposed_clock_in) {
                $withTolerance = \Carbon\Carbon::parse($shift->clock_in_time)
                    ->copy()
                    ->addMinutes((int) $shift->tolerance_minutes);
                $clockIn = \Carbon\Carbon::parse($correction->proposed_clock_in);
                if ($clockIn->gt($withTolerance)) {
                    $lateMinutes = (int) abs($withTolerance->diffInMinutes($clockIn));
                }
            }

            $attendance->clock_in = $correction->proposed_clock_in ?? $attendance->clock_in;
            $attendance->clock_out = $correction->proposed_clock_out ?? $attendance->clock_out;
            $attendance->late_minutes = max(0, $lateMinutes);
            $attendance->status = $attendance->late_minutes > 0 ? Attendance::STATUS_LATE : Attendance::STATUS_PRESENT;
            $attendance->check_in_method = Attendance::METHOD_MANUAL;
            $attendance->source = Attendance::SOURCE_WEB;
            $attendance->note = 'Koreksi disetujui: '.$correction->reason;
            $attendance->created_by = auth()->id();
            $attendance->save();

            $correction->update([
                'status' => \App\Models\AttendanceCorrection::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        Log::record(
            'attendance_correction_approve',
            "Koreksi absensi #{$correctionId} disetujui — record absensi {$correction->employee->name} diperbarui",
            'MOD_ER_ATTENDANCE'
        );

        return response()->json(['success' => true, 'message' => 'Koreksi disetujui dan absensi diperbarui.']);
    }

    public function rejectCorrection(Request $request, $correctionId): JsonResponse
    {
        $request->validate(['note' => 'required|string']);

        $correction = \App\Models\AttendanceCorrection::findOrFail($correctionId);

        if ($correction->status !== \App\Models\AttendanceCorrection::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Koreksi sudah diproses.'], 422);
        }

        $correction->update([
            'status' => \App\Models\AttendanceCorrection::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'decision_note' => $request->input('note'),
        ]);

        Log::record(
            'attendance_correction_reject',
            "Koreksi absensi #{$correctionId} ditolak: {$request->input('note')}",
            'MOD_ER_ATTENDANCE'
        );

        return response()->json(['success' => true, 'message' => 'Pengajuan koreksi ditolak.']);
    }

    // ---------------- Import log device fingerprint ----------------

    public function importDevice(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:2048']);

        try {
            $rows = $this->parseDeviceFile($request->file('file'));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Berkas tidak dapat dibaca: '.$e->getMessage()], 422);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $employeeNo = trim((string) ($row['employee_no'] ?? ''));
            $timestamp = $row['timestamp'] ?? null;

            if (!$employeeNo || !$timestamp) {
                $skipped++;
                continue;
            }

            $employee = Employee::where(function ($q) use ($employeeNo) {
                $q->where('employee_no', $employeeNo)->orWhere('nik', $employeeNo);
            })->first();

            if (!$employee) {
                $skipped++;
                continue;
            }

            $date = $timestamp->toDateString();
            $time = $timestamp->format('H:i:s');

            $attendance = Attendance::firstOrNew([
                'employee_id' => $employee->id,
                'work_date' => $date,
            ]);

            if (!$attendance->exists) {
                $attendance->check_in_method = Attendance::METHOD_FINGERPRINT;
                $attendance->source = Attendance::SOURCE_DEVICE;
                $attendance->status = Attendance::STATUS_PENDING;
                $attendance->clock_in = $time;
            } elseif ($attendance->check_in_method === Attendance::METHOD_MANUAL) {
                // record manual dikunci dari overwrite device
                $skipped++;
                continue;
            } elseif (strtotime($time) < strtotime($attendance->clock_in)) {
                // scan lebih awal dari clock_in yang sudah terekam → geser masuk
                $attendance->clock_in = $time;
            } elseif (!$attendance->clock_out || strtotime($time) > strtotime($attendance->clock_out)) {
                $attendance->clock_out = $time;
            }

            $attendance->save();
            $imported++;
        }

        Log::record(
            'attendance_import',
            "Import absensi perangkat: {$imported} baris terimpor, {$skipped} dilewati.",
            'MOD_ER_ATTENDANCE'
        );

        return response()->json([
            'success' => true,
            'message' => "Import selesai: {$imported} terimpor, {$skipped} baris dilewati.",
        ]);
    }

    /**
     * Kolom berkas: NIP/EMP-xxxx | datetime (YYYY-MM-DD HH:MM[:SS])
     * Mendukung .csv dan .xlsx.
     */
    private function parseDeviceFile($file): array
    {
        $rows = [];
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            $index = 0;

            while (($line = fgetcsv($handle)) !== false) {
                if ($index++ === 0 && count($line) > 1 && str_contains(strtolower(trim($line[0])), 'employee_no')) {
                    continue;
                }

                if (count($line) < 2) {
                    $rows[] = ['employee_no' => (string) ($line[0] ?? ''), 'timestamp' => null];
                    continue;
                }

                try {
                    $ts = \Carbon\Carbon::parse(trim($line[1]));
                } catch (\Throwable) {
                    $ts = null;
                }

                $rows[] = [
                    'employee_no' => (string) $line[0],
                    'timestamp' => $ts,
                ];
            }

            fclose($handle);
        } else {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($sheet->getRowIterator() as $i => $row) {
                if ($i === 1) {
                    continue; // header
                }

                $empNo = (string) $sheet->getCellByColumnAndRow(1, $i)->getValue();
                $tsVal = $sheet->getCellByColumnAndRow(2, $i)->getValue();

                $ts = null;
                if ($tsVal !== null) {
                    try {
                        if (is_numeric($tsVal)) {
                            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tsVal);
                            $ts = \Carbon\Carbon::instance($dt);
                        } else {
                            $ts = \Carbon\Carbon::parse(trim((string) $tsVal));
                        }
                    } catch (\Throwable) {
                        $ts = null;
                    }
                }

                if (!$empNo || !$ts) {
                    $rows[] = ['employee_no' => $empNo, 'timestamp' => null];
                    continue;
                }

                $rows[] = ['employee_no' => $empNo, 'timestamp' => $ts];
            }
        }

        return $rows;
    }

    // ---------------- Approve / reject ----------------

    public function approve(Request $request, $id): JsonResponse
    {
        $attendance = Attendance::findOrFail($id);

        if ($attendance->status !== Attendance::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Absensi sudah diproses.'], 422);
        }

        $attendance->status = $attendance->late_minutes > 0 ? Attendance::STATUS_LATE : Attendance::STATUS_PRESENT;
        $attendance->save();

        AttendanceApproval::create([
            'attendance_id' => $attendance->id,
            'action' => AttendanceApproval::ACTION_APPROVE,
            'note' => $request->input('note'),
            'actor_id' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Absensi disetujui.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $attendance = Attendance::findOrFail($id);

        $request->validate(['note' => 'required|string']);

        if ($attendance->status !== Attendance::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Absensi sudah diproses.'], 422);
        }

        $attendance->update(['status' => Attendance::STATUS_ABSENT]);

        AttendanceApproval::create([
            'attendance_id' => $attendance->id,
            'action' => AttendanceApproval::ACTION_REJECT,
            'note' => $request->input('note'),
            'actor_id' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Absensi ditolak dan ditandai absent.']);
    }

    // ---------------- Master Office ----------------

    public function office()
    {
        return view('attendance.office', [
            'offices' => Office::orderBy('name')->get(),
            'shifts' => Shift::orderBy('name')->get(),
        ]);
    }

    public function storeOffice(Request $request): JsonResponse
    {
        $validated = $request->validate($this->officeRules());

        $office = Office::create($validated);

        Log::record('office_create', "Menambah office {$office->name}", 'MOD_ER_ATTENDANCE');

        return response()->json(['success' => true, 'message' => 'Office berhasil ditambahkan.']);
    }

    public function updateOffice(Request $request, $officeId): JsonResponse
    {
        $office = Office::findOrFail($officeId);

        $office->update($request->validate($this->officeRules()));

        Log::record('office_update', "Memperbarui office {$office->name}", 'MOD_ER_ATTENDANCE');

        return response()->json(['success' => true, 'message' => 'Office berhasil diperbarui.']);
    }

    public function destroyOffice($officeId): JsonResponse
    {
        $office = Office::findOrFail($officeId);

        if ($office->attendances()->exists()) {
            return response()->json(['success' => false, 'message' => 'Office masih dipakai pada record absensi, tidak dapat dihapus. Nonaktifkan saja.'], 422);
        }

        $name = $office->name;
        $office->delete();

        Log::record('office_delete', "Menghapus office {$name}", 'MOD_ER_ATTENDANCE');

        return response()->json(['success' => true, 'message' => 'Office berhasil dihapus.']);
    }

    // ---------------- Master Shift ----------------

    public function storeShift(Request $request): JsonResponse
    {
        $validated = $request->validate($this->shiftRules());

        $shift = Shift::create($validated);

        Log::record('shift_create', "Menambah shift {$shift->name}", 'MOD_ER_ATTENDANCE');

        return response()->json(['success' => true, 'message' => 'Shift berhasil ditambahkan.']);
    }

    public function updateShift(Request $request, $shiftId): JsonResponse
    {
        $shift = Shift::findOrFail($shiftId);

        $shift->update($request->validate($this->shiftRules()));

        Log::record('shift_update', "Memperbarui shift {$shift->name}", 'MOD_ER_ATTENDANCE');

        return response()->json(['success' => true, 'message' => 'Shift berhasil diperbarui.']);
    }

    public function destroyShift($shiftId): JsonResponse
    {
        $shift = Shift::findOrFail($shiftId);

        if ($shift->attendances()->exists()) {
            return response()->json(['success' => false, 'message' => 'Shift masih dipakai pada record absensi, tidak dapat dihapus.'], 422);
        }

        $name = $shift->name;
        $shift->delete();

        Log::record('shift_delete', "Menghapus shift {$name}", 'MOD_ER_ATTENDANCE');

        return response()->json(['success' => true, 'message' => 'Shift berhasil dihapus.']);
    }

    // ---------------- Helper ----------------

    private function officeRules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric',
            'radius_meters' => 'required|integer|min:10',
            'is_active' => 'nullable|boolean',
        ];
    }

    private function shiftRules(): array
    {
        return [
            'name' => 'required|string|max:50',
            'clock_in_time' => 'required|date_format:H:i:s',
            'clock_out_time' => 'required|date_format:H:i:s',
            'tolerance_minutes' => 'required|integer|min:0',
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeFamily;
use App\Models\EmployeeSalaryComponent;
use App\Models\JobTitle;
use App\Models\Log;
use App\Models\SalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeManagementController extends Controller
{
    public function index()
    {
        return view('employee-management.index', [
            'divisions' => Division::orderBy('division_name')->get(),
            'jobTitles' => JobTitle::orderBy('title_name')->get(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['division', 'jobTitle', 'manager']);

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('employee_no', 'like', "%{$searchValue}%")
                    ->orWhere('nik', 'like', "%{$searchValue}%")
                    ->orWhere('phone', 'like', "%{$searchValue}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsTotal = Employee::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $employees = $query->orderBy('employee_no')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = [];
        foreach ($employees as $i => $employee) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $employee->id,
                'employee_no' => $employee->employee_no,
                'name' => $employee->name,
                'division' => $employee->division->division_name ?? null,
                'job_title' => $employee->jobTitle->title_name ?? null,
                'phone' => $employee->phone,
                'join_date' => $employee->join_date?->format('d-m-Y'),
                'base_salary' => number_format((float) $employee->base_salary, 2),
                'status' => $employee->status,
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $employee = DB::transaction(function () use ($validated) {
            return Employee::create($validated);
        });

        Log::record(
            'create',
            "Menambahkan karyawan {$employee->employee_no} — {$employee->name}",
            'MOD_ER_EMPLOYEE',
            $employee
        );

        return response()->json([
            'success' => true,
            'message' => 'Karyawan '.$employee->name.' berhasil ditambahkan.',
        ]);
    }

    public function edit($id): JsonResponse
    {
        $employee = Employee::with(['families', 'manager'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $employee,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate($this->rules($employee->id));

        if (!empty($validated['manager_id']) && (int) $validated['manager_id'] === (int) $employee->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'manager_id' => ['Karyawan tidak bisa menjadi atasan dirinya sendiri.'],
            ]);
        }

        $employee->update($validated);

        Log::record(
            'update',
            "Memperbarui data karyawan {$employee->employee_no} — {$employee->name}",
            'MOD_ER_EMPLOYEE',
            $employee
        );

        return response()->json([
            'success' => true,
            'message' => 'Karyawan '.$employee->name.' berhasil diperbarui.',
        ]);
    }

    /**
     * ER tidak mengenal hard-delete karyawan (FK ke attendance/leave/loan/
     * payslip akan terputus). Menu hapus diarahkan ke penonaktifan.
     */
    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Karyawan tidak dapat dihapus permanen. Gunakan tombol Nonaktifkan untuk mengakhiri kepegawaian.',
        ], 422);
    }

    public function show($id)
    {
        $employee = Employee::with(['division', 'jobTitle', 'manager'])
            ->findOrFail($id);

        $attendanceMonth = request()->input('att_month', now()->format('Y-m'));

        $attendances = $employee->attendances()
            ->with(['shift', 'office'])
            ->where('work_date', 'like', $attendanceMonth.'-%')
            ->orderByDesc('work_date')
            ->limit(120)
            ->get();

        $logs = $employee->logs()->with('user')->latest()->limit(50)->get();

        return view('employee-management.show', [
            'employee' => $employee,
            'attendances' => $attendances,
            'attendanceMonth' => $attendanceMonth,
            'logs' => $logs,
            'divisions' => Division::orderBy('division_name')->get(),
            'jobTitles' => JobTitle::orderBy('title_name')->get(),
        ]);
    }

    public function searchManagers(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $query = Employee::where('status', Employee::STATUS_ACTIVE)
            ->where('name', 'like', "%{$q}%");

        if ($request->filled('except_id')) {
            $query->whereKeyNot($request->input('except_id'));
        }

        $results = $query->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'text' => "{$e->employee_no} — {$e->name}",
            ]);

        return response()->json(['results' => $results]);
    }

    public function terminate(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        if ($employee->status === Employee::STATUS_TERMINATED) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan sudah berstatus terminated.',
            ], 422);
        }

        $employee->update([
            'status' => Employee::STATUS_TERMINATED,
            'resign_date' => now()->toDateString(),
        ]);

        Log::record(
            'terminate',
            "Mengakhiri kepegawaian {$employee->employee_no} — {$employee->name}",
            'MOD_ER_EMPLOYEE',
            $employee
        );

        return response()->json([
            'success' => true,
            'message' => 'Status karyawan '.$employee->name.' diakhiri (terminated).',
        ]);
    }

    public function reactivate($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $employee->update([
            'status' => Employee::STATUS_ACTIVE,
            'resign_date' => null,
        ]);

        Log::record(
            'reactivate',
            "Mengaktifkan kembali {$employee->employee_no} — {$employee->name}",
            'MOD_ER_EMPLOYEE',
            $employee
        );

        return response()->json([
            'success' => true,
            'message' => 'Status karyawan '.$employee->name.' kembali active.',
        ]);
    }

    // ---------------- Keluarga ----------------

    public function families($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $employee->families()->latest()->get(),
        ]);
    }

    public function storeFamily(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate($this->familyRules());

        $family = $employee->families()->create($validated);

        Log::record(
            'family_create',
            "Menambah anggota keluarga {$family->name} ({$family->relation}) atas karyawan {$employee->name}",
            'MOD_ER_EMPLOYEE',
            $employee
        );

        return response()->json([
            'success' => true,
            'message' => 'Anggota keluarga berhasil ditambahkan.',
            'data' => $family,
        ]);
    }

    public function updateFamily(Request $request, $id, $familyId): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $family = $employee->families()->findOrFail($familyId);

        $family->update($request->validate($this->familyRules()));

        Log::record(
            'family_update',
            "Memperbarui anggota keluarga {$family->name} atas karyawan {$employee->name}",
            'MOD_ER_EMPLOYEE',
            $employee
        );

        return response()->json(['success' => true, 'message' => 'Anggota keluarga berhasil diperbarui.']);
    }

    public function destroyFamily($id, $familyId): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $family = $employee->families()->findOrFail($familyId);
        $familyName = $family->name;

        $family->delete();

        Log::record(
            'family_delete',
            "Menghapus anggota keluarga {$familyName} atas karyawan {$employee->name}",
            'MOD_ER_EMPLOYEE',
            $employee
        );

        return response()->json(['success' => true, 'message' => 'Anggota keluarga berhasil dihapus.']);
    }

    // ---------------- Komponen Gaji (Tab) ----------------

    public function salaryComponents($id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $assigned = $employee->salaryComponents()
            ->with('salaryComponent')
            ->get();

        $masters = SalaryComponent::orderBy('name')->get()->map(fn (SalaryComponent $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'text' => "{$c->code} — {$c->name}",
            'type' => $c->type,
            'frequency' => $c->frequency,
            'default_amount' => (float) $c->amount,
            'calculation' => $c->calculation,
        ]);

        return response()->json([
            'success' => true,
            'data' => $assigned->map(fn (EmployeeSalaryComponent $esc) => [
                'id' => $esc->id,
                'override_amount' => (float) $esc->override_amount,
                'active_since' => $esc->active_since?->toDateString(),
                'is_active' => $esc->is_active,
                'component' => $esc->salaryComponent ? [
                    'id' => $esc->salaryComponent->id,
                    'code' => $esc->salaryComponent->code,
                    'name' => $esc->salaryComponent->name,
                    'type' => $esc->salaryComponent->type,
                    'frequency' => $esc->salaryComponent->frequency,
                    'default_amount' => (float) $esc->salaryComponent->amount,
                ] : null,
            ])->values(),
            'masters' => $masters,
        ]);
    }

    public function storeSalaryComponent(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $validated = $request->validate([
            'salary_component_id' => 'required|integer|exists:salary_components,id',
            'override_amount' => 'nullable|numeric|min:0',
            'active_since' => 'nullable|date',
        ]);

        $esc = $employee->salaryComponents()->create([
            'salary_component_id' => $validated['salary_component_id'],
            'override_amount' => $validated['override_amount'] ?? null,
            'active_since' => $validated['active_since'] ?? null,
            'is_active' => true,
        ]);

        Log::record(
            'salary_component_assign',
            "Menambah override komponen gaji (spec: {$esc->salaryComponent->code}) utk {$employee->name}",
            'MOD_ER_EMPLOYEE',
            $employee
        );

        return response()->json(['success' => true, 'message' => 'Komponen gaji berhasil ditambahkan.', 'data' => $esc]);
    }

    public function updateSalaryComponent(Request $request, $id, $employeeSalaryComponentId): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $esc = $employee->salaryComponents()->findOrFail($employeeSalaryComponentId);

        $esc->update($request->validate([
            'override_amount' => 'nullable|numeric|min:0',
            'active_since' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]));

        Log::record('salary_component_update', "Update override komponen gaji utk {$employee->name}", 'MOD_ER_EMPLOYEE', $employee);

        return response()->json(['success' => true, 'message' => 'Override komponen gaji diperbarui.']);
    }

    public function destroySalaryComponent($id, $employeeSalaryComponentId): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $esc = $employee->salaryComponents()->findOrFail($employeeSalaryComponentId);

        $esc->delete();

        Log::record('salary_component_delete', "Hapus override komponen gaji utk {$employee->name}", 'MOD_ER_EMPLOYEE', $employee);

        return response()->json(['success' => true, 'message' => 'Override komponen gaji dihapus.']);
    }

    // ---------------- Helper ----------------

    private function rules(?int $ignoreId = null): array
    {
        return [
            'employee_no' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('employees', 'employee_no')->ignore($ignoreId),
            ],
            'name' => 'required|string|max:150',
            'gender' => 'nullable|in:male,female',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:25',
            'email' => 'nullable|email|max:150',
            'nik' => 'nullable|string|max:30',
            'npwp_no' => 'nullable|string|max:30',
            'ptkp_status' => 'nullable|in:TK0,TK1,TK2,TK3,K0,K1,K2,K3',
            'bpjs_kesehatan_no' => 'nullable|string|max:30',
            'bpjs_tk_no' => 'nullable|string|max:30',
            'division_id' => 'nullable|integer|exists:divisions,id',
            'job_title_id' => 'nullable|integer|exists:job_titles,id',
            'manager_id' => 'nullable|integer|exists:employees,id',
            'join_date' => 'nullable|date',
            'base_salary' => 'required|numeric|min:0',
            'bank_name' => 'nullable|string|max:50',
            'bank_account_no' => 'nullable|string|max:30',
            'bank_account_name' => 'nullable|string|max:100',
        ];
    }

    private function familyRules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'relation' => 'required|in:spouse,child,parent',
            'birth_date' => 'nullable|date',
            'occupation' => 'nullable|string|max:100',
        ];
    }
}

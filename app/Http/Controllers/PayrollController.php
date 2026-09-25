<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Log;
use App\Models\LoanInstallment;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipComponent;
use App\Models\SalaryComponent;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollController extends Controller
{
    public function index()
    {
        return view('payroll.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = PayrollPeriod::query();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where('name', 'like', "%{$searchValue}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsTotal = PayrollPeriod::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $periods = $query->orderByDesc('start_date')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = [];
        foreach ($periods as $i => $period) {
            $payslips = Payslip::where('payroll_period_id', $period->id)->get();

            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $period->id,
                'name' => $period->name,
                'range' => $period->start_date->format('d-m-Y').' s/d '.$period->end_date->format('d-m-Y'),
                'employees' => $payslips->count(),
                'gross' => number_format((float) $payslips->sum('total_gross'), 2),
                'deduction' => number_format((float) $payslips->sum('total_deduction'), 2),
                'net' => number_format((float) $payslips->sum('total_net'), 2),
                'is_thr' => $period->is_thr,
                'status' => $period->status,
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
        $period = PayrollPeriod::findOrFail($id);

        return view('payroll.show', [
            'period' => $period,
            'payslips' => Payslip::with('employee')
                ->where('payroll_period_id', $period->id)
                ->orderBy('id')
                ->get(),
            'compilePreview' => in_array($period->status, [PayrollPeriod::STATUS_OPEN, PayrollPeriod::STATUS_PROCESSING])
                ? PayrollService::compilePreview($period)
                : collect(),
            'logs' => $period->logs()->with('actor')->latest()->get(),
        ]);
    }

    /**
     * Buat periode baru — cutoff auto 25→24, anti-overlap, is_thr otomatis
     * (periode yang mencakup bulan THR di config).
     */
    public function store(Request $request): JsonResponse
    {
        [$start, $end] = PayrollService::nextPeriodRange();

        $overlap = PayrollPeriod::where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date', [$start, $end]);
        })->exists();

        if ($overlap) {
            return response()->json(['success' => false, 'message' => 'Periode overlap dengan periode sebelumnya.'], 422);
        }

        $thrMonth = (int) config('er.payroll.thr_month', 3);
        $isThr = false;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->month === $thrMonth) {
                $isThr = true;
                break;
            }
        }

        $period = PayrollPeriod::create([
            'name' => $start->format('Y-m-d').' → '.$end->format('Y-m-d'),
            'start_date' => $start,
            'end_date' => $end,
            'is_thr' => $isThr,
            'status' => PayrollPeriod::STATUS_OPEN,
            'created_by' => auth()->id(),
        ]);

        Log::record(
            'payroll_period_create',
            "Membuat periode payroll {$period->name} (cutoff 25–24)".($period->is_thr ? ' — periode THR' : ''),
            'MOD_ER_PAYROLL',
            $period
        );

        return response()->json([
            'success' => true,
            'message' => "Periode payroll {$period->name} dibuat.",
            'data' => ['id' => $period->id],
        ]);
    }

    public function edit($id): JsonResponse
    {
        $period = PayrollPeriod::with(['payslips.employee', 'payslips.components', 'payslips.payrollPeriod'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $period]);
    }

    /**
     * Step Compile → Generate Draft payslips (idempotent; re-generate membersihkan komponen).
     */
    public function generateDraft(Request $request, $id): JsonResponse
    {
        $period = PayrollPeriod::findOrFail($id);

        if ($period->status === PayrollPeriod::STATUS_CLOSED) {
            return response()->json(['success' => false, 'message' => 'Periode sudah dikunci.'], 422);
        }

        $employeeIds = $request->input('employee_ids');
        if (! $request->boolean('all') && ! $employeeIds) {
            return response()->json(['success' => false, 'message' => 'Pilih minimal satu karyawan untuk generate draft.'], 422);
        }

        $created = DB::transaction(fn () => PayrollService::generateDraft($period, $request->boolean('all') ? null : $employeeIds));

        PayrollService::logPeriod($period, 'generate_draft', "Generate draft {$created} karyawan");

        return response()->json(['success' => true, 'message' => "Draft payslip digenerate untuk {$created} karyawan."]);
    }

    /**
     * Step Draft — editor komponen: tambah/edit/hapus payslip_components, recalc totals.
     */
    public function updateComponent(Request $request, $id, ?int $componentId = null): JsonResponse
    {
        $payslip = Payslip::with('payrollPeriod')->findOrFail($id);

        if ($payslip->locked_at || $payslip->payrollPeriod->status === PayrollPeriod::STATUS_CLOSED) {
            return response()->json(['success' => false, 'message' => 'Payslip sudah dikunci.'], 422);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'type' => 'required|in:allowance,deduction,bonus,overtime',
            'amount' => 'required|numeric',
        ]);

        if ($componentId) {
            $component = PayslipComponent::where('payslip_id', $id)->findOrFail($componentId);
            $component->update([
                'label' => $validated['label'],
                'type' => $validated['type'],
                'amount' => $validated['amount'],
            ]);
        } else {
            $component = PayslipComponent::create([
                'payslip_id' => $id,
                'label' => $validated['label'],
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'sort_order' => PayslipComponent::where('payslip_id', $id)->max('sort_order') + 1,
            ]);
        }

        $this->recalcTotals($payslip);

        return response()->json([
            'success' => true,
            'message' => $componentId ? 'Komponen diperbarui.' : 'Komponen ditambahkan.',
            'data' => ['totals' => $payslip->only(['total_gross', 'total_deduction', 'total_bpjs_company', 'total_pph21', 'total_thr', 'total_net'])],
        ]);
    }

    public function destroyComponent($id, ?int $componentId = null): JsonResponse
    {
        $payslip = Payslip::with('payrollPeriod')->findOrFail($id);

        if ($payslip->locked_at || $payslip->payrollPeriod->status === PayrollPeriod::STATUS_CLOSED) {
            return response()->json(['success' => false, 'message' => 'Payslip sudah dikunci.'], 422);
        }

        PayslipComponent::where('payslip_id', $id)
            ->where('id', $componentId)
            ->where('source_type', 'manual')
            ->delete();

        $this->recalcTotals($payslip);

        return response()->json(['success' => true, 'message' => 'Komponen dihapus.']);
    }

    private function recalcTotals(Payslip $payslip): void
    {
        $components = PayslipComponent::where('payslip_id', $payslip->id)->get();

        $income = $payslip->base_salary_prorata
            + $components->whereIn('type', ['allowance', 'bonus', 'overtime'])
                ->whereNotIn('source_type', ['loan_installment', 'pph21'])
                ->sum('amount');

        $totalDeduction = $components->where('type', 'deduction')->sum('amount');

        $payslip->update([
            'total_gross' => round($income, 2),
            'total_deduction' => round($totalDeduction, 2),
            'total_pph21' => round($components->where('source_type', 'pph21')->sum('amount'), 2),
            'total_bpjs_company' => round(
                $components
                    ->where('source_type', 'salary_component')
                    ->filter(fn ($c) => str_contains($c->label, '(Perusahaan)'))
                    ->sum('amount'),
                2
            ),
            'total_thr' => round(
                $components
                    ->where('source_type', 'salary_component')
                    ->where('label', 'Tunjangan Hari Raya')
                    ->sum('amount'),
                2
            ),
            'total_net' => round($income - $totalDeduction, 2),
        ]);
    }

    /**
     * Finalize: lock seluruh payslip + period closed.
     */
    public function finalize(Request $request, $id): JsonResponse
    {
        $period = PayrollPeriod::with('payslips')->findOrFail($id);

        if ($period->status === PayrollPeriod::STATUS_CLOSED) {
            return response()->json(['success' => false, 'message' => 'Periode sudah closed.'], 422);
        }

        DB::transaction(function () use ($period) {
            Payslip::where('payroll_period_id', $period->id)->update(['locked_at' => now()]);
            $period->update(['status' => PayrollPeriod::STATUS_CLOSED, 'processed_at' => now()]);
        });

        Log::record(
            'payroll_finalize',
            "Periode {$period->name} diproses & dilock (".$period->payslips->count().' payslip)',
            'MOD_ER_PAYROLL',
            $period
        );

        PayrollService::logPeriod($period, 'finalize', 'Periode ditutup & payslip dikunci');

        return response()->json(['success' => true, 'message' => 'Periode diterminasi & payslip dikunci.']);
    }

    // ---------------- Pay Slip PDF ----------------

    public function payslipPdf(Request $request, $payroll, $payslipId)
    {
        $payslip = Payslip::with(['employee', 'payrollPeriod', 'components'])
            ->findOrFail($payslipId);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payroll.pdf.payslip', [
            'payslip' => $payslip,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('payslip_'.$payslip->employee->employee_no.'_'.$payslip->payrollPeriod->end_date->format('Ym').'.pdf');
    }

    // ---------------- Export Excel periode ----------------

    public function excel(Request $request, $id)
    {
        $period = PayrollPeriod::findOrFail($id);

        $payslips = Payslip::with('employee')
            ->where('payroll_period_id', $period->id)
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll '.$period->end_date->format('Ym'));

        $sheet->fromArray([
            ['No. Pegawai', 'Nama', 'Periode', 'Working days', 'Attended', 'Sakit', 'Izin', 'Alpa', 'Gaji Pokok (prorata)', 'THR', 'Gross', 'BPJS Company', 'PPh21', 'Deduction', 'Net'],
        ], null, 'A1');

        $row = 2;
        foreach ($payslips as $i => $payslip) {
            $sheet->fromArray([
                $payslip->employee->employee_no,
                $payslip->employee->name,
                $period->name,
                $payslip->working_days,
                $payslip->attended_days,
                $payslip->sick_days,
                $payslip->leave_days,
                $payslip->absent_days,
                $payslip->base_salary_prorata,
                $payslip->total_thr,
                $payslip->total_gross,
                $payslip->total_bpjs_company,
                $payslip->total_pph21,
                $payslip->total_deduction,
                $payslip->total_net,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/payroll/payroll_'.$period->end_date->format('Ym').'.xlsx');
        @mkdir(dirname($path), 0755, true);
        $writer->save($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ---------------- destroy guard ----------------

    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Periode payroll tidak dihapus —simpan histori untuk audit.',
        ], 422);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index()
    {
        return view('loan.index', [
            'divisor' => null,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Loan::query()->with(['employee', 'installments']);

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('loan_no', 'like', "%{$searchValue}%")
                    ->orWhere('purpose', 'like', "%{$searchValue}%")
                    ->orWhereHas('employee', function ($e) use ($searchValue) {
                        $e->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('employee_no', 'like', "%{$searchValue}%");
                    });
            });
        }

        foreach (array_filter([
            'status' => $request->input('status'),
        ]) as $field => $value) {
            $query->where($field, $value);
        }

        $recordsTotal = Loan::count();
        $recordsFiltered = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $loans = $query->orderByDesc('id')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = [];
        foreach ($loans as $i => $loan) {
            $installments = $loan->installments;
            $paidCount = $installments->where('status', LoanInstallment::STATUS_PAID)->count();

            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $loan->id,
                'loan_no' => $loan->loan_no,
                'employee_id' => $loan->employee_id,
                'employee_no' => $loan->employee->employee_no ?? null,
                'employee' => $loan->employee->name ?? null,
                'amount' => number_format((float) $loan->amount, 2),
                'tenor' => $loan->tenor_months,
                'installment_amount' => number_format((float) $loan->installment_amount, 2),
                'progress' => $installments->count() ? "{$paidCount}/{$installments->count()}" : null,
                'status' => $loan->status,
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
        $loan = Loan::with(['employee', 'installments', 'approvedBy'])
            ->findOrFail($id);

        return view('loan.show', [
            'loan' => $loan,
            'installs' => $loan->installments()->orderBy('installment_no')->get(),
            'paidTotal' => $loan->installments->where('status', LoanInstallment::STATUS_PAID)->sum('amount'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'amount' => 'required|numeric|min:1',
            'tenor_months' => 'required|integer|min:1|max:36',
            'installment_amount' => 'nullable|numeric|min:1',
            'fee_amount' => 'nullable|numeric|min:0',
            'disburse_date' => 'nullable|date',
            'purpose' => 'nullable|string|max:500',
        ]);

        $hasActiveInstallment = LoanInstallment::whereHas('loan', fn ($q) => $q
            ->where('employee_id', $validated['employee_id'])
            ->whereIn('status', [Loan::STATUS_ACTIVE, Loan::STATUS_APPROVED]))
            ->where('status', LoanInstallment::STATUS_UNPAID)
            ->exists();

        if ($hasActiveInstallment) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan ini masih punya angsuran berjalan. Selesaikan pinjaman sebelumnya dulu.',
            ], 422);
        }

        $finalInstallment = $validated['installment_amount']
            ?? round($validated['amount'] / $validated['tenor_months'], 2);

        $loan = DB::transaction(function () use ($validated, $finalInstallment) {
            return Loan::create([
                'loan_no' => $this->nextLoanNo(),
                'employee_id' => $validated['employee_id'],
                'amount' => $validated['amount'],
                'tenor_months' => $validated['tenor_months'],
                'installment_amount' => $finalInstallment,
                'fee_amount' => $validated['fee_amount'] ?? 0,
                'disburse_date' => $validated['disburse_date'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
                'status' => Loan::STATUS_PENDING,
            ]);
        });

        Log::record(
            'loan_submit',
            "Pengajuan pinjaman {$loan->loan_no} — {$loan->employee->name} Rp {$validated['amount']} tenor {$validated['tenor_months']} bln",
            'MOD_ER_LOAN',
            $loan
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan pinjaman '.$loan->loan_no.' diterima.',
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $loan = Loan::findOrFail($id);

        if ($loan->status !== Loan::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Pinjaman sudah dikunci (approval berakhir).'], 422);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1',
            'tenor_months' => 'nullable|integer|min:1|max:36',
            'fee_amount' => 'nullable|numeric|min:0',
            'disburse_date' => 'nullable|date',
            'purpose' => 'nullable|string|max:500',
            'installment_amount' => 'nullable|numeric|min:1',
        ]);

        // recompute angsuran hanya bila amount/tenor/installment_amount berubah,
        // dan selalu di-round agar jumlah angsuran = nominal pinjaman
        if (isset($validated['amount']) || isset($validated['tenor_months']) || isset($validated['installment_amount'])) {
            if (isset($validated['installment_amount'])) {
                $validated['installment_amount'] = round((float) $validated['installment_amount'], 2);
            } else {
                $amount = $validated['amount'] ?? $loan->amount;
                $tenor = $validated['tenor_months'] ?? $loan->tenor_months;

                $validated['installment_amount'] = round(($amount / $tenor), 2);
            }
        }

        $loan->update(array_filter($validated, fn ($v) => !is_null($v)));

        Log::record('loan_update', "Pengajuan pinjaman {$loan->loan_no} dikoreksi", 'MOD_ER_LOAN', $loan);

        return response()->json(['success' => true, 'message' => "Pinjaman {$loan->loan_no} diperbarui."]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $loan = Loan::with('employee')->findOrFail($id);

        if ($loan->status !== Loan::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Pinjaman sudah diproses.'], 422);
        }

        DB::transaction(function () use ($loan, $request) {
            $base = $loan->disburse_date?->copy()->addMonthNoOverflow() ?? now()->startOfMonth()->addMonthNoOverflow();
            if ($base->isPast()) {
                $base = now()->startOfMonth()->addMonthNoOverflow();
            }
            $disburseBase = $loan->disburse_date
                ? $loan->disburse_date->copy()->addMonthNoOverflow()
                : $base;

            foreach (range(1, $loan->tenor_months) as $no) {
                LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_no' => $no,
                    'due_date' => $disburseBase->copy()->addMonths($no - 1),
                    'amount' => $loan->installment_amount,
                    'status' => LoanInstallment::STATUS_UNPAID,
                ]);
            }

            $loan->update([
                'status' => Loan::STATUS_ACTIVE,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'decision_note' => $request->input('note'),
            ]);
        });

        Log::record(
            'loan_approve',
            "Pinjaman {$loan->loan_no} disetujui — jadwal {$loan->tenor_months} angsuran @ Rp {$loan->installment_amount} digenerate",
            'MOD_ER_LOAN',
            $loan
        );

        return response()->json(['success' => true, 'message' => "Pinjaman {$loan->loan_no} disetujui & angsuran digenerate."]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate(['note' => 'required|string']);

        $loan = Loan::findOrFail($id);

        if ($loan->status !== Loan::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Pinjaman sudah diproses.'], 422);
        }

        $loan->update([
            'status' => Loan::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'decision_note' => $request->input('note'),
        ]);

        Log::record('loan_reject', "Pinjaman {$loan->loan_no} ditolak: {$request->input('note')}", 'MOD_ER_LOAN', $loan);

        return response()->json(['success' => true, 'message' => 'Pengajuan pinjaman ditolak.']);
    }

    /**
     * Settle early = lunaskan seluruh sisa angsuran sekaligus (off-payroll).
     */
    public function settle(Request $request, $id): JsonResponse
    {
        $loan = Loan::with('installments')->findOrFail($id);

        if (!in_array($loan->status, [Loan::STATUS_ACTIVE], true)) {
            return response()->json(['success' => false, 'message' => 'Status pinjaman tidak dapat dilunaskan lebih awal.'], 422);
        }

        DB::transaction(function () use ($loan) {
            $loan->installments()
                ->where('status', LoanInstallment::STATUS_UNPAID)
                ->update([
                    'status' => LoanInstallment::STATUS_PAID,
                    'paid_at' => now(),
                ]);

            $loan->update(['status' => Loan::STATUS_SETTLED]);
        });

        Log::record('loan_settle', "Pinjaman {$loan->loan_no} dilunaskan lebih awal (early settle)", 'MOD_ER_LOAN', $loan);

        return response()->json(['success' => true, 'message' => "Pinjaman {$loan->loan_no} dilunaskan lebih awal."]);
    }

    /**
     * Bayar manual satu angsuran (off-payroll, admin) — potongan via payslip
     * diproses di Milestone 6 dengan menautkan paid_payslip_id.
     */
    public function pay($id): JsonResponse
    {
        $installment = LoanInstallment::with('loan')->findOrFail($id);

        if ($installment->status === LoanInstallment::STATUS_PAID) {
            return response()->json(['success' => false, 'message' => 'Angsuran ini sudah dibayar.'], 422);
        }

        if (!in_array($installment->loan->status, [Loan::STATUS_ACTIVE], true)) {
            return response()->json(['success' => false, 'message' => 'Pinjaman tidak sedang berstatus active.'], 422);
        }

        DB::transaction(function () use ($installment) {
            $installment->update(['status' => LoanInstallment::STATUS_PAID, 'paid_at' => now()]);

            if ($installment->loan->installments()->where('status', LoanInstallment::STATUS_UNPAID)->doesntExist()) {
                $installment->loan->update(['status' => Loan::STATUS_PAID_OFF]);
            }
        });

        Log::record(
            'loan_pay',
            "Angsuran #{$installment->installment_no} pinjaman {$installment->loan->loan_no} dibayar manual",
            'MOD_ER_LOAN',
            $installment->loan
        );

        return response()->json(['success' => true, 'message' => 'Angsuran dibayar.']);
    }

    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Pinjaman tidak dihapus permanen — gunakan Reject atau Settle Early.',
        ], 422);
    }

    // ---------------- Helper ----------------

private function nextLoanNo(): string
    {
        $count = Loan::whereYear('created_at', now()->year)
            ->count();

        return 'LN-'.now()->year.'-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}

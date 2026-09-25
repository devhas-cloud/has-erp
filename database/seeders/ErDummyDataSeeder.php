<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\EmployeeFamily;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestLog;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\Office;
use App\Models\Division;
use App\Models\JobTitle;
use App\Models\Shift;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ErDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Employee::exists()) {
            $this->command->warn('ER dummy data dilewati — tabel employees sudah berisi data.');

            return;
        }

        DB::transaction(function () {
            $this->seedMasters();
            $employees = $this->seedEmployees();
            $this->seedFamilies($employees);
            $this->seedAttendances($employees);
            $this->seedLeaveRequests($employees);
            $this->seedOvertimeRequests($employees);
            $this->seedLoans($employees);
            $this->seedPayrollPeriod($employees);
        });

        $this->command->info('ER dummy data seeded: employees='.Employee::count());
    }

    private function seedMasters(): void
    {
        Shift::firstOrCreate(['name' => 'Pagi'], [
            'clock_in_time' => '08:00:00',
            'clock_out_time' => '17:00:00',
            'tolerance_minutes' => 15,
        ]);

        Shift::firstOrCreate(['name' => 'Malam'], [
            'clock_in_time' => '22:00:00',
            'clock_out_time' => '06:00:00',
            'tolerance_minutes' => 15,
        ]);

        Shift::firstOrCreate(['name' => 'Sore'], [
            'clock_in_time' => '14:00:00',
            'clock_out_time' => '22:00:00',
            'tolerance_minutes' => 10,
        ]);

        Office::firstOrCreate(['name' => 'HQ Jakarta'], [
            'address' => 'Jl. Sudirman No. 45, Jakarta Pusat',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 150,
            'is_active' => true,
        ]);

        Office::firstOrCreate(['name' => 'Cabang Bandung'], [
            'address' => 'Jl. Asia Afrika No. 20, Bandung',
            'latitude' => -6.9128,
            'longitude' => 107.6084,
            'radius_meters' => 120,
            'is_active' => true,
        ]);
    }

    private function seedEmployees(): array
    {
        $divisionIds = Division::whereIn('division_name', ['Admin', 'WATER', 'IMS'])->pluck('id', 'division_name');
        $jobTitleIds = JobTitle::whereIn('title_name', ['Staff', 'Supervisor', 'Manager', 'CEO'])->pluck('id', 'title_name');

        $rows = [
            // [nama, gender, division, title, base, join (yrs ago), status]
            ['Budi Setiawan',      'male',   'Admin',  'CEO',        15000000, 6, 'active'],
            ['Ratih Prasetyo',     'female', 'WATER',  'Manager',    11000000, 5, 'active'],
            ['Agus Hidayat',       'male',   'IMS',    'Manager',    10000000, 4, 'active'],
            ['Siti Aminah',        'female', 'WATER',  'Supervisor',  8000000, 4, 'active'],
            ['Dedi Supriyadi',     'male',   'IMS',    'Supervisor',  9500000, 3, 'on_leave'],
            ['Andi Prasetyo',      'male',   'WATER',  'Staff',       6500000, 3, 'active'],
            ['Rina Marlina',       'female', 'Admin',  'Staff',       5200000, 2, 'active'],
            ['Hendra Wijaya',      'male',   'WATER',  'Staff',       5800000, 2, 'active'],
            ['Fitri Rahmawati',    'female', 'IMS',    'Staff',       5500000, 2, 'active'],
            ['Joko Susilo',        'male',   'WATER',  'Staff',       4700000, 2, 'terminated'],
        ];

        $bosses = [
            'CEO' => null,
            'Manager' => 'CEO',
            'Supervisor' => 'Manager',
            'Staff' => 'Supervisor',
        ];

        $banks = [['BCA', '3820'], ['Mandiri', '1300'], ['BCA', '4560'], ['Mandiri', '1140']];

        $created = [];
        foreach ($rows as $i => $row) {
            [$name, $gender, $divisionName, $titleName, $base, $joinYears, $status] = $row;

            $slug = strtolower(str_replace(' ', '.', $name));
            $prefix = match ($gender) {
                'male' => '+62812',
                'female' => '+62813',
            };
            $seed = sprintf('%04d', $i + 1);

            $managerName = $bosses[$titleName] ? collect($created)->firstWhere('title', $bosses[$titleName])['name'] ?? null : null;

            $employee = Employee::create([
                'employee_no' => 'EMP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => $name,
                'gender' => $gender,
                'birth_date' => now()->subYears(28 + $i)->subDays($i * 3),
                'birth_place' => ['Jakarta', 'Bandung', 'Surabaya', 'Semarang'][$i % 4],
                'address' => 'Jl. Melati No. '.($i + 1).', Jakarta Selatan',
                'phone' => $prefix.sprintf('%08d', 34000000 + $i * 7351),
                'email' => $slug.'@erp.local',
                'nik' => '320'.str_pad((string) (4020010000 + $i * 173), 12, '0', STR_PAD_LEFT),
                'npwp_no' => str_pad((string) (9000000000 + $i * 137), 15, '0', STR_PAD_LEFT),
                'ptkp_status' => $i % 3 === 0 ? 'K1' : 'TK0',
                'bpjs_kesehatan_no' => str_pad((string) (1001000000000 + $i * 97), 13, '0', STR_PAD_LEFT),
                'bpjs_tk_no' => str_pad((string) (2002000000000 + $i * 331), 13, '0', STR_PAD_LEFT),
                'division_id' => $divisionIds[$divisionName],
                'job_title_id' => $jobTitleIds[$titleName],
                'manager_id' => $managerName ? ($created[$managerName]['id'] ?? null) : null,
                'join_date' => now()->subYears($joinYears)->subDays($i),
                'resign_date' => $status === 'terminated' ? now()->subDays(10 + $i) : null,
                'status' => $status,
                'base_salary' => $base,
                'bank_name' => $banks[$i % 4][0],
                'bank_account_no' => $banks[$i % 4][1].str_pad((string) ($i + 1), 5, '3', STR_PAD_LEFT),
                'bank_account_name' => $name,
            ]);

            $created[$name] = ['id' => $employee->id, 'title' => $titleName];
        }

        return $created;
    }

    private function seedFamilies(array $employees): void
    {
        $spouseData = [
            'Budi Setiawan' => ['Ani Retno', 'spouse'],
            'Ratih Prasetyo' => ['Dimas Prasetyo', 'child'],
            'Agus Hidayat' => ['Maya Lestari', 'spouse'],
            'Siti Aminah' => ['Dani Firmansyah', 'child'],
            'Joko Susilo' => ['Dewi Astuti', 'spouse'],
        ];

        foreach ($spouseData as $employeeName => [$familyName, $relation]) {
            if (! isset($employees[$employeeName])) continue;

            EmployeeFamily::firstOrCreate([
                'employee_id' => $employees[$employeeName]['id'],
                'name' => $familyName,
            ], [
                'relation' => $relation,
                'birth_date' => now()->subYears($relation === 'spouse' ? 27 : 4),
                'occupation' => $relation === 'spouse' ? 'Ibu Rumah Tangga' : null,
            ]);
        }
    }

    private function seedAttendances(array $employees): void
    {
        $pagiShift = Shift::where('name', 'Pagi')->first();
        $hq = Office::where('name', 'HQ Jakarta')->first();

        $names = array_keys($employees);
        $activeNames = array_filter($names, fn ($n) => in_array($employees[$n]['title'], ['Staff', 'Supervisor', 'Manager']) && $n !== 'Joko Susilo');

        $date = now()->copy()->subDays(45);

        while ($date->lessThanOrEqualTo(now())) {
            if (in_array($date->dayOfWeekIso, [6, 7], true)) {
                $date->addDay();
                continue;
            }

            foreach ($activeNames as $name) {
                if (! isset($employees[$name])) continue;
                $employeeId = $employees[$name]['id'];

                $status = Attendance::STATUS_PRESENT;
                $clockIn = '08:00';
                $lateMinutes = 0;

                $hash = crc32($name.$date->format('Ymd'));
                if ($hash % 10 < 1) {
                    $status = Attendance::STATUS_LATE;
                    $clockIn = '08:'.sprintf('%02d', 16 + ($hash % 19));
                    $lateMinutes = 1 + ($hash % 30);
                } elseif ($hash % 40 === 3) {
                    $status = Attendance::STATUS_SICK;
                    $clockIn = null;
                } elseif ($hash % 50 === 4) {
                    $status = Attendance::STATUS_LEAVE;
                    continue;
                }

                $method = ($hash % 7 === 1) ? Attendance::METHOD_GPS : Attendance::METHOD_FINGERPRINT;

                Attendance::firstOrCreate([
                    'employee_id' => $employees[$name]['id'],
                    'work_date' => $date->toDateString(),
                ], [
                    'shift_id' => $pagiShift->id,
                    'clock_in' => $clockIn,
                    'clock_out' => '17:0'.($hash % 3),
                    'late_minutes' => $lateMinutes,
                    'status' => $status,
                    'check_in_method' => $method,
                    'source' => Attendance::SOURCE_API,
                    'office_id' => ($hash % 3 === 0) ? $hq->id : null,
                    'check_in_lat' => $method === Attendance::METHOD_GPS ? $hq->latitude + 0.002 : null,
                    'check_in_lng' => $method === Attendance::METHOD_GPS ? $hq->longitude + 0.0008 : null,
                    'face_matched' => null,
                    'created_by' => 1,
                ]);
            }

            $date->addDay();
        }

        // 2 pending GPS utk demo approve (force overwrite absensi hari tsb)
        foreach (['Andi Prasetyo', 'Fitri Rahmawati'] as $i => $name) {
            if (! isset($employees[$name])) continue;

            Attendance::updateOrCreate([
                'employee_id' => $employees[$name]['id'],
                'work_date' => now()->subDays(2 - $i)->toDateString(),
            ], [
                'shift_id' => $pagiShift->id,
                'clock_in' => '07:55',
                'clock_out' => '17:05',
                'late_minutes' => 0,
                'status' => Attendance::STATUS_PENDING,
                'check_in_method' => Attendance::METHOD_GPS,
                'source' => Attendance::SOURCE_API,
                'office_id' => $hq->id,
                'check_in_lat' => $hq->latitude + 0.0003,
                'check_in_lng' => $hq->longitude + 0.0002,
                'created_by' => 1,
            ]);

            // koreksi pending utk demo panel
            \App\Models\AttendanceCorrection::firstOrCreate(
                ['employee_id' => $employees[$name]['id'], 'work_date' => now()->subDays(4 - $i)->toDateString()],
                [
                    'proposed_clock_in' => '08:30',
                    'proposed_clock_out' => '17:30',
                    'reason' => 'Lupa absen masuk (dummy)',
                    'status' => AttendanceCorrection::STATUS_PENDING,
                ]
            );
        }
    }

    private function seedLeaveRequests(array $employees): void
    {
        $annual = LeaveType::where('code', 'ANN')->first();
        $izin = LeaveType::where('name', 'Izin')->first();
        $sakit = LeaveType::where('name', 'Sakit')->first();

        $submittedBy = User::first()->id;

        // approved (balikan potongan + attendance integration)
        $approvedSpecs = [
            // [employee, type, start offset_hari, days]
            ['Siti Aminah', $annual, 20, 3],
            ['Hendra Wijaya', $izin, 12, 2],
        ];

        $requestNo = 1;
        foreach ($approvedSpecs as [$name, $type, $offset, $days]) {
            $employeeId = $employees[$name]['id'];
            $start = now()->subDays($offset);
            $end = $start->copy()->addDays($days - 1);

            $days = $this->workingDays($start, $end);

            $leave = LeaveRequest::create([
                'request_no' => 'LR-'.now()->format('Y-m').'-'.str_pad((string) ($requestNo++), 4, '0', STR_PAD_LEFT),
                'employee_id' => $employeeId,
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'days' => $days,
                'reason' => 'Demo keperluan keluarga',
                'status' => LeaveRequest::STATUS_APPROVED,
                'approved_by' => 1,
                'approved_at' => now()->subDays($offset - 2),
                'submitted_by' => 1,
                'decision_note' => 'Dummy data — approved',
            ]);

            $this->applyApprovalToAttendance($leave);

            $this->createTimeline($leave, LeaveRequestLog::ACTION_SUBMIT, 'Pengajuan diajukan (dummy)');
            $this->createTimeline($leave, LeaveRequestLog::ACTION_APPROVE, 'Dummy data — approved');

            // potong saldo tahunan utk jenis cuti berkuota (sama spt alur approve runtime)
            if ($type->quota_days) {
                LeaveBalance::firstOrCreate(
                    ['employee_id' => $employeeId, 'year' => $leave->start_date->year, 'leave_type_id' => $type->id],
                    ['entitlement' => $type->quota_days]
                )->increment('used', $days);
            }
        }

        $this->createLeaveBalances($employees);

        // submitted & others
        $otherSpecs = [
            ['Rina Marlina', $annual, 5, 3, LeaveRequest::STATUS_SUBMITTED, null],
            ['Fitri Rahmawati', $izin, -3, 1, LeaveRequest::STATUS_SUBMITTED, null],
            ['Siti Aminah', $sakit, -8, 2, LeaveRequest::STATUS_REJECTED, 'lampiran surat tidak valid — dummy'],
            ['Andi Prasetyo', $annual, -10, 2, LeaveRequest::STATUS_REVISE, 'Periode bentrok — dummy'],
        ];

        foreach ($otherSpecs as [$name, $type, $offset, $days, $status, $note]) {
            $employeeId = $employees[$name]['id'];
            $start = now()->addDays($offset);
            $end = $start->copy()->addDays($days - 1);

            $leave = LeaveRequest::create([
                'request_no' => 'LR-'.now()->format('Y-m').'-'.str_pad((string) ($requestNo++), 4, '0', STR_PAD_LEFT),
                'employee_id' => $employeeId,
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'days' => max(1, $this->workingDays($start, $end)),
                'reason' => 'Alasan dummy - keperluan keluarga',
                'status' => $status,
                'submitted_by' => 1,
                'decision_note' => $note,
            ]);

            $this->createTimeline($leave, LeaveRequestLog::ACTION_SUBMIT, 'Pengajuan diajukan (dummy)');

            if ($status === LeaveRequest::STATUS_REJECTED) {
                $this->createTimeline($leave, LeaveRequestLog::ACTION_REJECT, $note);
            } elseif ($status === LeaveRequest::STATUS_REVISE) {
                $this->createTimeline($leave, LeaveRequestLog::ACTION_REVISE, $note);
            }
        }

    }

    private function createTimeline(LeaveRequest $leave, string $action, ?string $note = null): void
    {
        \App\Models\LeaveRequestLog::create([
            'leave_request_id' => $leave->id,
            'action' => $action,
            'note' => $note,
            'actor_id' => 1,
        ]);
    }

    private function createLeaveBalances(array $employees): void
    {
        $annual = LeaveType::where('name', 'Cuti Tahunan')->first();

        foreach ($employees as $name => $info) {
            if ($info['title'] === 'CEO') continue;

            LeaveBalance::firstOrCreate([
                'employee_id' => $info['id'],
                'year' => now()->year,
                'leave_type_id' => $annual->id,
            ], [
                'entitlement' => 12,
                'used' => 0,
                'carried_over' => 0,
            ]);
        }
    }

    private function seedOvertimeRequests(array $employees): void
    {
        $divisor = (int) config('er.overtime.hour_divisor', 173);
        $defaultMultiplier = (float) config('er.overtime.default_multiplier', 1.5);

        $specs = [
            // [nama, tanggal offset, shift_end, end, hours, multiplier, status]
            ['Andi Prasetyo', 3, 17, 20, 1.5, 'approved'],
            ['Hendra Wijaya', 1, 17, 21, 1.5, 'approved'],
            ['Fitri Rahmawati', 0, 17, 19, 1.5, 'submitted'],
            ['Rina Marlina', -2, 17, 18, 1.5, 'submitted'],
            ['Siti Aminah', -4, 17, 22, 1.5, 'submitted'],
        ];

        $counter = 1;
        foreach ($specs as $spec) {
            [$name, $offset, $shiftEnd, $endTime, $multiplier, $status] = $spec;
            $counter++;

            $employeeId = $employees[$name]['id'];
            $employee = Employee::find($employeeId);
            $workDate = now()->addDays($offset);
            $hours = ($endTime - $shiftEnd) / 1.0;
            $estimated = round(($employee->base_salary / $divisor) * $hours * $multiplier, 2);

            OvertimeRequest::create([
                'request_no' => 'OT-'.now()->format('Y-m').'-'.str_pad((string) $counter++, 4, '0', STR_PAD_LEFT),
                'employee_id' => $employeeId,
                'work_date' => now()->addDays($offset),
                'shift_end_time' => sprintf('%02d:00:00', $shiftEnd),
                'start_time' => sprintf('%02d:00:00', $shiftEnd),
                'end_time' => sprintf('%02d:00:00', $endTime),
                'hours' => $hours,
                'multiplier' => $multiplier,
                'estimated_amount' => $estimated,
                'reason' => 'Bersihkan backlog pengiriman',
                'status' => $status,
                'submitted_by' => 1,
                'approved_at' => $status === 'approved' ? now()->subHours(2) : null,
            ]);
        }
    }

    private function seedLoans(array $employees): void
    {
        $activeSpecs = [
            // [nama, amount, tenor, disburse, paid_count]
            ['Budi Setiawan', 10000000, 6, now()->subMonths(2), 2],
            ['Rina Marlina', 8000000, 4, now()->subDays(9), 1],
        ];

        $pendingSpecs = [
            ['Andi Prasetyo', 5000000],
            ['Fitri Rahmawati', 3000000],
        ];

        $counter = 1;
        foreach ($activeSpecs as [$name, $amount, $tenor, $disburse, $paidCount]) {
            $employeeId = $employees[$name]['id'];
            $loan = Loan::create([
                'loan_no' => 'LN-'.now()->year.'-'.str_pad((string) $counter++, 4, '0', STR_PAD_LEFT),
                'employee_id' => $employeeId,
                'amount' => $amount,
                'tenor_months' => $tenor,
                'installment_amount' => round($amount / $tenor, 2),
                'fee_amount' => 0,
                'purpose' => 'Danang keperluan keluarga (dummy)',
                'disburse_date' => $disburse->toDateString(),
                'status' => Loan::STATUS_ACTIVE,
                'approved_by' => 1,
                'approved_at' => $disburse->copy()->subDays(2),
                'decision_note' => 'Dummy data — active',
            ]);

            foreach (range(1, $tenor) as $no) {
                $dueDate = $disburse->copy()->addMonthNoOverflow()->startOfMonth()->addMonths($no - 1)->setDay(1);

                LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_no' => $no,
                    'due_date' => $dueDate,
                    'amount' => $loan->installment_amount,
                    'status' => $no <= $paidCount
                        ? LoanInstallment::STATUS_PAID
                        : LoanInstallment::STATUS_UNPAID,
                    'paid_at' => $no <= $paidCount ? $dueDate->copy()->addDays(2) : null,
                ]);
            }
        }

        foreach ($pendingSpecs as [$name, $amount]) {
            Loan::create([
                'loan_no' => 'LN-'.now()->year.'-'.str_pad((string) $counter++, 4, '0', STR_PAD_LEFT),
                'employee_id' => $employees[$name]['id'],
                'amount' => $amount,
                'tenor_months' => 6,
                'installment_amount' => round($amount / 6, 2),
                'fee_amount' => 0,
                'purpose' => 'Renovasi rumah (dummy)',
                'status' => Loan::STATUS_PENDING,
            ]);
        }
    }

    private function seedPayrollPeriod(array $employees): void
    {
        [$start, $end] = PayrollService::nextPeriodRange();

        PayrollPeriod::firstOrCreate([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ], [
            'name' => $start->format('Y-m-d').' s/d '.$end->format('Y-m-d'),
            'is_thr' => false,
            'status' => 'open',
            'created_by' => 1,
        ]);
    }

    private function workingDays(Carbon $start, Carbon $end): int
    {
        $count = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (! in_array($date->dayOfWeekIso, [6, 7], true)) {
                $count++;
            }
        }

        return $count;
    }

    private function applyApprovalToAttendance(LeaveRequest $leaveRequest): void
    {
        $status = LeaveType::find($leaveRequest->leave_type_id)->name === 'Sakit'
            ? Attendance::STATUS_SICK
            : Attendance::STATUS_LEAVE;

        $period = new \DatePeriod(
            new \DateTime($leaveRequest->start_date->toDateString()),
            new \DateInterval('P1D'),
            (new \DateTime($leaveRequest->end_date->toDateString()))->modify('+1 day')
        );

        foreach ($period as $date) {
            if (in_array($date->format('N'), [6, 7], true)) continue;

            Attendance::where('employee_id', $leaveRequest->employee_id)
                ->where('work_date', $date->format('Y-m-d'))
                ->whereNotIn('check_in_method', [Attendance::METHOD_MANUAL])
                ->delete();

            Attendance::firstOrCreate([
                'employee_id' => $leaveRequest->employee_id,
                'work_date' => $date->format('Y-m-d'),
            ], [
                'status' => $status,
                'check_in_method' => Attendance::METHOD_LEAVE_INTEGRATION,
                'source' => Attendance::SOURCE_AUTO,
                'note' => 'Auto dari pengajuan '.$leaveRequest->request_no,
                'created_by' => 1,
            ]);
        }
    }
}

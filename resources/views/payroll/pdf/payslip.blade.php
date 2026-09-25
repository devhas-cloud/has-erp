<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji — {{ $payslip->employee->employee_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        .head { text-align: center; margin-bottom: 8px; }
        .head h2 { margin: 0; font-size: 15px; }
        .head .sub { font-size: 10px; color: #666; }
        .info td { padding: 2px 6px; font-size: 11px; }
        .info td:first-child { color: #666; width: 140px; }
        table.slip { width: 60%; border-collapse: collapse; margin-top: 8px; }
        table.slip th, table.slip td { border: 1px solid #999; padding: 3px 6px; }
        table.slip th { background: #eee; text-align: left; font-size: 11px; }
        .amount { text-align: right; width: 22%; }
        .totals td { font-weight: bold; background: #f6f6f6; }
        .kpis td { padding: 2px 6px; font-size: 11px; }
    </style>
</head>
<body>
    @php
        $incomeItems = [];
        $deductionItems = [];
        $incomeSum = 0;
        $deductionSum = 0;
        $bpjsCompanyItems = [];

        foreach ($payslip->components->sortBy('sort_order') as $c) {
            // BPJS perusahaan adalah benefit info (bukan potongan) — pisahkan
            if ($c->source_type === 'salary_component'
                && in_array($c->label, [
                    'BPJS Kesehatan (Perusahaan)',
                    'BPJS JHT (Perusahaan)',
                    'BPJS JP (Perusahaan)',
                ], true)) {
                $bpjsCompanyItems[] = $c;
                continue;
            }

            if ($c->type === 'deduction') {
                $deductionItems[] = $c;
                $deductionSum += (float) $c->amount;
            } else {
                $incomeItems[] = $c;
                $incomeSum += (float) $c->amount;
            }
        }

        $totalIncome = (float) $payslip->base_salary_prorata + $incomeSum;
        $rowCount = max(count($incomeItems), count($deductionItems), 1);
    @endphp

    <div class="head">
        <h2>SLIP GAJI KARYAWAN</h2>
        <div class="sub">Periode {{ $payslip->payrollPeriod->start_date->format('d-m-Y') }} s/d {{ $payslip->payrollPeriod->end_date->format('d-m-Y') }} (cutoff 25–24)</div>
    </div>

    <table class="info">
        <tr><td>No. Pegawai</td><td>: {{ $payslip->employee->employee_no }}</td></tr>
        <tr><td>Nama</td><td>: {{ $payslip->employee->name }}</td></tr>
        <tr><td>Divisi / Jabatan</td><td>: {{ $payslip->employee->division?->division_name ?? '—' }} / {{ $payslip->employee->jobTitle?->title_name ?? '—' }}</td></tr>
        <tr><td>Bank / Rekening</td><td>: {{ ($payslip->bank_name ?? $payslip->employee->bank_name) ?? '—' }} — {{ $payslip->bank_account_no ?? $payslip->employee->bank_account_no }}</td></tr>
    </table>

    <table class="slip">
        <thead>
            <tr>
                <th style="width:7%">Dt</th>
                <th style="width:30%">Deskripsi Pendapatan</th>
                <th class="amount">Rp</th>
                <th style="width:30%">Deskripsi Potongan</th>
                <th class="amount">Rp</th>
            </tr>
        </thead>
        <tbody>
            {{-- Uang makan/transport per hari hadir: line pendapatan dasar dari payslip base prorata --}}
            <tr>
                <td>1</td>
                <td>Gaji Pokok (prorata / {{ $payslip->working_days }} hari kerja)</td>
                <td class="amount">{{ number_format((float) $payslip->base_salary_prorata, 2, '.', '.') }}</td>
                <td></td>
                <td class="amount"></td>
            </tr>
            @for($i = 0; $i < $rowCount; $i++)
            <tr>
                <td>{{ $i + 2 }}</td>
                <td>{{ $incomeItems[$i]->label ?? '' }}</td>
                <td class="amount">{{ isset($incomeItems[$i]) ? number_format((float) $incomeItems[$i]->amount, 2, '.', '.') : '' }}</td>
                <td>{{ $deductionItems[$i]->label ?? '' }}</td>
                <td class="amount">{{ isset($deductionItems[$i]) ? number_format((float) $deductionItems[$i]->amount, 2, '.', '.') : '' }}</td>
            </tr>
            @endfor
            <tr class="totals">
                <td colspan="2" style="text-align:right;">Total Pendapatan</td>
                <td class="amount">{{ number_format($totalIncome, 2, '.', '.') }}</td>
                <td style="text-align:right;">Total Potongan</td>
                <td class="amount">{{ number_format($deductionSum, 2, '.', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="kpis info" style="margin-top:10px;width:100%">
        <tr>
            <td style="width:30%">BPJS Company (blackbox benefit)</td><td>: Rp {{ number_format((float) $payslip->total_bpjs_company, 2, '.', '.') }}</td>
            <td style="width:12%">PPh 21</td><td>: Rp {{ number_format((float) $payslip->total_pph21, 2, '.', '.') }}</td>
        </tr>
        <tr>
            <td>THR (terpisah)</td><td>: Rp {{ number_format((float) $payslip->total_thr, 2, '.', '.') }}</td>
            <td><strong>THP</strong></td><td>: <strong>Rp {{ number_format((float) $payslip->total_net, 2, '.', '.') }}</strong></td>
        </tr>
    </table>
    <p style="margin-top:10px;font-size:9px;color:#666;" >
        Slip digenerasi otomatis oleh sistem — {{ now()->format('d-m-Y G:i') }}.
    </p>
</body>
</html>

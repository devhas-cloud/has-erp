<?php

// Konfigurasi Employee Relations (ER) — bisa berubah tanpa ubah kode
return [
    'overtime' => [
        // multiplier default pengajuan lembur (bisa dikoreksi admin saat approve)
        'default_multiplier' => env('ER_OVERTIME_DEFAULT_MULTIPLIER', 1.5),

        // pembagi upah per jam dalam sebulan (konvensi regulasi Indonesia: 173)
        'hour_divisor' => env('ER_OVERTIME_HOUR_DIVISOR', 173),

        // batas max jam lembur per pengajuan (opsional guarding)
        'max_hours' => env('ER_OVERTIME_MAX_HOURS', 8),

        // default jam pulang shift saat absensi belum ada (fallback admin manual)
        'default_shift_end' => env('ER_OVERTIME_DEFAULT_SHIFT_END', '17:00'),
    ],

    'payroll' => [
        // cutoff 25 s/d 24 bulan berikut
        'cutoff_start_day' => 25,
        'cutoff_end_day' => 24,

        // bulan THR (default Maret) — periode yg mengandung bln ini di-atas is_thr
        'thr_month' => env('ER_PAYROLL_THR_MONTH', 3),

        // Rate BPJS (persen upah, dengan salary cap)
        'bpjs' => [
            'kesehatan_company' => 4.0,
            'kesehatan_employee' => 1.0,
            'jht_company' => 3.67,
            'jht_employee' => 2.0,
            'jp_company' => 2.0,
            'jp_employee' => 1.0,
            'salary_cap' => 12000000,
        ],

        // PPH21 monthly flat progressive rate (skeleton M6; TER nanti disempurnakan)
        'pph21_monthly_deductible' => 4500000,

        'pph21_rates' => [
            ['limit_up_to' => null, 'rate' => 5.0],
        ],

        // hari kerja standar dalam 1 bulan (derikan average 22) — untuk prorata
        'standard_working_days' => 22,
    ],
];

<?php

namespace App\Http\Controllers;

use App\Models\SupplierBill;
use App\Services\PayableDueNotifier;
use App\Support\ModuleAccess;
use App\Support\PayableReport;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Laporan Hutang Supplier (grup Finance, Tahap 5): ringkasan per supplier,
 * umur hutang, jadwal pembayaran, dan biaya pembelian per project — semua
 * bisa diekspor ke Excel. Angka dihitung dari dokumen oleh App\Support\PayableReport.
 * Pengingat WhatsApp jatuh tempo diatur di modul Notifikasi Finance (Master Data);
 * laporan hanya menampilkan statusnya.
 */
class PayableReportController extends Controller
{
    public const TABS = [
        'supplier' => 'Hutang per Supplier',
        'aging' => 'Umur Hutang',
        'schedule' => 'Jadwal Pembayaran',
        'project' => 'Biaya per Project',
        'fx' => 'Selisih Kurs',
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'supplier';
        $report = new PayableReport;

        return view('payable-report.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'base' => $report->baseCurrency(),
            'report' => $report,
            'rows' => $this->rows($report, $tab, $request),
            'period' => $this->period($request),
            'totals' => $this->headline($report),
            'reminder' => PayableDueNotifier::settings(),
            'canManageNotification' => ModuleAccess::for()->module('MOD_FINANCE_NOTIFICATION')->canRead(),
        ]);
    }

    public function export(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'supplier';
        $report = new PayableReport;
        [$headers, $data] = $this->sheet($report, $tab, $this->rows($report, $tab, $request));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(self::TABS[$tab], 0, 31));
        $sheet->fromArray([self::TABS[$tab].' — per '.now()->format('d/m/Y').' (nilai '.$report->baseCurrency().', kurs master)'], null, 'A1');
        $sheet->fromArray($headers, null, 'A3');
        $sheet->fromArray($data, null, 'A4');
        $sheet->getStyle('A3:'.$sheet->getHighestColumn().'3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/laporan-hutang-'.$tab.'-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'laporan-hutang-'.$tab.'-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(true);
    }

    /** Rentang tanggal tab Selisih Kurs (default: awal tahun s/d hari ini). */
    private function period(Request $request): array
    {
        return [
            'from' => $request->input('from') ?: now()->startOfYear()->toDateString(),
            'to' => $request->input('to') ?: now()->toDateString(),
        ];
    }

    private function rows(PayableReport $report, string $tab, Request $request)
    {
        $period = $this->period($request);

        return match ($tab) {
            'fx' => $report->fx($period['from'], $period['to']),
            'aging' => $report->aging(),
            'schedule' => $report->schedule(),
            'project' => $report->projectCosts(),
            default => $report->supplierSummary(),
        };
    }

    /** Angka utama di atas laporan. */
    private function headline(PayableReport $report): array
    {
        $open = $report->openBills();

        return [
            'outstanding' => round($open->sum(fn (SupplierBill $b) => $b->outstandingBase()), 2),
            'overdue' => round($open->filter(fn ($b) => $report->daysOverdue($b) > 0)->sum(fn (SupplierBill $b) => $b->outstandingBase()), 2),
            'due_week' => round($open->filter(fn ($b) => $report->daysOverdue($b) === 0 && $b->due_date->lte(now()->endOfWeek()))->sum(fn (SupplierBill $b) => $b->outstandingBase()), 2),
            'open_count' => $open->count(),
        ];
    }

    /** @return array{0: array, 1: array} */
    private function sheet(PayableReport $report, string $tab, $rows): array
    {
        return match ($tab) {
            'aging' => [
                array_merge(['Supplier'], array_values(PayableReport::AGING_BUCKETS), ['Total']),
                $rows->map(fn ($r) => array_merge([$r['supplier']?->name], array_values($r['buckets']), [$r['total']]))->all(),
            ],
            'schedule' => [
                ['Periode', 'No. Tagihan', 'Supplier', 'No. Invoice', 'Jatuh Tempo', 'Terlambat (hari)', 'Mata Uang', 'Sisa', 'Kurs Tagihan', 'Sisa ('.$report->baseCurrency().', nilai buku)'],
                $rows->flatMap(fn ($group) => $group['bills']->map(fn (SupplierBill $b) => [
                    $group['label'], $b->bill_number, $b->supplier?->name, $b->supplier_invoice_number, $b->due_date?->format('d/m/Y'),
                    $report->daysOverdue($b), $b->currency, $b->outstanding(), $b->exchange_rate, $b->outstandingBase(),
                ]))->all(),
            ],
            'fx' => [
                ['Jenis', 'Tanggal', 'No. Dokumen', 'Supplier', 'Keterangan', 'Mata Uang', 'Nominal', 'Kurs Buku', 'Kurs Aktual', 'Nilai Buku', 'Nilai Aktual', 'Selisih (+ rugi / − laba)'],
                $rows['realized']->map(fn ($r) => [
                    $r['kind'] === 'refund' ? 'Refund uang muka' : 'Pembayaran', $r['date']?->format('d/m/Y'), $r['number'], $r['supplier'], $r['reference'],
                    $r['currency'], $r['amount'], $r['book_rate'], $r['actual_rate'], $r['book'], $r['actual'], $r['difference'],
                ])->all(),
            ],
            'project' => [
                ['No. Project', 'Project', 'Komitmen Pembelian', 'Diterima', 'Ditagih', 'Terbayar', 'Sisa Tagihan', 'Alokasi Stok', 'Total Biaya', 'Estimasi Biaya (PL)', 'Selisih'],
                $rows->map(fn ($r) => [
                    $r['project']->project_number, $r['project']->project_name, $r['commitment'], $r['received'], $r['billed'], $r['paid'],
                    $r['outstanding'], $r['stock_allocated'], $r['actual'], $r['estimate'], $r['variance'],
                ])->all(),
            ],
            default => [
                ['Supplier', 'Nilai PO Aktif', 'Kas Keluar Bersih', 'Uang Muka Belum Terpakai', 'Sisa Hutang (nilai buku)', 'Selisih Kurs Belum Terealisasi', 'Terlambat', 'Tagihan Terlambat', 'Tagihan Terbuka', 'Jatuh Tempo Terdekat'],
                $rows->map(fn ($r) => [
                    $r['supplier']->name, $r['po_value'], $r['paid'], $r['advance_balance'], $r['outstanding'], $r['unrealized_fx'], $r['overdue'],
                    $r['overdue_count'], $r['open_count'], $r['next_due']?->format('d/m/Y'),
                ])->all(),
            ],
        };
    }
}

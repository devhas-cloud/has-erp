<?php

namespace App\Http\Controllers;

use App\Services\ReceivableDueNotifier;
use App\Support\ModuleAccess;
use App\Support\ReceivableReport;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Laporan Piutang Customer (grup Finance, piutang Tahap C): per customer,
 * umur piutang, proyeksi penerimaan, dan piutang per project — semua bisa
 * diekspor ke Excel. Angka dihitung oleh App\Support\ReceivableReport.
 * Pengingat WhatsApp piutang diatur di modul Notifikasi Finance (Master Data);
 * laporan hanya menampilkan statusnya.
 */
class ReceivableReportController extends Controller
{
    public const TABS = [
        'customer' => 'Piutang per Customer',
        'aging' => 'Umur Piutang',
        'forecast' => 'Proyeksi Penerimaan',
        'project' => 'Piutang per Project',
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'customer';
        $report = new ReceivableReport;

        return view('receivable-report.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'report' => $report,
            'rows' => $this->rows($report, $tab),
            'totals' => $report->headline(),
            'reminder' => ReceivableDueNotifier::settings(),
            'canManageNotification' => ModuleAccess::for()->module('MOD_FINANCE_NOTIFICATION')->canRead(),
        ]);
    }

    public function export(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'customer';
        $report = new ReceivableReport;
        [$headers, $data, $note] = $this->sheet($report, $tab, $this->rows($report, $tab));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(self::TABS[$tab], 0, 31));
        $sheet->fromArray([self::TABS[$tab].' — per '.now()->format('d/m/Y').' ('.$note.')'], null, 'A1');
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
        $path = $dir.'/laporan-piutang-'.$tab.'-'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'laporan-piutang-'.$tab.'-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend(true);
    }

    private function rows(ReceivableReport $report, string $tab)
    {
        return match ($tab) {
            'aging' => $report->aging(),
            'forecast' => $report->forecast(),
            'project' => $report->projectSummary(),
            default => $report->customerSummary(),
        };
    }

    /** @return array{0: array, 1: array, 2: string} */
    private function sheet(ReceivableReport $report, string $tab, $rows): array
    {
        return match ($tab) {
            'aging' => [
                array_merge(['Customer'], array_values(ReceivableReport::AGING_BUCKETS), ['Total']),
                $rows->map(fn ($r) => array_merge([$r['company']?->account_name], array_values($r['buckets']), [$r['total']]))->all(),
                'nilai invoice termasuk PPN, umur dari jatuh tempo invoice',
            ],
            'forecast' => [
                ['Periode', 'Jenis', 'Tanggal', 'Customer', 'Project', 'Termin', 'No. Invoice', 'Posisi', 'Nominal'],
                $rows->flatMap(fn ($group) => $group['rows']->map(fn ($r) => [
                    $group['label'],
                    $r['kind'] === 'invoice' ? 'Pasti (invoice)' : 'Estimasi',
                    $r['date']?->format('d/m/Y'),
                    $r['project']?->company?->account_name,
                    $r['project'] ? $r['project']->project_number.' — '.$r['project']->project_name : null,
                    $r['term']?->name ?? ($r['invoice']?->noted ?: 'Di luar timeline'),
                    $r['invoice']?->invoice_number,
                    ReceivableReport::POSITIONS[$r['position']],
                    $r['amount'],
                ]))->all(),
                'invoice termasuk PPN; termin belum diinvoice sebelum PPN',
            ],
            'project' => [
                array_merge(['No. Project', 'Project', 'Customer', 'Nilai Kontrak', 'Terjadwal (%)'], array_values(ReceivableReport::POSITIONS), ['Di Luar Timeline', 'PO Due Date', 'Sisa Hari', 'Termin Terlambat Ditagih']),
                $rows->map(fn ($r) => array_merge(
                    [$r['project']->project_number, $r['project']->project_name, $r['project']->company?->account_name, $r['contract'], $r['scheduled_percent']],
                    array_values($r['positions']),
                    [$r['outside_timeline'], $r['po_due_date']?->format('d/m/Y'), $r['due_days'], $r['late_billing']]
                ))->all(),
                'nilai sebelum PPN',
            ],
            default => [
                ['Customer', 'Sudah Ditagih', 'Sudah Diterima', 'Sisa Piutang', 'Terlambat', 'Invoice Terlambat', 'Invoice Terbuka', 'Jatuh Tempo Terdekat', 'Belum Ditagih (sebelum PPN)'],
                $rows->map(fn ($r) => [
                    $r['company']?->account_name, $r['billed'], $r['received'], $r['outstanding'], $r['overdue'],
                    $r['overdue_count'], $r['open_count'], $r['next_due']?->format('d/m/Y'), $r['unbilled'],
                ])->all(),
                'invoice termasuk PPN',
            ],
        };
    }
}

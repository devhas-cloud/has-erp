<?php

namespace App\Http\Controllers;

use App\Models\SupplierBill;
use App\Services\PayableDueNotifier;
use App\Support\ModuleAccess;
use App\Support\PayableReport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            // Tab supplier & project dimuat lewat ajax (DataTables server-side).
            'rows' => in_array($tab, ['aging', 'schedule', 'fx'], true) ? $this->rows($report, $tab, $request) : collect(),
            'period' => $this->period($request),
            'currencies' => $report->currencies(),
            'reminder' => PayableDueNotifier::settings(),
            'canManageNotification' => ModuleAccess::for()->module('MOD_FINANCE_NOTIFICATION')->canRead(),
        ]);
    }

    /** Data JSON untuk DataTables server-side (tab supplier & project). */
    public function data(Request $request)
    {
        $tab = $request->input('tab') === 'project' ? 'project' : 'supplier';
        $report = new PayableReport;
        $rows = $this->rows($report, $tab, $request);
        $total = $rows->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $page = $rows->slice($start, $length)->values();

        $view = $tab === 'project' ? 'payable-report.partials.project-row' : 'payable-report.partials.supplier-row';
        $rowClass = fn (array $r) => $tab === 'supplier' && ($r['overdue'] ?? 0) > 0 ? 'pr-row-overdue' : '';
        $data = $page->map(fn (array $r, $i) => [
            'DT_RowClass' => $rowClass($r),
            'html' => view($view, ['r' => $r, 'i' => $start + $i])->render(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'footer' => $this->footer($tab, $rows),
            'data' => $data,
        ]);
    }

    /** Baris total (tfoot); nominal tidak dijumlah lintas mata uang. */
    private function footer(string $tab, $rows): array
    {
        $single = $rows->pluck('currency')->unique()->count() === 1;
        $sum = fn (string $key) => $single ? \App\Support\Number::money($rows->sum($key)) : '—';
        $label = $single ? 'Total' : 'Total (multi-currency)';

        return $tab === 'project'
            ? ['', $label, '', $sum('total'), $sum('paid'), $sum('sisa')]
            : ['', $label, $sum('total'), $sum('paid'), $sum('sisa')];
    }

    public function export(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'supplier';
        $report = new PayableReport;
        [$headers, $data] = $this->sheet($report, $tab, $this->rows($report, $tab, $request));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(self::TABS[$tab], 0, 31));
        $note = in_array($tab, ['supplier', 'project'], true) ? 'dalam mata uang tagihan' : 'nilai '.$report->baseCurrency().', kurs master';
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
            'project' => $this->filterRows($report->projectBills(), 'project', $request),
            default => $this->filterRows($report->supplierByCurrency(), 'supplier', $request),
        };
    }

    /** Terapkan cari + filter mata uang (+ rentang tanggal untuk project). */
    private function filterRows(Collection $rows, string $tab, Request $request): Collection
    {
        $q = mb_strtolower(trim((string) ($request->input('q') ?: $request->input('search.value', ''))));
        if ($q !== '') {
            $rows = $rows->filter(function (array $r) use ($q, $tab) {
                $parts = $tab === 'project'
                    ? [$r['project']?->project_number, $r['project']?->project_name, $r['bill']?->supplier?->name, $r['bill']?->bill_number, $r['bill']?->supplier_invoice_number]
                    : [$r['supplier']?->name];

                return str_contains(mb_strtolower(implode(' ', array_filter($parts))), $q);
            });
        }

        $currency = strtoupper((string) $request->input('currency', ''));
        if ($currency !== '') {
            $rows = $rows->filter(fn (array $r) => $r['currency'] === $currency);
        }

        if ($tab === 'project') {
            $from = $request->date('from');
            $to = $request->date('to')?->endOfDay();
            $rows = $rows
                ->when($from, fn ($c) => $c->filter(fn (array $r) => $r['date'] && $r['date']->gte($from)))
                ->when($to, fn ($c) => $c->filter(fn (array $r) => $r['date'] && $r['date']->lte($to)));
        }

        return $rows->values();
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
                ['No. Project', 'Project', 'Tanggal', 'Mata Uang', 'Total Biaya', 'Bayar', 'Sisa Biaya'],
                $rows->map(fn ($r) => [
                    $r['project']?->project_number, $r['project']?->project_name, $r['date']?->format('d/m/Y'),
                    $r['currency'], $r['total'], $r['paid'], $r['sisa'],
                ])->all(),
            ],
            default => [
                ['Supplier', 'Mata Uang', 'Total Hutang', 'Bayar', 'Sisa Hutang'],
                $rows->map(fn ($r) => [
                    $r['supplier']?->name, $r['currency'], $r['total'], $r['paid'], $r['sisa'],
                ])->all(),
            ],
        };
    }
}

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
 * proyeksi penerimaan, dan piutang per project — semua bisa difilter dan
 * diekspor ke Excel. Angka dihitung oleh App\Support\ReceivableReport.
 * Pengingat WhatsApp piutang diatur di modul Notifikasi Finance (Master Data);
 * laporan hanya menampilkan statusnya.
 */
class ReceivableReportController extends Controller
{
    public const TABS = [
        'customer' => 'Piutang per Customer',
        'forecast' => 'Proyeksi Penerimaan',
        'project' => 'Piutang per Project',
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'customer';
        $report = new ReceivableReport;

        $tabCounts = [
            'customer' => $report->customerSummary()->count(),
            'forecast' => $report->forecast()->sum(fn (array $group) => $group['rows']->count()),
            'project' => $report->projectSummary()->count(),
        ];

        return view('receivable-report.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'tabCounts' => $tabCounts,
            'reminder' => ReceivableDueNotifier::settings(),
            'canManageNotification' => ModuleAccess::for()->module('MOD_FINANCE_NOTIFICATION')->canRead(),
        ]);
    }

    /** Data JSON untuk DataTables server-side (tab customer & project). */
    public function data(Request $request)
    {
        $tab = $request->input('tab') === 'project' ? 'project' : 'customer';
        $f = [
            'q' => trim((string) $request->input('search.value', '')),
            'overdue' => $request->boolean('overdue'),
            'kind' => '',
            'from' => null,
            'to' => null,
            'late_billing' => $request->boolean('late_billing'),
            'receivable' => $request->boolean('receivable'),
            'group_by' => 'period',
        ];

        $report = new ReceivableReport;
        $rows = $this->rows($report, $tab, $f);
        $total = $rows->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $page = $rows->slice($start, $length)->values();

        $view = $tab === 'project' ? 'receivable-report.partials.project-row' : 'receivable-report.partials.customer-row';
        $rowClass = fn (array $r) => $tab === 'project'
            ? ($r['late_billing'] > 0 ? 'rr-row-late' : '')
            : ($r['overdue'] > 0 ? 'rr-row-overdue' : '');
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

    /** HTML kelompok proyeksi penerimaan (tab forecast), difilter server-side. */
    public function forecast(Request $request)
    {
        $f = $this->filters($request);
        $report = new ReceivableReport;

        return view('receivable-report.partials.forecast', [
            'groups' => $this->rows($report, 'forecast', $f),
            'report' => $report,
        ]);
    }

    /** Baris total (tfoot) atas seluruh baris terfilter, bukan halaman aktif. */
    private function footer(string $tab, $rows): array
    {
        if ($tab === 'project') {
            return [
                '', 'Total',
                \App\Support\Number::money($rows->sum('contract')),
                '',
                \App\Support\Number::money($rows->sum(fn ($r) => $r['positions']['in_process'] + $r['positions']['receivable'] + $r['positions']['paid'])),
                \App\Support\Number::money($rows->sum(fn ($r) => $r['positions']['paid'])),
                \App\Support\Number::money($rows->sum(fn ($r) => $r['positions']['receivable'])),
                '',
            ];
        }

        return [
            '', 'Total',
            \App\Support\Number::money($rows->sum('billed')),
            \App\Support\Number::money($rows->sum('received')),
            \App\Support\Number::money($rows->sum('outstanding')),
        ];
    }

    public function export(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'customer';
        $report = new ReceivableReport;
        [$headers, $data, $note] = $this->sheet($report, $tab, $this->rows($report, $tab, $this->filters($request)));

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

    /** Parameter filter dari query string (semua opsional). */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'overdue' => $request->boolean('overdue'),
            'kind' => in_array($request->input('kind'), ['invoice', 'estimate'], true) ? $request->input('kind') : '',
            'from' => $request->date('from'),
            'to' => $request->date('to')?->endOfDay(),
            'late_billing' => $request->boolean('late_billing'),
            'receivable' => $request->boolean('receivable'),
            'group_by' => in_array($request->input('group_by'), ['period', 'customer'], true) ? $request->input('group_by') : 'period',
        ];
    }

    private function rows(ReceivableReport $report, string $tab, array $f)
    {
        $needle = $f['q'] !== '' ? mb_strtolower($f['q']) : null;
        $match = function (array $parts) use ($needle): bool {
            if ($needle === null) {
                return true;
            }

            return str_contains(mb_strtolower(implode(' ', array_filter($parts))), $needle);
        };

        return match ($tab) {
            'forecast' => $this->forecastRows($report, $f, $match),

            'project' => $report->projectSummary()->filter(function (array $r) use ($f, $match) {
                if ($f['late_billing'] && $r['late_billing'] <= 0) {
                    return false;
                }
                if ($f['receivable'] && $r['positions']['receivable'] <= 0) {
                    return false;
                }

                return $match([
                    $r['project']->project_number,
                    $r['project']->project_name,
                    $r['project']->company?->account_name,
                ]);
            })->values(),

            default => $report->customerSummary()->filter(function (array $r) use ($f, $match) {
                if ($f['overdue'] && $r['overdue'] <= 0) {
                    return false;
                }

                return $match([$r['company']->account_name]);
            })->values(),
        };
    }

    /** Predikat filter bersama untuk satu baris proyeksi. */
    private function itemMatches(array $r, array $f, $match): bool
    {
        if ($f['kind'] !== '' && $r['kind'] !== $f['kind']) {
            return false;
        }
        if (($f['from'] || $f['to']) && ! $r['date']) {
            return false;
        }
        if ($f['from'] && $r['date']->lt($f['from'])) {
            return false;
        }
        if ($f['to'] && $r['date']->gt($f['to'])) {
            return false;
        }

        return $match([
            $r['project']?->company?->account_name,
            $r['project']?->project_number,
            $r['project']?->project_name,
            $r['invoice']?->invoice_number,
        ]);
    }

    /** Proyeksi penerimaan: dikelompokkan per periode (minggu) atau per customer. */
    private function forecastRows(ReceivableReport $report, array $f, $match)
    {
        $items = $report->forecast()->flatMap(fn (array $group) => $group['rows'])
            ->filter(fn (array $r) => $this->itemMatches($r, $f, $match))
            ->values();

        if ($f['group_by'] === 'customer') {
            return $items->groupBy(fn (array $r) => $r['project']?->company_id ?: 0)
                ->map(function ($rows) {
                    $first = $rows->first();

                    return $this->groupForecast($rows, 'c_'.($first['project']?->company_id ?? 0), $first['project']?->company?->account_name ?? 'Tanpa customer');
                })
                ->sortByDesc(fn (array $g) => $g['certain'] + $g['estimate'])
                ->values();
        }

        return $report->forecast()
            ->map(function (array $group) use ($f, $match) {
                $rows = $group['rows']->filter(fn (array $r) => $this->itemMatches($r, $f, $match))->values();
                $group['rows'] = $rows;
                $group['certain'] = round($rows->where('kind', 'invoice')->sum('amount'), 2);
                $group['estimate'] = round($rows->where('kind', 'estimate')->sum('amount'), 2);

                return $group;
            })
            ->filter(fn (array $group) => $group['rows']->isNotEmpty())
            ->values();
    }

    private function groupForecast($rows, string $key, string $label): array
    {
        $rows = $rows->sortBy(fn (array $r) => $r['date']?->timestamp ?? PHP_INT_MAX)->values();

        return [
            'key' => $key,
            'label' => $label,
            'rows' => $rows,
            'certain' => round($rows->where('kind', 'invoice')->sum('amount'), 2),
            'estimate' => round($rows->where('kind', 'estimate')->sum('amount'), 2),
        ];
    }

    /** @return array{0: array, 1: array, 2: string} */
    private function sheet(ReceivableReport $report, string $tab, $rows): array
    {
        return match ($tab) {
            'forecast' => [
                ['Kelompok', 'Jenis', 'Tanggal', 'Customer', 'Project', 'Termin', 'No. Invoice', 'Posisi', 'Nominal'],
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

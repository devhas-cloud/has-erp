<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\PayableDueNotifier;
use App\Support\ModuleAccess;
use App\Support\Number;
use App\Support\PayableReport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Laporan Hutang Supplier (grup Finance): hutang per supplier, per project, dan
 * selisih kurs terealisasi — semua lewat DataTables server-side dengan filter yang
 * sama dan bisa diekspor ke Excel. Angka dihitung dari dokumen oleh App\Support\PayableReport.
 * Nominal selalu dalam mata uang tagihan; total dijumlah per mata uang (tidak lintas mata uang).
 * Pengingat WhatsApp jatuh tempo diatur di modul Notifikasi Finance (Master Data).
 */
class PayableReportController extends Controller
{
    public const TABS = [
        'supplier' => 'By Supplier',
        'project' => 'By Project',
        'fx' => 'Exchange Differences',
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'supplier';
        $report = new PayableReport;

        return view('payable-report.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'base' => $report->baseCurrency(),
            // Pilihan mata uang dari master Currency aktif (bukan hanya yang ada di tagihan).
            'currencies' => Currency::active()->orderByDesc('is_base')->orderBy('name')->pluck('name')->map(fn ($c) => strtoupper($c))->unique()->values(),
            // Supplier yang punya tagihan (untuk filter tab Project & Selisih Kurs).
            'suppliers' => Supplier::whereIn('id', SupplierBill::query()->distinct()->pluck('supplier_id'))->orderBy('name')->get(['id', 'name']),
            'period' => $this->period($request),
            'reminder' => PayableDueNotifier::settings(),
            'canManageNotification' => ModuleAccess::for()->module('MOD_FINANCE_NOTIFICATION')->canRead(),
        ]);
    }

    /** Data JSON untuk DataTables server-side (semua tab). */
    public function data(Request $request)
    {
        $tab = $this->tab($request);
        $report = new PayableReport;
        $rows = $this->rows($report, $tab, $request);
        $total = $rows->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $page = $rows->slice($start, max(1, $length))->values();

        $view = 'payable-report.partials.'.$tab.'-row';
        $data = $page->map(fn (array $r, $i) => [
            'DT_RowClass' => $tab === 'supplier' && ($r['overdue'] ?? 0) > 0 ? 'pr-row-overdue' : '',
            'html' => view($view, ['r' => $r, 'i' => $start + $i, 'base' => $report->baseCurrency()])->render(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'footer' => $this->footer($tab, $rows, $report->baseCurrency()),
            'data' => $data,
        ]);
    }

    /**
     * Baris total (tfoot). Supplier/project: dijumlah per mata uang, satu baris per
     * mata uang dalam satu sel. Selisih kurs: nilai buku/aktual/selisih dalam mata uang dasar.
     * Jumlah sel = jumlah kolom tabel supaya footer sejajar header.
     */
    private function footer(string $tab, Collection $rows, string $base): array
    {
        if ($tab === 'fx') {
            $diff = round($rows->sum('difference'), 2);
            $label = 'Total '.($diff > 0 ? '(loss)' : ($diff < 0 ? '(gain)' : ''));

            return ['', trim($label), '', '', '', '', '',
                e(Number::moneyWithCode($rows->sum('book'), $base)),
                e(Number::moneyWithCode($rows->sum('actual'), $base)),
                '<span class="'.($diff > 0 ? 'text-danger' : ($diff < 0 ? 'text-success' : '')).'">'.e(Number::moneyWithCode($diff, $base)).'</span>',
            ];
        }

        $byCurrency = $rows->groupBy('currency')->sortKeys();
        $sum = fn (string $key) => $byCurrency->isEmpty() ? '—' : $byCurrency
            ->map(fn ($group, $code) => e(Number::moneyWithCode($group->sum($key), $code)))
            ->implode('<br>');
        $label = $byCurrency->count() > 1 ? 'Total per currency' : 'Total';

        return $tab === 'project'
            ? ['', $label, '', $sum('total'), $sum('paid'), $sum('sisa')]
            : ['', $label, $sum('total'), $sum('paid'), $sum('sisa')];
    }

    public function export(Request $request)
    {
        $tab = $this->tab($request);
        $report = new PayableReport;
        [$headers, $data] = $this->sheet($tab, $this->rows($report, $tab, $request));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(self::TABS[$tab], 0, 31));
        $note = $tab === 'fx' ? 'book / actual / difference in '.$report->baseCurrency() : 'amounts in bill currency';
        $sheet->fromArray(['Payables '.self::TABS[$tab].' — as of '.now()->format('d/m/Y').' ('.$note.')'], null, 'A1');
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

    private function tab(Request $request): string
    {
        return array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'supplier';
    }

    /** Rentang tanggal awal tab Selisih Kurs (default: awal tahun s/d hari ini). */
    private function period(Request $request): array
    {
        return [
            'from' => $request->input('from') ?: now()->startOfYear()->toDateString(),
            'to' => $request->input('to') ?: now()->toDateString(),
        ];
    }

    private function rows(PayableReport $report, string $tab, Request $request): Collection
    {
        return match ($tab) {
            'fx' => $this->filterRows(collect($report->fx($request->input('from'), $request->input('to'))['realized']), 'fx', $request),
            'project' => $this->projectRows($this->filterRows($report->projectBills(), 'project', $request)),
            default => $this->filterRows($report->supplierByCurrency(), 'supplier', $request),
        };
    }

    /** Tagihan ber-project dijumlah per supplier × project × mata uang (filter diterapkan per tagihan dulu). */
    private function projectRows(Collection $bills): Collection
    {
        return $bills
            ->groupBy(fn (array $r) => ($r['bill']?->supplier_id ?? 0).'|'.($r['project']?->id ?? 0).'|'.$r['currency'])
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'supplier' => $first['bill']?->supplier,
                    'project' => $first['project'],
                    'currency' => $first['currency'],
                    'bill_count' => $group->count(),
                    'total' => round($group->sum('total'), 2),
                    'paid' => round($group->sum('paid'), 2),
                    'sisa' => round($group->sum('sisa'), 2),
                ];
            })
            ->sortBy(fn (array $r) => ($r['supplier']?->name ?? '').'|'.($r['project']?->project_number ?? ''))
            ->values();
    }

    /**
     * Cari global + filter: mata uang, masih punya sisa (yes/no), supplier, rentang tanggal.
     * Sisa: supplier/project = sisa hutang; selisih kurs = sisa tagihan yang dibayar.
     */
    private function filterRows(Collection $rows, string $tab, Request $request): Collection
    {
        $q = mb_strtolower(trim((string) ($request->input('q') ?: $request->input('search.value', ''))));
        if ($q !== '') {
            $rows = $rows->filter(function (array $r) use ($q, $tab) {
                $parts = match ($tab) {
                    'project' => [$r['project']?->project_number, $r['project']?->project_name, $r['bill']?->supplier?->name, $r['bill']?->bill_number, $r['bill']?->supplier_invoice_number],
                    'fx' => array_merge([$r['number'], $r['supplier'], $r['reference'], $r['currency']], collect($r['projects'])->flatMap(fn ($p) => [$p->project_number, $p->project_name])->all()),
                    default => [$r['supplier']?->name],
                };

                return str_contains(mb_strtolower(implode(' ', array_filter($parts))), $q);
            });
        }

        $currency = strtoupper((string) $request->input('currency', ''));
        if ($currency !== '') {
            $rows = $rows->filter(fn (array $r) => strtoupper((string) $r['currency']) === $currency);
        }

        $remainingKey = $tab === 'fx' ? 'remaining' : 'sisa';
        $outstanding = $request->input('outstanding');
        if ($outstanding === 'yes') {
            $rows = $rows->filter(fn (array $r) => ($r[$remainingKey] ?? 0) > 0.005);
        } elseif ($outstanding === 'no') {
            $rows = $rows->filter(fn (array $r) => ($r[$remainingKey] ?? 0) <= 0.005);
        }

        $supplierId = (int) $request->input('supplier');
        if ($supplierId && $tab !== 'supplier') {
            $rows = $rows->filter(fn (array $r) => (int) ($tab === 'fx' ? $r['supplier_id'] : $r['bill']?->supplier_id) === $supplierId);
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
    private function sheet(string $tab, Collection $rows): array
    {
        return match ($tab) {
            'fx' => [
                ['No', 'Date', 'Project', 'Document', 'Reference', 'Supplier', 'Currency', 'Amount', 'Book Rate', 'Actual Rate', 'Book Value', 'Actual Value', 'Difference (+ loss / − gain)', 'Bill Remaining'],
                $rows->values()->map(fn ($r, $i) => [
                    $i + 1, $r['date']?->format('d/m/Y'),
                    collect($r['projects'])->map(fn ($p) => $p->project_number)->implode(', '),
                    $r['number'], $r['reference'], $r['supplier'], $r['currency'], $r['amount'],
                    $r['book_rate'], $r['actual_rate'], $r['book'], $r['actual'], $r['difference'], $r['remaining'],
                ])->all(),
            ],
            'project' => [
                ['No', 'Supplier', 'Project No.', 'Project Name', 'Currency', 'Total Payable', 'Paid', 'Outstanding'],
                $rows->values()->map(fn ($r, $i) => [
                    $i + 1, $r['supplier']?->name, $r['project']?->project_number, $r['project']?->project_name,
                    $r['currency'], $r['total'], $r['paid'], $r['sisa'],
                ])->all(),
            ],
            default => [
                ['No', 'Supplier', 'Currency', 'Total Payable', 'Paid', 'Outstanding'],
                $rows->values()->map(fn ($r, $i) => [
                    $i + 1, $r['supplier']?->name, $r['currency'], $r['total'], $r['paid'], $r['sisa'],
                ])->all(),
            ],
        };
    }
}

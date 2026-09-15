<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Log;
use App\Models\ProfitEstimate;
use App\Models\Quotation;
use App\Support\ModuleAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul Estimasi Perhitungan Pendapatan (PL) — satu PL per quotation.
 * Tanpa approval; PL ditandai outdated bila quotation sumber berubah.
 */
class ProfitEstimateController extends Controller
{
    public const MODULE_CODE = 'MOD_PROFIT_ESTIMATE';

    public function index()
    {
        return view('profit-estimate.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = ProfitEstimate::with(['quotation', 'creator']);

        $recordsTotal = ProfitEstimate::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('project_name', 'like', "%{$searchValue}%")
                    ->orWhereHas('quotation', fn ($x) => $x->where('quotation_number', 'like', "%{$searchValue}%")
                        ->orWhere('to_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $columnOrderMap = [
            3 => 'date',
            4 => 'real_project_value',
            5 => 'total_cost',
            6 => 'real_profit',
        ];
        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $rows = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($rows as $i => $pl) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $pl->id,
                'quotation_number' => $pl->quotation?->quotation_number ?? '—',
                'quotation_id' => $pl->quotation_id,
                'project_name' => $pl->project_name ?? '—',
                'date' => $pl->date?->format('d/m/Y') ?? '—',
                'real_project_value' => ProfitEstimate::formatMoney($pl->real_project_value),
                'total_cost' => ProfitEstimate::formatMoney($pl->total_cost),
                'real_profit' => ProfitEstimate::formatMoney($pl->real_profit),
                'real_profit_percent' => number_format($pl->real_profit_percent, 2).'%',
                'is_outdated' => $pl->is_outdated,
                'status_badge' => $pl->is_outdated
                    ? '<span class="status-badge" style="background:#fef3c7;color:#92400e;">Outdated</span>'
                    : '<span class="status-badge status-active">Up to date</span>',
                'creator_name' => $pl->creator?->username ?? '—',
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Tanpa quotation_id: halaman pilih quotation. Dengan quotation_id: form
     * yang sudah terisi (prefill) dari quotation tersebut.
     */
    public function create(Request $request)
    {
        $quotationId = $request->integer('quotation_id');

        if (! $quotationId) {
            return view('profit-estimate.create', [
                'quotations' => $this->eligibleQuotations(),
            ]);
        }

        $quotation = $this->loadQuotation($quotationId);

        if ($quotation->profitEstimate) {
            return redirect()
                ->route('profit-estimate.show', $quotation->profitEstimate->id)
                ->with('info', 'Quotation ini sudah memiliki Estimasi PL.');
        }

        $previous = $this->previousEstimateFor($quotation);

        return view('profit-estimate.form', [
            'estimate' => null,
            'quotation' => $quotation,
            'data' => $this->prefillFromQuotation($quotation, $previous),
            'currencyCodes' => $this->currencyCodes(),
            'currencySymbols' => collect(Currency::formOptions())->pluck('symbol', 'name')->all(),
            'configItems' => $this->configItemsForPicker($quotation),
            'previous' => $previous,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge(
            ['quotation_id' => 'required|integer|exists:quotations,id'],
            $this->rules()
        ));

        $quotation = $this->loadQuotation((int) $validated['quotation_id']);

        if ($quotation->profitEstimate) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation ini sudah memiliki Estimasi PL. Satu quotation hanya boleh memiliki satu PL.',
            ], 422);
        }

        if (in_array($quotation->status, [Quotation::STATUS_REJECTED, Quotation::STATUS_ARCHIVED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation berstatus '.$quotation->status_label.' tidak bisa dibuatkan Estimasi PL.',
            ], 422);
        }

        if ($error = $this->rejectProductsWithoutVendor($validated['lines'] ?? [])) {
            return $error;
        }

        try {
            $estimate = DB::transaction(function () use ($validated, $quotation) {
                $estimate = new ProfitEstimate([
                    'quotation_id' => $quotation->id,
                    'task_id' => $quotation->task_id,
                    'opportunity_id' => $quotation->opportunity_id,
                    'created_by' => Auth::id(),
                ]);

                $this->persist($estimate, $validated);

                return $estimate;
            });

            Log::record(
                'create_profit_estimate',
                "Estimasi PL #{$estimate->id} dibuat dari Quotation #{$quotation->id} ({$quotation->quotation_number})",
                self::MODULE_CODE,
                $estimate
            );

            return response()->json([
                'success' => true,
                'message' => 'Estimasi PL berhasil dibuat.',
                'id' => $estimate->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan Estimasi PL: '.$e->getMessage(),
            ], 422);
        }
    }

    public function show($id)
    {
        $estimate = ProfitEstimate::with([
            'lines',
            'creator',
            'updater',
            'quotation.opportunity.accountCompany',
            'quotation.task',
        ])->findOrFail($id);

        $currentQuotation = $this->currentQuotationOfGroup($estimate->quotation);
        $currencySymbols = collect(Currency::formOptions())->pluck('symbol', 'name')->all();

        return view('profit-estimate.show', [
            'estimate' => $estimate,
            'currentQuotation' => $currentQuotation,
            'currencySymbols' => $currencySymbols,
        ]);
    }

    public function edit($id)
    {
        $estimate = ProfitEstimate::with('lines')->findOrFail($id);
        $quotation = $this->loadQuotation($estimate->quotation_id);

        return view('profit-estimate.form', [
            'estimate' => $estimate,
            'quotation' => $quotation,
            'data' => $this->dataFromEstimate($estimate),
            'currencyCodes' => $this->currencyCodes(),
            'currencySymbols' => collect(Currency::formOptions())->pluck('symbol', 'name')->all(),
            'configItems' => $this->configItemsForPicker($quotation),
            'previous' => null,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $estimate = ProfitEstimate::findOrFail($id);
        $validated = $request->validate($this->rules());

        if ($error = $this->rejectProductsWithoutVendor($validated['lines'] ?? [])) {
            return $error;
        }

        try {
            DB::transaction(function () use ($estimate, $validated) {
                $estimate->updated_by = Auth::id();
                $this->persist($estimate, $validated);
            });

            Log::record(
                'update_profit_estimate',
                "Estimasi PL #{$estimate->id} diupdate",
                self::MODULE_CODE,
                $estimate
            );

            return response()->json([
                'success' => true,
                'message' => 'Estimasi PL berhasil diupdate.',
                'id' => $estimate->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate Estimasi PL: '.$e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id): JsonResponse
    {
        $estimate = ProfitEstimate::findOrFail($id);
        $estimate->delete();

        Log::record(
            'delete_profit_estimate',
            "Estimasi PL #{$estimate->id} (Quotation #{$estimate->quotation_id}) dihapus",
            self::MODULE_CODE,
            $estimate
        );

        return response()->json([
            'success' => true,
            'message' => 'Estimasi PL berhasil dihapus.',
        ]);
    }

    /**
     * Sinkronisasi: buka form edit yang sudah diisi ulang dari quotation sumber
     * (Nilai Awal, PPN, Discount, daftar item, nama project). Input manual
     * (vendor/amount item yang namanya sama, koreksi HPP, biaya, persentase,
     * kurs, tanda tangan) dipertahankan. Menyimpan form menghapus tanda outdated.
     */
    public function sync($id)
    {
        $estimate = ProfitEstimate::with('lines')->findOrFail($id);
        $quotation = $this->loadQuotation($estimate->quotation_id);

        return view('profit-estimate.form', [
            'estimate' => $estimate,
            'quotation' => $quotation,
            'data' => $this->prefillFromQuotation($quotation, $estimate),
            'currencyCodes' => $this->currencyCodes(),
            'currencySymbols' => collect(Currency::formOptions())->pluck('symbol', 'name')->all(),
            'configItems' => $this->configItemsForPicker($quotation),
            'previous' => null,
            'syncing' => true,
        ]);
    }

    public function pdf($id)
    {
        $estimate = ProfitEstimate::with([
            'lines',
            'quotation.opportunity.accountCompany',
        ])->findOrFail($id);

        $currencySymbols = collect(Currency::formOptions())->pluck('symbol', 'name')->all();

        $pdf = Pdf::loadView('profit-estimate.pdf', compact('estimate', 'currencySymbols'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Estimasi-PL-'.$estimate->id.'.pdf');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function rules(): array
    {
        return [
            'date' => 'nullable|date',
            'project_name' => 'nullable|string|max:255',
            'rates' => 'nullable|array',
            'rates.*' => 'nullable|numeric|min:0',
            'nilai_awal' => 'nullable|numeric|min:0',
            'ppn_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'referral_percent' => 'nullable|numeric|min:0|max:100',
            'investment_percent' => 'nullable|numeric|min:0|max:100',
            'marketing_fee_percent' => 'nullable|numeric|min:0|max:100',
            'pm_fee_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'sales_person_name' => 'nullable|string|max:150',
            'finance_name' => 'nullable|string|max:150',
            'accounting_name' => 'nullable|string|max:150',
            'lines' => 'nullable|array',
            'lines.*.section' => 'required|string|in:'.implode(',', ProfitEstimate::SECTIONS),
            'lines.*.label' => 'nullable|string|max:255',
            'lines.*.qty' => 'nullable|numeric|min:0',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.vendor' => 'nullable|string|max:150',
            'lines.*.currency' => 'nullable|string|max:10',
            'lines.*.amount' => 'nullable|numeric|min:0',
            'lines.*.is_manual' => 'nullable|boolean',
            'lines.*.percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.is_up' => 'nullable|boolean',
        ];
    }

    /**
     * Simpan header + baris, hitung nilai turunan, hapus tanda outdated.
     */
    private function persist(ProfitEstimate $estimate, array $input): void
    {
        $rates = $this->normalizeRates($input['rates'] ?? []);

        // HPP selalu dibentuk ulang dari baris item (override manual dipertahankan).
        $lines = ProfitEstimate::deriveHppLines($this->nonBlankLines($input['lines'] ?? []));

        $result = ProfitEstimate::calculate($input, $lines, $rates);

        $estimate->fill(array_merge($result['header'], [
            'date' => $input['date'] ?? ($estimate->date?->toDateString() ?: now()->toDateString()),
            'project_name' => $input['project_name'] ?? null,
            'rates' => $rates,
            'notes' => $input['notes'] ?? null,
            'sales_person_name' => $input['sales_person_name'] ?? null,
            'finance_name' => $input['finance_name'] ?? null,
            'accounting_name' => $input['accounting_name'] ?? null,
            'is_outdated' => false,
            'outdated_at' => null,
        ]));
        $estimate->save();

        $estimate->lines()->delete();
        foreach ($result['lines'] as $line) {
            $estimate->lines()->create([
                'section' => $line['section'],
                'label' => $line['label'] ?? null,
                'qty' => isset($line['qty']) && $line['qty'] !== '' ? (float) $line['qty'] : null,
                'unit' => $line['unit'] ?? null,
                'vendor' => $line['vendor'] ?? null,
                'currency' => $line['currency'],
                'amount' => $line['amount'],
                'amount_idr' => $line['amount_idr'],
                'is_manual' => (bool) ($line['is_manual'] ?? false),
                'percent' => $line['percent'] ?? null,
                'is_up' => (bool) ($line['is_up'] ?? false),
                'sort_order' => $line['sort_order'],
            ]);
        }

        $estimate->unsetRelation('lines');
    }

    /**
     * Buang baris kosong (tanpa label, vendor, qty, dan nominal).
     */
    private function nonBlankLines(array $lines): array
    {
        return collect($lines)
            ->filter(function ($line) {
                $hasText = trim((string) ($line['label'] ?? '')) !== '' || trim((string) ($line['vendor'] ?? '')) !== '';
                $hasAmount = (float) ($line['amount'] ?? 0) > 0 || (float) ($line['qty'] ?? 0) > 0 || (float) ($line['percent'] ?? 0) > 0;

                return $hasText || $hasAmount;
            })
            ->values()
            ->all();
    }

    /**
     * Setiap item wajib punya vendor (HPP dikelompokkan per vendor).
     */
    private function rejectProductsWithoutVendor(array $lines): ?JsonResponse
    {
        $missing = ProfitEstimate::productLabelsWithoutVendor($this->nonBlankLines($lines));

        if (empty($missing)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Setiap item harus memiliki vendor. Item tanpa vendor: '.implode(', ', $missing).'.',
        ], 422);
    }

    /**
     * Kurs: hanya kode mata uang non-base yang dikenal; nilai numerik.
     */
    private function normalizeRates(array $rates): array
    {
        $known = $this->currencyCodes();
        $out = [];

        foreach ($known as $code) {
            if ($code === ProfitEstimate::BASE_CURRENCY) {
                continue;
            }
            $out[$code] = round((float) ($rates[$code] ?? 0), 4);
        }

        return $out;
    }

    /**
     * Kode mata uang aktif, base (IDR) di urutan pertama.
     */
    private function currencyCodes(): array
    {
        $codes = Currency::active()
            ->orderByDesc('is_base')
            ->orderBy('id')
            ->pluck('name')
            ->map(fn ($c) => strtoupper($c))
            ->all();

        if (! in_array(ProfitEstimate::BASE_CURRENCY, $codes, true)) {
            array_unshift($codes, ProfitEstimate::BASE_CURRENCY);
        }

        return $codes;
    }

    /**
     * Snapshot kurs saat ini dari master currency (non-base).
     */
    private function currentRates(): array
    {
        return Currency::active()
            ->where('is_base', false)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($c) => [strtoupper($c->name) => (float) $c->rate])
            ->all();
    }

    private function loadQuotation(int $id): Quotation
    {
        return Quotation::with([
            'items',
            'costItems',
            'configurations.items.product.currency',
            'configItems',
            'opportunity.accountCompany',
            'task.creator',
            'profitEstimate',
        ])->findOrFail($id);
    }

    /**
     * Quotation versi terakhir per group yang belum punya PL dan tidak
     * berstatus rejected/archived.
     */
    private function eligibleQuotations()
    {
        $latestIds = Quotation::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('group_id')
            ->pluck('id');

        return Quotation::with(['opportunity.accountCompany'])
            ->whereIn('id', $latestIds)
            ->whereNotIn('status', [Quotation::STATUS_REJECTED, Quotation::STATUS_ARCHIVED])
            ->whereDoesntHave('profitEstimate')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * PL terakhir dari quotation lain di group yang sama (versi sebelumnya),
     * dipakai untuk menyalin input manual saat quotation direvisi.
     */
    private function previousEstimateFor(Quotation $quotation): ?ProfitEstimate
    {
        $groupId = $quotation->group_id ?: $quotation->id;

        return ProfitEstimate::with('lines')
            ->whereHas('quotation', fn ($q) => $q->where('group_id', $groupId))
            ->where('quotation_id', '!=', $quotation->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Quotation versi terakhir di group yang sama (untuk link "buat PL baru").
     */
    private function currentQuotationOfGroup(?Quotation $quotation): ?Quotation
    {
        if (! $quotation) {
            return null;
        }

        $groupId = $quotation->group_id ?: $quotation->id;

        return Quotation::with('profitEstimate')
            ->where('group_id', $groupId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Data form dari PL yang sudah ada (mode edit).
     */
    private function dataFromEstimate(ProfitEstimate $estimate): array
    {
        return [
            'date' => $estimate->date?->toDateString(),
            'project_name' => $estimate->project_name,
            'rates' => $estimate->rates ?: [],
            'nilai_awal' => $estimate->nilai_awal,
            'ppn_amount' => $estimate->ppn_amount,
            'discount_amount' => $estimate->discount_amount,
            'referral_percent' => $estimate->referral_percent,
            'investment_percent' => $estimate->investment_percent,
            'marketing_fee_percent' => $estimate->marketing_fee_percent,
            'pm_fee_percent' => $estimate->pm_fee_percent,
            'notes' => $estimate->notes,
            'sales_person_name' => $estimate->sales_person_name,
            'finance_name' => $estimate->finance_name,
            'accounting_name' => $estimate->accounting_name,
            'lines' => $estimate->lines->map(fn ($l) => [
                'section' => $l->section,
                'label' => $l->label,
                'qty' => $l->qty,
                'unit' => $l->unit,
                'vendor' => $l->vendor,
                'currency' => $l->currency,
                'amount' => $l->amount,
                'is_manual' => $l->is_manual,
                'percent' => $l->percent,
                'is_up' => $l->is_up,
            ])->values()->all(),
        ];
    }

    /**
     * Susun data form dari quotation. Bila $previous ada (PL versi lama atau
     * PL yang sedang disinkronkan), input manualnya disalin: vendor/mata
     * uang/amount item dengan nama sama, koreksi manual HPP, biaya,
     * persentase, kurs, tanda tangan, catatan. Nilai project, daftar item,
     * dan nama project selalu diambil segar dari quotation.
     */
    private function prefillFromQuotation(Quotation $quotation, ?ProfitEstimate $previous = null): array
    {
        $rates = $previous?->rates ?: $this->currentRates();

        // Daftar item TIDAK diisi dari quotation: user memilih sendiri dari snapshot
        // List Configuration (modal di form). Bila ada PL sebelumnya (sync / revisi),
        // baris itemnya disalin apa adanya.
        $products = $previous
            ? $previous->linesOf(ProfitEstimate::SECTION_PRODUCT)->map(fn ($l) => [
                'section' => ProfitEstimate::SECTION_PRODUCT,
                'label' => $l->label,
                'qty' => $l->qty,
                'unit' => $l->unit,
                'vendor' => $l->vendor,
                'currency' => $l->currency,
                'amount' => $l->amount,
            ])->values()->all()
            : [];

        $copy = fn (string $section) => $previous
            ? $previous->linesOf($section)->map(fn ($l) => [
                'section' => $section,
                'label' => $l->label,
                'qty' => $l->qty,
                'unit' => $l->unit,
                'vendor' => $l->vendor,
                'currency' => $l->currency,
                'amount' => $l->amount,
                'is_manual' => $l->is_manual,
                'percent' => $l->percent,
                'is_up' => $l->is_up,
            ])->all()
            : null;

        // Koreksi HPP dari PL sebelumnya: baik nominal custom (is_manual)
        // maupun toggle "+40" (is_up) dibawa; is_up dihitung ulang dari item
        // terkini oleh deriveHppLines(), bukan dari nominal tersimpan.
        $hppOverrides = array_values(array_filter(
            $copy(ProfitEstimate::SECTION_HPP) ?? [],
            fn ($l) => ! empty($l['is_manual']) || ! empty($l['is_up'])
        ));

        $costSpent = $copy(ProfitEstimate::SECTION_COST_SPENT)
            ?? array_map(fn ($label) => [
                'section' => ProfitEstimate::SECTION_COST_SPENT,
                'label' => $label, 'qty' => null, 'unit' => null, 'vendor' => null,
                'currency' => ProfitEstimate::BASE_CURRENCY, 'amount' => 0,
                // Kirim masuk/keluar: persentase dari total HPP vendor non-IDR.
                'percent' => ProfitEstimate::DEFAULT_COST_SPENT_PERCENT[$label] ?? null,
            ], ProfitEstimate::DEFAULT_COST_SPENT_LABELS);

        $costPlanned = $copy(ProfitEstimate::SECTION_COST_PLANNED);
        if ($costPlanned === null) {
            $costParentIds = $quotation->costItems->pluck('parent_id')->filter()->unique()->all();
            $costTotal = $quotation->costItems
                ->filter(fn ($i) => ! in_array($i->id, $costParentIds, true))
                ->reduce(fn ($c, $i) => $c + (($i->qty ?? 0) * ($i->price ?? 0)), 0);

            $costPlanned = [];
            foreach (ProfitEstimate::DEFAULT_COST_PLANNED_LABELS as $idx => $label) {
                $costPlanned[] = [
                    'section' => ProfitEstimate::SECTION_COST_PLANNED,
                    'label' => $label, 'qty' => null, 'unit' => null, 'vendor' => null,
                    'currency' => ProfitEstimate::BASE_CURRENCY,
                    'amount' => $idx === 0 ? round($costTotal, 2) : 0,
                ];
            }
        }

        $company = $quotation->opportunity?->accountCompany?->account_name;

        return [
            'date' => $previous?->date?->toDateString() ?: now()->toDateString(),
            'project_name' => $quotation->to_name ?: $company,
            'rates' => $rates,
            'nilai_awal' => (float) $quotation->grand_total,
            'ppn_amount' => (float) $quotation->ppn,
            'discount_amount' => (float) ($quotation->discount_amount ?? 0),
            'referral_percent' => $previous?->referral_percent ?? ProfitEstimate::DEFAULT_REFERRAL_PERCENT,
            'investment_percent' => $previous?->investment_percent ?? ProfitEstimate::DEFAULT_INVESTMENT_PERCENT,
            'marketing_fee_percent' => $previous?->marketing_fee_percent ?? ProfitEstimate::DEFAULT_MARKETING_FEE_PERCENT,
            'pm_fee_percent' => $previous?->pm_fee_percent ?? ProfitEstimate::DEFAULT_PM_FEE_PERCENT,
            'notes' => $previous?->notes,
            'sales_person_name' => $previous?->sales_person_name
                ?: ($quotation->from_name ?: $quotation->task?->creator?->username),
            'finance_name' => $previous?->finance_name,
            'accounting_name' => $previous?->accounting_name,
            'lines' => ProfitEstimate::deriveHppLines(array_merge($products, $hppOverrides, $costSpent, $costPlanned)),
        ];
    }

    /**
     * Snapshot item List Configuration quotation untuk modal "Pilih dari
     * Configuration" di form PL (harga satuan sebelum & sesudah kurs).
     */
    private function configItemsForPicker(Quotation $quotation): array
    {
        return $quotation->configItems->map(fn ($it) => [
            'id' => $it->id,
            'category' => $it->category ?: 'Lainnya',
            'part_number' => $it->part_number,
            'description' => $this->firstLine($it->description),
            'qty' => (float) ($it->qty ?: 0),
            'unit' => $it->unit,
            'currency' => strtoupper($it->currency ?: ProfitEstimate::BASE_CURRENCY),
            'price_currency' => (float) ($it->price_currency ?? $it->price ?? 0),
            'price' => (float) ($it->price ?? 0),
        ])->values()->all();
    }

    private function firstLine(?string $html): string
    {
        $text = str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
        $first = strtok(trim($text), "\n");

        return trim((string) ($first === false ? '' : $first));
    }

    private function normalizeLabel(?string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', strip_tags((string) $text))));
    }

    /**
     * User punya akses baca modul PL (dipakai tombol di halaman quotation).
     */
    public static function userCanRead(): bool
    {
        return ModuleAccess::for()->module(self::MODULE_CODE)->canRead();
    }
}

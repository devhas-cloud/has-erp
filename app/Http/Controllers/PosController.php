<?php

namespace App\Http\Controllers;

use App\Models\AccountCompany;
use App\Models\Log;
use App\Models\Pos;
use App\Models\PosItem;
use App\Models\Quotation;
use App\Support\ModuleAccess;
use App\Support\Number;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * POS (Point of Sale = Sales Order = penjualan) — dokumen penjualan datar
 * yang menjembatani Quotation -> Project. POS wajib berbasis quotation yang
 * sudah punya estimasi PL; item datar, deskripsi = penamaan customer. Disimpan
 * = Draft, diajukan = Waiting Approval, disetujui/ditolak. Project dibuat dari
 * quotation ber-POS lewat modul Project.
 */
class PosController extends Controller
{
    public function index()
    {
        return view('pos.index', [
            'canCreate' => ModuleAccess::for(Auth::user())->module(Pos::MODULE_CODE)->canCreate(),
            // Filter customer hanya berisi customer yang punya POS.
            'companies' => AccountCompany::whereIn('id', Pos::whereNotNull('company_id')->select('company_id'))
                ->orderBy('account_name')->get(['id', 'account_name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        // Filter selain status; jumlah per status mengikuti filter ini.
        $query = Pos::query()
            ->when($request->input('company'), fn ($q, $company) => $q->where('company_id', $company))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('pos_number', 'like', "%{$search}%")
                ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$search}%"))
                ->orWhereHas('quotation', fn ($qt) => $qt->where('quotation_number', 'like', "%{$search}%"))
                ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$search}%"))
                ->orWhereHas('items', fn ($i) => $i->where('description', 'like', "%{$search}%")));
        }

        $statusCounts = (clone $query)->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);
        $query->with(['company', 'quotation', 'project', 'creator'])->withCount('items')->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        $recordsTotal = Pos::count();
        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        // Yang perlu tindakan lebih dulu: menunggu approval, ditolak, draft, lalu disetujui.
        $rows = $query->orderByRaw("CASE status WHEN 'waiting_approval' THEN 0 WHEN 'rejected' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderByDesc('id')->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => collect(Pos::STATUS_LABELS)->map(fn ($label, $key) => $statusCounts[$key] ?? 0),
            'data' => $rows->map(fn (Pos $pos, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $pos->id,
                'pos_number' => $pos->pos_number,
                'date' => $pos->date?->format('d/m/Y'),
                'company' => $pos->company?->account_name ?? '—',
                'quotation' => $pos->quotation?->quotation_number,
                'quotation_url' => $pos->quotation_id ? route('quotation.show', $pos->quotation_id) : null,
                'item_count' => $pos->items_count,
                'grand_total' => 'Rp '.Number::money($pos->grand_total),
                'requires_dp' => (bool) $pos->requires_dp,
                'project' => $pos->project?->project_number,
                'project_url' => $pos->project_id ? route('project.show', $pos->project_id) : null,
                'creator' => $pos->creator?->display_name ?? '—',
                'status' => $pos->status,
                'status_badge' => $pos->statusBadgeHtml(),
            ]),
        ]);
    }

    public function create()
    {
        return view('pos.form', [
            'pos' => null,
            'quotations' => $this->selectableQuotations(),
        ]);
    }

    public function edit($id)
    {
        $pos = Pos::with('items')->findOrFail($id);

        abort_unless(in_array($pos->status, [Pos::STATUS_DRAFT, Pos::STATUS_REJECTED], true), 403, 'A submitted or approved POS cannot be edited.');

        return view('pos.form', [
            'pos' => $pos,
            'quotations' => $this->selectableQuotations(),
        ]);
    }

    /** Item quotation untuk prefill (datar, hanya baris induk) + info customer & DP. */
    public function quotationItems($id): JsonResponse
    {
        $quotation = Quotation::with(['items', 'opportunity.accountCompany'])->findOrFail($id);

        $items = $quotation->items
            ->map(fn ($item) => [
                'quotation_item_id' => $item->id,
                'description' => trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", Quotation::sanitizeDescription($item->description)))),
                'qty' => $item->qty,
                'unit' => $item->unit,
                'unit_price' => $item->selling_price,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'company_id' => $quotation->opportunity?->account_companies_id,
            'company_name' => $quotation->opportunity?->accountCompany?->account_name,
            'requires_dp' => (bool) $quotation->requires_dp,
            'discount_percent' => $quotation->discount_percent,
            'discount_amount' => $quotation->discount_amount,
            'ppn_percent' => $quotation->ppn_percent,
            'ppn_amount' => $quotation->ppn_amount,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateInput($request);

        $quotation = Quotation::with(['opportunity', 'profitEstimate'])->find($validated['quotation_id']);
        $totals = $this->totals($validated);
        $this->assertMatchesQuotationTotal($quotation, $totals);
        $meta = $this->quotationMeta($quotation);

        $pos = DB::transaction(function () use ($validated, $totals, $meta) {
            $pos = Pos::create([
                'pos_number' => Pos::generateNumber($validated['date']),
                'quotation_id' => $validated['quotation_id'],
                'company_id' => $meta['company_id'],
                'date' => $validated['date'],
                'requires_dp' => $meta['requires_dp'],
                'subtotal' => $totals['subtotal'],
                'discount_percent' => $totals['discount_percent'],
                'discount_amount' => $totals['discount_amount'],
                'ppn_percent' => $totals['ppn_percent'],
                'ppn_amount' => $totals['ppn'],
                'grand_total' => $totals['grand_total'],
                'status' => Pos::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);

            $this->saveItems($pos, $validated['items']);

            return $pos;
        });

        Log::record('create_pos', 'POS '.$pos->pos_number.' dibuat oleh '.Auth::user()->display_name, Pos::MODULE_CODE, $pos);

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' saved as Draft.', 'id' => $pos->id]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $pos = Pos::findOrFail($id);

        abort_unless(in_array($pos->status, [Pos::STATUS_DRAFT, Pos::STATUS_REJECTED], true), 403, 'A submitted or approved POS cannot be edited.');

        $validated = $this->validateInput($request);

        $quotation = Quotation::with(['opportunity', 'profitEstimate'])->find($validated['quotation_id']);
        $totals = $this->totals($validated);
        $this->assertMatchesQuotationTotal($quotation, $totals);
        $meta = $this->quotationMeta($quotation);

        DB::transaction(function () use ($pos, $validated, $totals, $meta) {
            $pos->update([
                'quotation_id' => $validated['quotation_id'],
                'company_id' => $meta['company_id'],
                'date' => $validated['date'],
                'requires_dp' => $meta['requires_dp'],
                'subtotal' => $totals['subtotal'],
                'discount_percent' => $totals['discount_percent'],
                'discount_amount' => $totals['discount_amount'],
                'ppn_percent' => $totals['ppn_percent'],
                'ppn_amount' => $totals['ppn'],
                'grand_total' => $totals['grand_total'],
                'status' => Pos::STATUS_DRAFT,
                'rejected_by' => null,
                'rejected_at' => null,
                'reject_reason' => null,
            ]);

            $pos->items()->delete();
            $this->saveItems($pos, $validated['items']);
        });

        Log::record('update_pos', 'POS '.$pos->pos_number.' diubah oleh '.Auth::user()->display_name, Pos::MODULE_CODE, $pos);

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' updated.', 'id' => $pos->id]);
    }

    public function show($id)
    {
        $pos = Pos::with(['company', 'quotation', 'project', 'creator', 'approver', 'rejecter', 'items.quotationItem', 'items.masterProduct'])->findOrFail($id);

        return view('pos.show', [
            'pos' => $pos,
            'canSubmit' => in_array($pos->status, [Pos::STATUS_DRAFT, Pos::STATUS_REJECTED], true) && ModuleAccess::for(Auth::user())->module(Pos::MODULE_CODE)->canCreate(),
            'canDecide' => $pos->isWaitingApproval() && ModuleAccess::for(Auth::user())->module(Pos::MODULE_CODE)->canApprove(),
            'canRunProject' => $pos->isApproved() && ! $pos->project_id,
        ]);
    }

    public function submit($id): JsonResponse
    {
        $pos = Pos::with('items')->findOrFail($id);

        if (! in_array($pos->status, [Pos::STATUS_DRAFT, Pos::STATUS_REJECTED], true)) {
            return response()->json(['success' => false, 'message' => 'This POS cannot be submitted.'], 422);
        }

        if ($pos->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'A POS must have at least one item.'], 422);
        }

        $pos->update(['status' => Pos::STATUS_WAITING_APPROVAL]);

        Log::record('submit_pos', 'POS '.$pos->pos_number.' diajukan untuk approval oleh '.Auth::user()->display_name, Pos::MODULE_CODE, $pos);

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' submitted for approval.']);
    }

    public function approve($id): JsonResponse
    {
        $pos = Pos::findOrFail($id);

        if (! $pos->isWaitingApproval()) {
            return response()->json(['success' => false, 'message' => 'This POS is not waiting for approval.'], 422);
        }

        $pos->update([
            'status' => Pos::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        Log::record('approve_pos', 'POS '.$pos->pos_number.' disetujui oleh '.Auth::user()->display_name, Pos::MODULE_CODE, $pos);

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' approved.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255'], ['reason.required' => 'Rejection reason is required.']);

        $pos = Pos::findOrFail($id);

        if (! $pos->isWaitingApproval()) {
            return response()->json(['success' => false, 'message' => 'This POS is not waiting for approval.'], 422);
        }

        $pos->update([
            'status' => Pos::STATUS_REJECTED,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'reject_reason' => $validated['reason'],
        ]);

        Log::record('reject_pos', 'POS '.$pos->pos_number.' ditolak oleh '.Auth::user()->display_name.': '.$validated['reason'], Pos::MODULE_CODE, $pos);

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' rejected.']);
    }

    public function destroy($id): JsonResponse
    {
        $pos = Pos::findOrFail($id);

        abort_unless(in_array($pos->status, [Pos::STATUS_DRAFT, Pos::STATUS_REJECTED], true), 403, 'Only Draft / Rejected POS can be deleted.');

        $number = $pos->pos_number;
        $pos->delete();

        Log::record('delete_pos', 'POS '.$number.' dihapus oleh '.Auth::user()->display_name, Pos::MODULE_CODE);

        return response()->json(['success' => true, 'message' => 'POS '.$number.' deleted.']);
    }

    private function validateInput(Request $request): array
    {
        return $request->validate([
            'date' => 'required|date',
            'quotation_id' => 'required|integer|exists:quotations,id',
            'discount_percent' => 'nullable|numeric|gte:0',
            'discount_amount' => 'nullable|numeric|gte:0',
            'ppn_percent' => 'nullable|numeric|gte:0',
            'ppn_amount' => 'nullable|numeric|gte:0',
            'items' => 'required|array|min:1',
            'items.*.quotation_item_id' => 'nullable|integer|exists:quotation_items,id',
            'items.*.description' => 'required|string|max:2000',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|gte:0',
        ], [
            'quotation_id.required' => 'A POS must be based on a quotation. Select a quotation first.',
            'items.required' => 'At least one item is required.',
            'items.*.description.required' => 'Item description (customer naming) is required.',
            'items.*.qty.gt' => 'Item qty must be greater than 0.',
        ]);
    }

    /** Customer & syarat DP POS mengikuti quotation sumbernya. */
    private function quotationMeta(?Quotation $quotation): array
    {
        if (! $quotation?->profitEstimate) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'No Profit Estimate exists for this quotation yet. Create the Profit Estimate first.',
            ], 422));
        }

        return [
            'company_id' => $quotation?->opportunity?->account_companies_id,
            'requires_dp' => (bool) ($quotation?->requires_dp ?? false),
        ];
    }

    /**
     * Nilai POS wajib sesuai quotation sumbernya: grand total hasil kalkulasi
     * item/diskon/PPN dibandingkan dengan grand total quotation (toleransi 1
     * untuk selisih pembulatan). Tidak sesuai = tolak simpan.
     */
    private function assertMatchesQuotationTotal(?Quotation $quotation, array $totals): void
    {
        $posTotal = round((float) ($totals['grand_total'] ?? 0), 2);
        $quotationTotal = round((float) ($quotation?->grand_total ?? 0), 2);

        if (abs($posTotal - $quotationTotal) > 1) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'POS grand total ('.Number::money($posTotal).') does not match the quotation grand total ('.Number::money($quotationTotal).'). Adjust the items, discount or VAT to match the quotation.',
            ], 422));
        }
    }

    private function totals(array $validated): array
    {
        $items = collect($validated['items'])->map(fn ($row) => [
            'qty' => (float) $row['qty'],
            'selling_price' => (float) $row['unit_price'],
        ])->all();

        return Quotation::calculateTotals(
            $items,
            $this->nullableFloat($validated, 'discount_percent'),
            $this->nullableFloat($validated, 'discount_amount'),
            $this->nullableFloat($validated, 'ppn_percent'),
            $this->nullableFloat($validated, 'ppn_amount'),
        );
    }

    private function nullableFloat(array $data, string $key): ?float
    {
        return isset($data[$key]) && $data[$key] !== null && $data[$key] !== ''
            ? (float) $data[$key]
            : null;
    }

    private function saveItems(Pos $pos, array $items): void
    {
        foreach ($items as $i => $row) {
            $pos->items()->create([
                'quotation_item_id' => $row['quotation_item_id'] ?? null,
                'description' => $row['description'],
                'qty' => (float) $row['qty'],
                'unit' => $row['unit'] ?? null,
                'unit_price' => (float) $row['unit_price'],
                'subtotal' => round((float) $row['qty'] * (float) $row['unit_price'], 2),
                'sort_order' => $i,
            ]);
        }
    }

    private function selectableQuotations()
    {
        return Quotation::with('opportunity.accountCompany')
            ->whereIn('status', [Quotation::STATUS_APPROVED, Quotation::STATUS_FINISH])
            ->whereHas('profitEstimate')
            ->orderByDesc('id')
            ->get(['id', 'quotation_number', 'to_name', 'grand_total', 'requires_dp', 'opportunity_id'])
            ->map(fn ($q) => [
                'id' => $q->id,
                'label' => $q->quotation_number.($q->to_name ? ' — '.$q->to_name : ''),
                'grand_total' => $q->grand_total,
                'company_id' => $q->opportunity?->account_companies_id,
                'company_name' => $q->opportunity?->accountCompany?->account_name,
                'requires_dp' => (bool) $q->requires_dp,
            ]);
    }
}

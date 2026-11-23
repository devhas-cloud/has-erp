<?php

namespace App\Http\Controllers;

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
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Pos::with(['company', 'quotation', 'project', 'creator', 'items'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('date', '<=', $date));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('pos_number', 'like', "%{$search}%")
                ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$search}%"))
                ->orWhereHas('quotation', fn ($qt) => $qt->where('quotation_number', 'like', "%{$search}%"))
                ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$search}%"))
                ->orWhereHas('items', fn ($i) => $i->where('description', 'like', "%{$search}%")));
        }

        $recordsTotal = Pos::count();
        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $rows = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn (Pos $pos, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $pos->id,
                'pos_number' => $pos->pos_number,
                'date' => $pos->date?->format('d/m/Y'),
                'company' => $pos->company?->account_name ?? '—',
                'quotation' => $pos->quotation?->quotation_number ?? '—',
                'item_count' => $pos->items->count(),
                'grand_total' => Number::money($pos->grand_total),
                'project' => $pos->project?->project_number ?? '—',
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

        abort_unless(in_array($pos->status, [Pos::STATUS_DRAFT, Pos::STATUS_REJECTED], true), 403, 'POS yang sudah diajukan/disetujui tidak bisa diubah.');

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

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' tersimpan sebagai Draft.', 'id' => $pos->id]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $pos = Pos::findOrFail($id);

        abort_unless(in_array($pos->status, [Pos::STATUS_DRAFT, Pos::STATUS_REJECTED], true), 403, 'POS yang sudah diajukan/disetujui tidak bisa diubah.');

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

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' diperbarui.', 'id' => $pos->id]);
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
            return response()->json(['success' => false, 'message' => 'POS ini tidak bisa diajukan.'], 422);
        }

        if ($pos->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'POS harus punya minimal satu item.'], 422);
        }

        $pos->update(['status' => Pos::STATUS_WAITING_APPROVAL]);

        Log::record('submit_pos', 'POS '.$pos->pos_number.' diajukan untuk approval oleh '.Auth::user()->display_name, Pos::MODULE_CODE, $pos);

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' diajukan untuk approval.']);
    }

    public function approve($id): JsonResponse
    {
        $pos = Pos::findOrFail($id);

        if (! $pos->isWaitingApproval()) {
            return response()->json(['success' => false, 'message' => 'POS ini tidak sedang menunggu approval.'], 422);
        }

        $pos->update([
            'status' => Pos::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        Log::record('approve_pos', 'POS '.$pos->pos_number.' disetujui oleh '.Auth::user()->display_name, Pos::MODULE_CODE, $pos);

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' disetujui.']);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255'], ['reason.required' => 'Alasan penolakan wajib diisi.']);

        $pos = Pos::findOrFail($id);

        if (! $pos->isWaitingApproval()) {
            return response()->json(['success' => false, 'message' => 'POS ini tidak sedang menunggu approval.'], 422);
        }

        $pos->update([
            'status' => Pos::STATUS_REJECTED,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'reject_reason' => $validated['reason'],
        ]);

        Log::record('reject_pos', 'POS '.$pos->pos_number.' ditolak oleh '.Auth::user()->display_name.': '.$validated['reason'], Pos::MODULE_CODE, $pos);

        return response()->json(['success' => true, 'message' => 'POS '.$pos->pos_number.' ditolak.']);
    }

    public function destroy($id): JsonResponse
    {
        $pos = Pos::findOrFail($id);

        abort_unless(in_array($pos->status, [Pos::STATUS_DRAFT, Pos::STATUS_REJECTED], true), 403, 'Hanya POS Draft/Rejected yang bisa dihapus.');

        $number = $pos->pos_number;
        $pos->delete();

        Log::record('delete_pos', 'POS '.$number.' dihapus oleh '.Auth::user()->display_name, Pos::MODULE_CODE);

        return response()->json(['success' => true, 'message' => 'POS '.$number.' dihapus.']);
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
            'quotation_id.required' => 'POS wajib berbasis quotation. Pilih quotation terlebih dahulu.',
            'items.required' => 'Minimal satu item wajib diisi.',
            'items.*.description.required' => 'Deskripsi (penamaan customer) item wajib diisi.',
            'items.*.qty.gt' => 'Qty item harus lebih dari 0.',
        ]);
    }

    /** Customer & syarat DP POS mengikuti quotation sumbernya. */
    private function quotationMeta(?Quotation $quotation): array
    {
        if (! $quotation?->profitEstimate) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Estimasi PL belum dibuat untuk quotation ini. Buat Profit Estimate terlebih dahulu.',
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
                'message' => 'Grand total POS ('.Number::money($posTotal).') tidak sesuai dengan grand total quotation ('.Number::money($quotationTotal).'). Sesuaikan item, diskon, atau PPN agar sesuai quotation.',
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

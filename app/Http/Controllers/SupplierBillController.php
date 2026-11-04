<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPurchaseDocuments;
use App\Models\Currency;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\SupplierPayment;
use App\Support\ModuleAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Tagihan Supplier (grup Finance, Tahap 1) — mengakui hutang dari PO dengan
 * 3-way match: qty per baris <= diterima (Penerimaan Barang posted) - sudah
 * ditagih; harga dalam toleransi harga PO; no. invoice supplier unik per
 * supplier. Draft dicatat Purchasing/Finance, diverifikasi approver Finance
 * (supplier-bill.approve -> can_approve; pembuat boleh memverifikasi bila
 * punya can_approve), void beralasan (supplier-bill.void -> can_approve).
 *
 * Tahap 3 — Uang Muka: tagihan tipe advance untuk PO Approved, total uang muka
 * PO <= nilai PO x maks % supplier. Uang muka yang sudah dibayar WAJIB
 * dipotongkan otomatis di tagihan reguler (advance_deduction), dihitung ulang
 * saat verifikasi; sisa yang tidak terpakai menjadi refund (SupplierRefundController).
 *
 * Tahap 4 — Pajak & selisih: biaya tambahan (baris charge, ikut DPP), PPh
 * dipotong dengan tarif bebas per tagihan (saran dari master supplier), faktur
 * pajak wajib untuk supplier PKP ber-PPN sebelum verifikasi, bukti potong
 * dicatat setelahnya (supplier-bill.pph-slip -> can_update).
 */
class SupplierBillController extends Controller
{
    use ManagesPurchaseDocuments;

    /** PO yang boleh ditagih: sudah disetujui (termasuk batal, untuk barang yang terlanjur diterima). */
    private const BILLABLE_PO_STATUSES = [
        PurchaseOrder::STATUS_APPROVED,
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        PurchaseOrder::STATUS_RECEIVED,
        PurchaseOrder::STATUS_CANCELLED,
    ];

    public function index()
    {
        return view('supplier-bill.index', [
            'suppliers' => Supplier::whereIn('id', SupplierBill::select('supplier_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = SupplierBill::with(['supplier', 'purchaseOrder', 'directPurchase', 'allocations.payment']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('bill_number', 'like', "%{$searchValue}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$searchValue}%")
                    ->orWhere('tax_invoice_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', "%{$searchValue}%"));
            });
        }

        $query->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->input('bill_type'), fn ($q, $type) => $q->where('bill_type', $type))
            ->when($request->boolean('pph_pending'), fn ($q) => $q->where('pph_amount', '>', 0)->whereNull('pph_slip_number')->whereNotIn('status', [SupplierBill::STATUS_DRAFT, SupplierBill::STATUS_VOID]))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('bill_date', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('bill_date', '<=', $to))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereIn('status', SupplierBill::OPEN_STATUSES)->whereDate('due_date', '<', now()->toDateString()))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $statusCounts = (clone $query)->reorder()->toBase()
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $bills = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = $bills->map(fn (SupplierBill $bill, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'supplier_invoice_number' => $bill->supplier_invoice_number,
            'supplier_name' => $bill->supplier?->name ?? '—',
            'source' => $bill->sourceNumber(),
            'bill_date' => $bill->bill_date?->format('d/m/Y'),
            'due_date' => $bill->due_date?->format('d/m/Y'),
            'is_overdue' => $bill->isOverdue(),
            'bill_type' => $bill->bill_type,
            'total_label' => $bill->currency.' '.SupplierBill::formatMoney($bill->total),
            'outstanding_label' => $bill->currency.' '.SupplierBill::formatMoney($bill->outstanding()),
            'status' => $bill->status,
            'status_badge' => $bill->statusBadgeHtml(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => collect(SupplierBill::STATUS_LABELS)->map(fn ($label, $key) => (int) ($statusCounts[$key] ?? 0)),
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        return view('supplier-bill.form', [
            'bill' => null,
            'billType' => $request->input('type') === SupplierBill::TYPE_ADVANCE ? SupplierBill::TYPE_ADVANCE : SupplierBill::TYPE_REGULAR,
            'purchaseOrders' => $this->purchaseOrderOptions(),
            'canSetAdvanceLimit' => ModuleAccess::for()->module('MOD_SUPPLIER')->canApprove(),
            'preselectPoId' => $request->integer('purchase_order_id') ?: null,
            'items' => [],
            'charges' => [],
        ]);
    }

    /**
     * Header PO + baris yang bisa ditagih (3-way match) — dipanggil form saat PO dipilih.
     */
    public function billableLines(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
            'bill_id' => 'nullable|integer',
        ]);

        $po = $this->loadPurchaseOrder((int) $validated['purchase_order_id']);
        $own = $this->ownQty($validated['bill_id'] ?? null);
        $exceptId = $validated['bill_id'] ?? null;

        return response()->json([
            'success' => true,
            'header' => [
                'supplier_id' => $po->supplier_id,
                'supplier_name' => $po->supplier?->name ?? $po->supplier_name,
                'currency' => $po->currencyCode(),
                'base_currency' => Currency::baseName(),
                'is_foreign' => $po->currencyCode() !== Currency::baseName(),
                'default_rate' => Currency::rateOf($po->currencyCode()),
                'discount_percent' => (float) $po->discount_percent,
                'ppn_percent' => (float) $po->ppn_percent,
                'payment_term_days' => $this->termDays($po),
                'is_pkp' => (bool) $po->supplier?->is_pkp,
                'default_pph_percent' => $po->supplier?->default_pph_percent,
                'po_type' => $po->po_type,
                'po_total' => $po->netTotal(),
                'advance_allowed' => in_array($po->status, PurchaseOrder::RECEIVABLE_STATUSES, true),
                'advance_percent' => (float) ($po->supplier?->max_advance_percent ?? 0),
                'advance_limit' => $po->advanceLimit(),
                'advance_billed' => $po->advanceBilled($exceptId),
                'advance_balance' => $po->advanceBalance($exceptId),
            ],
            'lines' => $this->billableRows($po, $own)->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateBill($request);
        $isAdvance = $validated['bill_type'] === SupplierBill::TYPE_ADVANCE;
        $po = $this->loadPurchaseOrder((int) $validated['purchase_order_id']);
        $rows = $isAdvance ? [] : $this->matchLines($po, $validated['items'], []);
        if ($isAdvance) {
            $this->checkAdvance($po, (float) $validated['advance_amount']);
        }
        $this->ensureUniqueInvoice($po->supplier_id, $validated['supplier_invoice_number']);

        $bill = DB::transaction(function () use ($validated, $po, $rows, $isAdvance) {
            $bill = SupplierBill::create($this->headerAttributes($validated, $po) + [
                'bill_number' => SupplierBill::generateNumber($validated['bill_date']),
                'bill_type' => $validated['bill_type'],
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'currency' => $po->currencyCode(),
                'discount_percent' => $isAdvance ? 0 : (float) $po->discount_percent,
                'ppn_percent' => $isAdvance ? 0 : (float) $po->ppn_percent,
                'status' => SupplierBill::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);
            $isAdvance ? $this->setAdvanceAmount($bill, (float) $validated['advance_amount']) : $this->syncItems($bill, $rows, $validated['charges'], $validated['pph_base'] ?? null);
            $this->applyAdvance($bill);

            return $bill;
        });

        $this->log($bill, 'create_supplier_bill', ($isAdvance ? 'uang muka ' : '').'dibuat (draft)');

        return response()->json(['success' => true, 'message' => 'Tagihan '.$bill->bill_number.' disimpan sebagai draft.', 'id' => $bill->id]);
    }

    public function show($id)
    {
        $bill = SupplierBill::with(['items.project', 'items.purchaseOrderItem', 'supplier', 'purchaseOrder.supplierBills.allocations.payment', 'purchaseOrder.supplierRefunds', 'purchaseOrder.supplier', 'directPurchase', 'creator', 'verifier', 'voider', 'allocations.payment'])->findOrFail($id);

        return view('supplier-bill.show', [
            'bill' => $bill,
            'documents' => $this->documentList($bill),
            'canCreatePayment' => ModuleAccess::for()->module('MOD_SUPPLIER_PAYMENT')->canCreate(),
            'baseCurrency' => Currency::baseName(),
            'canCorrectRate' => ModuleAccess::for()->module('MOD_SUPPLIER_BILL')->canUpdate() && $this->rateCorrectable($bill),
        ]);
    }

    public function edit($id)
    {
        $bill = SupplierBill::with('items')->findOrFail($id);
        abort_unless($bill->isDraft(), 403, 'Hanya tagihan Draft yang bisa diubah.');

        return view('supplier-bill.form', [
            'bill' => $bill,
            'billType' => $bill->bill_type,
            'purchaseOrders' => $this->purchaseOrderOptions($bill->purchase_order_id),
            'canSetAdvanceLimit' => ModuleAccess::for()->module('MOD_SUPPLIER')->canApprove(),
            'preselectPoId' => $bill->purchase_order_id,
            'items' => $bill->items->where('line_type', SupplierBillItem::TYPE_ITEM)->map(fn ($item) => ['purchase_order_item_id' => $item->purchase_order_item_id, 'qty' => $item->qty, 'price' => $item->price])->values(),
            'charges' => $bill->items->where('line_type', SupplierBillItem::TYPE_CHARGE)->map(fn ($item) => ['description' => $item->description, 'amount' => $item->amount])->values(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $bill = SupplierBill::with('items')->findOrFail($id);
        if (! $bill->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya tagihan Draft yang bisa diubah.'], 422);
        }

        $request->merge(['bill_type' => $bill->bill_type]);
        $validated = $this->validateBill($request);
        $po = $this->loadPurchaseOrder($bill->purchase_order_id);
        if ($bill->isAdvance()) {
            $this->checkAdvance($po, (float) $validated['advance_amount'], $bill->id);
        }
        $rows = $bill->isAdvance() ? [] : $this->matchLines($po, $validated['items'], $this->ownQty($bill->id));
        $this->ensureUniqueInvoice($po->supplier_id, $validated['supplier_invoice_number'], $bill->id);

        DB::transaction(function () use ($bill, $validated, $po, $rows) {
            $bill->update($this->headerAttributes($validated, $po));
            if ($bill->isAdvance()) {
                $this->setAdvanceAmount($bill, (float) $validated['advance_amount']);
            } else {
                $bill->items()->delete();
                $this->syncItems($bill, $rows, $validated['charges'], $validated['pph_base'] ?? null);
            }
            $this->applyAdvance($bill);
        });

        return response()->json(['success' => true, 'message' => 'Tagihan '.$bill->bill_number.' diperbarui.', 'id' => $bill->id]);
    }

    public function destroy($id): JsonResponse
    {
        $bill = SupplierBill::findOrFail($id);
        if (! $bill->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya tagihan Draft yang bisa dihapus. Tagihan terverifikasi dibatalkan lewat Void.'], 422);
        }

        $bill->documents()->get()->each->delete();
        $bill->delete();

        return response()->json(['success' => true, 'message' => 'Tagihan dihapus.']);
    }

    /**
     * Verifikasi Finance: 3-way match dicek ulang, lalu tagihan menjadi hutang (Belum Dibayar).
     */
    public function approve($id): JsonResponse
    {
        $bill = SupplierBill::with('items')->findOrFail($id);
        if (! $bill->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Tagihan ini sudah diverifikasi atau dibatalkan.'], 422);
        }

        $po = $this->loadPurchaseOrder($bill->purchase_order_id);
        if ($bill->isAdvance()) {
            $this->checkAdvance($po, $bill->total, $bill->id);
        } else {
            if ($po->supplier?->is_pkp && $bill->ppn_amount > 0 && ! $bill->tax_invoice_number) {
                return response()->json(['success' => false, 'message' => 'Supplier PKP: isi no. faktur pajak sebelum tagihan ber-PPN diverifikasi.'], 422);
            }
            $this->matchLines($po, $bill->items->where('line_type', SupplierBillItem::TYPE_ITEM)->map(fn ($item) => [
                'purchase_order_item_id' => $item->purchase_order_item_id, 'qty' => $item->qty, 'price' => $item->price,
            ])->all(), $this->ownQty($bill->id));
        }

        DB::transaction(function () use ($bill) {
            // Uang muka bisa saja baru dibayar setelah draft dibuat: potongan dihitung ulang.
            $this->applyAdvance($bill);
            $bill->update([
                'status' => SupplierBill::STATUS_VERIFIED,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);
            $bill->refreshPaymentStatus();
        });
        $this->log($bill, 'verify_supplier_bill', 'diverifikasi');

        $bill->refresh();
        $note = $bill->advance_deduction > 0 ? ' Dipotong uang muka '.$bill->currency.' '.SupplierBill::formatMoney($bill->advance_deduction).'.' : '';

        return response()->json(['success' => true, 'message' => 'Tagihan '.$bill->bill_number.' diverifikasi'.($bill->status === SupplierBill::STATUS_PAID ? ' dan langsung lunas (tertutup uang muka).' : ' — tercatat sebagai hutang jatuh tempo '.$bill->due_date->format('d/m/Y').'.').$note]);
    }

    public function void(Request $request, $id): JsonResponse
    {
        $bill = SupplierBill::findOrFail($id);
        if (! $bill->canBeVoided()) {
            return response()->json(['success' => false, 'message' => 'Tagihan yang sudah dibayar atau sudah void tidak bisa dibatalkan.'], 422);
        }

        $validated = $request->validate(['void_reason' => 'required|string|max:1000'], ['void_reason.required' => 'Alasan void wajib diisi.']);

        $bill->update([
            'status' => SupplierBill::STATUS_VOID,
            'void_reason' => $validated['void_reason'],
            'voided_by' => Auth::id(),
            'voided_at' => now(),
        ]);
        $this->log($bill, 'void_supplier_bill', 'di-void: '.$validated['void_reason']);

        return response()->json(['success' => true, 'message' => 'Tagihan di-void. Qty-nya kembali bisa ditagih.']);
    }

    // ── Internal ──

    private function validateBill(Request $request): array
    {
        $isAdvance = $request->input('bill_type') === SupplierBill::TYPE_ADVANCE;

        $validated = $request->validate([
            'bill_type' => 'nullable|in:'.SupplierBill::TYPE_REGULAR.','.SupplierBill::TYPE_ADVANCE,
            'advance_amount' => $isAdvance ? 'required|numeric|gt:0' : 'nullable',
            'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
            'supplier_invoice_number' => 'required|string|max:100',
            'tax_invoice_number' => 'nullable|string|max:100',
            'bill_date' => 'required|date|before_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'notes' => 'nullable|string|max:2000',
            'items' => $isAdvance ? 'nullable|array' : 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'charges' => 'nullable|array',
            'charges.*.description' => 'required|string|max:255',
            'charges.*.amount' => 'required|numeric|gt:0',
            'pph_label' => 'nullable|string|max:50',
            'pph_percent' => 'nullable|numeric|min:0|max:100',
            'pph_base' => 'nullable|numeric|min:0',
            'exchange_rate' => 'nullable|numeric|gt:0',
        ], [
            'exchange_rate.gt' => 'Kurs harus lebih dari 0.',
            'charges.*.description.required' => 'Keterangan biaya tambahan wajib diisi.',
            'pph_percent.max' => 'Tarif PPh maksimal 100%.',
            'advance_amount.required' => 'Nominal uang muka wajib diisi.',
            'supplier_invoice_number.required' => 'No. invoice supplier wajib diisi.',
            'bill_date.before_or_equal' => 'Tanggal tagihan tidak boleh melebihi hari ini.',
            'due_date.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal tagihan.',
            'items.required' => 'Isi qty minimal satu baris yang ditagih.',
        ]);
        $validated['bill_type'] ??= SupplierBill::TYPE_REGULAR;
        $validated['items'] ??= [];
        $validated['charges'] = $isAdvance ? [] : ($validated['charges'] ?? []);
        if ($isAdvance || empty($validated['pph_percent'])) {
            $validated['pph_percent'] = null;
            $validated['pph_base'] = null;
        }

        return $validated;
    }

    private function headerAttributes(array $validated, PurchaseOrder $po): array
    {
        // Kurs tanggal invoice supplier = nilai buku hutang; wajib untuk tagihan valas.
        $foreign = $po->currencyCode() !== Currency::baseName();
        if ($foreign && empty($validated['exchange_rate'])) {
            abort(response()->json(['success' => false, 'message' => 'Kurs '.$po->currencyCode().' ke '.Currency::baseName().' pada tanggal invoice supplier wajib diisi.'], 422));
        }

        // Uang muka jatuh tempo saat diminta; tagihan reguler mengikuti termin.
        $term = ($validated['bill_type'] ?? SupplierBill::TYPE_REGULAR) === SupplierBill::TYPE_ADVANCE ? 0 : $this->termDays($po);
        $dueDate = $validated['due_date']
            ?? Carbon::parse($validated['bill_date'])->addDays($term)->toDateString();

        return [
            'supplier_invoice_number' => trim($validated['supplier_invoice_number']),
            'tax_invoice_number' => $validated['tax_invoice_number'] ?? null,
            'bill_date' => $validated['bill_date'],
            'due_date' => $dueDate,
            'notes' => $validated['notes'] ?? null,
            'pph_percent' => $validated['pph_percent'] ?? null,
            'pph_label' => ($validated['pph_percent'] ?? null) ? (trim((string) ($validated['pph_label'] ?? '')) ?: 'PPh 23') : null,
            'exchange_rate' => $foreign ? round((float) $validated['exchange_rate'], 6) : 1,
            'rate_estimated' => false,
        ];
    }

    /**
     * 3-way match per baris. $own = qty tagihan ini sendiri per baris PO (saat edit/verifikasi).
     *
     * @return array<int, array> baris siap simpan
     */
    private function matchLines(PurchaseOrder $po, array $items, array $own): array
    {
        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));
        $poItems = $po->items->keyBy('id');
        $rows = [];

        foreach (array_values($items) as $i => $row) {
            $item = $poItems->get((int) $row['purchase_order_item_id']);
            if (! $item) {
                $fail('Baris '.($i + 1).' bukan bagian dari PO ini.');
            }

            $label = $item->part_number ?: 'baris '.($i + 1);
            $billable = $item->billableQty() + (int) ($own[$item->id] ?? 0);
            $qty = (int) $row['qty'];
            if ($qty > $billable) {
                $fail($label.': qty ditagih ('.$qty.') melebihi yang sudah diterima dan belum ditagih ('.$billable.'). Tagih hanya barang yang sudah datang.');
            }

            $price = round((float) $row['price'], 2);
            $poPrice = (float) $item->price;
            $tolerance = $poPrice * SupplierBill::PRICE_TOLERANCE_PERCENT / 100;
            if (abs($price - $poPrice) > $tolerance + 0.005) {
                $fail($label.': harga tagihan '.SupplierBill::formatMoney($price).' berbeda dari harga PO '.SupplierBill::formatMoney($poPrice).' (toleransi '.SupplierBill::PRICE_TOLERANCE_PERCENT.'%). Konfirmasi ke supplier atau revisi PO.');
            }

            $rows[] = [
                'purchase_order_item_id' => $item->id,
                'master_product_id' => $item->master_product_id,
                'project_id' => $item->projectId(),
                'part_number' => $item->part_number,
                'description' => $item->description,
                'qty' => $qty,
                'unit' => $item->unit,
                'price' => $price,
                'amount' => round($qty * $price, 2),
            ];
        }

        return $rows;
    }

    /**
     * Baris barang + biaya tambahan (project = project barang bila semuanya satu project).
     */
    private function syncItems(SupplierBill $bill, array $rows, array $charges = [], ?float $pphBase = null): void
    {
        foreach ($rows as $i => $row) {
            $bill->items()->create($row + ['line_type' => SupplierBillItem::TYPE_ITEM, 'sort_order' => $i + 1]);
        }

        $projects = collect($rows)->pluck('project_id')->unique();
        $chargeProject = $projects->count() === 1 ? $projects->first() : null;
        foreach (array_values($charges) as $i => $charge) {
            $amount = round((float) $charge['amount'], 2);
            $bill->items()->create([
                'line_type' => SupplierBillItem::TYPE_CHARGE,
                'project_id' => $chargeProject,
                'description' => trim($charge['description']),
                'qty' => 1,
                'price' => $amount,
                'amount' => $amount,
                'sort_order' => count($rows) + $i + 1,
            ]);
        }

        $bill->recalculate($pphBase !== null ? (float) $pphBase : null);
    }

    /**
     * Batas uang muka: PO masih menunggu barang, supplier diizinkan, dan total
     * uang muka PO (selain void) <= nilai PO x maks % supplier.
     */
    private function checkAdvance(PurchaseOrder $po, float $amount, ?int $exceptBillId = null): void
    {
        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));

        if (! in_array($po->status, PurchaseOrder::RECEIVABLE_STATUSES, true)) {
            $fail('Uang muka hanya untuk PO Approved yang barangnya belum diterima penuh.');
        }
        $percent = (float) ($po->supplier?->max_advance_percent ?? 0);
        if ($percent <= 0) {
            $fail('Supplier '.$po->supplier?->name.' tidak diizinkan uang muka (batas 0%). Minta approver Supplier mengatur batasnya.');
        }

        $room = round($po->advanceLimit() - $po->advanceBilled($exceptBillId), 2);
        if ($amount > $room + 0.005) {
            $fail('Uang muka melebihi batas '.rtrim(rtrim(number_format($percent, 2), '0'), '.').'% nilai PO. Sisa yang boleh: '.$po->currencyCode().' '.SupplierBill::formatMoney(max(0, $room)).'.');
        }
    }

    private function setAdvanceAmount(SupplierBill $bill, float $amount): void
    {
        $amount = round($amount, 2);
        $bill->update(['subtotal' => $amount, 'discount_amount' => 0, 'ppn_amount' => 0, 'total' => $amount]);
    }

    /**
     * Potongan uang muka wajib pada tagihan reguler = min(saldo uang muka PO yang
     * sudah dibayar, total tagihan ini).
     */
    private function applyAdvance(SupplierBill $bill): void
    {
        if ($bill->isAdvance() || ! $bill->purchase_order_id) {
            return;
        }

        $po = PurchaseOrder::with(['supplierBills.allocations.payment', 'supplierRefunds'])->find($bill->purchase_order_id);
        $bill->refresh();
        $bill->update(['advance_deduction' => round(max(0, min($po->advanceBalance($bill->id), $bill->total - $bill->pph_amount)), 2)]);
    }

    /**
     * Kurs tagihan valas terkunci setelah Verified, kecuali kurs perkiraan (diisi
     * otomatis dari kurs master untuk data lama / Direct Purchase) — boleh
     * dikoreksi sekali menjadi kurs tanggal invoice supplier.
     */
    private function rateCorrectable(SupplierBill $bill): bool
    {
        return $bill->isForeign() && $bill->rate_estimated && ! $bill->isVoid() && ! $bill->isDraft();
    }

    /**
     * Koreksi kurs perkiraan: nilai buku & selisih kurs semua alokasi pembayaran
     * tagihan ini dihitung ulang (Rupiah yang sudah dibayar tidak berubah).
     */
    public function updateExchangeRate(Request $request, $id): JsonResponse
    {
        $bill = SupplierBill::with('allocations.payment')->findOrFail($id);
        if (! $this->rateCorrectable($bill)) {
            return response()->json(['success' => false, 'message' => 'Kurs tagihan ini sudah terkunci (kurs tanggal invoice sudah dikonfirmasi).'], 422);
        }

        $validated = $request->validate(['exchange_rate' => 'required|numeric|gt:0'], ['exchange_rate.gt' => 'Kurs harus lebih dari 0.']);

        DB::transaction(function () use ($bill, $validated) {
            $bill->update(['exchange_rate' => round((float) $validated['exchange_rate'], 6), 'rate_estimated' => false]);
            $bill->allocations->filter(fn ($a) => $a->payment?->status !== SupplierPayment::STATUS_VOID)->each(function ($allocation) use ($bill) {
                $book = $bill->toBook($allocation->amount);
                $allocation->update(['amount_base_book' => $book, 'fx_difference' => round($allocation->amount_base_paid - $book, 2)]);
            });
        });
        $this->log($bill, 'update_supplier_bill_rate', 'kurs dikoreksi menjadi '.$validated['exchange_rate']);

        return response()->json(['success' => true, 'message' => 'Kurs tagihan disimpan: 1 '.$bill->currency.' = '.Currency::baseName().' '.SupplierBill::formatMoney($bill->exchange_rate).'.']);
    }

    /**
     * Catat bukti potong PPh setelah tagihan diverifikasi.
     */
    public function updatePphSlip(Request $request, $id): JsonResponse
    {
        $bill = SupplierBill::findOrFail($id);
        if ($bill->pph_amount <= 0 || $bill->isDraft() || $bill->isVoid()) {
            return response()->json(['success' => false, 'message' => 'Bukti potong hanya untuk tagihan terverifikasi yang memotong PPh.'], 422);
        }

        $validated = $request->validate([
            'pph_slip_number' => 'required|string|max:100',
            'pph_slip_date' => 'required|date|before_or_equal:today',
        ], ['pph_slip_number.required' => 'No. bukti potong wajib diisi.']);

        $bill->update($validated);
        $this->log($bill, 'pph_slip_supplier_bill', 'bukti potong '.$bill->pph_label.' '.$validated['pph_slip_number'].' dicatat');

        return response()->json(['success' => true, 'message' => 'Bukti potong '.$validated['pph_slip_number'].' dicatat.']);
    }

    private function ensureUniqueInvoice(int $supplierId, string $invoiceNumber, ?int $exceptId = null): void
    {
        $exists = SupplierBill::where('supplier_id', $supplierId)
            ->where('supplier_invoice_number', trim($invoiceNumber))
            ->where('status', '!=', SupplierBill::STATUS_VOID)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->first();

        if ($exists) {
            abort(response()->json(['success' => false, 'message' => 'No. invoice supplier '.$invoiceNumber.' sudah tercatat di tagihan '.$exists->bill_number.'. Satu invoice supplier hanya boleh ditagih sekali.'], 422));
        }
    }

    /**
     * @return array<int, int> [purchase_order_item_id => qty] milik tagihan ini
     */
    private function ownQty(?int $billId): array
    {
        if (! $billId) {
            return [];
        }

        return SupplierBill::find($billId)?->items->groupBy('purchase_order_item_id')->map->sum('qty')->all() ?? [];
    }

    private function loadPurchaseOrder(int $id): PurchaseOrder
    {
        $po = PurchaseOrder::with([
            'supplier',
            'items.goodsReceiptItems.goodsReceipt',
            'items.billItems.bill',
            'items.goodsRequestItem.goodsRequest.project',
            'supplierBills.allocations.payment',
            'supplierRefunds',
        ])->findOrFail($id);

        if (! in_array($po->status, self::BILLABLE_PO_STATUSES, true)) {
            abort(response()->json(['success' => false, 'message' => 'PO harus sudah Approved untuk bisa ditagih.'], 422));
        }
        if (! $po->supplier_id) {
            abort(response()->json(['success' => false, 'message' => 'PO belum tertaut ke master Supplier. Tautkan supplier di PO dulu.'], 422));
        }

        return $po;
    }

    private function billableRows(PurchaseOrder $po, array $own)
    {
        return $po->items->map(fn (PurchaseOrderItem $item) => [
            'purchase_order_item_id' => $item->id,
            'part_number' => $item->part_number,
            'description' => Quotation::renderDescription($item->description),
            'unit' => $item->unit,
            'price' => (float) $item->price,
            'ordered' => (int) $item->qty,
            'received' => $item->receivedQty(),
            'billed' => $item->billedQty() - (int) ($own[$item->id] ?? 0),
            'billable' => $item->billableQty() + (int) ($own[$item->id] ?? 0),
            'project_label' => ($request = $item->goodsRequest()) && $request->isForProject()
                ? ($request->project?->project_number.' — '.$request->project?->project_name)
                : 'Stok / Internal',
        ]);
    }

    /**
     * Pilihan PO di form beserta kelayakannya per tipe tagihan:
     *  - regular: masih ada qty diterima yang belum ditagih;
     *  - advance: PO Approved / Diterima Sebagian; 'ok' bila batas uang muka
     *    supplier > 0% dan masih ada sisa, 'no_limit' bila batas supplier 0%,
     *    'full' bila batas sudah habis diminta.
     * Form menampilkan PO yang tidak layak sebagai pilihan nonaktif beserta alasannya.
     */
    private function purchaseOrderOptions(?int $includeId = null): array
    {
        return PurchaseOrder::with(['items.goodsReceiptItems.goodsReceipt', 'items.billItems.bill', 'supplier', 'supplierBills'])
            ->whereIn('status', self::BILLABLE_PO_STATUSES)
            ->whereNotNull('supplier_id')
            ->orderByDesc('id')
            ->get()
            ->map(function (PurchaseOrder $po) use ($includeId) {
                $advance = null;
                if (in_array($po->status, PurchaseOrder::RECEIVABLE_STATUSES, true)) {
                    $advance = match (true) {
                        ($po->supplier?->max_advance_percent ?? 0) <= 0 => 'no_limit',
                        $po->advanceLimit() - $po->advanceBilled() <= 0.005 => 'full',
                        default => 'ok',
                    };
                }

                return [
                    'id' => $po->id,
                    'label' => $po->po_number.' — '.($po->supplier?->name ?? $po->supplier_name).' ('.$po->status_label.')',
                    'supplier_id' => $po->supplier_id,
                    'regular' => $po->id === $includeId || $po->items->contains(fn (PurchaseOrderItem $item) => $item->billableQty() > 0),
                    'advance' => $po->id === $includeId ? 'ok' : $advance,
                ];
            })
            ->filter(fn (array $option) => $option['regular'] || $option['advance'] !== null)
            ->values()
            ->all();
    }

    private function termDays(PurchaseOrder $po): int
    {
        return (int) ($po->payment_term_days ?? $po->supplier?->payment_term_days ?? 0);
    }

    private function log(SupplierBill $bill, string $action, string $what): void
    {
        Log::record($action, 'Tagihan supplier '.$bill->bill_number.' ('.$bill->supplier_invoice_number.') '.$what.' oleh '.Auth::user()->display_name, 'MOD_SUPPLIER_BILL', $bill);
    }

    protected function documentOwner($id): Model
    {
        return SupplierBill::findOrFail($id);
    }

    protected function documentRoutePrefix(): string
    {
        return 'supplier-bill';
    }

    protected function documentModuleCode(): string
    {
        return 'MOD_SUPPLIER_BILL';
    }
}

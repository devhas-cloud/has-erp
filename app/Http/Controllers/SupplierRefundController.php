<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPurchaseDocuments;
use App\Models\Currency;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\SupplierRefund;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Refund Supplier (grup Finance, Tahap 3) — uang muka PO yang tidak terpakai
 * (PO batal / semua barang sudah ditagih) dikembalikan supplier. Daftar
 * "Menunggu Refund" dihitung dari saldo uang muka PO (PurchaseOrder::refundDue).
 * Draft dicatat staf Finance, diposting pemegang can_approve (boleh pembuatnya)
 * (supplier-refund.approve -> can_approve); void beralasan (supplier-refund.void).
 */
class SupplierRefundController extends Controller
{
    use ManagesPurchaseDocuments;

    public function index()
    {
        return view('supplier-refund.index', [
            'pending' => $this->pendingRefunds(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = SupplierRefund::with(['supplier', 'purchaseOrder']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('refund_number', 'like', "%{$searchValue}%")
                    ->orWhere('reference_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('purchaseOrder', fn ($po) => $po->where('po_number', 'like', "%{$searchValue}%"));
            });
        }

        $query->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('refund_date', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('refund_date', '<=', $to));

        $statusCounts = (clone $query)->reorder()->toBase()
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsFiltered = $query->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $data = $query->orderByDesc('id')->offset($start)->limit($length)->get()
            ->map(fn (SupplierRefund $refund, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $refund->id,
                'refund_number' => $refund->refund_number,
                'supplier_name' => $refund->supplier?->name ?? '—',
                'po_number' => $refund->purchaseOrder?->po_number ?? '—',
                'refund_date' => $refund->refund_date?->format('d/m/Y'),
                'reference_number' => $refund->reference_number ?: '—',
                'amount_label' => $refund->currency.' '.SupplierBill::formatMoney($refund->amount),
                'status' => $refund->status,
                'status_badge' => $refund->statusBadgeHtml(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => collect(SupplierRefund::STATUS_LABELS)->map(fn ($label, $key) => (int) ($statusCounts[$key] ?? 0)),
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        return view('supplier-refund.form', [
            'pending' => $this->pendingRefunds(),
            'preselectPoId' => $request->integer('purchase_order_id') ?: null,
            'baseCurrency' => Currency::baseName(),
            'rates' => collect(Currency::formOptions())->pluck('rate', 'name'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
            'refund_date' => 'required|date|before_or_equal:today',
            'method' => 'required|in:'.implode(',', array_keys(SupplierPayment::METHOD_LABELS)),
            'destination_account' => 'nullable|string|max:150',
            'reference_number' => 'required_unless:method,cash|nullable|string|max:100',
            'amount' => 'required|numeric|gt:0',
            'exchange_rate' => 'nullable|numeric|gt:0',
            'notes' => 'nullable|string|max:2000',
        ], [
            'refund_date.before_or_equal' => 'Tanggal refund tidak boleh melebihi hari ini.',
            'reference_number.required_unless' => 'No. referensi wajib diisi untuk transfer / giro.',
        ]);

        $po = $this->loadPurchaseOrder((int) $validated['purchase_order_id']);
        $amount = round((float) $validated['amount'], 2);
        $room = $this->refundRoom($po);
        if ($amount > $room + 0.005) {
            return response()->json(['success' => false, 'message' => 'Nominal refund melebihi saldo uang muka yang harus dikembalikan ('.$po->currencyCode().' '.SupplierBill::formatMoney($room).').'], 422);
        }

        $currency = $po->currencyCode();
        $isBase = $currency === Currency::baseName();
        if (! $isBase && empty($validated['exchange_rate'])) {
            return response()->json(['success' => false, 'message' => 'Kurs '.$currency.' wajib diisi untuk refund valas.'], 422);
        }
        $rate = $isBase ? 1.0 : (float) $validated['exchange_rate'];

        $refund = SupplierRefund::create([
            'refund_number' => SupplierRefund::generateNumber($validated['refund_date']),
            'supplier_id' => $po->supplier_id,
            'purchase_order_id' => $po->id,
            'refund_date' => $validated['refund_date'],
            'method' => $validated['method'],
            'destination_account' => $validated['destination_account'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'currency' => $currency,
            'amount' => $amount,
            'exchange_rate' => $rate,
            'amount_base' => round($amount * $rate, 2),
        ] + $this->fxAttributes($po, $amount, round($amount * $rate, 2)) + [
            'status' => SupplierRefund::STATUS_DRAFT,
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->log($refund, 'create_supplier_refund', 'dibuat (draft)');

        return response()->json(['success' => true, 'message' => 'Refund '.$refund->refund_number.' disimpan sebagai draft.', 'id' => $refund->id]);
    }

    public function show($id)
    {
        $refund = SupplierRefund::with(['supplier', 'purchaseOrder', 'creator', 'poster', 'voider'])->findOrFail($id);

        return view('supplier-refund.show', [
            'refund' => $refund,
            'documents' => $this->documentList($refund),
            'baseCurrency' => Currency::baseName(),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $refund = SupplierRefund::findOrFail($id);
        if (! $refund->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya refund Draft yang bisa dihapus. Refund Posted dibatalkan lewat Void.'], 422);
        }

        $refund->documents()->get()->each->delete();
        $refund->delete();

        return response()->json(['success' => true, 'message' => 'Draft refund dihapus.']);
    }

    public function approve($id): JsonResponse
    {
        $refund = SupplierRefund::findOrFail($id);
        if (! $refund->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Refund ini sudah diposting atau dibatalkan.'], 422);
        }

        $po = $this->loadPurchaseOrder($refund->purchase_order_id);
        if ($refund->amount > $po->refundDue() + 0.005) {
            return response()->json(['success' => false, 'message' => 'Nominal refund melebihi saldo uang muka yang harus dikembalikan ('.$refund->currency.' '.SupplierBill::formatMoney($po->refundDue()).').'], 422);
        }

        $refund->update($this->fxAttributes($po, $refund->amount, $refund->amount_base) + ['status' => SupplierRefund::STATUS_POSTED, 'posted_by' => Auth::id(), 'posted_at' => now()]);
        $this->log($refund, 'post_supplier_refund', 'diposting');

        return response()->json(['success' => true, 'message' => 'Refund '.$refund->refund_number.' diposting — saldo uang muka PO berkurang.']);
    }

    public function void(Request $request, $id): JsonResponse
    {
        $refund = SupplierRefund::findOrFail($id);
        if ($refund->status !== SupplierRefund::STATUS_POSTED) {
            return response()->json(['success' => false, 'message' => 'Hanya refund Posted yang bisa di-void. Draft cukup dihapus.'], 422);
        }

        $validated = $request->validate(['void_reason' => 'required|string|max:1000'], ['void_reason.required' => 'Alasan void wajib diisi.']);

        $refund->update(['status' => SupplierRefund::STATUS_VOID, 'void_reason' => $validated['void_reason'], 'voided_by' => Auth::id(), 'voided_at' => now()]);
        $this->log($refund, 'void_supplier_refund', 'di-void: '.$validated['void_reason']);

        return response()->json(['success' => true, 'message' => 'Refund di-void. PO kembali berstatus Menunggu Refund.']);
    }

    /**
     * Uang muka tercatat dengan kurs tagihan uang muka (nilai buku); refund diterima
     * dengan kurs saat refund. Selisih = buku - diterima (positif = rugi kurs).
     */
    private function fxAttributes(PurchaseOrder $po, float $amount, float $received): array
    {
        $book = round($amount * $po->advanceRate(), 2);

        return ['amount_base_book' => $book, 'fx_difference' => round($book - $received, 2)];
    }

    /**
     * Sisa yang boleh dicatat = refund jatuh tempo - draft refund lain yang belum diposting.
     */
    private function refundRoom(PurchaseOrder $po): float
    {
        $drafts = $po->supplierRefunds->where('status', SupplierRefund::STATUS_DRAFT)->sum('amount');

        return round(max(0, $po->refundDue() - $drafts), 2);
    }

    private function loadPurchaseOrder(int $id): PurchaseOrder
    {
        return PurchaseOrder::with(['supplier', 'items.goodsReceiptItems.goodsReceipt', 'items.billItems.bill', 'supplierBills.allocations.payment', 'supplierRefunds'])->findOrFail($id);
    }

    /**
     * PO yang uang mukanya harus dikembalikan supplier.
     */
    private function pendingRefunds(): Collection
    {
        return PurchaseOrder::with(['supplier', 'items.goodsReceiptItems.goodsReceipt', 'items.billItems.bill', 'supplierBills.allocations.payment', 'supplierRefunds'])
            ->whereHas('supplierBills', fn ($q) => $q->where('bill_type', SupplierBill::TYPE_ADVANCE)->where('status', '!=', SupplierBill::STATUS_VOID))
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PurchaseOrder $po) => $po->refundDue() > 0)
            ->values();
    }

    private function log(SupplierRefund $refund, string $action, string $what): void
    {
        Log::record($action, 'Refund supplier '.$refund->refund_number.' ('.$refund->currency.' '.SupplierBill::formatMoney($refund->amount).') '.$what.' oleh '.Auth::user()->display_name, 'MOD_SUPPLIER_REFUND', $refund);
    }

    protected function documentOwner($id): Model
    {
        return SupplierRefund::findOrFail($id);
    }

    protected function documentRoutePrefix(): string
    {
        return 'supplier-refund';
    }

    protected function documentModuleCode(): string
    {
        return 'MOD_SUPPLIER_REFUND';
    }
}

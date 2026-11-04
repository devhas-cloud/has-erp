<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPurchaseDocuments;
use App\Models\Currency;
use App\Models\Log;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran Supplier (grup Finance, Tahap 2). Satu pembayaran melunasi satu
 * atau banyak tagihan terverifikasi dari supplier yang sama, boleh sebagian;
 * satu mata uang per pembayaran, kurs dicatat untuk nilai Rupiah.
 * Draft dicatat staf Finance, diposting approver Finance
 * (supplier-payment.approve -> can_approve; pembuat boleh memposting bila
 * punya can_approve). Void (supplier-payment.void) membuka kembali sisa tagihan.
 */
class SupplierPaymentController extends Controller
{
    use ManagesPurchaseDocuments;

    public function index()
    {
        return view('supplier-payment.index', [
            'suppliers' => Supplier::whereIn('id', SupplierPayment::select('supplier_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = SupplierPayment::with('supplier')->withCount('allocations');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('payment_number', 'like', "%{$searchValue}%")
                    ->orWhere('reference_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('allocations.bill', fn ($b) => $b->where('bill_number', 'like', "%{$searchValue}%")->orWhere('supplier_invoice_number', 'like', "%{$searchValue}%"));
            });
        }

        $query->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->input('method'), fn ($q, $method) => $q->where('method', $method))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('payment_date', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('payment_date', '<=', $to))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $statusCounts = (clone $query)->reorder()->toBase()
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $payments = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = $payments->map(fn (SupplierPayment $payment, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'supplier_name' => $payment->supplier?->name ?? '—',
            'payment_date' => $payment->payment_date?->format('d/m/Y'),
            'method_label' => $payment->method_label,
            'reference_number' => $payment->reference_number ?: '—',
            'bill_count' => $payment->allocations_count,
            'amount_label' => $payment->currency.' '.SupplierBill::formatMoney($payment->amount),
            'status' => $payment->status,
            'status_badge' => $payment->statusBadgeHtml(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => collect(SupplierPayment::STATUS_LABELS)->map(fn ($label, $key) => (int) ($statusCounts[$key] ?? 0)),
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $bill = $request->integer('bill_id') ? SupplierBill::find($request->integer('bill_id')) : null;

        return view('supplier-payment.form', [
            'payment' => null,
            'suppliers' => $this->suppliersWithOpenBills(),
            'preselectSupplierId' => $bill?->supplier_id ?? ($request->integer('supplier_id') ?: null),
            'preselectBillId' => $bill?->id,
            'allocations' => [],
            'baseCurrency' => Currency::baseName(),
        ]);
    }

    /**
     * Tagihan terbuka supplier (sisa > 0) + rekening tujuan — dipanggil form saat supplier dipilih.
     */
    public function openBills(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'payment_id' => 'nullable|integer',
        ]);
        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $own = $this->ownAllocations($validated['payment_id'] ?? null);

        $bills = $this->openBillsFor($supplier->id, array_keys($own))
            ->map(fn (SupplierBill $bill) => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'supplier_invoice_number' => $bill->supplier_invoice_number,
                'source' => $bill->sourceNumber(),
                'due_date' => $bill->due_date?->format('d/m/Y'),
                'is_overdue' => $bill->isOverdue(),
                'currency' => $bill->currency,
                'total' => $bill->total,
                'outstanding' => $bill->outstanding(),
                'exchange_rate' => $bill->exchange_rate,
                'in_other_drafts' => $this->amountInOtherDrafts($bill, $validated['payment_id'] ?? null),
            ])->values();

        return response()->json([
            'success' => true,
            'supplier' => [
                'name' => $supplier->name,
                'bank_name' => $supplier->bank_name,
                'bank_account_no' => $supplier->bank_account_no,
                'bank_account_name' => $supplier->bank_account_name,
            ],
            'bills' => $bills,
            'rates' => Currency::formOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayment($request);
        [$currency, $rows] = $this->matchAllocations($validated, []);

        $payment = DB::transaction(function () use ($validated, $currency, $rows) {
            $supplier = Supplier::findOrFail($validated['supplier_id']);
            $header = $this->headerAttributes($validated, $currency, $rows);
            $payment = SupplierPayment::create($header + [
                'payment_number' => SupplierPayment::generateNumber($validated['payment_date']),
                'supplier_id' => $supplier->id,
                'bank_name' => $supplier->bank_name,
                'bank_account_no' => $supplier->bank_account_no,
                'bank_account_name' => $supplier->bank_account_name,
                'status' => SupplierPayment::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);
            $payment->allocations()->createMany($this->withPaidBase($rows, $header));

            return $payment;
        });

        $this->log($payment, 'create_supplier_payment', 'dibuat (draft)');

        return response()->json(['success' => true, 'message' => 'Pembayaran '.$payment->payment_number.' disimpan sebagai draft.', 'id' => $payment->id]);
    }

    public function show($id)
    {
        $payment = SupplierPayment::with(['supplier', 'allocations.bill.purchaseOrder', 'allocations.bill.directPurchase', 'creator', 'poster', 'voider'])->findOrFail($id);

        return view('supplier-payment.show', [
            'payment' => $payment,
            'documents' => $this->documentList($payment),
            'baseCurrency' => Currency::baseName(),
        ]);
    }

    public function edit($id)
    {
        $payment = SupplierPayment::with('allocations')->findOrFail($id);
        abort_unless($payment->isDraft(), 403, 'Hanya pembayaran Draft yang bisa diubah.');

        return view('supplier-payment.form', [
            'payment' => $payment,
            'suppliers' => $this->suppliersWithOpenBills($payment->supplier_id),
            'preselectSupplierId' => $payment->supplier_id,
            'preselectBillId' => null,
            'allocations' => $payment->allocations->map(fn ($a) => ['supplier_bill_id' => $a->supplier_bill_id, 'amount' => $a->amount])->values(),
            'baseCurrency' => Currency::baseName(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $payment = SupplierPayment::findOrFail($id);
        if (! $payment->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya pembayaran Draft yang bisa diubah.'], 422);
        }

        $validated = $this->validatePayment($request);
        if ((int) $validated['supplier_id'] !== (int) $payment->supplier_id) {
            return response()->json(['success' => false, 'message' => 'Supplier pembayaran tidak bisa diganti. Hapus draft ini dan buat pembayaran baru.'], 422);
        }
        [$currency, $rows] = $this->matchAllocations($validated, $this->ownAllocations($payment->id));

        DB::transaction(function () use ($payment, $validated, $currency, $rows) {
            $header = $this->headerAttributes($validated, $currency, $rows);
            $payment->update($header);
            $payment->allocations()->delete();
            $payment->allocations()->createMany($this->withPaidBase($rows, $header));
        });

        return response()->json(['success' => true, 'message' => 'Pembayaran '.$payment->payment_number.' diperbarui.', 'id' => $payment->id]);
    }

    public function destroy($id): JsonResponse
    {
        $payment = SupplierPayment::findOrFail($id);
        if (! $payment->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya pembayaran Draft yang bisa dihapus. Pembayaran Posted dibatalkan lewat Void.'], 422);
        }

        $payment->documents()->get()->each->delete();
        $payment->delete();

        return response()->json(['success' => true, 'message' => 'Draft pembayaran dihapus.']);
    }

    /**
     * Posting: cek ulang sisa tiap tagihan, lalu hutang berkurang & status tagihan diperbarui.
     */
    public function approve($id): JsonResponse
    {
        $payment = SupplierPayment::with('allocations.bill')->findOrFail($id);
        if (! $payment->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Pembayaran ini sudah diposting atau dibatalkan.'], 422);
        }

        DB::transaction(function () use ($payment) {
            foreach ($payment->allocations as $allocation) {
                $bill = SupplierBill::lockForUpdate()->findOrFail($allocation->supplier_bill_id);
                if (! in_array($bill->status, SupplierBill::OPEN_STATUSES, true)) {
                    abort(response()->json(['success' => false, 'message' => 'Tagihan '.$bill->bill_number.' sudah tidak terbuka ('.$bill->status_label.').'], 422));
                }
                if ($allocation->amount > $bill->outstanding() + 0.005) {
                    abort(response()->json(['success' => false, 'message' => 'Pembayaran untuk '.$bill->bill_number.' melebihi sisa hutang ('.$bill->currency.' '.SupplierBill::formatMoney($bill->outstanding()).'). Edit draft ini.'], 422));
                }
            }

            $payment->update(['status' => SupplierPayment::STATUS_POSTED, 'posted_by' => Auth::id(), 'posted_at' => now()]);
            $payment->allocations->each(fn ($allocation) => $allocation->bill->refreshPaymentStatus());
        });

        $this->log($payment, 'post_supplier_payment', 'diposting');

        return response()->json(['success' => true, 'message' => 'Pembayaran '.$payment->payment_number.' diposting — hutang '.$payment->allocations->count().' tagihan berkurang.']);
    }

    public function void(Request $request, $id): JsonResponse
    {
        $payment = SupplierPayment::with('allocations.bill')->findOrFail($id);
        if (! $payment->isPosted()) {
            return response()->json(['success' => false, 'message' => 'Hanya pembayaran Posted yang bisa di-void. Draft cukup dihapus.'], 422);
        }

        $validated = $request->validate(['void_reason' => 'required|string|max:1000'], ['void_reason.required' => 'Alasan void wajib diisi.']);

        DB::transaction(function () use ($payment, $validated) {
            $payment->update([
                'status' => SupplierPayment::STATUS_VOID,
                'void_reason' => $validated['void_reason'],
                'voided_by' => Auth::id(),
                'voided_at' => now(),
            ]);
            $payment->allocations->each(fn ($allocation) => $allocation->bill->refreshPaymentStatus());
        });

        $this->log($payment, 'void_supplier_payment', 'di-void: '.$validated['void_reason']);

        return response()->json(['success' => true, 'message' => 'Pembayaran di-void. Sisa hutang tagihan terkait dibuka kembali.']);
    }

    // ── Internal ──

    private function validatePayment(Request $request): array
    {
        return $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'method' => 'required|in:'.implode(',', array_keys(SupplierPayment::METHOD_LABELS)),
            'source_account' => 'nullable|string|max:150',
            'reference_number' => 'required_unless:method,cash|nullable|string|max:100',
            'exchange_rate' => 'nullable|numeric|gt:0',
            'debit_amount_base' => 'nullable|numeric|gt:0',
            'bank_charge' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'allocations' => 'required|array|min:1',
            'allocations.*.supplier_bill_id' => 'required|integer|distinct',
            'allocations.*.amount' => 'required|numeric|gt:0',
        ], [
            'payment_date.before_or_equal' => 'Tanggal bayar tidak boleh melebihi hari ini.',
            'reference_number.required_unless' => 'No. referensi wajib diisi untuk transfer / giro.',
            'allocations.required' => 'Pilih minimal satu tagihan yang dibayar.',
            'allocations.*.amount.gt' => 'Nominal pembayaran per tagihan harus lebih dari 0.',
        ]);
    }

    /**
     * Validasi alokasi: tagihan milik supplier ini, terbuka, satu mata uang,
     * nominal <= sisa (+ alokasi draft ini sendiri saat edit).
     *
     * @return array{0: string, 1: array}
     */
    private function matchAllocations(array $validated, array $own): array
    {
        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));
        $bills = SupplierBill::with('allocations.payment')
            ->whereIn('id', collect($validated['allocations'])->pluck('supplier_bill_id'))
            ->get()->keyBy('id');

        $rows = [];
        $currency = null;
        foreach ($validated['allocations'] as $i => $row) {
            $bill = $bills->get((int) $row['supplier_bill_id']);
            if (! $bill || (int) $bill->supplier_id !== (int) $validated['supplier_id']) {
                $fail('Baris '.($i + 1).': tagihan bukan milik supplier ini.');
            }
            if (! in_array($bill->status, SupplierBill::OPEN_STATUSES, true)) {
                $fail('Tagihan '.$bill->bill_number.' belum diverifikasi atau sudah lunas/void.');
            }
            $currency ??= $bill->currency;
            if ($bill->currency !== $currency) {
                $fail('Satu pembayaran hanya untuk satu mata uang. Pisahkan tagihan '.$bill->currency.' ke pembayaran lain.');
            }

            $amount = round((float) $row['amount'], 2);
            $max = $bill->outstanding();
            if ($amount > $max + 0.005) {
                $fail('Pembayaran '.$bill->bill_number.' ('.SupplierBill::formatMoney($amount).') melebihi sisa hutang '.$bill->currency.' '.SupplierBill::formatMoney($max).'.');
            }

            // Nilai buku = nominal x kurs tagihan; nilai bayar & selisih kurs diisi withPaidBase().
            $rows[] = ['supplier_bill_id' => $bill->id, 'amount' => $amount, 'amount_base_book' => $bill->toBook($amount)];
        }

        if ($currency !== Currency::baseName() && empty($validated['exchange_rate']) && empty($validated['debit_amount_base'])) {
            $fail('Isi Rupiah yang didebit bank atau kurs '.$currency.' ke '.Currency::baseName().' untuk pembayaran valas.');
        }

        return [$currency, $rows];
    }

    /**
     * Kurs bayar valas: bila Rupiah yang didebit bank diisi, kurs = Rupiah / nominal
     * (kurs konversi bank sesungguhnya); selain itu kurs yang diisi.
     */
    private function headerAttributes(array $validated, string $currency, array $rows): array
    {
        $amount = round(collect($rows)->sum('amount'), 2);
        $isBase = $currency === Currency::baseName();
        $debit = $isBase ? null : ($validated['debit_amount_base'] ?? null);
        $rate = $isBase ? 1.0 : ($debit ? round((float) $debit / $amount, 6) : (float) $validated['exchange_rate']);

        return [
            'payment_date' => $validated['payment_date'],
            'method' => $validated['method'],
            'source_account' => $validated['source_account'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'currency' => $currency,
            'amount' => $amount,
            'exchange_rate' => $rate,
            'amount_base' => $debit ? round((float) $debit, 2) : round($amount * $rate, 2),
            'bank_charge' => round((float) ($validated['bank_charge'] ?? 0), 2),
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * Nilai bayar per tagihan = nominal x kurs bayar (selisih pembulatan ke baris
     * terakhir agar total = Rupiah pembayaran); selisih kurs = bayar - buku (positif = rugi).
     */
    private function withPaidBase(array $rows, array $header): array
    {
        $remaining = (float) $header['amount_base'];
        $last = count($rows) - 1;

        foreach ($rows as $i => $row) {
            $paid = $i === $last ? round($remaining, 2) : round($row['amount'] * $header['exchange_rate'], 2);
            $remaining -= $paid;
            $rows[$i]['amount_base_paid'] = $paid;
            $rows[$i]['fx_difference'] = round($paid - $row['amount_base_book'], 2);
        }

        return $rows;
    }

    /**
     * @return array<int, float> [supplier_bill_id => amount] milik draft ini
     */
    private function ownAllocations(?int $paymentId): array
    {
        if (! $paymentId) {
            return [];
        }

        return SupplierPayment::find($paymentId)?->allocations->pluck('amount', 'supplier_bill_id')->all() ?? [];
    }

    private function openBillsFor(int $supplierId, array $includeIds = []): Collection
    {
        return SupplierBill::with(['allocations.payment', 'purchaseOrder', 'directPurchase'])
            ->where('supplier_id', $supplierId)
            ->where(fn ($q) => $q->whereIn('status', SupplierBill::OPEN_STATUSES)->orWhereIn('id', $includeIds))
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Nominal tagihan ini yang sedang dialokasikan di draft pembayaran lain (info agar tidak dobel bayar).
     */
    private function amountInOtherDrafts(SupplierBill $bill, ?int $exceptPaymentId): float
    {
        return round((float) $bill->allocations
            ->filter(fn ($a) => $a->payment?->status === SupplierPayment::STATUS_DRAFT && (int) $a->supplier_payment_id !== (int) $exceptPaymentId)
            ->sum('amount'), 2);
    }

    private function suppliersWithOpenBills(?int $includeId = null): Collection
    {
        return Supplier::where(fn ($q) => $q->whereIn('id', SupplierBill::whereIn('status', SupplierBill::OPEN_STATUSES)->select('supplier_id'))
            ->when($includeId, fn ($q) => $q->orWhere('id', $includeId)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function log(SupplierPayment $payment, string $action, string $what): void
    {
        Log::record($action, 'Pembayaran supplier '.$payment->payment_number.' ('.$payment->currency.' '.SupplierBill::formatMoney($payment->amount).') '.$what.' oleh '.Auth::user()->display_name, 'MOD_SUPPLIER_PAYMENT', $payment);
    }

    protected function documentOwner($id): Model
    {
        return SupplierPayment::findOrFail($id);
    }

    protected function documentRoutePrefix(): string
    {
        return 'supplier-payment';
    }

    protected function documentModuleCode(): string
    {
        return 'MOD_SUPPLIER_PAYMENT';
    }
}

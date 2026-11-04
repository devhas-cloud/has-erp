<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesPurchaseDocuments;
use App\Models\AccountCompany;
use App\Models\CustomerReceipt;
use App\Models\Invoice;
use App\Models\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Penerimaan Customer (grup Finance, piutang Tahap B). Satu penerimaan untuk
 * satu invoice Approved yang belum dibayar; customer selalu membayar pas,
 * jadi nominal mengikuti total invoice. Draft dicatat staf Finance, diposting
 * approver Finance (customer-receipt.approve -> can_approve; pembuat boleh
 * memposting bila punya can_approve) — invoice menjadi Paid. Void
 * (customer-receipt.void) mengembalikan invoice menjadi Unpaid.
 */
class CustomerReceiptController extends Controller
{
    use ManagesPurchaseDocuments;

    public function index()
    {
        return view('customer-receipt.index', [
            'companies' => AccountCompany::whereIn('id', CustomerReceipt::select('company_id'))->orderBy('account_name')->get(['id', 'account_name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = CustomerReceipt::with(['company', 'invoice.project']);

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('receipt_number', 'like', "%{$searchValue}%")
                    ->orWhere('reference_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%"))
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$searchValue}%"))
                    ->orWhereHas('invoice.project', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")->orWhere('project_name', 'like', "%{$searchValue}%"));
            });
        }

        $query->when($request->integer('company_id'), fn ($q, $id) => $q->where('company_id', $id))
            ->when($request->input('method'), fn ($q, $method) => $q->where('method', $method))
            ->when($request->input('date_from'), fn ($q, $from) => $q->whereDate('receipt_date', '>=', $from))
            ->when($request->input('date_to'), fn ($q, $to) => $q->whereDate('receipt_date', '<=', $to))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', Auth::id()));

        $statusCounts = (clone $query)->reorder()->toBase()
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $receipts = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = $receipts->map(fn (CustomerReceipt $receipt, $i) => [
            'DT_RowIndex' => $start + $i + 1,
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'company_name' => $receipt->company?->account_name ?? '—',
            'invoice_number' => $receipt->invoice?->invoice_number ?? '—',
            'invoice_id' => $receipt->invoice_id,
            'project_label' => $receipt->invoice?->project ? $receipt->invoice->project->project_number.' — '.$receipt->invoice->project->project_name : '—',
            'receipt_date' => $receipt->receipt_date?->format('d/m/Y'),
            'method_label' => $receipt->method_label,
            'reference_number' => $receipt->reference_number ?: '—',
            'amount_label' => Invoice::formatMoney($receipt->amount),
            'status' => $receipt->status,
            'status_badge' => $receipt->statusBadgeHtml(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status_counts' => collect(CustomerReceipt::STATUS_LABELS)->map(fn ($label, $key) => (int) ($statusCounts[$key] ?? 0)),
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $invoice = $request->integer('invoice_id') ? Invoice::find($request->integer('invoice_id')) : null;

        return view('customer-receipt.form', [
            'receipt' => null,
            'preselectInvoice' => $invoice?->awaitsReceipt() ? $this->invoiceOption($invoice) : null,
            'depositAccounts' => $this->depositAccounts(),
        ]);
    }

    /**
     * Invoice Approved & Unpaid tanpa penerimaan aktif — pilihan select2 di form.
     */
    public function openInvoices(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        $invoices = Invoice::with(['project.company', 'billingTerm'])
            ->where('approval_status', Invoice::APPROVAL_APPROVED)
            ->where('status', Invoice::STATUS_UNPAID)
            ->whereDoesntHave('receipts', fn ($r) => $r->whereIn('status', CustomerReceipt::ACTIVE_STATUSES))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('invoice_number', 'like', "%{$q}%")
                    ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$q}%")->orWhere('project_name', 'like', "%{$q}%"))
                    ->orWhereHas('project.company', fn ($c) => $c->where('account_name', 'like', "%{$q}%"));
            }))
            ->orderBy('due_date')
            ->limit(30)
            ->get();

        return response()->json([
            'results' => $invoices->map(fn (Invoice $invoice) => $this->invoiceOption($invoice))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateReceipt($request);
        $invoice = $this->receivableInvoice((int) $validated['invoice_id'], null);

        $receipt = DB::transaction(fn () => CustomerReceipt::create($this->attributes($validated, $invoice) + [
            'receipt_number' => CustomerReceipt::generateNumber($validated['receipt_date']),
            'invoice_id' => $invoice->id,
            'status' => CustomerReceipt::STATUS_DRAFT,
            'created_by' => Auth::id(),
        ]));

        $this->log($receipt, 'create_customer_receipt', 'dibuat (draft)');

        return response()->json(['success' => true, 'message' => 'Penerimaan '.$receipt->receipt_number.' disimpan sebagai draft.', 'id' => $receipt->id]);
    }

    public function show($id)
    {
        $receipt = CustomerReceipt::with(['company', 'invoice.project', 'invoice.billingTerm', 'creator', 'poster', 'voider'])->findOrFail($id);

        return view('customer-receipt.show', [
            'receipt' => $receipt,
            'documents' => $this->documentList($receipt),
        ]);
    }

    public function edit($id)
    {
        $receipt = CustomerReceipt::with('invoice.project.company', 'invoice.billingTerm')->findOrFail($id);
        abort_unless($receipt->isDraft(), 403, 'Hanya penerimaan Draft yang bisa diubah.');

        return view('customer-receipt.form', [
            'receipt' => $receipt,
            'preselectInvoice' => $this->invoiceOption($receipt->invoice),
            'depositAccounts' => $this->depositAccounts(),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $receipt = CustomerReceipt::findOrFail($id);
        if (! $receipt->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya penerimaan Draft yang bisa diubah.'], 422);
        }

        $validated = $this->validateReceipt($request);
        if ((int) $validated['invoice_id'] !== (int) $receipt->invoice_id) {
            return response()->json(['success' => false, 'message' => 'Invoice penerimaan tidak bisa diganti. Hapus draft ini dan buat penerimaan baru.'], 422);
        }
        $invoice = $this->receivableInvoice($receipt->invoice_id, $receipt->id);

        $receipt->update($this->attributes($validated, $invoice));

        return response()->json(['success' => true, 'message' => 'Penerimaan '.$receipt->receipt_number.' diperbarui.', 'id' => $receipt->id]);
    }

    public function destroy($id): JsonResponse
    {
        $receipt = CustomerReceipt::findOrFail($id);
        if (! $receipt->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Hanya penerimaan Draft yang bisa dihapus. Penerimaan Posted dibatalkan lewat Void.'], 422);
        }

        $receipt->documents()->get()->each->delete();
        $receipt->delete();

        return response()->json(['success' => true, 'message' => 'Draft penerimaan dihapus.']);
    }

    /**
     * Posting: cek ulang invoice masih Approved & Unpaid, lalu invoice menjadi Paid.
     */
    public function approve($id): JsonResponse
    {
        $receipt = CustomerReceipt::findOrFail($id);
        if (! $receipt->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Penerimaan ini sudah diposting atau dibatalkan.'], 422);
        }

        DB::transaction(function () use ($receipt) {
            $invoice = Invoice::lockForUpdate()->findOrFail($receipt->invoice_id);
            if (! $invoice->canRecordPayment()) {
                abort(response()->json(['success' => false, 'message' => 'Invoice '.$invoice->invoice_number.' sudah tidak menunggu pembayaran ('.$invoice->status_label.').'], 422));
            }

            // Nominal mengikuti total invoice terbaru (customer selalu membayar pas).
            $receipt->update([
                'amount' => $invoice->total_payment,
                'status' => CustomerReceipt::STATUS_POSTED,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ]);

            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'date_payment' => $receipt->receipt_date,
                'reference_number' => $receipt->reference_number,
                'paid_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        });

        $this->log($receipt, 'post_customer_receipt', 'diposting');

        return response()->json(['success' => true, 'message' => 'Penerimaan '.$receipt->receipt_number.' diposting — invoice '.$receipt->invoice->invoice_number.' Lunas.']);
    }

    public function void(Request $request, $id): JsonResponse
    {
        $receipt = CustomerReceipt::with('invoice')->findOrFail($id);
        if (! $receipt->isPosted()) {
            return response()->json(['success' => false, 'message' => 'Hanya penerimaan Posted yang bisa di-void. Draft cukup dihapus.'], 422);
        }

        $validated = $request->validate(['void_reason' => 'required|string|max:1000'], ['void_reason.required' => 'Alasan void wajib diisi.']);

        DB::transaction(function () use ($receipt, $validated) {
            $receipt->update([
                'status' => CustomerReceipt::STATUS_VOID,
                'void_reason' => $validated['void_reason'],
                'voided_by' => Auth::id(),
                'voided_at' => now(),
            ]);

            $receipt->invoice->update([
                'status' => Invoice::STATUS_UNPAID,
                'date_payment' => null,
                'reference_number' => null,
                'paid_by' => null,
                'updated_by' => Auth::id(),
            ]);
        });

        $this->log($receipt, 'void_customer_receipt', 'di-void: '.$validated['void_reason']);

        return response()->json(['success' => true, 'message' => 'Penerimaan di-void. Invoice '.$receipt->invoice->invoice_number.' kembali Belum Dibayar.']);
    }

    // ── Internal ──

    private function validateReceipt(Request $request): array
    {
        return $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'receipt_date' => 'required|date|before_or_equal:today',
            'method' => 'required|in:'.implode(',', array_keys(CustomerReceipt::METHOD_LABELS)),
            'deposit_account' => 'nullable|string|max:150',
            'reference_number' => 'required_unless:method,cash|nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ], [
            'invoice_id.required' => 'Pilih invoice yang dibayar.',
            'receipt_date.required' => 'Tanggal terima wajib diisi.',
            'receipt_date.before_or_equal' => 'Tanggal terima tidak boleh melebihi hari ini.',
            'reference_number.required_unless' => 'No. referensi wajib diisi untuk transfer / giro.',
        ]);
    }

    /**
     * Invoice harus Approved, Unpaid, dan belum punya penerimaan aktif lain.
     */
    private function receivableInvoice(int $invoiceId, ?int $exceptReceiptId): Invoice
    {
        $invoice = Invoice::with(['project', 'receipts'])->findOrFail($invoiceId);
        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));

        if (! $invoice->canRecordPayment()) {
            $fail('Invoice '.$invoice->invoice_number.' belum Approved atau sudah tidak menunggu pembayaran.');
        }

        $active = $invoice->activeReceipt();
        if ($active && (int) $active->id !== (int) $exceptReceiptId) {
            $fail('Invoice '.$invoice->invoice_number.' sudah dicatat di penerimaan '.$active->receipt_number.' ('.$active->status_label.').');
        }

        return $invoice;
    }

    private function attributes(array $validated, Invoice $invoice): array
    {
        return [
            'company_id' => $invoice->project?->company_id,
            'receipt_date' => $validated['receipt_date'],
            'method' => $validated['method'],
            'deposit_account' => isset($validated['deposit_account']) && trim($validated['deposit_account']) !== '' ? trim($validated['deposit_account']) : null,
            'reference_number' => isset($validated['reference_number']) && trim($validated['reference_number']) !== '' ? trim($validated['reference_number']) : null,
            'amount' => $invoice->total_payment,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function invoiceOption(Invoice $invoice): array
    {
        $invoice->loadMissing(['project.company', 'billingTerm']);
        $project = $invoice->project;

        return [
            'id' => $invoice->id,
            'text' => $invoice->invoice_number.' — '.($project?->company?->account_name ?? '—').' · '.Invoice::formatMoney($invoice->total_payment),
            'invoice_number' => $invoice->invoice_number,
            'company_name' => $project?->company?->account_name,
            'project_label' => $project ? $project->project_number.' — '.$project->project_name : null,
            'term_label' => ($invoice->noted ?: ($invoice->billingTerm?->name ?? 'Pembayaran')).' ('.Invoice::formatNumber($invoice->percent_payment).'%)',
            'outside_timeline' => $invoice->isOutsideTimeline(),
            'invoice_date' => $invoice->invoice_date?->format('d/m/Y'),
            'due_date' => $invoice->due_date?->format('d/m/Y'),
            'is_overdue' => $invoice->due_date && $invoice->due_date->lt(now()->startOfDay()),
            'total' => $invoice->total_payment,
            'total_label' => Invoice::formatMoney($invoice->total_payment),
        ];
    }

    /**
     * Rekening penerimaan yang pernah dipakai, sebagai saran isian.
     */
    private function depositAccounts(): array
    {
        return CustomerReceipt::whereNotNull('deposit_account')->distinct()->orderBy('deposit_account')->limit(20)->pluck('deposit_account')->all();
    }

    private function log(CustomerReceipt $receipt, string $action, string $what): void
    {
        $receipt->loadMissing('invoice');
        Log::record($action, 'Penerimaan customer '.$receipt->receipt_number.' (invoice '.$receipt->invoice?->invoice_number.', '.Invoice::formatMoney($receipt->amount).') '.$what.' oleh '.Auth::user()->display_name, 'MOD_CUSTOMER_RECEIPT', $receipt);
    }

    protected function documentOwner($id): Model
    {
        return CustomerReceipt::findOrFail($id);
    }

    protected function documentRoutePrefix(): string
    {
        return 'customer-receipt';
    }

    protected function documentModuleCode(): string
    {
        return 'MOD_CUSTOMER_RECEIPT';
    }
}

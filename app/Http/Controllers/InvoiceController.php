<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Project;
use App\Models\ProjectBillingTerm;
use App\Models\Quotation;
use App\Support\ModuleAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('invoice.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Invoice::with(['project.company'])->withCount('items');

        $recordsTotal = (clone $query)->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('invoice_number', 'like', "%{$searchValue}%")
                    ->orWhere('reference_number', 'like', "%{$searchValue}%")
                    ->orWhere('do_number', 'like', "%{$searchValue}%")
                    ->orWhereHas('project', fn ($p) => $p->where('project_number', 'like', "%{$searchValue}%")
                        ->orWhere('project_name', 'like', "%{$searchValue}%")
                        ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$searchValue}%")));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $invoices = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        $data = [];
        foreach ($invoices as $i => $invoice) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->format('d/m/Y') ?? '—',
                'project_number' => $invoice->project?->project_number ?? '—',
                'project_name' => $invoice->project?->project_name ?? '—',
                'company_name' => $invoice->project?->company?->account_name ?? '—',
                'due_date' => $invoice->due_date?->format('d/m/Y') ?? '—',
                'item_count' => $invoice->items_count,
                'subtotal' => (float) $invoice->subtotal,
                'ppn' => (float) $invoice->ppn,
                'total_payment' => (float) $invoice->total_payment,
                'status' => $invoice->status,
                'status_label' => $invoice->status_label,
                'status_badge' => $invoice->statusBadgeHtml(),
                'approval_status' => $invoice->approval_status,
                'approval_status_label' => $invoice->approval_status_label,
                'approval_badge' => $invoice->approvalBadgeHtml(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(Request $request)
    {
        $selectedProject = null;
        if ($request->filled('project_id')) {
            $project = Project::with('company')->find($request->input('project_id'));
            if ($project) {
                $selectedProject = [
                    'id' => $project->id,
                    'text' => $project->project_number.' — '.$project->project_name,
                ];
            }
        }

        // Dibuka dari baris timeline penagihan project: termin langsung terpilih.
        $selectedTermId = null;
        if ($selectedProject && $request->filled('billing_term_id')) {
            // Hanya termin yang sudah dibuka (unlock) yang boleh langsung terpilih.
            $selectedTermId = ProjectBillingTerm::where('project_id', $selectedProject['id'])
                ->whereKey($request->integer('billing_term_id'))
                ->whereNotNull('unlocked_at')
                ->value('id');
        }

        return view('invoice.form', [
            'invoice' => null,
            'items' => [],
            'selectedProject' => $selectedProject,
            'selectedTermId' => $selectedTermId,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateInvoice($request, null);
        $totals = $this->invoiceTotals($validated, null);

        $invoice = DB::transaction(function () use ($validated, $totals) {
            $terms = (int) ($validated['terms'] ?? Invoice::DEFAULT_TERMS);

            $invoice = Invoice::create([
                'project_id' => $validated['project_id'],
                'billing_term_id' => $validated['billing_term_id'] ?? null,
                'invoice_number' => Invoice::generateInvoiceNumber($validated['invoice_date']),
                'invoice_date' => $validated['invoice_date'],
                'ship_to' => $validated['ship_to'] ?? null,
                'date_shipped' => $validated['date_shipped'] ?? null,
                'shipping_method' => $validated['shipping_method'] ?? null,
                'do_number' => $validated['do_number'] ?? null,
                'terms' => $terms,
                'due_date' => $this->dueDate($validated['invoice_date'], $terms),
                'subtotal' => $totals['subtotal'],
                'discount_percent' => $totals['discount_percent'],
                'discount_amount' => $totals['discount_amount'],
                'noted' => $validated['noted'] ?? null,
                'non_term_reason' => $validated['non_term_reason'],
                'tax_invoice_number' => $validated['tax_invoice_number'] ?? null,
                'signer_name' => $validated['signer_name'] ?? Invoice::DEFAULT_SIGNER_NAME,
                'signer_position' => $validated['signer_position'] ?? Invoice::DEFAULT_SIGNER_POSITION,
                'percent_payment' => $validated['percent_payment'],
                'amount_payment' => $totals['amount_payment'],
                'ppn' => $validated['ppn'],
                'total_payment' => $totals['total_payment'],
                'status' => Invoice::STATUS_UNPAID,
                'approval_status' => Invoice::APPROVAL_DRAFT,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $invoice->items()->createMany($totals['items']);

            Log::record(
                'create_invoice',
                "Invoice {$invoice->invoice_number} dibuat oleh ".Auth::user()->display_name,
                'MOD_INVOICE',
                $invoice
            );

            return $invoice;
        });

        return response()->json([
            'success' => true,
            'message' => 'Invoice berhasil dibuat.',
            'redirect' => route('invoice.show', $invoice->id),
        ]);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['project.company', 'items.product', 'creator', 'finalChecker', 'payer', 'canceller', 'billingTerm.invoices', 'billingTerm.documents.uploader', 'receipts.poster'])
            ->findOrFail($id);

        return view('invoice.show', [
            'invoice' => $invoice,
            'termDocuments' => $this->termDocumentItems($invoice),
            'canCreateReceipt' => ModuleAccess::for()->module('MOD_CUSTOMER_RECEIPT')->canCreate(),
        ]);
    }

    /**
     * Upload dokumen pendukung termin dari halaman invoice (mis. BAST yang
     * menyusul setelah invoice terbit). Dokumen melekat pada termin penagihan.
     */
    public function uploadDocument(Request $request, $id): JsonResponse
    {
        $invoice = Invoice::with('billingTerm')->findOrFail($id);
        $term = $invoice->billingTerm;

        if (! $term) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice ini tidak terhubung ke termin penagihan, jadi tidak punya dokumen pendukung.',
            ], 422);
        }

        $validated = $request->validate([
            'files' => 'required|array|min:1|max:'.Document::MAX_FILES,
            'files.*' => Document::VALIDATION_RULE,
            'notes' => 'nullable|string|max:255',
        ], [
            'files.required' => 'Pilih minimal satu file.',
            'files.max' => 'Maksimal '.Document::MAX_FILES.' file sekali upload.',
            'files.*.mimes' => 'Dokumen harus berformat PDF, JPG, atau PNG.',
            'files.*.max' => 'Ukuran setiap file tidak boleh lebih dari 10MB.',
        ]);

        $documents = DB::transaction(fn () => collect($request->file('files'))
            ->map(fn ($file) => Document::storeFor($term, $file, Document::TYPE_SUPPORT, $validated['notes'] ?? null)));

        return response()->json([
            'success' => true,
            'message' => $documents->count().' dokumen berhasil diupload.',
            'documents' => $this->termDocumentItems($invoice->load('billingTerm.invoices', 'billingTerm.documents.uploader')),
        ]);
    }

    public function viewDocument($id, $documentId)
    {
        $invoice = Invoice::findOrFail($id);
        $document = $this->invoiceDocument($invoice, $documentId);

        return $document->inlineResponse();
    }

    public function deleteDocument($id, $documentId): JsonResponse
    {
        $invoice = Invoice::with('billingTerm.invoices')->findOrFail($id);
        $document = $this->invoiceDocument($invoice, $documentId);

        if (! $invoice->billingTerm->documentsDeletable()) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak bisa dihapus karena invoice termin ini sudah Approved. Dokumen hanya bisa ditambah.',
            ], 422);
        }

        $document->delete();

        return response()->json(['success' => true, 'message' => 'Dokumen dihapus.']);
    }

    public function pdf($id)
    {
        $invoice = Invoice::with(['project.company', 'project.quotation.opportunity.owner', 'items'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('invoice.pdf', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');

        return $pdf->stream('INV-'.($invoice->invoice_number ?: $invoice->id).'.pdf');
    }

    public function edit($id)
    {
        $invoice = Invoice::with(['project.company', 'items'])->findOrFail($id);

        if (! $invoice->canBeModified()) {
            return redirect()->route('invoice.show', $invoice->id)
                ->with('error', 'Invoice yang sudah diajukan approval tidak bisa diedit.');
        }

        $project = $invoice->project;

        return view('invoice.form', [
            'invoice' => $invoice,
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'description' => Quotation::renderDescription($item->description ?: $item->product_name),
                'qty' => $item->qty,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ])->all(),
            'selectedProject' => $project ? [
                'id' => $project->id,
                'text' => $project->project_number.' — '.$project->project_name,
            ] : null,
            'selectedTermId' => $invoice->billing_term_id,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if (! $invoice->canBeModified()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice yang sudah diajukan approval tidak bisa diedit.',
            ], 422);
        }

        $validated = $this->validateInvoice($request, $invoice);
        $totals = $this->invoiceTotals($validated, $invoice);

        DB::transaction(function () use ($invoice, $validated, $totals) {
            $terms = (int) ($validated['terms'] ?? Invoice::DEFAULT_TERMS);

            $invoice->update([
                'project_id' => $validated['project_id'],
                'billing_term_id' => $validated['billing_term_id'] ?? null,
                'invoice_date' => $validated['invoice_date'],
                'ship_to' => $validated['ship_to'] ?? null,
                'date_shipped' => $validated['date_shipped'] ?? null,
                'shipping_method' => $validated['shipping_method'] ?? null,
                'do_number' => $validated['do_number'] ?? null,
                'terms' => $terms,
                'due_date' => $this->dueDate($validated['invoice_date'], $terms),
                'subtotal' => $totals['subtotal'],
                'discount_percent' => $totals['discount_percent'],
                'discount_amount' => $totals['discount_amount'],
                'noted' => $validated['noted'] ?? null,
                'non_term_reason' => $validated['non_term_reason'],
                'tax_invoice_number' => $validated['tax_invoice_number'] ?? null,
                'signer_name' => $validated['signer_name'] ?? Invoice::DEFAULT_SIGNER_NAME,
                'signer_position' => $validated['signer_position'] ?? Invoice::DEFAULT_SIGNER_POSITION,
                'percent_payment' => $validated['percent_payment'],
                'amount_payment' => $totals['amount_payment'],
                'ppn' => $validated['ppn'],
                'total_payment' => $totals['total_payment'],
                'updated_by' => Auth::id(),
            ]);

            $invoice->items()->delete();
            $invoice->items()->createMany($totals['items']);

            Log::record(
                'update_invoice',
                "Invoice {$invoice->invoice_number} diperbarui oleh ".Auth::user()->display_name,
                'MOD_INVOICE',
                $invoice
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Invoice berhasil diperbarui.',
            'redirect' => route('invoice.show', $invoice->id),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if (! $invoice->canBeModified()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice yang sudah diajukan approval tidak bisa dihapus.',
            ], 422);
        }

        DB::transaction(function () use ($invoice) {
            Log::record(
                'delete_invoice',
                "Invoice {$invoice->invoice_number} dihapus oleh ".Auth::user()->display_name,
                'MOD_INVOICE',
                $invoice
            );

            $invoice->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Invoice berhasil dihapus.',
        ]);
    }

    public function submit($id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if (! in_array($invoice->approval_status, [Invoice::APPROVAL_DRAFT, Invoice::APPROVAL_REJECTED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya invoice Draft atau Rejected yang bisa diajukan approval.',
            ], 422);
        }

        if ($invoice->items()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tambahkan minimal satu item sebelum submit.',
            ], 422);
        }

        $invoice->update([
            'approval_status' => Invoice::APPROVAL_WAITING_APPROVAL,
            'final_checked_by' => null,
            'approval_note' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'updated_by' => Auth::id(),
        ]);

        Log::record(
            'submit_invoice',
            "Invoice {$invoice->invoice_number} diajukan approval oleh ".Auth::user()->display_name,
            'MOD_INVOICE',
            $invoice
        );

        return response()->json([
            'success' => true,
            'message' => 'Invoice dikirim untuk approval.',
        ]);
    }

    public function approve($id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->approval_status !== Invoice::APPROVAL_WAITING_APPROVAL) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice ini tidak sedang menunggu approval.',
            ], 422);
        }

        $invoice->update([
            'approval_status' => Invoice::APPROVAL_APPROVED,
            'final_checked_by' => Auth::id(),
            'approval_note' => null,
            'approved_at' => now(),
            'rejected_at' => null,
            'updated_by' => Auth::id(),
        ]);

        Log::record(
            'approve_invoice',
            "Invoice {$invoice->invoice_number} disetujui oleh ".Auth::user()->display_name,
            'MOD_INVOICE',
            $invoice
        );

        return response()->json([
            'success' => true,
            'message' => 'Invoice disetujui.',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->approval_status !== Invoice::APPROVAL_WAITING_APPROVAL) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice ini tidak sedang menunggu approval.',
            ], 422);
        }

        $validated = $request->validate([
            'approval_note' => 'required|string|max:1000',
        ]);

        $invoice->update([
            'approval_status' => Invoice::APPROVAL_REJECTED,
            'final_checked_by' => Auth::id(),
            'approval_note' => $validated['approval_note'],
            'approved_at' => null,
            'rejected_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        Log::record(
            'reject_invoice',
            "Invoice {$invoice->invoice_number} ditolak oleh ".Auth::user()->display_name,
            'MOD_INVOICE',
            $invoice
        );

        return response()->json([
            'success' => true,
            'message' => 'Invoice ditolak.',
        ]);
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if (! $invoice->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya invoice Approved yang belum dibayar yang bisa dibatalkan.',
            ], 422);
        }

        $draftReceipt = $invoice->activeReceipt();
        if ($draftReceipt) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice ini sedang dicatat di penerimaan '.$draftReceipt->receipt_number.'. Hapus draft penerimaan itu dulu.',
            ], 422);
        }

        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:1000',
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        $invoice->update([
            'status' => Invoice::STATUS_CANCELLED,
            'cancel_reason' => trim($validated['cancel_reason']),
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        Log::record(
            'cancel_invoice',
            "Invoice {$invoice->invoice_number} dibatalkan oleh ".Auth::user()->display_name.": {$invoice->cancel_reason}",
            'MOD_INVOICE',
            $invoice
        );

        return response()->json([
            'success' => true,
            'message' => 'Invoice berhasil dibatalkan.',
        ]);
    }

    /**
     * No. faktur pajak keluaran: boleh diisi/diubah kapan saja selama invoice
     * belum dibatalkan, karena faktur sering terbit setelah invoice Approved.
     */
    public function updateTaxInvoice(Request $request, $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice yang dibatalkan tidak bisa diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'tax_invoice_number' => 'nullable|string|max:50',
        ]);

        $number = trim((string) ($validated['tax_invoice_number'] ?? ''));
        $invoice->update([
            'tax_invoice_number' => $number !== '' ? $number : null,
            'updated_by' => Auth::id(),
        ]);

        Log::record(
            'update_invoice_tax_number',
            "No. faktur pajak invoice {$invoice->invoice_number} diisi ".($invoice->tax_invoice_number ?? '(kosong)').' oleh '.Auth::user()->display_name,
            'MOD_INVOICE',
            $invoice
        );

        return response()->json([
            'success' => true,
            'message' => 'No. faktur pajak disimpan.',
        ]);
    }

    public function searchProjects(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $projects = Project::with('company')
            ->where(function ($query) use ($q) {
                $query->where('project_number', 'like', "%{$q}%")
                    ->orWhere('project_name', 'like', "%{$q}%")
                    ->orWhere('po_number_company', 'like', "%{$q}%")
                    ->orWhereHas('company', fn ($c) => $c->where('account_name', 'like', "%{$q}%"));
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'results' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'text' => $project->project_number.' — '.$project->project_name,
            ])->values(),
        ]);
    }

    public function fetchProject(Request $request, $id): JsonResponse
    {
        $project = Project::with(['company', 'quotation.items', 'billingTerms.invoices', 'billingTerms.documents'])->findOrFail($id);
        $currentInvoiceId = $request->integer('invoice_id') ?: null;
        $company = $project->company;
        $quotationTotals = $this->quotationTotals($project);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $project->id,
                'project_number' => $project->project_number,
                'project_name' => $project->project_name,
                'company_name' => $company?->account_name,
                'ship_to' => $company?->address_billing_province,
                'before_ppn' => $quotationTotals['before_ppn'],
                'discount_percent' => $quotationTotals['discount_percent'],
                'discount_amount' => $quotationTotals['discount_amount'],
                'net_sale_amount' => (float) $project->net_sale_amount,
                'quotation_number' => $project->quotation?->quotation_number,
                'quotation_items' => $this->quotationItems($project),
                'billing_terms' => $project->billingTerms->map(function (ProjectBillingTerm $term) use ($currentInvoiceId) {
                    $active = $term->activeInvoice();

                    return [
                        'id' => $term->id,
                        'name' => $term->name,
                        'percent' => $term->percent,
                        'description' => $term->description,
                        'document_count' => $term->documents->count(),
                        'unlocked' => $term->isUnlocked(),
                        // Tersedia bila sudah dibuka & belum ditagih, atau invoice aktifnya adalah invoice yang sedang diedit.
                        'available' => ($active && $currentInvoiceId && $active->id === $currentInvoiceId)
                            || (! $active && $term->isUnlocked()),
                        'invoice_number' => $active?->invoice_number,
                    ];
                })->values(),
            ],
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $products = MasterProduct::active()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'code', 'selling_price']);

        return response()->json([
            'results' => $products->map(fn (MasterProduct $product) => [
                'id' => $product->id,
                'text' => trim(($product->code ? $product->code.' — ' : '').($product->name ?: 'Produk #'.$product->id)),
                'name' => $product->name ?: $product->code,
                'price' => (float) $product->selling_price,
            ])->values(),
        ]);
    }

    private function validateInvoice(Request $request, ?Invoice $invoice): array
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:projects,id',
            'billing_term_id' => ['nullable', 'integer', Rule::exists('project_billing_terms', 'id')->where('project_id', $request->integer('project_id'))],
            'invoice_date' => 'required|date',
            'ship_to' => 'nullable|string',
            'date_shipped' => 'nullable|date',
            'shipping_method' => 'nullable|string|max:100',
            'do_number' => 'nullable|string|max:100',
            'terms' => 'nullable|integer|min:0|max:3650',
            'noted' => 'nullable|string|max:255',
            'non_term_reason' => [Rule::requiredIf(! $request->filled('billing_term_id')), 'nullable', 'string', 'max:1000'],
            'tax_invoice_number' => 'nullable|string|max:50',
            'signer_name' => 'nullable|string|max:100',
            'signer_position' => 'nullable|string|max:100',
            'percent_payment' => 'required|numeric|min:0|max:100',
            'ppn' => 'required|numeric|min:0|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:master_products,id',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.description' => 'required|string|max:5000',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
        ], [
            'items.required' => 'Tambahkan minimal satu item invoice.',
            'billing_term_id.exists' => 'Termin penagihan tidak sesuai dengan project yang dipilih.',
            'non_term_reason.required' => 'Invoice tanpa termin adalah tagihan di luar timeline project. Isi alasannya.',
        ]);

        $reason = trim((string) ($validated['non_term_reason'] ?? ''));
        $validated['non_term_reason'] = empty($validated['billing_term_id']) && $reason !== '' ? $reason : null;

        // Termin penagihan: satu invoice aktif per termin, persen terkunci mengikuti termin.
        if (! empty($validated['billing_term_id'])) {
            $term = ProjectBillingTerm::with('invoices')->findOrFail($validated['billing_term_id']);
            $active = $term->activeInvoice();

            if ($active && (! $invoice || $active->id !== $invoice->id)) {
                throw ValidationException::withMessages([
                    'billing_term_id' => '"'.$term->name.'" sudah ditagih lewat invoice '.$active->invoice_number.'.',
                ]);
            }

            // Gerbang tagihan: termin harus sudah dibuka oleh pemegang can_update Project
            // (invoice yang sedang diedit pada termin miliknya tetap boleh disimpan).
            if (! $term->isUnlocked() && (! $invoice || (int) $invoice->billing_term_id !== (int) $term->id)) {
                throw ValidationException::withMessages([
                    'billing_term_id' => '"'.$term->name.'" masih terkunci. Minta pemegang akses project membuka termin ini untuk ditagih.',
                ]);
            }

            $validated['percent_payment'] = $term->percent;
        }

        foreach ($validated['items'] as $index => $item) {
            $description = Quotation::sanitizeDescription($item['description'] ?? '');
            $plain = trim(html_entity_decode(strip_tags(
                str_replace(['<br>', '<br/>', '<br />'], ' ', $description)
            ), ENT_QUOTES | ENT_HTML5));

            if ($plain === '') {
                throw ValidationException::withMessages([
                    "items.{$index}.description" => 'Deskripsi item baris '.($index + 1).' wajib diisi.',
                ]);
            }

            $validated['items'][$index]['description'] = $description;
        }

        return $validated;
    }

    /**
     * Total invoice dari item, lalu aturan termin:
     * - dasar invoice termin (sub total - diskon) wajib sama dengan nilai kontrak project,
     *   sehingga jumlah semua termin = nilai project;
     * - termin yang melengkapi timeline 100% menagih sisa nilai kontrak (bukan persen x dasar)
     *   agar tidak tersisa selisih pembulatan.
     */
    private function invoiceTotals(array $validated, ?Invoice $invoice): array
    {
        $totals = $this->calculateTotals(
            $validated['items'],
            (float) $validated['percent_payment'],
            (float) $validated['ppn'],
            isset($validated['discount_percent']) ? (float) $validated['discount_percent'] : null,
            (float) ($validated['discount_amount'] ?? 0)
        );

        if (empty($validated['billing_term_id'])) {
            return $totals;
        }

        $project = Project::with(['quotation', 'billingTerms.invoices'])->findOrFail($validated['project_id']);
        $baseCents = (int) round($project->billingBaseAmount() * 100);
        $netCents = (int) round(($totals['subtotal'] - $totals['discount_amount']) * 100);

        if ($netCents !== $baseCents) {
            throw ValidationException::withMessages([
                'items' => 'Nilai sebelum PPN invoice termin ('.Invoice::formatMoney($netCents / 100).') harus sama dengan nilai kontrak project ('
                    .Invoice::formatMoney($baseCents / 100).'). Muat item dari quotation; perubahan nilai dilakukan lewat revisi quotation/project.',
            ]);
        }

        $terms = $project->billingTerms;
        if (abs((float) $terms->sum('percent') - 100) > 0.001) {
            return $totals;
        }

        $billedCents = 0;
        foreach ($terms as $term) {
            if ((int) $term->id === (int) $validated['billing_term_id']) {
                continue;
            }

            $active = $term->invoices->first(fn (Invoice $other) => $other->status !== Invoice::STATUS_CANCELLED
                && (! $invoice || $other->id !== $invoice->id));

            if (! $active) {
                return $totals;
            }

            $billedCents += (int) round($active->amount_payment * 100);
        }

        $amountCents = $baseCents - $billedCents;
        if ($amountCents < 0) {
            return $totals;
        }

        $ppnCents = (int) round($amountCents * (float) $validated['ppn'] / 100);
        $totals['amount_payment'] = $amountCents / 100;
        $totals['total_payment'] = ($amountCents + $ppnCents) / 100;

        return $totals;
    }

    /**
     * Sub total item - diskon = nilai sebelum PPN (DPP).
     * Diskon persen diutamakan; jika kosong, pakai nominal diskon.
     * Persentase pembayaran dihitung dari nilai sebelum PPN, lalu PPN dari nilai pembayaran.
     */
    private function calculateTotals(
        array $items,
        float $percentPayment,
        float $ppnPercent,
        ?float $discountPercent = null,
        float $discountAmount = 0
    ): array {
        $rows = [];
        $subtotalCents = 0;

        foreach ($items as $item) {
            $qty = round((float) $item['qty'], 2);
            $unitPrice = round((float) $item['unit_price'], 2);
            $lineCents = (int) round($qty * $unitPrice * 100);
            $subtotalCents += $lineCents;

            $description = Quotation::sanitizeDescription($item['description'] ?? '');
            $productName = trim((string) ($item['product_name'] ?? ''));

            if ($productName === '') {
                $productName = Str::limit(Str::squish(html_entity_decode(strip_tags(
                    str_replace(['<br>', '<br/>', '<br />'], ' ', $description)
                ), ENT_QUOTES | ENT_HTML5)), 255, '');
            }

            $rows[] = [
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $productName !== '' ? $productName : 'Item',
                'description' => $description,
                'qty' => $qty,
                'unit' => $item['unit'] ?? null,
                'unit_price' => $unitPrice,
                'total_price' => $lineCents / 100,
            ];
        }

        $discountPercent = $discountPercent > 0 ? $discountPercent : null;
        $discountCents = $discountPercent
            ? (int) round($subtotalCents * $discountPercent / 100)
            : (int) round($discountAmount * 100);

        if ($discountCents > $subtotalCents) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Diskon tidak boleh melebihi sub total.',
            ]);
        }

        $amountCents = (int) round(($subtotalCents - $discountCents) * $percentPayment / 100);
        $ppnCents = (int) round($amountCents * $ppnPercent / 100);

        return [
            'items' => $rows,
            'subtotal' => $subtotalCents / 100,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountCents / 100,
            'amount_payment' => $amountCents / 100,
            'total_payment' => ($amountCents + $ppnCents) / 100,
        ];
    }

    /**
     * Nilai penawaran sebelum PPN (sub total - diskon) beserta diskon quotation,
     * dipakai sebagai acuan nilai project dan default diskon invoice.
     * Project tanpa quotation memakai total sale project.
     */
    private function quotationTotals(Project $project): array
    {
        $quotation = $project->quotation;

        if (! $quotation) {
            return [
                'before_ppn' => $project->billingBaseAmount(),
                'discount_percent' => null,
                'discount_amount' => 0.0,
            ];
        }

        $subtotal = (float) $quotation->subtotal;
        $discountPercent = (float) $quotation->discount_percent > 0 ? (float) $quotation->discount_percent : null;
        $discount = $discountPercent
            ? round($subtotal * $discountPercent / 100, 2)
            : round((float) $quotation->discount_amount, 2);

        return [
            'before_ppn' => round($subtotal - $discount, 2),
            'discount_percent' => $discountPercent,
            'discount_amount' => $discount,
        ];
    }

    private function termDocumentItems(Invoice $invoice): array
    {
        $term = $invoice->billingTerm;
        if (! $term) {
            return [];
        }

        $deletable = $term->documentsDeletable();

        return $term->documents->map(fn (Document $doc) => $doc->toListItem(
            route('invoice.documents.show', [$invoice->id, $doc->id]),
            $deletable ? route('invoice.documents.destroy', [$invoice->id, $doc->id]) : null
        ))->values()->all();
    }

    /**
     * Dokumen hanya bisa dibuka lewat invoice bila milik termin penagihan invoice tsb.
     */
    private function invoiceDocument(Invoice $invoice, $documentId): Document
    {
        $document = Document::findOrFail($documentId);

        abort_unless(
            $invoice->billing_term_id
                && $document->documentable_type === (new ProjectBillingTerm)->getMorphClass()
                && (int) $document->documentable_id === (int) $invoice->billing_term_id,
            404
        );

        return $document;
    }

    /**
     * Item quotation project yang memiliki harga, siap dipakai sebagai item invoice.
     * Baris judul/induk tanpa harga dilewati.
     */
    private function quotationItems(Project $project): array
    {
        if (! $project->quotation) {
            return [];
        }

        $items = $project->quotation->items;
        $partNumbers = $items->pluck('part_number')
            ->map(fn ($partNumber) => trim((string) $partNumber))
            ->filter()
            ->unique()
            ->values();

        $products = $partNumbers->isEmpty()
            ? collect()
            : MasterProduct::whereIn('code', $partNumbers)
                ->get(['id', 'code', 'name'])
                ->keyBy(fn (MasterProduct $product) => mb_strtolower((string) $product->code));

        return $items
            ->filter(fn ($item) => (float) $item->selling_price > 0 && (float) $item->qty > 0)
            ->map(function ($item) use ($products) {
                $partNumber = trim((string) $item->part_number);
                $product = $partNumber !== '' ? $products->get(mb_strtolower($partNumber)) : null;

                $plainDescription = Str::squish(html_entity_decode(strip_tags(
                    str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', (string) $item->description)
                ), ENT_QUOTES | ENT_HTML5));

                $description = Quotation::renderDescription($item->description);
                $name = $product?->name ?: ($partNumber !== '' ? $partNumber : $plainDescription);

                return [
                    'product_id' => $product?->id,
                    'product_name' => Str::limit($name !== '' ? $name : 'Item', 255, ''),
                    'description' => $description !== '' ? $description : e($name),
                    'qty' => (float) $item->qty,
                    'unit' => $item->unit,
                    'unit_price' => (float) $item->selling_price,
                ];
            })
            ->values()
            ->all();
    }

    private function dueDate(string $invoiceDate, int $terms): string
    {
        return Carbon::parse($invoiceDate)->addDays($terms)->toDateString();
    }
}

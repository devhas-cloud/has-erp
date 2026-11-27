<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ItemLoan;
use App\Models\ItemLoanItem;
use App\Models\Log;
use App\Models\Project;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WhatsAppGroup;
use App\Services\ItemLoanDueNotifier;
use App\Services\StockException;
use App\Services\StockService;
use App\Models\AppSetting;
use App\Support\ModuleAccess;
use App\Support\StockPicker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Item Loan — peminjaman unit ber-SN, dicatat petugas inventory (tanpa
 * pengajuan / approval peminjam). Disimpan = langsung dipinjam: unit ditahan
 * (StockService) tetapi stok & nilainya tetap. Pengembalian per unit: baik /
 * rusak (kembali ke stok) atau hilang (stok berkurang, log loan_lost).
 * Hak akses lewat middleware modul: POST -> can_create, DELETE -> can_delete.
 */
class ItemLoanController extends Controller
{
    public const MODULE_CODE = 'MOD_ITEM_LOAN';

    public function index()
    {
        $warehouseIds = $this->visibleLoans()->select('warehouse_id');

        return view('item-loan.index', [
            'warehouses' => Warehouse::whereIn('id', $warehouseIds)->orderBy('name')->get(['id', 'code', 'name']),
            'reminder' => ItemLoanDueNotifier::settings(),
            'whatsappGroups' => WhatsAppGroup::orderBy('group_name')->get(['id', 'group_name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $query = $this->visibleLoans()->withCount([
            'items',
            'items as out_count' => fn ($q) => $q->whereNull('return_status'),
        ])->with(['borrowerUser', 'warehouse', 'creator', 'items.masterProduct:id,code,name', 'items.stock:id,serial_number']);

        $recordsTotal = (clone $query)->count();
        $overdueTotal = (clone $query)->where('status', ItemLoan::STATUS_ON_LOAN)->whereDate('due_date', '<', $today)->count();

        $status = $request->input('status');
        $query
            ->when($status === 'overdue', fn ($q) => $q->where('status', ItemLoan::STATUS_ON_LOAN)->whereDate('due_date', '<', $today))
            ->when($status && $status !== 'overdue', fn ($q) => $q->where('status', $status))
            ->when(in_array($request->input('borrower_type'), [ItemLoan::BORROWER_INTERNAL, ItemLoan::BORROWER_EXTERNAL], true), fn ($q) => $q->where('borrower_type', $request->input('borrower_type')))
            ->when($request->integer('warehouse'), fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('loan_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('loan_date', '<=', $date));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('loan_number', 'like', "%{$search}%")
                ->orWhere('borrower_name', 'like', "%{$search}%")
                ->orWhere('borrower_company', 'like', "%{$search}%")
                ->orWhere('purpose', 'like', "%{$search}%")
                ->orWhereHas('borrowerUser', fn ($u) => $u->where('full_name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"))
                ->orWhereHas('items.stock', fn ($st) => $st->where('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('masterProduct', fn ($p) => $p->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))));
        }

        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        // Yang masih dipinjam lebih dulu, urut jatuh tempo terdekat (overdue paling atas).
        $rows = $query->orderByRaw("CASE status WHEN '".ItemLoan::STATUS_ON_LOAN."' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = '".ItemLoan::STATUS_ON_LOAN."' THEN due_date END ASC")
            ->orderByDesc('id')->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'overdue_total' => $overdueTotal,
            'data' => $rows->map(fn (ItemLoan $loan) => [
                'id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'creator' => $loan->creator?->display_name ?? '—',
                'borrower' => $loan->borrowerLabel(),
                'borrower_type' => $loan->borrower_type,
                'borrower_company' => $loan->isExternal() ? $loan->borrower_company : $loan->borrowerUser?->division?->division_name,
                'units' => $loan->items->take(2)->map(fn (ItemLoanItem $i) => trim(($i->masterProduct?->code ?? '').' · SN '.($i->stock?->serial_number ?? '—')))->values(),
                'item_count' => $loan->items_count,
                'out_count' => $loan->out_count,
                'warehouse' => $loan->warehouse?->code,
                'loan_date' => $loan->loan_date?->format('d/m/Y'),
                'due_date' => $loan->due_date?->format('d/m/Y'),
                'days_to_due' => $loan->isOnLoan() ? $loan->daysToDue() : null,
                'status' => $loan->status,
                'status_badge' => $loan->statusBadgeHtml(),
            ]),
        ]);
    }

    public function create()
    {
        $user = Auth::user();

        return view('item-loan.form', [
            'warehouses' => Warehouse::active()->with('branch')->ofBranch($user->branch_id)->orderBy('name')->get(),
            'users' => User::orderBy('full_name')->orderBy('username')->get(['id', 'full_name', 'username', 'phone_number']),
            'projects' => Project::where('status', Project::STATUS_ACTIVE)->orderByDesc('id')->get(['id', 'project_number', 'project_name']),
        ]);
    }

    /** Unit ber-SN tersedia (tidak dipesan / dipinjam) di gudang terpilih. */
    public function stockOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'q' => 'nullable|string|max:100',
        ]);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        abort_unless(Auth::user()->canAccessBranch($warehouse->branch_id), 403, 'Warehouse is not in your branch.');

        $rows = StockPicker::options(['warehouse_id' => $warehouse->id, 'q' => $validated['q'] ?? null, 'limit' => 2000])
            ->filter(fn (array $row) => $row['serialized'])
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request, StockService $stockService): JsonResponse
    {
        $validated = $request->validate([
            'borrower_type' => 'required|in:'.ItemLoan::BORROWER_INTERNAL.','.ItemLoan::BORROWER_EXTERNAL,
            'borrower_user_id' => 'required_if:borrower_type,internal|nullable|integer|exists:users,id',
            'borrower_name' => 'required_if:borrower_type,external|nullable|string|max:150',
            'borrower_company' => 'nullable|string|max:150',
            'borrower_phone' => 'nullable|string|max:30',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'purpose' => 'required|string|max:255',
            'loan_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:loan_date',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.stock_id' => 'required|integer|distinct',
            'items.*.condition_out' => 'nullable|string|max:255',
        ], [
            'borrower_user_id.required_if' => 'Select the borrower.',
            'borrower_name.required_if' => 'Enter the borrower name.',
            'purpose.required' => 'Enter the purpose of the loan.',
            'due_date.after_or_equal' => 'Return date cannot be before the loan date.',
            'items.required' => 'Select at least one unit.',
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        abort_unless(Auth::user()->canAccessBranch($warehouse->branch_id), 403, 'Warehouse is not in your branch.');
        $external = $validated['borrower_type'] === ItemLoan::BORROWER_EXTERNAL;

        try {
            $loan = DB::transaction(function () use ($validated, $warehouse, $external, $stockService) {
                $stocks = $stockService->lock(array_column($validated['items'], 'stock_id'));

                $loan = ItemLoan::create([
                    'loan_number' => ItemLoan::generateNumber($validated['loan_date']),
                    'borrower_type' => $validated['borrower_type'],
                    'borrower_user_id' => $external ? null : $validated['borrower_user_id'],
                    'borrower_name' => $external ? $validated['borrower_name'] : User::whereKey($validated['borrower_user_id'])->first()?->display_name,
                    'borrower_company' => $external ? ($validated['borrower_company'] ?? null) : null,
                    'borrower_phone' => $validated['borrower_phone'] ?? null,
                    'warehouse_id' => $warehouse->id,
                    'project_id' => $validated['project_id'] ?? null,
                    'purpose' => $validated['purpose'],
                    'loan_date' => $validated['loan_date'],
                    'due_date' => $validated['due_date'],
                    'status' => ItemLoan::STATUS_ON_LOAN,
                    'note' => $validated['note'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                foreach ($validated['items'] as $row) {
                    $stock = $stocks->get((int) $row['stock_id']);
                    $this->assertLendable($stock, $warehouse, $stockService);
                    $item = $loan->items()->create([
                        'stock_id' => $stock->id,
                        'master_product_id' => $stock->master_product_id,
                        'condition_out' => $row['condition_out'] ?? null,
                    ]);
                    $stockService->note($stock, StockLog::TYPE_LOAN_OUT, [
                        'date' => $validated['loan_date'],
                        'ref_type' => StockLog::REF_ITEM_LOAN_ITEM,
                        'ref_id' => $item->id,
                        'note' => 'Dipinjam '.$loan->borrowerLabel().' s/d '.$loan->due_date->format('d/m/Y'),
                    ]);
                }

                return $loan;
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('create_item_loan', 'Pinjaman '.$loan->loan_number.' ('.$loan->items()->count().' unit) ke '.$loan->borrowerLabel().' dicatat oleh '.Auth::user()->display_name, self::MODULE_CODE, $loan);

        return response()->json(['success' => true, 'message' => 'Loan '.$loan->loan_number.' recorded. The units are now on loan.', 'id' => $loan->id]);
    }

    public function show($id)
    {
        $loan = $this->visibleLoans()->with([
            'items.stock.warehouse', 'items.masterProduct', 'items.returner', 'items.photos.uploader',
            'extensions.creator', 'borrowerUser.division', 'warehouse.branch', 'project', 'creator', 'canceller',
        ])->findOrFail($id);

        return view('item-loan.show', [
            'loan' => $loan,
            'photoLists' => $loan->items->mapWithKeys(fn (ItemLoanItem $item) => [$item->id => $this->photoList($loan, $item)]),
        ]);
    }

    /** Bukti peminjaman (untuk ditandatangani saat serah & kembali), ditampilkan di browser. */
    public function pdf($id)
    {
        $loan = $this->visibleLoans()->with([
            'items.stock', 'items.masterProduct', 'extensions', 'borrowerUser.division', 'warehouse', 'project', 'creator',
        ])->findOrFail($id);

        return Pdf::loadView('item-loan.pdf', compact('loan'))
            ->setPaper('a4', 'portrait')
            ->stream('Peminjaman-'.$loan->loan_number.'.pdf');
    }

    /** Catat pengembalian sebagian / semua unit: good / damaged / lost. */
    public function recordReturn(Request $request, StockService $stockService, $id): JsonResponse
    {
        $loan = $this->visibleLoans()->findOrFail($id);
        if (! $loan->isOnLoan()) {
            return response()->json(['success' => false, 'message' => 'This loan is no longer active.'], 422);
        }

        $validated = $request->validate([
            'returned_at' => 'required|date|after_or_equal:'.$loan->loan_date->toDateString(),
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|distinct',
            'items.*.status' => 'required|in:'.implode(',', array_keys(ItemLoanItem::RETURN_LABELS)),
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Select at least one unit to return.',
            'returned_at.after_or_equal' => 'Return date cannot be before the loan date.',
        ]);

        foreach ($validated['items'] as $row) {
            if (in_array($row['status'], [ItemLoanItem::RETURN_DAMAGED, ItemLoanItem::RETURN_LOST], true) && trim((string) ($row['note'] ?? '')) === '') {
                return response()->json(['success' => false, 'message' => 'Add a note for units returned damaged or lost.'], 422);
            }
        }

        try {
            $summary = DB::transaction(function () use ($loan, $validated, $stockService) {
                $items = $loan->items()->whereIn('id', array_column($validated['items'], 'id'))->lockForUpdate()->get()->keyBy('id');
                $counts = array_fill_keys(array_keys(ItemLoanItem::RETURN_LABELS), 0);

                foreach ($validated['items'] as $row) {
                    $item = $items->get((int) $row['id']);
                    if (! $item || ! $item->isOut()) {
                        throw new StockException('A selected unit is not on loan anymore. Refresh the page.');
                    }
                    $item->update([
                        'return_status' => $row['status'],
                        'returned_at' => $validated['returned_at'],
                        'returned_by' => Auth::id(),
                        'return_note' => $row['note'] ?? null,
                    ]);
                    $stock = $stockService->lock([$item->stock_id])->first();
                    $log = [
                        'date' => $validated['returned_at'],
                        'ref_type' => StockLog::REF_ITEM_LOAN_ITEM,
                        'ref_id' => $item->id,
                    ];
                    if ($row['status'] === ItemLoanItem::RETURN_LOST) {
                        // Hilang: stok berkurang atas nama peminjam.
                        $stockService->take($stock, (float) $stock->qty, StockLog::TYPE_LOAN_LOST, $log + [
                            'note' => 'Hilang saat dipinjam '.$loan->borrowerLabel().' ('.$loan->loan_number.'): '.$row['note'],
                        ]);
                    } else {
                        $stockService->note($stock, StockLog::TYPE_LOAN_IN, $log + [
                            'note' => 'Kembali dari '.$loan->borrowerLabel().' — '.ItemLoanItem::RETURN_LABELS[$row['status']]
                                .(! empty($row['note']) ? ': '.$row['note'] : ''),
                        ]);
                    }
                    $counts[$row['status']]++;
                }

                if (! $loan->items()->whereNull('return_status')->exists()) {
                    $loan->update(['status' => ItemLoan::STATUS_RETURNED, 'closed_at' => now()]);
                }

                return $counts;
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $parts = collect($summary)->filter()->map(fn ($n, $status) => $n.' '.strtolower(ItemLoanItem::RETURN_LABELS[$status]))->implode(', ');
        Log::record('return_item_loan', 'Pengembalian '.$loan->loan_number.' ('.$parts.') dicatat oleh '.Auth::user()->display_name, self::MODULE_CODE, $loan);

        return response()->json([
            'success' => true,
            'message' => 'Return recorded ('.$parts.').'.($loan->fresh()->status === ItemLoan::STATUS_RETURNED ? ' All units are back — loan closed.' : ''),
        ]);
    }

    /** Perpanjang tanggal kembali (riwayat tersimpan). */
    public function extend(Request $request, $id): JsonResponse
    {
        $loan = $this->visibleLoans()->findOrFail($id);
        if (! $loan->isOnLoan()) {
            return response()->json(['success' => false, 'message' => 'This loan is no longer active.'], 422);
        }
        $validated = $request->validate([
            'new_due_date' => 'required|date|after:'.$loan->due_date->toDateString(),
            'reason' => 'required|string|max:255',
        ], [
            'new_due_date.after' => 'The new return date must be after the current one ('.$loan->due_date->format('d/m/Y').').',
            'reason.required' => 'Enter the reason for the extension.',
        ]);

        DB::transaction(function () use ($loan, $validated) {
            $loan->extensions()->create([
                'old_due_date' => $loan->due_date->toDateString(),
                'new_due_date' => $validated['new_due_date'],
                'reason' => $validated['reason'],
                'created_by' => Auth::id(),
            ]);
            $loan->update(['due_date' => $validated['new_due_date'], 'last_reminded_on' => null]);
        });

        Log::record('extend_item_loan', 'Pinjaman '.$loan->loan_number.' diperpanjang s/d '.$loan->fresh()->due_date->format('d/m/Y').' oleh '.Auth::user()->display_name, self::MODULE_CODE, $loan);

        return response()->json(['success' => true, 'message' => 'Return date extended to '.$loan->fresh()->due_date->format('d/m/Y').'.']);
    }

    /** Batal (salah input): hanya bila belum ada unit yang dikembalikan. */
    public function cancel(Request $request, StockService $stockService, $id): JsonResponse
    {
        abort_unless(ModuleAccess::for(Auth::user())->module(self::MODULE_CODE)->canDelete(), 403, 'You are not allowed to cancel loans.');
        $loan = $this->visibleLoans()->findOrFail($id);
        $validated = $request->validate(['reason' => 'required|string|max:255'], ['reason.required' => 'Enter the reason for cancelling.']);

        if (! $loan->isOnLoan() || $loan->items()->whereNotNull('return_status')->exists()) {
            return response()->json(['success' => false, 'message' => 'Only an active loan without any recorded return can be cancelled.'], 422);
        }

        DB::transaction(function () use ($loan, $validated, $stockService) {
            $loan->update([
                'status' => ItemLoan::STATUS_CANCELLED,
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
                'closed_at' => now(),
            ]);
            foreach ($loan->items()->with('stock')->get() as $item) {
                $stockService->note($item->stock, StockLog::TYPE_LOAN_IN, [
                    'ref_type' => StockLog::REF_ITEM_LOAN_ITEM,
                    'ref_id' => $item->id,
                    'note' => 'Pinjaman '.$loan->loan_number.' dibatalkan: '.$validated['reason'],
                ]);
            }
        });

        Log::record('cancel_item_loan', 'Pinjaman '.$loan->loan_number.' dibatalkan oleh '.Auth::user()->display_name.': '.$validated['reason'], self::MODULE_CODE, $loan);

        return response()->json(['success' => true, 'message' => 'Loan '.$loan->loan_number.' cancelled. The units are available again.']);
    }

    public function uploadPhotos(Request $request, $id, $itemId): JsonResponse
    {
        $loan = $this->visibleLoans()->findOrFail($id);
        $item = $loan->items()->findOrFail($itemId);
        $room = Document::MAX_FILES - $item->photos()->count();
        $request->validate([
            'files' => 'required|array|min:1|max:'.max($room, 0),
            'files.*' => Document::IMAGE_RULE,
        ], [
            'files.required' => 'Select at least one photo.',
            'files.max' => $room > 0 ? 'This unit can take '.$room.' more photo(s) (max '.Document::MAX_FILES.').' : 'This unit already has '.Document::MAX_FILES.' photos.',
            'files.*.mimes' => 'Photos must be JPG, PNG or WEBP.',
            'files.*.max' => 'Each photo must not exceed 10MB.',
            'files.*.uploaded' => 'A photo failed to upload (file too large for the server).',
        ]);

        $stored = [];
        try {
            DB::transaction(function () use ($request, $item, &$stored) {
                foreach ($request->file('files') as $file) {
                    $stored[] = Document::storeFor($item, $file, Document::TYPE_RECEIPT_PHOTO)->file_path;
                }
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk(Document::DISK)->delete($path);
            }
            throw $e;
        }

        return response()->json(['success' => true, 'message' => count($stored).' photo(s) uploaded.', 'photos' => $this->photoList($loan, $item)]);
    }

    public function viewPhoto($id, $itemId, $documentId)
    {
        $loan = $this->visibleLoans()->findOrFail($id);

        return $loan->items()->findOrFail($itemId)->photos()->findOrFail($documentId)->inlineResponse();
    }

    public function deletePhoto($id, $itemId, $documentId): JsonResponse
    {
        $loan = $this->visibleLoans()->findOrFail($id);
        $item = $loan->items()->findOrFail($itemId);
        $document = $item->photos()->findOrFail($documentId);
        if (! $this->canDeletePhoto($document)) {
            return response()->json(['success' => false, 'message' => 'Only the uploader can delete this photo.'], 422);
        }
        $document->delete();

        return response()->json(['success' => true, 'message' => 'Photo deleted.', 'photos' => $this->photoList($loan, $item)]);
    }

    /** Pengaturan pengingat WhatsApp jatuh tempo. */
    public function saveReminder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'send_time' => 'required|date_format:H:i',
            'days_ahead' => 'required|integer|min:0|max:30',
            'whatsapp_group_id' => 'nullable|integer|exists:whatsapp_groups,id',
            'notify_borrowers' => 'boolean',
        ]);

        AppSetting::put(ItemLoanDueNotifier::SETTING_KEY, array_merge(ItemLoanDueNotifier::settings(), [
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'send_time' => $validated['send_time'],
            'days_ahead' => (int) $validated['days_ahead'],
            'whatsapp_group_id' => $validated['whatsapp_group_id'] ?? null,
            'notify_borrowers' => (bool) ($validated['notify_borrowers'] ?? false),
        ]));

        return response()->json(['success' => true, 'message' => 'Reminder settings saved.']);
    }

    /** Pinjaman yang terlihat user: gudang di branch-nya (tanpa branch = semua). */
    private function visibleLoans()
    {
        $branchId = Auth::user()?->branch_id;

        return ItemLoan::query()->when($branchId, fn ($q) => $q->whereIn('warehouse_id', Warehouse::where('branch_id', $branchId)->select('id')));
    }

    private function assertLendable(?Stock $stock, Warehouse $warehouse, StockService $stockService): void
    {
        if (! $stock || $stock->qty <= Stock::EPSILON) {
            throw new StockException('A selected unit is no longer in stock. Refresh the unit list.');
        }
        if ((int) $stock->warehouse_id !== (int) $warehouse->id) {
            throw new StockException($stockService->label($stock).' is not in warehouse '.$warehouse->name.'.');
        }
        if (! $stock->isSerialized()) {
            throw new StockException($stockService->label($stock).' is a material item; only serial-numbered units can be lent.');
        }
        if (! $stock->serial_number) {
            throw new StockException($stockService->label($stock).' has no serial number yet. Fill in the SN first so the unit can be identified.');
        }
        $stockService->assertNotOnLoan($stock);
        if (StockService::available($stock) < 1 - Stock::EPSILON) {
            throw new StockException($stockService->label($stock).' is reserved by a Packing List and cannot be lent.');
        }
    }

    private function canDeletePhoto(Document $document): bool
    {
        return Auth::user()?->role === 'Admin' || (int) $document->uploaded_by === (int) Auth::id();
    }

    private function photoList(ItemLoan $loan, ItemLoanItem $item): array
    {
        return $item->photos()->with('uploader')->get()->map(fn (Document $doc) => [
            'id' => $doc->id,
            'url' => route('item-loan.photos.show', [$loan->id, $item->id, $doc->id]),
            'delete_url' => $this->canDeletePhoto($doc) ? route('item-loan.photos.destroy', [$loan->id, $item->id, $doc->id]) : null,
            'title' => ($doc->uploader?->display_name ?? '—').' · '.$doc->created_at?->format('d/m/Y H:i'),
        ])->values()->all();
    }
}

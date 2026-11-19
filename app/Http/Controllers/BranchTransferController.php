<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use App\Models\Document;
use App\Models\Log;
use App\Models\Stock;
use App\Models\StockLog;
use App\Models\Warehouse;
use App\Services\StockException;
use App\Services\StockService;
use App\Support\StockCost;
use App\Support\StockPicker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Branch Transfer — pengiriman barang antar branch dengan surat jalan.
 *
 * Branch asal menyusun draft (pilih gudang asal, branch tujuan, baris stok),
 * lalu Kirim: stok keluar dari gudang asal (log ship_out) dan tercatat "dalam
 * pengiriman". Branch tujuan memeriksa setiap barang (qty diterima, kondisi,
 * catatan, foto) dan memilih gudang tujuan; penerimaan hanya bisa diselesaikan
 * bila SEMUA barang sesuai — barang lalu masuk ke gudang tujuan (log ship_in).
 *
 * Hak lewat akses modul (POST = can_create, dst). User ber-branch hanya melihat
 * dokumen branch-nya, mengirim dari branch-nya, dan menerima ke branch-nya.
 */
class BranchTransferController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $visible = BranchTransfer::visibleTo($user);
        $branchIds = (clone $visible)->pluck('from_branch_id')->merge((clone $visible)->pluck('to_branch_id'))->unique()->values();

        return view('branch-transfer.index', [
            'branches' => Branch::whereIn('id', $branchIds)->orderBy('name')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::whereIn('id', (clone $visible)->select('from_warehouse_id'))
                ->orWhereIn('id', (clone $visible)->whereNotNull('to_warehouse_id')->select('to_warehouse_id'))
                ->orderBy('name')->get(['id', 'code', 'name']),
            'userBranchId' => $user->branch_id,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = BranchTransfer::visibleTo(Auth::user())
            ->withCount('items')
            ->with(['fromBranch', 'toBranch', 'fromWarehouse', 'toWarehouse', 'creator']);

        $recordsTotal = (clone $query)->count();
        $user = Auth::user();

        $query
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('from_branch'), fn ($q, $id) => $q->where('from_branch_id', $id))
            ->when($request->integer('to_branch'), fn ($q, $id) => $q->where('to_branch_id', $id))
            ->when($request->integer('warehouse'), fn ($q, $id) => $q->where(fn ($w) => $w->where('from_warehouse_id', $id)->orWhere('to_warehouse_id', $id)))
            // Arah relatif branch user: keluar = dikirim dari branch saya, masuk = menuju branch saya.
            ->when($user->branch_id && $request->input('direction') === 'out', fn ($q) => $q->where('from_branch_id', $user->branch_id))
            ->when($user->branch_id && $request->input('direction') === 'in', fn ($q) => $q->where('to_branch_id', $user->branch_id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('transfer_date', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('transfer_date', '<=', $date))
            ->when($request->boolean('mine'), fn ($q) => $q->where('created_by', $user->id));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('transfer_number', 'like', "%{$search}%")
                ->orWhere('note', 'like', "%{$search}%")
                ->orWhere('courier', 'like', "%{$search}%")
                ->orWhere('vehicle_no', 'like', "%{$search}%")
                ->orWhere('driver_name', 'like', "%{$search}%")
                ->orWhereHas('items.stock', fn ($st) => $st->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhereHas('masterProduct', fn ($p) => $p->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))));
        }

        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $rows = $query->orderByDesc('id')->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn (BranchTransfer $t, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $t->id,
                'transfer_number' => $t->transfer_number,
                'transfer_date' => $t->transfer_date?->format('d/m/Y'),
                'from' => ($t->fromBranch?->name ?? '—').' · '.($t->fromWarehouse?->code ?? '—'),
                'to' => ($t->toBranch?->name ?? '—').($t->toWarehouse ? ' · '.$t->toWarehouse->code : ''),
                'from_branch' => $t->fromBranch?->name ?? '—',
                'from_warehouse' => $t->fromWarehouse ? $t->fromWarehouse->code.' — '.$t->fromWarehouse->name : '—',
                'to_branch' => $t->toBranch?->name ?? '—',
                'to_warehouse' => $t->toWarehouse ? $t->toWarehouse->code.' — '.$t->toWarehouse->name : null,
                'direction' => $user->branch_id ? ((int) $t->from_branch_id === (int) $user->branch_id ? 'out' : 'in') : null,
                'courier' => trim(implode(' · ', array_filter([$t->courier, $t->vehicle_no]))),
                'creator' => $t->creator?->display_name ?? '—',
                'item_count' => $t->items_count,
                'status' => $t->status,
                'status_badge' => $t->statusBadgeHtml(),
            ]),
        ]);
    }

    public function create()
    {
        return view('branch-transfer.form', $this->formData(null));
    }

    /**
     * Baris stok di gudang asal untuk dipilih (pemilih stok bersama).
     */
    public function stockOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'q' => 'nullable|string|max:100',
        ]);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        abort_unless(Auth::user()->canAccessBranch($warehouse->branch_id), 403, 'Gudang bukan milik branch Anda.');

        return response()->json(['success' => true, 'data' => StockPicker::options([
            'warehouse_id' => $warehouse->id,
            'q' => $validated['q'] ?? null,
            // Semua baris gudang dimuat sekali; pencarian & filter di browser.
            'limit' => 2000,
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTransfer($request);

        $transfer = DB::transaction(function () use ($validated) {
            $transfer = BranchTransfer::create($this->headerAttributes($validated) + [
                'transfer_number' => BranchTransfer::generateNumber($validated['transfer_date']),
                'status' => BranchTransfer::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);
            $this->syncItems($transfer, $validated['items'], $validated['units']);

            return $transfer;
        });

        Log::record('create_branch_transfer', 'Branch Transfer '.$transfer->transfer_number.' dibuat oleh '.Auth::user()->display_name, 'MOD_BRANCH_TRANSFER', $transfer);

        return response()->json(['success' => true, 'message' => 'Branch Transfer '.$transfer->transfer_number.' disimpan sebagai draft.', 'id' => $transfer->id]);
    }

    public function show($id)
    {
        $transfer = $this->find($id, ['items.stock.masterProduct', 'items.receivedStock', 'items.photos.uploader', 'fromBranch', 'toBranch', 'fromWarehouse', 'toWarehouse', 'creator', 'shippedBy', 'receivedBy']);
        $user = Auth::user();

        return view('branch-transfer.show', [
            'transfer' => $transfer,
            'canSend' => $transfer->canBeSentBy($user),
            'canReceive' => $transfer->canBeReceivedBy($user),
            'destinationWarehouses' => Warehouse::active()->where('branch_id', $transfer->to_branch_id)->orderBy('name')->get(),
        ]);
    }

    public function edit($id)
    {
        $transfer = $this->find($id, ['items']);
        abort_unless($transfer->isDraft(), 403, 'Branch Transfer yang sudah dikirim tidak bisa diubah.');
        abort_unless($transfer->canBeSentBy(Auth::user()), 403, 'Hanya branch asal yang bisa mengubah dokumen ini.');

        return view('branch-transfer.form', $this->formData($transfer));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $transfer = $this->find($id);
        if (! $transfer->isDraft() || ! $transfer->canBeSentBy(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Hanya draft dari branch Anda yang bisa diubah.'], 422);
        }

        $validated = $this->validateTransfer($request);

        DB::transaction(function () use ($transfer, $validated) {
            $transfer->update($this->headerAttributes($validated));
            $transfer->items()->delete();
            $this->syncItems($transfer, $validated['items'], $validated['units']);
        });

        return response()->json(['success' => true, 'message' => 'Branch Transfer '.$transfer->transfer_number.' diperbarui.', 'id' => $transfer->id]);
    }

    public function destroy($id): JsonResponse
    {
        $transfer = $this->find($id);
        if (! $transfer->isDraft() || ! $transfer->canBeSentBy(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Hanya draft dari branch Anda yang bisa dihapus.'], 422);
        }

        $transfer->delete();

        return response()->json(['success' => true, 'message' => 'Branch Transfer dihapus.']);
    }

    /**
     * Kirim: baris stok keluar dari gudang asal -> "dalam pengiriman".
     */
    public function ship($id): JsonResponse
    {
        $transfer = $this->find($id, ['items']);
        if (! $transfer->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Branch Transfer sudah dikirim.'], 422);
        }
        if (! $transfer->canBeSentBy(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Hanya branch asal yang bisa mengirim dokumen ini.'], 403);
        }

        $service = app(StockService::class);
        $date = $transfer->transfer_date?->toDateString() ?? now()->toDateString();

        try {
            DB::transaction(function () use ($transfer, $service, $date) {
                $stocks = $service->lock($transfer->items->pluck('stock_id'));
                foreach ($transfer->items as $item) {
                    $stock = $stocks->get($item->stock_id);
                    if (! $stock) {
                        throw new StockException('Baris stok barang ke-'.$item->sort_order.' tidak ditemukan.');
                    }
                    $service->assertPosition($stock, (int) $transfer->from_warehouse_id, false);
                    $service->take($stock, (float) $item->qty, StockLog::TYPE_SHIP_OUT, [
                        'date' => $date,
                        'ref_type' => StockLog::REF_BRANCH_TRANSFER_ITEM,
                        'ref_id' => $item->id,
                        'note' => 'Kirim ke '.$transfer->toBranch?->name.' ('.$transfer->transfer_number.')',
                    ], false);
                }

                $transfer->update([
                    'status' => BranchTransfer::STATUS_IN_TRANSIT,
                    'shipped_by' => Auth::id(),
                    'shipped_at' => now(),
                ]);
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('ship_branch_transfer', 'Branch Transfer '.$transfer->transfer_number.' dikirim oleh '.Auth::user()->display_name, 'MOD_BRANCH_TRANSFER', $transfer);

        return response()->json(['success' => true, 'message' => 'Barang dikirim. Stok tercatat dalam pengiriman sampai diterima '.$transfer->toBranch?->name.'.']);
    }

    public function pdf($id)
    {
        $transfer = $this->find($id, ['items.stock.masterProduct', 'fromBranch', 'toBranch', 'fromWarehouse', 'creator', 'shippedBy']);
        abort_if($transfer->isDraft(), 403, 'Surat jalan tersedia setelah barang dikirim.');

        return Pdf::loadView('branch-transfer.pdf', compact('transfer'))
            ->setPaper('a4', 'portrait')
            ->stream('SuratJalan-'.$transfer->transfer_number.'.pdf');
    }

    /**
     * Simpan hasil pemeriksaan (boleh berkali-kali sebelum diterima).
     */
    public function inspect(Request $request, $id): JsonResponse
    {
        $transfer = $this->find($id, ['items']);
        if ($error = $this->receiveGuard($transfer)) {
            return $error;
        }

        $validated = $request->validate([
            'to_warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'receive_note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.received_qty' => 'nullable|numeric|min:0',
            'items.*.condition_ok' => 'nullable|boolean',
            'items.*.receive_note' => 'nullable|string|max:255',
        ]);

        if (! empty($validated['to_warehouse_id']) && ! $this->destinationWarehouse($transfer, (int) $validated['to_warehouse_id'])) {
            return response()->json(['success' => false, 'message' => 'Gudang tujuan harus gudang aktif milik '.$transfer->toBranch?->name.'.'], 422);
        }

        $items = $transfer->items->keyBy('id');
        DB::transaction(function () use ($transfer, $validated, $items) {
            $transfer->update([
                'to_warehouse_id' => $validated['to_warehouse_id'] ?? $transfer->to_warehouse_id,
                'receive_note' => $validated['receive_note'] ?? $transfer->receive_note,
            ]);
            foreach ($validated['items'] as $row) {
                $item = $items->get((int) $row['id']);
                if (! $item || ! array_key_exists('received_qty', $row) || $row['received_qty'] === null) {
                    continue;
                }
                $item->update([
                    'received_qty' => StockService::round((float) $row['received_qty']),
                    'condition_ok' => (bool) ($row['condition_ok'] ?? false),
                    'receive_note' => $row['receive_note'] ?? null,
                    'checked_at' => now(),
                ]);
            }
        });

        $transfer->load('items');
        $mismatch = $transfer->items->filter(fn (BranchTransferItem $i) => $i->checkState() === 'mismatch')->count();
        $unchecked = $transfer->items->filter(fn (BranchTransferItem $i) => $i->checkState() === 'unchecked')->count();

        return response()->json([
            'success' => true,
            'message' => 'Hasil pemeriksaan disimpan.'.($mismatch ? ' '.$mismatch.' barang tidak sesuai.' : '').($unchecked ? ' '.$unchecked.' barang belum diperiksa.' : ''),
            'ready' => ! $mismatch && ! $unchecked && $transfer->to_warehouse_id,
        ]);
    }

    /**
     * Selesaikan penerimaan: hanya bila semua barang sudah diperiksa dan sesuai.
     */
    public function receive($id): JsonResponse
    {
        $transfer = $this->find($id, ['items.stock.masterProduct', 'toBranch']);
        if ($error = $this->receiveGuard($transfer)) {
            return $error;
        }

        $warehouse = $transfer->to_warehouse_id ? $this->destinationWarehouse($transfer, (int) $transfer->to_warehouse_id) : null;
        if (! $warehouse) {
            return response()->json(['success' => false, 'message' => 'Pilih gudang tujuan milik '.$transfer->toBranch?->name.' lalu simpan pemeriksaan.'], 422);
        }

        $problems = $transfer->items->reject(fn (BranchTransferItem $i) => $i->isMatched())
            ->map(fn (BranchTransferItem $i) => trim(($i->stock?->masterProduct?->code ?? 'Barang').' '.($i->stock?->identity_label ?? ''))
                .($i->checked_at ? ' (tidak sesuai)' : ' (belum diperiksa)'));
        if ($problems->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Penerimaan tidak bisa diselesaikan. Semua barang harus diperiksa dan sesuai (qty sama, kondisi baik): '.$problems->implode(', ').'.',
            ], 422);
        }

        $service = app(StockService::class);
        $date = now()->toDateString();

        try {
            DB::transaction(function () use ($transfer, $warehouse, $service, $date) {
                $origins = $service->lock($transfer->items->pluck('stock_id'));
                foreach ($transfer->items as $item) {
                    $origin = $origins->get($item->stock_id);
                    $arrived = $service->arrive($origin, (float) $item->qty, $warehouse->id, $origin->project_id ? (int) $origin->project_id : null, [
                        'date' => $date,
                        'ref_type' => StockLog::REF_BRANCH_TRANSFER_ITEM,
                        'ref_id' => $item->id,
                        'note' => 'Terima dari '.$transfer->fromBranch?->name.' ('.$transfer->transfer_number.')',
                    ]);
                    $item->update(['received_stock_id' => $arrived->id]);
                }

                $transfer->update([
                    'status' => BranchTransfer::STATUS_RECEIVED,
                    'received_by' => Auth::id(),
                    'received_at' => now(),
                ]);
            });
        } catch (StockException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::record('receive_branch_transfer', 'Branch Transfer '.$transfer->transfer_number.' diterima di '.$warehouse->name.' oleh '.Auth::user()->display_name, 'MOD_BRANCH_TRANSFER', $transfer);

        return response()->json(['success' => true, 'message' => 'Barang diterima di '.$warehouse->name.'.']);
    }

    public function uploadPhotos(Request $request, $id, $itemId): JsonResponse
    {
        $transfer = $this->find($id);
        if ($error = $this->receiveGuard($transfer)) {
            return $error;
        }
        $item = $transfer->items()->findOrFail($itemId);

        $request->validate([
            'files' => 'required|array|min:1|max:'.Document::MAX_FILES,
            'files.*' => Document::IMAGE_RULE,
            'notes' => 'nullable|string|max:255',
        ], [
            'files.required' => 'Pilih minimal satu foto.',
            'files.*.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'files.*.max' => 'Ukuran setiap foto tidak boleh lebih dari 10MB.',
        ]);

        $photos = DB::transaction(fn () => collect($request->file('files'))
            ->map(fn ($file) => Document::storeFor($item, $file, Document::TYPE_RECEIPT_PHOTO, $request->input('notes'))));

        return response()->json([
            'success' => true,
            'message' => $photos->count().' foto diunggah.',
            'photos' => $photos->map(fn (Document $doc) => [
                'id' => $doc->id,
                'url' => route('branch-transfer.photos.show', [$transfer->id, $item->id, $doc->id]),
                'delete_url' => route('branch-transfer.photos.destroy', [$transfer->id, $item->id, $doc->id]),
            ])->values(),
        ]);
    }

    public function viewPhoto($id, $itemId, $documentId)
    {
        $transfer = $this->find($id);
        $item = $transfer->items()->findOrFail($itemId);
        $document = $item->photos()->findOrFail($documentId);

        return $document->inlineResponse();
    }

    public function deletePhoto($id, $itemId, $documentId): JsonResponse
    {
        $transfer = $this->find($id);
        $item = $transfer->items()->findOrFail($itemId);
        $document = $item->photos()->findOrFail($documentId);

        if (! $transfer->isInTransit() || (int) $document->uploaded_by !== (int) Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Foto hanya bisa dihapus oleh pengunggahnya sebelum barang diterima.'], 422);
        }

        $document->delete();

        return response()->json(['success' => true, 'message' => 'Foto dihapus.']);
    }

    private function find($id, array $with = []): BranchTransfer
    {
        $transfer = BranchTransfer::with(array_merge(['fromBranch', 'toBranch'], $with))->findOrFail($id);
        abort_unless(Auth::user()->canAccessBranch($transfer->from_branch_id) || Auth::user()->canAccessBranch($transfer->to_branch_id), 404);

        return $transfer;
    }

    private function receiveGuard(BranchTransfer $transfer): ?JsonResponse
    {
        if (! $transfer->isInTransit()) {
            return response()->json(['success' => false, 'message' => 'Branch Transfer tidak sedang dalam pengiriman.'], 422);
        }
        if (! $transfer->canBeReceivedBy(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Hanya branch tujuan yang bisa memeriksa dan menerima barang ini.'], 403);
        }

        return null;
    }

    private function destinationWarehouse(BranchTransfer $transfer, int $warehouseId): ?Warehouse
    {
        return Warehouse::active()->where('branch_id', $transfer->to_branch_id)->find($warehouseId);
    }

    private function formData(?BranchTransfer $transfer): array
    {
        $user = Auth::user();

        return [
            'transfer' => $transfer,
            'warehouses' => Warehouse::active()->with('branch')->whereNotNull('branch_id')->ofBranch($user->branch_id)->orderBy('name')->get(),
            'branches' => Branch::active()->orderBy('name')->get(),
            'selected' => $transfer?->items->mapWithKeys(fn ($item) => [$item->stock_id => $item->qty]) ?? collect(),
        ];
    }

    private function validateTransfer(Request $request): array
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|integer|exists:warehouses,id',
            'to_branch_id' => 'required|integer|exists:branches,id',
            'transfer_date' => 'required|date',
            'courier' => 'nullable|string|max:150',
            'vehicle_no' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.stock_id' => 'required|integer|distinct',
            'items.*.qty' => 'required|numeric|gt:0',
        ], [
            'items.required' => 'Pilih minimal satu barang yang dikirim.',
            'items.*.stock_id.distinct' => 'Baris stok yang sama tidak boleh dipilih dua kali.',
        ]);

        $fail = fn (string $message) => abort(response()->json(['success' => false, 'message' => $message], 422));
        $warehouse = Warehouse::active()->find($validated['from_warehouse_id']);
        if (! $warehouse || ! $warehouse->branch_id) {
            $fail('Gudang asal harus aktif dan sudah terhubung ke branch.');
        }
        if (! Auth::user()->canAccessBranch($warehouse->branch_id)) {
            $fail('Gudang asal bukan milik branch Anda.');
        }
        if ((int) $validated['to_branch_id'] === (int) $warehouse->branch_id) {
            $fail('Branch tujuan sama dengan branch asal. Untuk pindah gudang dalam satu branch gunakan Transfer Stok.');
        }
        if (! Branch::active()->whereKey($validated['to_branch_id'])->exists()) {
            $fail('Branch tujuan tidak aktif.');
        }

        $service = app(StockService::class);
        $stocks = Stock::with('masterProduct')->whereIn('id', collect($validated['items'])->pluck('stock_id'))->get()->keyBy('id');
        try {
            foreach (array_values($validated['items']) as $i => $row) {
                $stock = $stocks->get((int) $row['stock_id']);
                if (! $stock || ! $stock->is_open) {
                    throw new StockException('Barang ke-'.($i + 1).': stok sudah tidak tersedia.');
                }
                $service->assertPosition($stock, $warehouse->id, false);
                if ((float) $row['qty'] > $stock->qty + Stock::EPSILON) {
                    throw new StockException('Qty '.$service->label($stock).' melebihi sisa ('.StockCost::qty($stock->qty).').');
                }
                if ($stock->isSerialized() && abs((float) $row['qty'] - $stock->qty) >= Stock::EPSILON) {
                    throw new StockException($service->label($stock).' adalah barang ber-SN dan harus dikirim utuh.');
                }
            }
        } catch (StockException $e) {
            $fail($e->getMessage());
        }

        $validated['from_branch_id'] = $warehouse->branch_id;
        $validated['units'] = $stocks->map(fn (Stock $stock) => $stock->unit)->all();

        return $validated;
    }

    private function headerAttributes(array $validated): array
    {
        return collect($validated)->only([
            'transfer_date', 'from_branch_id', 'from_warehouse_id', 'to_branch_id', 'courier', 'vehicle_no', 'driver_name', 'note',
        ])->all();
    }

    private function syncItems(BranchTransfer $transfer, array $items, array $units): void
    {
        foreach (array_values($items) as $i => $row) {
            $transfer->items()->create([
                'stock_id' => $row['stock_id'],
                'qty' => StockService::round((float) $row['qty']),
                'unit' => $units[(int) $row['stock_id']] ?? null,
                'sort_order' => $i + 1,
            ]);
        }
    }
}

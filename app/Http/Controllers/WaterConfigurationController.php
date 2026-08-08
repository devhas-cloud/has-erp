<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Module;
use App\Models\User;
use App\Models\UserAccessControl;
use App\Models\WaterConfiguration;
use App\Models\WaterConfigurationItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WaterConfigurationController extends Controller
{
    private const MODULE_CODE = 'MOD_WATER_CONFIGURATION';

    /**
     * Cek hak akses user terhadap modul ini (Admin selalu lolos).
     */
    private function userCan(string $permission): bool
    {
        $user = Auth::user();

        if ($user->role === 'Admin') {
            return true;
        }

        $module = Module::where('module_code', self::MODULE_CODE)->first();
        if (! $module) {
            return false;
        }

        $uac = UserAccessControl::where('user_id', $user->id)
            ->where('module_id', $module->id)
            ->first();

        return $uac && $uac->{$permission};
    }

    public function index()
    {
        $canCreate = $this->userCan('can_create');

        return view('water-configuration.index', compact('canCreate'));
    }

    /**
     * Halaman form untuk membuat quotation baru (bukan popup).
     */
    public function create()
    {
        $categories = $this->categorySuggestions();

        return view('water-configuration.form', [
            'quotation' => null,
            'items' => [],
            'categories' => $categories,
        ]);
    }

    /**
     * Kategori fleksibel: saran diambil dari master_products + item yang sudah pernah dipakai.
     */
    private function categorySuggestions(): array
    {
        $fromProducts = MasterProduct::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        $fromItems = WaterConfigurationItem::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        return $fromProducts
            ->merge($fromItems)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function data(Request $request): JsonResponse
    {
        $query = WaterConfiguration::with(['creator', 'finalChecker']);

        $recordsTotal = WaterConfiguration::count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('quotation_number', 'like', "%{$searchValue}%")
                    ->orWhere('to_name', 'like', "%{$searchValue}%")
                    ->orWhere('location', 'like', "%{$searchValue}%")
                    ->orWhere('pic_name', 'like', "%{$searchValue}%")
                    ->orWhere('sales_name', 'like', "%{$searchValue}%")
                    ->orWhereHas('creator', fn ($q) => $q->where('username', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columnOrderMap = [
            1 => 'quotation_number',
            2 => 'to_name',
            6 => 'quotation_date',
        ];

        if (isset($columnOrderMap[$orderColumnIndex])) {
            $query->orderBy($columnOrderMap[$orderColumnIndex], $orderDirection);
        }
        $query->orderBy('id', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $quotations = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($quotations as $i => $quotation) {
            $data[] = [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'to_name' => $quotation->to_name ?? '—',
                'location' => $quotation->location ?? '—',
                'pic_name' => $quotation->pic_name ?? '—',
                'sales_name' => $quotation->sales_name ?? '—',
                'quotation_date' => $quotation->quotation_date?->format('d/m/Y') ?? '—',
                'quotation_date_raw' => $quotation->quotation_date?->toISOString(),
                'item_count' => $quotation->items()->count(),
                'creator_name' => $quotation->creator?->username ?? '—',
                'status' => $quotation->status,
                'status_label' => $quotation->status_label,
                'status_badge' => $quotation->statusBadgeHtml(),
            ];
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_name' => 'nullable|string|max:150',
            'address' => 'nullable|string',
            'location' => 'nullable|string|max:150',
            'pic_name' => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:50',
            'pic_email' => 'nullable|email|max:100',
            'sales_name' => 'nullable|string|max:100',
            'quotation_date' => 'nullable|date',
            'parameter_note' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:master_products,id',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        try {
            $quotation = DB::transaction(function () use ($validated) {
                $quotation = WaterConfiguration::create([
                    'quotation_number' => WaterConfiguration::nextQuotationNumber(),
                    'to_name' => $validated['to_name'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'pic_name' => $validated['pic_name'] ?? null,
                    'pic_phone' => $validated['pic_phone'] ?? null,
                    'pic_email' => $validated['pic_email'] ?? null,
                    'sales_name' => $validated['sales_name'] ?? null,
                    'quotation_date' => $validated['quotation_date'] ?? null,
                    'parameter_note' => $validated['parameter_note'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'status' => WaterConfiguration::STATUS_DRAFT,
                    'created_by' => Auth::id(),
                ]);

                $this->syncItems($quotation, $validated['items']);

                return $quotation;
            });

            Log::record(
                'create_water_configuration',
                "Quotation {$quotation->quotation_number} dibuat",
                self::MODULE_CODE,
                $quotation
            );

            return response()->json([
                'success' => true,
                'message' => 'Quotation berhasil dibuat. Silakan submit untuk approval.',
                'id' => $quotation->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan quotation: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Halaman form edit (bukan popup). Hanya quotation berstatus Draft yang bisa diedit.
     */
    public function edit($id)
    {
        $quotation = WaterConfiguration::with(['items.product'])->findOrFail($id);

        if ($quotation->status !== WaterConfiguration::STATUS_DRAFT) {
            return redirect()->route('water-configuration.index')
                ->with('error', 'Quotation yang sudah dikirim untuk approval tidak bisa diedit.');
        }

        $categories = $this->categorySuggestions();

        return view('water-configuration.form', [
            'quotation' => $quotation,
            'items' => $quotation->items,
            'categories' => $categories,
        ]);
    }

    /**
     * Pencarian produk dari master_products untuk picker item quotation.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));

        $query = MasterProduct::query()
            ->where('status', 'Active')
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%");
            });
        }

        $products = $query->limit(20)->get(['id', 'name', 'code', 'brand', 'category', 'price']);

        return response()->json([
            'results' => $products->map(fn ($product) => [
                'id' => $product->id,
                'text' => $product->name,
                'name' => $product->name,
                'code' => $product->code,
                'brand' => $product->brand,
                'category' => $product->category,
            ]),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $quotation = WaterConfiguration::findOrFail($id);

        if ($quotation->status !== WaterConfiguration::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation yang sudah dikirim untuk approval tidak bisa diedit.',
            ], 422);
        }

        $validated = $request->validate([
            'to_name' => 'nullable|string|max:150',
            'address' => 'nullable|string',
            'location' => 'nullable|string|max:150',
            'pic_name' => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:50',
            'pic_email' => 'nullable|email|max:100',
            'sales_name' => 'nullable|string|max:100',
            'quotation_date' => 'nullable|date',
            'parameter_note' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:master_products,id',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($quotation, $validated) {
                $quotation->update([
                    'to_name' => $validated['to_name'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'pic_name' => $validated['pic_name'] ?? null,
                    'pic_phone' => $validated['pic_phone'] ?? null,
                    'pic_email' => $validated['pic_email'] ?? null,
                    'sales_name' => $validated['sales_name'] ?? null,
                    'quotation_date' => $validated['quotation_date'] ?? null,
                    'parameter_note' => $validated['parameter_note'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $this->syncItems($quotation, $validated['items']);
            });

            Log::record(
                'update_water_configuration',
                "Quotation {$quotation->quotation_number} diupdate",
                self::MODULE_CODE,
                $quotation
            );

            return response()->json([
                'success' => true,
                'message' => 'Quotation berhasil diupdate.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate quotation: '.$e->getMessage(),
            ], 422);
        }
    }

    public function destroy($id): JsonResponse
    {
        $quotation = WaterConfiguration::findOrFail($id);

        if ($quotation->status !== WaterConfiguration::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya quotation berstatus Draft yang bisa dihapus.',
            ], 422);
        }

        $quotation->delete();

        Log::record(
            'delete_water_configuration',
            "Quotation {$quotation->quotation_number} dihapus",
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quotation berhasil dihapus.',
        ]);
    }

    public function show($id)
    {
        $quotation = WaterConfiguration::with(['items', 'creator', 'finalChecker'])->findOrFail($id);

        $user = Auth::user();
        $canApprove = $this->canApproveQuotation($quotation);
        $canReject = $this->canRejectQuotation($quotation);

        return view('water-configuration.show', compact('quotation', 'canApprove', 'canReject'));
    }

    /**
     * Kirim quotation untuk approval (status draft -> waiting_approval).
     * Notifikasi dikirim ke semua user satu divisi dengan pembuat (selain pembuat)
     * yang memiliki hak approve pada modul ini.
     */
    public function submit($id): JsonResponse
    {
        $quotation = WaterConfiguration::findOrFail($id);

        if ($quotation->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembuat quotation yang bisa submit untuk approval.',
            ], 403);
        }

        if ($quotation->status !== WaterConfiguration::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak dalam status Draft.',
            ], 422);
        }

        if ($quotation->items()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation harus memiliki minimal 1 item sebelum di-submit.',
            ], 422);
        }

        $quotation->update(['status' => WaterConfiguration::STATUS_WAITING_APPROVAL]);

        $this->notifyApprovers($quotation);

        Log::record(
            'submit_water_configuration',
            "Quotation {$quotation->quotation_number} dikirim untuk approval",
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quotation dikirim untuk approval.',
        ]);
    }

    /**
     * ATURAN APPROVAL DIVISI:
     * 1. User dengan divisi WATER (pembuat dokumen) TIDAK BISA approve dokumen yang dia buat sendiri.
     * 2. Yang bisa approve adalah user LAIN yang SATU DIVISI dengan pembuat (divisi WATER).
     * 3. User dari divisi lain TIDAK BISA approve.
     * 4. Role Admin selalu bisa approve (override).
     */
    public function approve($id): JsonResponse
    {
        $quotation = WaterConfiguration::with('creator')->findOrFail($id);

        if ($quotation->status !== WaterConfiguration::STATUS_WAITING_APPROVAL) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak dalam status Waiting Approval.',
            ], 422);
        }

        if (! $this->canApproveQuotation($quotation)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa approve quotation ini. Pembuat tidak bisa approve dokumennya sendiri, dan hanya user satu divisi dengan pembuat yang bisa approve.',
            ], 403);
        }

        $quotation->update([
            'status' => WaterConfiguration::STATUS_APPROVED,
            'final_checked_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $this->notifyCreator(
            $quotation,
            'quotation_approved',
            'Quotation Disetujui',
            "Quotation {$quotation->quotation_number} telah disetujui oleh ".Auth::user()->username.'.'
        );

        Log::record(
            'approve_water_configuration',
            "Quotation {$quotation->quotation_number} disetujui oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quotation disetujui.',
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $quotation = WaterConfiguration::with('creator')->findOrFail($id);

        if ($quotation->status !== WaterConfiguration::STATUS_WAITING_APPROVAL) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak dalam status Waiting Approval.',
            ], 422);
        }

        if (! $this->canRejectQuotation($quotation)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa menolak quotation ini. Hanya user satu divisi dengan pembuat (bukan pembuatnya) yang bisa reject.',
            ], 403);
        }

        $validated = $request->validate([
            'approval_note' => 'required|string|max:1000',
        ]);

        $quotation->update([
            'status' => WaterConfiguration::STATUS_REJECTED,
            'final_checked_by' => Auth::id(),
            'approval_note' => $validated['approval_note'],
            'rejected_at' => now(),
        ]);

        $this->notifyCreator(
            $quotation,
            'quotation_rejected',
            'Quotation Ditolak',
            "Quotation {$quotation->quotation_number} ditolak oleh ".Auth::user()->username.'. Alasan: '.$validated['approval_note']
        );

        Log::record(
            'reject_water_configuration',
            "Quotation {$quotation->quotation_number} ditolak oleh ".Auth::user()->username,
            self::MODULE_CODE,
            $quotation
        );

        return response()->json([
            'success' => true,
            'message' => 'Quotation ditolak.',
        ]);
    }

    public function print($id)
    {
        $quotation = WaterConfiguration::with(['items', 'creator', 'finalChecker'])->findOrFail($id);

        return view('water-configuration.print', compact('quotation'));
    }

    /**
     * Aturan inti approval divisi (dipakai di approve() dan show()).
     */
    private function canApproveQuotation(WaterConfiguration $quotation): bool
    {
        $user = Auth::user();

        if ($user->role === 'Admin') {
            return true;
        }

        if (! $this->userCan('can_approve')) {
            return false;
        }

        // Pembuat (divisi WATER) tidak bisa approve dokumennya sendiri.
        if ($quotation->created_by === $user->id) {
            return false;
        }

        // Approver harus SATU DIVISI dengan pembuat (divisi WATER).
        if ($user->division_id !== $quotation->creator?->division_id) {
            return false;
        }

        return true;
    }

    private function canRejectQuotation(WaterConfiguration $quotation): bool
    {
        return $this->canApproveQuotation($quotation);
    }

    /**
     * Kirim notifikasi ke semua user satu divisi dengan pembuat (selain pembuat)
     * yang memiliki hak approve modul ini.
     */
    private function notifyApprovers(WaterConfiguration $quotation): void
    {
        $module = Module::where('module_code', self::MODULE_CODE)->first();
        if (! $module) {
            return;
        }

        $creator = $quotation->creator;
        if (! $creator || ! $creator->division_id) {
            return;
        }

        $uacUserIds = UserAccessControl::where('module_id', $module->id)
            ->where('can_approve', true)
            ->pluck('user_id')
            ->toArray();

        $approvers = User::where('division_id', $creator->division_id)
            ->where('id', '!=', $creator->id)
            ->whereIn('id', $uacUserIds)
            ->get();

        foreach ($approvers as $approver) {
            $quotation->notify(
                $approver,
                'quotation_approval_required',
                'Quotation Menunggu Approval',
                "Quotation {$quotation->quotation_number} dari {$creator->username} menunggu approval Anda.",
                [
                    'quotation_id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                ]
            );
        }
    }

    private function notifyCreator(WaterConfiguration $quotation, string $type, string $title, string $body): void
    {
        $creator = $quotation->creator;
        if (! $creator) {
            return;
        }

        $quotation->notify($creator, $type, $title, $body, [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
        ]);
    }

    private function syncItems(WaterConfiguration $quotation, array $items): void
    {
        $quotation->items()->delete();

        $payload = [];
        foreach (array_values($items) as $i => $item) {
            $payload[] = [
                'water_configuration_id' => $quotation->id,
                'product_id' => $item['product_id'] ?? null,
                'category' => $item['category'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => $item['description'],
                'qty' => (int) ($item['qty'] ?? 1),
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        WaterConfigurationItem::insert($payload);
    }
}

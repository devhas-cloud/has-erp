<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestTemplate;
use App\Models\MaterialRequestTemplateItem;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Template Material Request (tab "Template Material" di daftar MR).
 * Dipakai bersama semua user; hak mengikuti modul Material Request
 * (buat = can_create, ubah = can_update, hapus = can_delete).
 * Qty template = kebutuhan per 1 set; di form MR bisa dikalikan.
 */
class MaterialRequestTemplateController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $query = MaterialRequestTemplate::with(['creator', 'updater'])->withCount('items');
        $recordsTotal = (clone $query)->count();

        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('items.masterProduct', fn ($p) => $p->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")));
        }

        $recordsFiltered = (clone $query)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $rows = $query->orderBy('name')->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows->map(fn (MaterialRequestTemplate $t, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'item_count' => $t->items_count,
                'status' => $t->status,
                'creator' => $t->creator?->display_name ?? '—',
                'updated_at' => $t->updated_at?->format('d/m/Y H:i'),
                'updated_by' => ($t->updater ?? $t->creator)?->display_name ?? '—',
            ]),
        ]);
    }

    /** Daftar template aktif untuk popup "Ambil dari Template" di form MR. */
    public function options(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $templates = MaterialRequestTemplate::active()
            ->withCount('items')
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhereHas('items.masterProduct', fn ($p) => $p->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))))
            ->orderBy('name')
            ->limit(100)
            ->get();

        return response()->json(['data' => $templates->map(fn (MaterialRequestTemplate $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'item_count' => $t->items_count,
        ])]);
    }

    /** Isi template (produk aktif saja) — untuk pratinjau dan diambil ke form MR. */
    public function items($id): JsonResponse
    {
        $template = MaterialRequestTemplate::with('items.masterProduct')->findOrFail($id);

        $items = $template->items->map(function (MaterialRequestTemplateItem $item) {
            $p = $item->masterProduct;

            return [
                'master_product_id' => $item->master_product_id,
                'code' => $p?->code,
                'name' => $p?->name,
                'unit' => $p?->unit,
                'is_material' => (bool) $p?->isMaterial(),
                'active' => $p?->status === 'Active',
                'qty' => $item->qty,
                'note' => $item->note,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'template' => ['id' => $template->id, 'name' => $template->name, 'description' => $template->description, 'status' => $template->status],
            'items' => $items,
        ]);
    }

    public function create()
    {
        return view('material-request.template-form', ['template' => null, 'initialItems' => collect()]);
    }

    public function edit($id)
    {
        $template = MaterialRequestTemplate::with('items.masterProduct')->findOrFail($id);

        return view('material-request.template-form', [
            'template' => $template,
            'initialItems' => $template->items->map(fn (MaterialRequestTemplateItem $item) => [
                'master_product_id' => $item->master_product_id,
                'code' => $item->masterProduct?->code,
                'name' => $item->masterProduct?->name,
                'unit' => $item->masterProduct?->unit,
                'is_material' => (bool) $item->masterProduct?->isMaterial(),
                'qty' => $item->qty,
                'note' => $item->note,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTemplate($request);

        $template = DB::transaction(function () use ($validated) {
            $template = MaterialRequestTemplate::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? MaterialRequestTemplate::STATUS_ACTIVE,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->syncItems($template, $validated['items']);

            return $template;
        });

        Log::record('create_material_request_template', 'Template Material "'.$template->name.'" dibuat oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE, $template);

        return response()->json(['success' => true, 'message' => 'Template "'.$template->name.'" tersimpan.', 'id' => $template->id]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $template = MaterialRequestTemplate::findOrFail($id);
        $validated = $this->validateTemplate($request, $template);

        DB::transaction(function () use ($template, $validated) {
            $template->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? $template->status,
                'updated_by' => Auth::id(),
            ]);
            $template->items()->delete();
            $this->syncItems($template, $validated['items']);
        });

        Log::record('update_material_request_template', 'Template Material "'.$template->name.'" diubah oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE, $template);

        return response()->json(['success' => true, 'message' => 'Template "'.$template->name.'" diperbarui.', 'id' => $template->id]);
    }

    public function duplicate($id): JsonResponse
    {
        $source = MaterialRequestTemplate::with('items')->findOrFail($id);

        $copy = DB::transaction(function () use ($source) {
            $name = $this->copyName($source->name);
            $copy = MaterialRequestTemplate::create([
                'name' => $name,
                'description' => $source->description,
                'status' => $source->status,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            foreach ($source->items as $item) {
                $copy->items()->create($item->only(['master_product_id', 'qty', 'note', 'sort_order']));
            }

            return $copy;
        });

        Log::record('create_material_request_template', 'Template Material "'.$copy->name.'" diduplikat dari "'.$source->name.'" oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE, $copy);

        return response()->json(['success' => true, 'message' => 'Template diduplikat menjadi "'.$copy->name.'".', 'id' => $copy->id]);
    }

    public function destroy($id): JsonResponse
    {
        $template = MaterialRequestTemplate::findOrFail($id);
        $name = $template->name;
        $template->delete();

        Log::record('delete_material_request_template', 'Template Material "'.$name.'" dihapus oleh '.Auth::user()->display_name, MaterialRequest::MODULE_CODE);

        return response()->json(['success' => true, 'message' => 'Template "'.$name.'" dihapus.']);
    }

    private function copyName(string $name): string
    {
        $base = mb_substr($name, 0, 140).' (Salinan';
        $candidate = $base.')';
        for ($n = 2; MaterialRequestTemplate::where('name', $candidate)->exists(); $n++) {
            $candidate = $base.' '.$n.')';
        }

        return $candidate;
    }

    private function validateTemplate(Request $request, ?MaterialRequestTemplate $template = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('material_request_templates', 'name')->ignore($template?->id)],
            'description' => 'nullable|string|max:2000',
            'status' => 'nullable|in:Active,Inactive',
            'items' => 'required|array|min:1',
            'items.*.master_product_id' => 'required|integer|distinct|exists:master_products,id',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Nama template wajib diisi.',
            'name.unique' => 'Nama template sudah dipakai.',
            'items.required' => 'Tambahkan minimal satu material.',
            'items.*.master_product_id.distinct' => 'Material yang sama tidak boleh ada dua kali dalam satu template.',
        ]);

        $products = MasterProduct::whereIn('id', collect($validated['items'])->pluck('master_product_id'))->get()->keyBy('id');
        foreach ($validated['items'] as $row) {
            $product = $products[$row['master_product_id']];
            if (! $product->isMaterial() && abs($row['qty'] - round($row['qty'])) >= 0.00005) {
                abort(response()->json(['success' => false, 'message' => $product->code.' adalah barang ber-SN; qty harus bilangan bulat.'], 422));
            }
        }

        return $validated;
    }

    private function syncItems(MaterialRequestTemplate $template, array $items): void
    {
        foreach (array_values($items) as $i => $row) {
            $template->items()->create([
                'master_product_id' => $row['master_product_id'],
                'qty' => StockService::round((float) $row['qty']),
                'note' => $row['note'] ?? null,
                'sort_order' => $i + 1,
            ]);
        }
    }
}

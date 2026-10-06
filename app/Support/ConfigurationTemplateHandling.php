<?php

namespace App\Support;

use App\Models\ConfigurationTemplate;
use App\Models\ConfigurationTemplateItem;
use App\Models\Currency;
use App\Models\Log;
use App\Models\MasterProduct;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Handling template mandiri (berjudul, scoped per divisi) untuk seluruh
 * modul quote configuration (Water, Enviro, IH, Gas). Controllers pemakai
 * wajib mengimplement templateDivisionId() dan templateViewRoot().
 */
trait ConfigurationTemplateHandling
{
    abstract protected function templateDivisionId(): ?int;

    abstract protected function templateViewRoot(): string;

    /**
     * Daftar template milik divisi, dipakai sebagai pilihan isian saat membuat configuration.
     */
    public function templateList(): array
    {
        return ConfigurationTemplate::withCount('items')
            ->where('division_id', $this->templateDivisionId())
            ->orderBy('name')
            ->get()
            ->map(fn ($tpl) => [
                'id' => $tpl->id,
                'label' => $tpl->name.' ('.$tpl->items_count.' item)',
            ])
            ->values()
            ->all();
    }

    /**
     * Ambil item (parent + children) dari sebuah template sebagai isian,
     * dalam urutan DFS sehingga parent muncul sebelum children. Scoped per divisi.
     */
    public function fetchTemplate(Request $request, $id): JsonResponse
    {
        $template = ConfigurationTemplate::with(['items'])
            ->where('division_id', $this->templateDivisionId())
            ->findOrFail($id);

        $all = $template->items->keyBy('id');
        $children = $all->groupBy(fn ($item) => $item->parent_id ?: '_root');

        $items = [];
        $walk = function ($parentId) use (&$walk, &$items, $children) {
            foreach ($children[$parentId] ?? [] as $item) {
                $items[] = [
                    '_key' => 'tpl-'.$item->id,
                    'parent_key' => $item->parent_id ? 'tpl-'.$item->parent_id : null,
                    'item_no' => $item->item_no,
                    'product_id' => $item->product_id,
                    'category' => $item->category,
                    'part_number' => $item->part_number,
                    'description' => $item->description,
                    'qty' => $item->qty,
                ];
                $walk($item->id);
            }
        };

        $walk('_root');

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function templateShow($id)
    {
        $template = ConfigurationTemplate::with(['items.product', 'creator'])
            ->where('division_id', $this->templateDivisionId())
            ->findOrFail($id);

        return view('configuration.template-show', [
            'template' => $template,
            'backUrl' => route($this->templateViewRoot().'.index'),
            'editUrl' => route($this->templateViewRoot().'.template-edit', $template->id),
        ]);
    }

    public function templateData(Request $request): JsonResponse
    {
        $query = ConfigurationTemplate::with(['creator'])
            ->where('division_id', $this->templateDivisionId())
            ->withCount('items');

        $recordsTotal = $query->count();

        $searchValue = $request->input('search.value');
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('creator', fn ($u) => $u->where('username', 'like', "%{$searchValue}%")
                        ->orWhere('full_name', 'like', "%{$searchValue}%"));
            });
        }

        $recordsFiltered = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $data = $query->orderByDesc('id')
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(fn ($tpl, $i) => [
                'DT_RowIndex' => $start + $i + 1,
                'id' => $tpl->id,
                'name' => $tpl->name,
                'description' => $tpl->description,
                'item_count' => $tpl->items_count,
                'creator_name' => $tpl->creator?->display_name ?? '—',
                'created_at' => $tpl->created_at?->format('d/m/Y') ?? '—',
            ])
            ->all();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function templateCreate()
    {
        return view('configuration.template-form', [
            'template' => null,
            'items' => [],
            'storeUrl' => route($this->templateViewRoot().'.template-store'),
            'updateUrl' => route($this->templateViewRoot().'.template-update', '__ID__'),
            'indexUrl' => route($this->templateViewRoot().'.index'),
            'searchUrl' => route($this->templateViewRoot().'.search-products'),
        ]);
    }

    public function templateEdit($id)
    {
        $template = ConfigurationTemplate::with('items.product')
            ->where('division_id', $this->templateDivisionId())
            ->findOrFail($id);

        return view('configuration.template-form', [
            'template' => $template,
            'items' => $template->items,
            'storeUrl' => route($this->templateViewRoot().'.template-store'),
            'updateUrl' => route($this->templateViewRoot().'.template-update', '__ID__'),
            'indexUrl' => route($this->templateViewRoot().'.index'),
            'searchUrl' => route($this->templateViewRoot().'.search-products'),
        ]);
    }

    public function templateStore(Request $request): JsonResponse
    {
        $validated = $request->validate($this->templateValidationRules());

        if ($error = $this->rejectUnknownProducts($validated['items'])) {
            return $error;
        }

        try {
            $template = DB::transaction(function () use ($validated) {
                $template = ConfigurationTemplate::create([
                    'division_id' => $this->templateDivisionId(),
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $this->syncTemplateItems($template, $validated['items']);

                return $template;
            });

            Log::record(
                'create_'.$this->templateViewRoot().'_template',
                "Template '{$template->name}' dibuat",
                self::MODULE_CODE,
                $template
            );

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil dibuat.',
                'id' => $template->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan template: '.$e->getMessage(),
            ], 500);
        }
    }

    public function templateUpdate(Request $request, $id): JsonResponse
    {
        $template = ConfigurationTemplate::where('division_id', $this->templateDivisionId())
            ->findOrFail($id);

        $validated = $request->validate($this->templateValidationRules());

        if ($error = $this->rejectUnknownProducts($validated['items'])) {
            return $error;
        }

        try {
            DB::transaction(function () use ($template, $validated) {
                $template->update([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                ]);

                $this->syncTemplateItems($template, $validated['items']);
            });

            Log::record(
                'update_'.$this->templateViewRoot().'_template',
                "Template '{$template->name}' diupdate",
                self::MODULE_CODE,
                $template
            );

            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diupdate.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate template: '.$e->getMessage(),
            ], 500);
        }
    }

    public function templateDestroy($id): JsonResponse
    {
        $template = ConfigurationTemplate::where('division_id', $this->templateDivisionId())
            ->findOrFail($id);

        $name = $template->name;
        $template->delete();

        Log::record(
            'delete_'.$this->templateViewRoot().'_template',
            "Template '{$name}' dihapus",
            self::MODULE_CODE,
            $template
        );

        return response()->json([
            'success' => true,
            'message' => 'Template berhasil dihapus.',
        ]);
    }

    private function templateValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*._key' => 'required|string',
            'items.*.parent_key' => 'nullable|string',
            'items.*.item_no' => 'nullable|string|max:50',
            'items.*.product_id' => 'nullable|integer',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'nullable|integer',
        ];
    }

    /**
     * Simpan item hierarki template (parent-child) dengan jumlah query tetap,
     * meniru syncItems() milik quote_configuration_items.
     */
    private function syncTemplateItems(ConfigurationTemplate $template, array $items): void
    {
        $template->items()->delete();

        $items = array_values($items);

        $products = MasterProduct::with('currency')
            ->whereIn('id', collect($items)->pluck('product_id')->filter()->unique())
            ->get(['id', 'price', 'currency_id'])
            ->keyBy('id');

        $baseCurrency = strtoupper((string) (Currency::where('is_base', true)->value('name') ?: 'IDR'));

        $now = now();
        $payload = [];

        foreach ($items as $i => $item) {
            $qty = (int) ($item['qty'] ?? 0);
            $productId = $item['product_id'] ?? null;
            $pricing = $this->pricingFromProduct($productId ? $products->get($productId) : null, $qty, $baseCurrency);

            $payload[] = [
                'template_id' => $template->id,
                'item_no' => $item['item_no'] ?? null,
                'parent_id' => null,
                'product_id' => $productId,
                'category' => $item['category'] ?? null,
                'part_number' => $item['part_number'] ?? null,
                'description' => Quotation::sanitizeDescription($item['description'] ?? ''),
                'qty' => $qty,
                'price' => $pricing['price'],
                'price_currency' => $pricing['price_currency'],
                'currency' => $pricing['currency'],
                'unit' => $item['unit'] ?? null,
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ConfigurationTemplateItem::insert($payload);

        // Id hasil insert berurutan sama dengan urutan payload.
        $ids = ConfigurationTemplateItem::where('template_id', $template->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $indexByKey = [];
        foreach ($items as $i => $item) {
            $indexByKey[$item['_key']] = $i;
        }

        $parentByChildId = [];
        foreach ($items as $i => $item) {
            $parentKey = $item['parent_key'] ?? null;
            if (! $parentKey || ! isset($indexByKey[$parentKey]) || $indexByKey[$parentKey] === $i) {
                continue;
            }
            if (isset($ids[$i], $ids[$indexByKey[$parentKey]])) {
                $parentByChildId[$ids[$i]] = $ids[$indexByKey[$parentKey]];
            }
        }

        $this->assignParents($parentByChildId, 'configuration_template_items');
    }
}
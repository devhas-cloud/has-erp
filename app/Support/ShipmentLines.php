<?php

namespace App\Support;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pos;
use App\Models\PosItem;
use App\Models\Project;
use App\Models\QuotationItem;
use App\Models\Stock;
use App\Services\StockException;
use App\Services\StockService;
use Illuminate\Support\Collection;

/**
 * Aturan bersama baris dokumen pengiriman (koli Packing List dan baris DO
 * Langsung): setiap baris berisi minimal satu baris stok milik project di
 * gudang dokumen; unit ber-SN wajib ber-SN dan utuh; total per baris stok
 * tidak melebihi sisa yang belum dipesan Packing List Siap Kirim lain.
 *
 * Plus daftar item Quotation/POS project (sumber deskripsi) dengan qty yang
 * sudah dikirim (baris DO terposting).
 */
class ShipmentLines
{
    /**
     * @param  array  $lines  [['stocks' => [['stock_id' => .., 'qty' => ..], ..]], ..]
     * @return array<int, ?string> satuan per stock_id
     *
     * @throws StockException
     */
    public static function assertStocks(array $lines, int $projectId, int $warehouseId, ?int $exceptPackingListId = null): array
    {
        $ids = collect($lines)->flatMap(fn ($line) => collect($line['stocks'] ?? [])->pluck('stock_id'))->map(fn ($id) => (int) $id);
        $stocks = Stock::with('masterProduct')->whereIn('id', $ids->unique()->all() ?: [0])->get()->keyBy('id');
        $service = app(StockService::class);
        $totals = [];

        foreach (array_values($lines) as $i => $line) {
            $label = 'Baris '.($i + 1);
            if (empty($line['stocks'])) {
                throw new StockException($label.': pilih minimal satu barang dari stok.');
            }
            foreach ($line['stocks'] as $pick) {
                $stock = $stocks->get((int) $pick['stock_id']);
                if (! $stock || ! $stock->is_open) {
                    throw new StockException($label.': stok sudah tidak tersedia.');
                }
                $service->assertPosition($stock, $warehouseId, $projectId);
                if (! $stock->canLeave()) {
                    throw new StockException('Unit '.$service->label($stock).' belum punya SN. Lengkapi SN dulu sebelum dikirim.');
                }
                $qty = StockService::round((float) $pick['qty']);
                if ($stock->isSerialized() && abs($qty - $stock->qty) >= Stock::EPSILON) {
                    throw new StockException($service->label($stock).' adalah barang ber-SN dan harus dikirim utuh.');
                }
                $totals[$stock->id] = ($totals[$stock->id] ?? 0) + $qty;
            }
        }

        foreach ($totals as $stockId => $total) {
            $stock = $stocks->get($stockId);
            $available = StockService::available($stock, $exceptPackingListId);
            if ($total > $available + Stock::EPSILON) {
                throw new StockException('Qty '.$service->label($stock).' ('.StockCost::qty($total).') melebihi sisa yang bisa dikirim ('.StockCost::qty($available).').');
            }
        }

        return $stocks->map(fn (Stock $stock) => $stock->unit)->all();
    }

    /**
     * Item Quotation project untuk dipilih sebagai deskripsi baris: urut seperti
     * Quotation, dengan qty yang sudah dikirim (baris DO terposting).
     */
    public static function quotationItems(Project $project): Collection
    {
        $quotationId = $project->quotation_id;
        if (! $quotationId) {
            return collect();
        }

        $items = QuotationItem::where('quotation_id', $quotationId)->orderBy('sort_order')->orderBy('id')->get();
        $delivered = DeliveryOrderItem::whereIn('quotation_item_id', $items->pluck('id')->all() ?: [0])
            ->whereHas('deliveryOrder', fn ($q) => $q->where('status', DeliveryOrder::STATUS_POSTED))
            ->groupBy('quotation_item_id')
            ->selectRaw('quotation_item_id, SUM(qty) AS total')
            ->pluck('total', 'quotation_item_id');

        return $items->map(fn (QuotationItem $item) => [
            'id' => $item->id,
            'item_no' => $item->item_no,
            'is_child' => (bool) $item->parent_id,
            'part_number' => $item->part_number,
            'description' => self::text($item->description),
            'qty' => $item->qty !== null ? (float) $item->qty : null,
            'unit' => $item->unit,
            'delivered' => round((float) ($delivered[$item->id] ?? 0), 4),
        ])->values();
    }

    /** POS project (dokumen penjualan sumber item customer), bila ada. */
    public static function pos(Project $project): ?Pos
    {
        return Pos::with('items')->where('project_id', $project->id)->latest('id')->first();
    }

    /**
     * Item POS project dengan bentuk sama seperti quotationItems(): nomor item,
     * part number & hierarki diambil dari item Quotation yang ditautkan, qty &
     * satuan dari POS. Bedanya hanya deskripsi: penamaan customer di POS.
     * Item POS tanpa quotation_item_id tetap dipakai (nomor/part number kosong).
     */
    public static function posItems(?Pos $pos): Collection
    {
        if (! $pos) {
            return collect();
        }

        $items = $pos->items->filter(fn (PosItem $item) => (float) $item->qty > 0 && trim((string) $item->description) !== '');
        $quotationItems = QuotationItem::whereIn('id', $items->pluck('quotation_item_id')->filter()->unique()->all() ?: [0])
            ->get()->keyBy('id');
        $delivered = DeliveryOrderItem::whereIn('quotation_item_id', $quotationItems->keys()->all() ?: [0])
            ->whereHas('deliveryOrder', fn ($q) => $q->where('status', DeliveryOrder::STATUS_POSTED))
            ->groupBy('quotation_item_id')
            ->selectRaw('quotation_item_id, SUM(qty) AS total')
            ->pluck('total', 'quotation_item_id');

        return $items->map(function (PosItem $item) use ($quotationItems, $delivered) {
            $quotationItem = $item->quotation_item_id ? $quotationItems->get($item->quotation_item_id) : null;

            return [
                'id' => $item->quotation_item_id,
                'item_no' => $quotationItem?->item_no,
                'is_child' => (bool) $quotationItem?->parent_id,
                'part_number' => $quotationItem?->part_number,
                'description' => self::text($item->description),
                'qty' => (float) $item->qty,
                'unit' => $item->unit,
                'delivered' => round((float) ($delivered[$item->quotation_item_id] ?? 0), 4),
            ];
        })->values();
    }

    /** Deskripsi HTML Quotation -> teks biasa (baris baru dipertahankan). */
    public static function text(?string $html): string
    {
        $html = (string) $html;
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\s*li[^>]*>/i', '- ', $html);
        $html = preg_replace('/<\s*\/\s*(p|div|li|ul|ol|h[1-6]|tr)\s*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = preg_replace("/\n\s*\n+/", "\n", $text);

        return trim($text);
    }

    /** Default penerima & PO customer dari project. */
    public static function projectDefaults(Project $project): array
    {
        $company = $project->company;

        return [
            'name' => $company?->account_name,
            'phone' => $company?->phone,
            'address' => DeliveryOrder::defaultAddress($company),
            'customer_ref' => $project->po_number_company,
            'customer_ref_date' => $project->po_date?->toDateString(),
            'marking' => $company?->account_name,
        ];
    }

    /** Gudang yang menyimpan stok milik project (pilihan gudang dokumen). */
    public static function projectWarehouses(int $projectId, array $extraIds = []): Collection
    {
        $ids = Stock::open()->where('project_id', $projectId)->distinct()->pluck('warehouse_id')->merge($extraIds)->unique();

        return \App\Models\Warehouse::whereIn('id', $ids->all() ?: [0])->orderBy('name')->get()
            ->map(fn ($w) => ['id' => $w->id, 'label' => $w->code.' — '.$w->name])->values();
    }
}

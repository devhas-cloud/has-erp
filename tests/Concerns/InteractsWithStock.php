<?php

namespace Tests\Concerns;

use App\Models\MasterProduct;
use App\Models\Stock;
use App\Models\StockLog;
use App\Services\StockService;
use Illuminate\Support\Collection;

/**
 * Helper test untuk skema stok (stocks + stock_logs).
 */
trait InteractsWithStock
{
    /** Saldo satu posisi stok; $projectId null = stok bebas. */
    protected function stockBalance(int $warehouseId, int $productId, ?int $projectId = null): float
    {
        return round((float) Stock::open()->pool($productId, $warehouseId, $projectId)->sum('qty'), 4);
    }

    /**
     * Isi stok awal lewat StockService (seperti Stock Entry).
     *
     * @return Collection<int, Stock>
     */
    protected function seedStock(int $warehouseId, MasterProduct|int $product, float $qty, array $attrs = [], array $serials = []): Collection
    {
        $product = $product instanceof MasterProduct ? $product : MasterProduct::findOrFail($product);

        return app(StockService::class)->receive($product, $qty, array_merge([
            'warehouse_id' => $warehouseId,
            'project_id' => null,
            'received_date' => now()->toDateString(),
            'unit' => 'Unit',
            'unit_cost' => 0,
        ], $attrs), $serials, ['date' => now()->toDateString(), 'note' => 'seed test']);
    }

    /** Pilihan pemilih stok: semua baris terbuka suatu posisi, diambil sampai $qty. */
    protected function picks(int $warehouseId, int $productId, ?int $projectId, float $qty): array
    {
        $picks = [];
        foreach (Stock::open()->pool($productId, $warehouseId, $projectId)->orderBy('id')->get() as $stock) {
            if ($qty <= 0) {
                break;
            }
            $take = min($qty, $stock->qty);
            $picks[] = ['stock_id' => $stock->id, 'qty' => $take];
            $qty -= $take;
        }

        return $picks;
    }

    protected function logCount(?string $type = null): int
    {
        return StockLog::when($type, fn ($q) => $q->where('type', $type))->count();
    }
}

<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\PackingListItem;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Satu-satunya tempat yang mengubah qty/posisi baris stok. Setiap perubahan
 * mencatat stock_logs. Panggil di dalam DB::transaction; baris yang akan
 * dikurangi/dipindah harus dikunci lebih dulu lewat lock().
 *
 * Parameter $log: ['date' => 'Y-m-d', 'ref_type' => ?, 'ref_id' => ?,
 * 'note' => ?, 'goods_request_item_id' => ?].
 *
 * Reservasi: qty yang dipesan Packing List Siap Kirim tidak bisa diambil,
 * dipindah, atau dikurangi opname. Posting DO melepas PKL-nya (status
 * Terkirim) lebih dulu, baru mengambil stoknya.
 */
class StockService
{
    /** Batas unit barang ber-SN per baris dokumen (pengaman salah set is_material). */
    public const MAX_SERIAL_UNITS = 1000;

    public static function round(float $value): float
    {
        return round($value, 4);
    }

    /**
     * Kunci baris stok (urut id agar tidak deadlock) dan kembalikan keyBy id.
     *
     * @return Collection<int, Stock>
     */
    public function lock(iterable $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();

        return Stock::with('masterProduct')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * Normalisasi daftar SN dari input (array atau teks per baris/koma).
     *
     * @return array<int, string>
     */
    public static function parseSerials(array|string|null $input): array
    {
        if (is_string($input)) {
            $input = preg_split('/[\r\n,;]+/', $input) ?: [];
        }

        return array_values(array_filter(array_map(fn ($sn) => trim((string) $sn), $input ?? []), fn ($sn) => $sn !== ''));
    }

    /**
     * Barang masuk: buat baris stok baru (material = 1 baris; barang ber-SN =
     * 1 baris per unit, SN boleh sebagian/kosong).
     *
     * @param  array  $attrs  warehouse_id, project_id, received_date, expiry_date, batch_number,
     *                        supplier_id, warranty_until, unit, unit_cost, source_type, source_id, note
     * @return Collection<int, Stock>
     */
    public function receive(MasterProduct $product, float $qty, array $attrs, array $serials, array $log, string $type = StockLog::TYPE_IN): Collection
    {
        $qty = self::round($qty);
        if ($qty <= 0) {
            throw new StockException('Qty '.$product->code.' harus lebih dari 0.');
        }

        $serials = self::parseSerials($serials);
        $rows = collect();
        $base = array_merge($attrs, [
            'master_product_id' => $product->id,
            'is_material' => $product->isMaterial(),
            'created_by' => Auth::id(),
        ]);

        if ($product->isMaterial()) {
            if ($serials) {
                throw new StockException($product->code.' adalah material (dihitung per jumlah) dan tidak memakai SN.');
            }
            $rows->push(Stock::create($base + ['qty_in' => $qty, 'qty' => $qty, 'serial_number' => null]));
        } else {
            $units = $this->assertWholeUnits($product, $qty);
            if (count($serials) > $units) {
                throw new StockException('Jumlah SN '.$product->code.' ('.count($serials).') melebihi qty ('.$units.').');
            }
            $this->assertSerialsAvailable($product, $serials);
            for ($i = 0; $i < $units; $i++) {
                $rows->push(Stock::create($base + ['qty_in' => 1, 'qty' => 1, 'serial_number' => $serials[$i] ?? null]));
            }
        }

        foreach ($rows as $stock) {
            $this->writeLog($stock, $type, $stock->qty, $log);
        }

        return $rows;
    }

    /**
     * Kurangi baris stok (DO, pemakaian internal, retur supplier).
     * Material boleh sebagian; barang ber-SN selalu utuh dan wajib ber-SN bila $requireSerial.
     */
    public function take(Stock $stock, float $qty, string $type, array $log, bool $requireSerial = true): StockLog
    {
        $qty = $this->assertTakeable($stock, $qty);
        if ($requireSerial && ! $stock->canLeave()) {
            throw new StockException('Unit '.$this->label($stock).' belum punya SN. Lengkapi SN di halaman Lengkapi Data Stok sebelum dikeluarkan.');
        }

        $stock->qty = $this->remaining($stock->qty - $qty);
        $stock->save();

        return $this->writeLog($stock, $type, -$qty, $log);
    }

    /**
     * Pindah gudang dan/atau kepemilikan project. Pindah utuh bila qty = sisa
     * (baris yang sama), selain itu baris dipecah (parent_id). Barang ber-SN
     * selalu pindah utuh dan boleh tanpa SN.
     *
     * @param  string  $kind  StockLog::TYPE_TRANSFER_OUT (transfer) atau StockLog::TYPE_ALLOCATE (alokasi project)
     */
    public function move(Stock $stock, float $qty, int $warehouseId, ?int $projectId, string $kind, array $log): Stock
    {
        $qty = $this->assertTakeable($stock, $qty);
        if ((int) $stock->warehouse_id === $warehouseId && (int) $stock->project_id === (int) $projectId) {
            throw new StockException('Gudang dan project tujuan '.$this->label($stock).' sama dengan asal.');
        }

        [$outType, $inType] = $kind === StockLog::TYPE_ALLOCATE
            ? [StockLog::TYPE_ALLOCATE, StockLog::TYPE_ALLOCATE]
            : [StockLog::TYPE_TRANSFER_OUT, StockLog::TYPE_TRANSFER_IN];

        if (abs($qty - $stock->qty) < Stock::EPSILON) {
            $this->writeLog($stock, $outType, -$qty, $log, 0.0);
            $stock->update(['warehouse_id' => $warehouseId, 'project_id' => $projectId]);
            $this->writeLog($stock, $inType, $qty, $log);

            return $stock;
        }

        $stock->qty = $this->remaining($stock->qty - $qty);
        $stock->save();
        $this->writeLog($stock, $outType, -$qty, $log);

        $child = $this->split($stock, $qty, ['warehouse_id' => $warehouseId, 'project_id' => $projectId]);
        $this->writeLog($child, $inType, $qty, $log);

        return $child;
    }

    /**
     * Penyesuaian opname. Minus: kurangi (tidak boleh di bawah 0). Plus: naik
     * sampai qty_in; kelebihannya menjadi baris baru (dikembalikan).
     */
    public function adjust(Stock $stock, float $delta, array $log): ?Stock
    {
        $delta = self::round($delta);
        if (abs($delta) < Stock::EPSILON) {
            return null;
        }

        if ($delta < 0) {
            if ($stock->qty + $delta < -Stock::EPSILON) {
                throw new StockException('Penyesuaian '.$this->label($stock).' membuat stok minus (sisa sekarang '.self::round($stock->qty).', selisih '.$delta.').');
            }
            $reserved = PackingListItem::reservedQty($stock->id);
            if ($stock->qty + $delta < $reserved - Stock::EPSILON) {
                throw new StockException($this->reservedMessage($stock, $reserved));
            }
            $stock->qty = $this->remaining($stock->qty + $delta);
            $stock->save();
            $this->writeLog($stock, StockLog::TYPE_ADJUST, $delta, $log);

            return null;
        }

        $room = self::round($stock->qty_in - $stock->qty);
        $increase = min($delta, $room);
        if ($increase > Stock::EPSILON) {
            $stock->qty = self::round($stock->qty + $increase);
            $stock->save();
            $this->writeLog($stock, StockLog::TYPE_ADJUST, $increase, $log);
        }

        $rest = self::round($delta - max($increase, 0));
        if ($rest <= Stock::EPSILON) {
            return null;
        }
        if ($stock->isSerialized()) {
            throw new StockException('Unit '.$this->label($stock).' adalah barang ber-SN; qty-nya tidak bisa lebih dari 1.');
        }

        $child = $this->split($stock, $rest, []);
        $this->writeLog($child, StockLog::TYPE_ADJUST, $rest, $log);

        return $child;
    }

    /**
     * Unit ber-SN yang sudah keluar (qty 0) kembali masuk, mis. retur dari
     * customer atau ditemukan saat opname. Riwayat SN tetap satu baris.
     */
    public function reactivate(Stock $stock, int $warehouseId, ?int $projectId, string $type, array $log): Stock
    {
        if ($stock->qty > Stock::EPSILON) {
            throw new StockException($this->label($stock).' masih tercatat di stok.');
        }

        $stock->update(['qty' => 1, 'warehouse_id' => $warehouseId, 'project_id' => $projectId]);
        $this->writeLog($stock, $type, 1, $log);

        return $stock;
    }

    /**
     * Barang tiba dari pengiriman antar branch. Barang ber-SN kembali ke baris
     * SN-nya sendiri (riwayat satu jalur); material menjadi baris baru di gudang
     * tujuan dengan parent_id ke baris asal (harga modal, batch ikut disalin).
     */
    public function arrive(Stock $origin, float $qty, int $warehouseId, ?int $projectId, array $log): Stock
    {
        $qty = self::round($qty);
        if ($qty <= 0) {
            throw new StockException('Qty '.$this->label($origin).' harus lebih dari 0.');
        }

        if ($origin->isSerialized()) {
            return $this->reactivate($origin, $warehouseId, $projectId, StockLog::TYPE_SHIP_IN, $log);
        }

        $arrived = $this->split($origin, $qty, ['warehouse_id' => $warehouseId, 'project_id' => $projectId]);
        $this->writeLog($arrived, StockLog::TYPE_SHIP_IN, $qty, $log);

        return $arrived;
    }

    /**
     * Lengkapi/koreksi SN, batch, kedaluwarsa, garansi (hanya selama di stok).
     * Kembalikan null bila tidak ada perubahan.
     */
    public function updateDetails(Stock $stock, array $changes, array $log): ?StockLog
    {
        if (! $stock->is_open) {
            throw new StockException('Data '.$this->label($stock).' tidak bisa diubah karena barang sudah keluar.');
        }

        $labels = ['serial_number' => 'SN', 'batch_number' => 'Batch', 'expiry_date' => 'Kedaluwarsa', 'warranty_until' => 'Garansi'];
        $diff = [];
        foreach (array_intersect_key($changes, $labels) as $field => $value) {
            $value = is_string($value) ? trim($value) : $value;
            $value = $value === '' ? null : $value;
            $old = $stock->{$field} instanceof \DateTimeInterface ? $stock->{$field}->format('Y-m-d') : $stock->{$field};
            if ((string) $old === (string) $value) {
                continue;
            }
            if ($field === 'serial_number') {
                if (! $stock->isSerialized() && $value !== null) {
                    throw new StockException($stock->masterProduct?->code.' adalah material dan tidak memakai SN.');
                }
                if ($value !== null) {
                    $this->assertSerialsAvailable($stock->masterProduct, [$value], $stock->id);
                }
            }
            $diff[] = $labels[$field].': '.($old ?? '—').' → '.($value ?? '—');
            $stock->{$field} = $value;
        }

        if (! $diff) {
            return null;
        }

        $stock->save();

        return $this->writeLog($stock, StockLog::TYPE_DETAIL_UPDATE, 0, array_merge($log, ['note' => implode('; ', $diff)]));
    }

    /**
     * Pastikan baris stok berada di posisi yang diharapkan dokumen.
     * $projectId: null = harus stok bebas; false = project apa saja.
     */
    public function assertPosition(Stock $stock, ?int $warehouseId, int|false|null $projectId): void
    {
        if ($warehouseId !== null && (int) $stock->warehouse_id !== $warehouseId) {
            throw new StockException($this->label($stock).' tidak berada di gudang yang dipilih.');
        }
        if ($projectId === false) {
            return;
        }
        if ((int) $stock->project_id !== (int) $projectId) {
            throw new StockException($this->label($stock).($projectId ? ' bukan milik project yang dipilih.' : ' bukan stok bebas (sudah milik project).'));
        }
    }

    public function label(Stock $stock): string
    {
        return trim(($stock->masterProduct?->code ?? 'Barang').' '.$stock->identity_label);
    }

    private function assertTakeable(Stock $stock, float $qty): float
    {
        $qty = self::round($qty);
        if ($qty <= 0) {
            throw new StockException('Qty '.$this->label($stock).' harus lebih dari 0.');
        }
        if ($qty > $stock->qty + Stock::EPSILON) {
            throw new StockException('Sisa '.$this->label($stock).' tinggal '.self::round($stock->qty).' (diminta '.$qty.').');
        }
        $reserved = PackingListItem::reservedQty($stock->id);
        if ($reserved > Stock::EPSILON && $qty > $stock->qty - $reserved + Stock::EPSILON) {
            throw new StockException($this->reservedMessage($stock, $reserved));
        }
        if ($stock->isSerialized() && abs($qty - $stock->qty) >= Stock::EPSILON) {
            throw new StockException($this->label($stock).' adalah barang ber-SN dan harus diambil utuh.');
        }

        return min($qty, $stock->qty);
    }

    /** Qty baris stok yang belum dipesan Packing List Siap Kirim. */
    public static function available(Stock $stock, ?int $exceptPackingListId = null): float
    {
        return max(0.0, self::round($stock->qty - PackingListItem::reservedQty($stock->id, $exceptPackingListId)));
    }

    private function reservedMessage(Stock $stock, float $reserved): string
    {
        return $this->label($stock).': '.self::round($reserved).' dari sisa '.self::round($stock->qty).' sedang dipesan Packing List Siap Kirim. Buka kembali Packing List-nya dulu bila ingin dipakai.';
    }

    private function assertWholeUnits(MasterProduct $product, float $qty): int
    {
        if (abs($qty - round($qty)) >= Stock::EPSILON) {
            throw new StockException('Qty '.$product->code.' harus bilangan bulat karena tercatat sebagai barang ber-SN.');
        }
        $units = (int) round($qty);
        if ($units > self::MAX_SERIAL_UNITS) {
            throw new StockException($product->code.' tercatat sebagai barang ber-SN (1 baris per unit), qty '.$units.' terlalu besar. Kalau barang ini dihitung per jumlah, ubah ke Material di master produk.');
        }

        return $units;
    }

    /**
     * @param  array<int, string>  $serials
     */
    private function assertSerialsAvailable(MasterProduct $product, array $serials, ?int $exceptStockId = null): void
    {
        if (! $serials) {
            return;
        }

        $dupes = array_keys(array_filter(array_count_values($serials), fn ($n) => $n > 1));
        if ($dupes) {
            throw new StockException('SN '.$product->code.' diisi lebih dari sekali: '.implode(', ', $dupes).'.');
        }

        $taken = Stock::where('master_product_id', $product->id)
            ->whereIn('serial_number', $serials)
            ->when($exceptStockId, fn ($q) => $q->whereKeyNot($exceptStockId))
            ->pluck('serial_number')->all();
        if ($taken) {
            throw new StockException('SN '.$product->code.' sudah terdaftar: '.implode(', ', $taken).'.');
        }
    }

    private function split(Stock $stock, float $qty, array $position): Stock
    {
        return Stock::create(array_merge($stock->only([
            'master_product_id', 'warehouse_id', 'project_id', 'is_material', 'batch_number', 'received_date',
            'expiry_date', 'supplier_id', 'warranty_until', 'unit', 'unit_cost', 'source_type', 'source_id', 'note',
        ]), $position, [
            'serial_number' => null,
            'qty_in' => $qty,
            'qty' => $qty,
            'parent_id' => $stock->id,
            'created_by' => Auth::id(),
        ]));
    }

    private function remaining(float $qty): float
    {
        $qty = self::round($qty);

        return $qty < 0.0001 ? 0.0 : $qty;
    }

    private function writeLog(Stock $stock, string $type, float $qty, array $log, ?float $qtyAfter = null): StockLog
    {
        return StockLog::create([
            'stock_id' => $stock->id,
            'master_product_id' => $stock->master_product_id,
            'warehouse_id' => $stock->warehouse_id,
            'project_id' => $stock->project_id,
            'is_material' => $stock->is_material,
            'log_date' => $log['date'] ?? now()->toDateString(),
            'type' => $type,
            'qty' => self::round($qty),
            'qty_after' => self::round($qtyAfter ?? $stock->qty),
            'unit_cost' => $stock->unit_cost,
            'ref_type' => $log['ref_type'] ?? null,
            'ref_id' => $log['ref_id'] ?? null,
            'goods_request_item_id' => $log['goods_request_item_id'] ?? null,
            'note' => isset($log['note']) ? mb_substr((string) $log['note'], 0, 255) : null,
            'created_by' => Auth::id(),
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Valas hutang supplier (V1 + V2):
 * - tagihan menyimpan kurs tanggal invoice supplier (nilai buku hutang);
 * - alokasi pembayaran menyimpan nilai Rupiah menurut kurs tagihan (buku) dan
 *   kurs bayar, beserta selisih kurs terealisasi (positif = rugi);
 * - refund uang muka menyimpan nilai buku (kurs uang muka) dan selisihnya;
 * - pembayaran mencatat biaya bank (Rupiah) di luar hutang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
            // Kurs diisi otomatis (data lama / tagihan Direct Purchase), belum dikonfirmasi Finance.
            $table->boolean('rate_estimated')->default(false)->after('exchange_rate');
        });

        Schema::table('supplier_payment_allocations', function (Blueprint $table) {
            $table->decimal('amount_base_book', 18, 2)->default(0)->after('amount');
            $table->decimal('amount_base_paid', 18, 2)->default(0)->after('amount_base_book');
            $table->decimal('fx_difference', 18, 2)->default(0)->after('amount_base_paid');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->decimal('bank_charge', 18, 2)->default(0)->after('amount_base');
        });

        Schema::table('supplier_refunds', function (Blueprint $table) {
            $table->decimal('amount_base_book', 18, 2)->default(0)->after('amount_base');
            $table->decimal('fx_difference', 18, 2)->default(0)->after('amount_base_book');
        });

        $base = strtoupper((string) (DB::table('currencies')->where('is_base', true)->value('name') ?: 'IDR'));
        $rates = DB::table('currencies')->pluck('rate', 'name')->mapWithKeys(fn ($rate, $name) => [strtoupper($name) => (float) $rate]);

        // Tagihan valas lama: kurs master saat ini, ditandai perkiraan.
        DB::table('supplier_bills')->orderBy('id')->get(['id', 'currency'])->each(function ($bill) use ($base, $rates) {
            $code = strtoupper((string) $bill->currency);
            $foreign = $code !== '' && $code !== $base;
            DB::table('supplier_bills')->where('id', $bill->id)->update([
                'exchange_rate' => $foreign ? ($rates[$code] ?? 1) : 1,
                'rate_estimated' => $foreign,
            ]);
        });

        DB::table('supplier_payment_allocations as a')
            ->join('supplier_bills as b', 'b.id', '=', 'a.supplier_bill_id')
            ->join('supplier_payments as p', 'p.id', '=', 'a.supplier_payment_id')
            ->get(['a.id', 'a.amount', 'b.exchange_rate as bill_rate', 'p.exchange_rate as pay_rate'])
            ->each(function ($row) {
                $book = round($row->amount * $row->bill_rate, 2);
                $paid = round($row->amount * $row->pay_rate, 2);
                DB::table('supplier_payment_allocations')->where('id', $row->id)->update([
                    'amount_base_book' => $book,
                    'amount_base_paid' => $paid,
                    'fx_difference' => round($paid - $book, 2),
                ]);
            });

        DB::table('supplier_refunds')->get(['id', 'purchase_order_id', 'amount', 'amount_base'])->each(function ($refund) {
            $advances = DB::table('supplier_bills')->where('purchase_order_id', $refund->purchase_order_id)
                ->where('bill_type', 'advance')->where('status', '!=', 'void')->get(['total', 'exchange_rate']);
            $total = (float) $advances->sum('total');
            $rate = $total > 0 ? $advances->sum(fn ($b) => $b->total * $b->exchange_rate) / $total : 1;
            $book = round($refund->amount * $rate, 2);
            DB::table('supplier_refunds')->where('id', $refund->id)->update([
                'amount_base_book' => $book,
                'fx_difference' => round($book - $refund->amount_base, 2),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_refunds', fn (Blueprint $table) => $table->dropColumn(['amount_base_book', 'fx_difference']));
        Schema::table('supplier_payments', fn (Blueprint $table) => $table->dropColumn('bank_charge'));
        Schema::table('supplier_payment_allocations', fn (Blueprint $table) => $table->dropColumn(['amount_base_book', 'amount_base_paid', 'fx_difference']));
        Schema::table('supplier_bills', fn (Blueprint $table) => $table->dropColumn(['exchange_rate', 'rate_estimated']));
    }
};

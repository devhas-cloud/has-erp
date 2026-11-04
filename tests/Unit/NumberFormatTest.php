<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Support\Number;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Standar angka gaya US: koma = ribuan, titik = desimal. Rupiah tanpa desimal
 * kecuali ada sen; valas selalu 2 desimal.
 */
class NumberFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'rate' => 1, 'is_base' => true, 'status' => 'Active']);
    }

    public function test_money_rules(): void
    {
        $this->assertSame('10,000', Number::money(10000));
        $this->assertSame('10,000', Number::money(10000, 'IDR'));
        $this->assertSame('17,112,643.56', Number::money(17112643.56));
        $this->assertSame('1,000,000.50', Number::money(1000000.5));
        $this->assertSame('3,579.75', Number::money(3579.75, 'USD'));
        $this->assertSame('10.00', Number::money(10, 'USD'));
        $this->assertSame('-2,500', Number::money(-2500));
        $this->assertSame('USD 3,579.75', Number::moneyWithCode(3579.75, 'usd'));
    }

    public function test_other_kinds(): void
    {
        $this->assertSame('1,250', Number::qty(1250));
        $this->assertSame('11.5', Number::percent(11.5));
        $this->assertSame('1250.5', Number::percent(1250.5));
        $this->assertSame('16,250.123456', Number::rate(16250.123456));
        $this->assertSame('16,200', Number::rate(16200));
        $this->assertSame('1,000,000.5', Number::format(1000000.5));
        $this->assertSame('0', Number::format(-0.0001));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/** Money helper rounding policy (half-up, 2 decimals). */
class MoneyTest extends TestCase
{
    public function test_round_keeps_two_decimals(): void
    {
        $this->assertSame(10.50, Money::round(10.4999));
        $this->assertSame(10.50, Money::round(10.5));
        $this->assertSame(0.07, Money::round(0.0651));
    }

    public function test_tax_computation(): void
    {
        $this->assertSame(7.20, Money::tax(45.00, 16));
        $this->assertSame(0.00, Money::tax(45.00, 0));
        $this->assertSame(3.60, Money::tax(45.00, 8));
    }

    public function test_add_and_subtract(): void
    {
        $this->assertSame(52.20, Money::add(45.00, 7.20));
        $this->assertSame(47.80, Money::subtract(100.00, 52.20));
    }

    public function test_min_and_equality_tolerance(): void
    {
        $this->assertSame(52.20, Money::min(100.00, 52.20));
        $this->assertTrue(Money::equals(52.20, 52.204));
        $this->assertFalse(Money::equals(52.20, 52.21));
    }
}

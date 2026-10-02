<?php

declare(strict_types=1);

namespace Tests;

use App\PriceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\PriceCalculator
 */
final class PriceCalculatorTest extends TestCase
{
    public function totals(): array
    {
        return [
            'no discount' => [1000, 0, 1200],
            'ten percent off' => [1000, 10, 1080],
            'rounds the discount half up' => [999, 5, 1139],
            'free' => [1000, 100, 0],
        ];
    }

    /**
     * @dataProvider totals
     */
    public function testTotal(int $net, int $discount, int $expected): void
    {
        $this->assertSame($expected, (new PriceCalculator(20))->total($net, $discount));
    }

    public function testBreakdownExposesTheVatAmount(): void
    {
        $breakdown = (new PriceCalculator(20))->breakdown(1000);

        $this->assertObjectHasAttribute('vat', $breakdown);
        $this->assertSame(200, $breakdown->vat);
    }

    public function testRejectsANegativeVatRate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must not be negative, got -5/');

        new PriceCalculator(-5);
    }
}

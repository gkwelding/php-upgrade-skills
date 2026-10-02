<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;

final class PriceCalculator
{
    public function __construct(private readonly int $vatPercent)
    {
        if ($vatPercent < 0) {
            throw new InvalidArgumentException("VAT rate must not be negative, got {$vatPercent}");
        }
    }

    /**
     * Gross total in cents for a net amount, with an optional percentage discount applied before VAT.
     */
    public function total(int $netCents, int $discountPercent = 0): int
    {
        return $this->breakdown($netCents, $discountPercent)->gross;
    }

    public function breakdown(int $netCents, int $discountPercent = 0): PriceBreakdown
    {
        $discounted = (int) round($netCents * (100 - $discountPercent) / 100);
        $vat = (int) round($discounted * $this->vatPercent / 100);

        return new PriceBreakdown($discounted, $vat, $discounted + $vat);
    }
}

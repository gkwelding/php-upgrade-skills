<?php

declare(strict_types=1);

namespace App;

final class PriceBreakdown
{
    public function __construct(
        public readonly int $net,
        public readonly int $vat,
        public readonly int $gross,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App;

final class Invoice
{
    /**
     * @param list<string> $cc
     */
    public function __construct(
        public readonly string $number,
        public readonly string $customerEmail,
        public readonly int $totalCents,
        public readonly array $cc = [],
    ) {
    }
}

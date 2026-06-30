<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

/**
 * Immutable result of a single line calculation. All amounts are already
 * rounded to the document currency's precision; lineTotal is derived from the
 * rounded subtotal and tax so that lineSubtotal + lineTax === lineTotal exactly.
 */
final readonly class LineAmounts
{
    public function __construct(
        public float $unitPriceExcTax,
        public float $taxAmountPerUnit,
        public float $lineSubtotal,
        public float $lineTax,
        public float $lineTotal,
    ) {}
}

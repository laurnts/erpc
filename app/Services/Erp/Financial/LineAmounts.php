<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Support\Money;

/**
 * Immutable result of a single line calculation. All amounts are already rounded
 * to the caller's rounding scale; lineTotal is derived from the rounded subtotal
 * and tax so that lineSubtotal + lineTax === lineTotal exactly.
 */
final readonly class LineAmounts
{
    public function __construct(
        public Money $unitPriceExcTax,
        public Money $taxAmountPerUnit,
        public Money $lineSubtotal,
        public Money $lineTax,
        public Money $lineTotal,
    ) {}
}

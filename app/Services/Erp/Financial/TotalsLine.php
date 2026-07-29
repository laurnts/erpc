<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Support\Money;

/**
 * A single already-calculated line handed to TotalsCollector. Callers map their
 * model rows (main items only) onto this shape; the collector stays free of any
 * model or column-name coupling.
 *
 * quantity is a decimal string rather than Money — it is a count, not an amount.
 */
final readonly class TotalsLine
{
    public function __construct(
        public Money $lineSubtotal,
        public Money $lineTax,
        public Money $lineTotal,
        public Money $costPrice,
        public string $quantity,
    ) {}
}

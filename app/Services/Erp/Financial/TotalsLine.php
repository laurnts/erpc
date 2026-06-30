<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

/**
 * A single already-calculated line handed to TotalsCollector. Callers map their
 * model rows (main items only) onto this shape; the collector stays free of any
 * model or column-name coupling.
 */
final readonly class TotalsLine
{
    public function __construct(
        public float $lineSubtotal,
        public float $lineTax,
        public float $lineTotal,
        public float $costPrice,
        public float $quantity,
    ) {}
}

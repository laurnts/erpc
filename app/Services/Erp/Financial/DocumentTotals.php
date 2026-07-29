<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Support\Money;

/**
 * Aggregated document-level totals in the document's transaction currency.
 *
 * Margin fields are meaningful only for buyer documents (which carry a
 * cost-vs-sell relationship); supplier documents consume only subtotal,
 * taxTotal, and grandTotal. Any base-currency (FX) conversion is applied by the
 * document model to these figures — TotalsCollector is FX-agnostic.
 *
 * marginPercent is deliberately a float: it is a ratio, not an amount, and
 * carries no minor units to be exact about.
 */
final readonly class DocumentTotals
{
    public function __construct(
        public Money $subtotal,
        public Money $taxTotal,
        public Money $grandTotal,
        public Money $costTotal,
        public Money $marginAmount,
        public float $marginPercent,
    ) {}
}

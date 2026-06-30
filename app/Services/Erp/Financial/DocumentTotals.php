<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

/**
 * Aggregated document-level totals in the document's transaction currency.
 *
 * Margin fields are meaningful only for buyer documents (which carry a
 * cost-vs-sell relationship); supplier documents consume only subtotal,
 * taxTotal, and grandTotal. Any base-currency (FX) conversion is applied by the
 * document model to these figures — TotalsCollector is FX-agnostic.
 */
final readonly class DocumentTotals
{
    public function __construct(
        public float $subtotal,
        public float $taxTotal,
        public float $grandTotal,
        public float $costTotal,
        public float $marginAmount,
        public float $marginPercent,
    ) {}
}

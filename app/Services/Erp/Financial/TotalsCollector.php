<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use Illuminate\Support\Collection;

/**
 * Aggregates a pre-filtered collection of lines into document-level totals.
 *
 * The caller is responsible for filtering to main items only (e.g. via
 * BuyerQuoteItem::filterForTotals) before calling collect(); this service
 * applies no parent_id filter of its own.
 *
 * @see DocumentTotals for the FX and margin scope notes.
 */
final readonly class TotalsCollector
{
    /**
     * @param  Collection<int, TotalsLine>  $lines
     */
    public function collect(Collection $lines): DocumentTotals
    {
        $subtotal = (float) $lines->sum(fn (TotalsLine $line): float => $line->lineSubtotal);
        $taxTotal = (float) $lines->sum(fn (TotalsLine $line): float => $line->lineTax);
        $grandTotal = (float) $lines->sum(fn (TotalsLine $line): float => $line->lineTotal);
        $costTotal = (float) $lines->sum(fn (TotalsLine $line): float => $line->costPrice * $line->quantity);

        return new DocumentTotals(
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            grandTotal: $grandTotal,
            costTotal: $costTotal,
            marginAmount: $subtotal - $costTotal,
            marginPercent: MarginConvention::marginPercent($costTotal, $subtotal),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Aggregates a pre-filtered collection of lines into document-level totals.
 *
 * The caller is responsible for filtering to main items only (e.g. via
 * BuyerQuoteItem::filterForTotals) before calling collect(); this service
 * applies no parent_id filter of its own.
 *
 * Summation is exact: an amount is only ever added to another amount of the same
 * currency, so a hundred repeating-decimal lines total to the same figure a
 * human gets with a calculator. Lines arrive already rounded to their document's
 * scale, so no rounding happens here.
 *
 * @see DocumentTotals for the FX and margin scope notes.
 */
final readonly class TotalsCollector
{
    /**
     * @param  Collection<int, TotalsLine>  $lines
     */
    public function collect(Collection $lines, string $currency): DocumentTotals
    {
        $subtotal = Money::zero($currency);
        $taxTotal = Money::zero($currency);
        $grandTotal = Money::zero($currency);
        $costTotal = Money::zero($currency);

        foreach ($lines as $line) {
            $subtotal = $subtotal->plus($line->lineSubtotal);
            $taxTotal = $taxTotal->plus($line->lineTax);
            $grandTotal = $grandTotal->plus($line->lineTotal);
            $costTotal = $costTotal->plus($line->costPrice->multipliedBy($line->quantity));
        }

        return new DocumentTotals(
            subtotal: $subtotal,
            taxTotal: $taxTotal,
            grandTotal: $grandTotal,
            costTotal: $costTotal,
            marginAmount: $subtotal->minus($costTotal),
            marginPercent: MarginConvention::marginPercent($costTotal->toFloat(), $subtotal->toFloat()),
        );
    }
}

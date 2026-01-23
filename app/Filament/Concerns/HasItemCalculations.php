<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Trait for handling item calculations in Filament forms.
 *
 * Provides reusable calculation methods for:
 * - Line subtotal (quantity × unit price)
 * - Line tax
 * - Line total (subtotal + tax)
 * - Margin calculations
 */
trait HasItemCalculations
{
    /**
     * Calculate item totals based on quantity, price, and tax.
     */
    protected function calculateItemTotals(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price_exc_tax') ?? $get('unit_price') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);

        $lineSubtotal = $quantity * $unitPrice;
        $lineTax = $lineSubtotal * ($taxRate / 100);
        $lineTotal = $lineSubtotal + $lineTax;

        $set('line_subtotal', round($lineSubtotal, 4));
        $set('line_tax', round($lineTax, 4));
        $set('line_total', round($lineTotal, 4));
    }

    /**
     * Calculate margin based on cost and selling price.
     */
    protected function calculateMargin(Set $set, Get $get): void
    {
        $costPrice = (float) ($get('cost_price') ?? 0);
        $sellingPrice = (float) ($get('unit_price_exc_tax') ?? $get('unit_price') ?? 0);
        $quantity = (float) ($get('quantity') ?? 0);

        if ($costPrice > 0) {
            $marginPercent = (($sellingPrice - $costPrice) / $costPrice) * 100;
            $marginAmount = ($sellingPrice - $costPrice) * $quantity;

            $set('margin_percent', round($marginPercent, 2));
            $set('margin_amount', round($marginAmount, 4));
        } else {
            $set('margin_percent', 0);
            $set('margin_amount', 0);
        }
    }

    /**
     * Calculate all item values (totals + margin).
     */
    protected function calculateAllItemValues(Set $set, Get $get): void
    {
        $this->calculateItemTotals($set, $get);
        $this->calculateMargin($set, $get);
    }
}

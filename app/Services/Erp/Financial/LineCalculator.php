<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Enums\Erp\PriceBasis;

/**
 * The single source of truth for per-line tax and total arithmetic across the
 * buyer-quote and supplier-quote document types.
 *
 * Each model maps its own tax flags onto an explicit price basis and a taxable
 * boolean (the two families assign opposite meanings to is_tax_inclusive), so
 * this one engine reproduces both conventions without any second formula.
 */
final readonly class LineCalculator
{
    public function calculate(
        float $unitPriceInput,
        PriceBasis $priceBasis,
        bool $taxable,
        float $taxRate,
        float $quantity,
        int $currencyDecimals,
    ): LineAmounts {
        $applyTax = $taxable && $taxRate > 0.0;

        if ($applyTax && $priceBasis === PriceBasis::GROSS) {
            $rawExcTax = $unitPriceInput / (1 + $taxRate / 100);
            $rawTaxPerUnit = $unitPriceInput - $rawExcTax;
        } elseif ($applyTax) {
            $rawExcTax = $unitPriceInput;
            $rawTaxPerUnit = $unitPriceInput * $taxRate / 100;
        } else {
            $rawExcTax = $unitPriceInput;
            $rawTaxPerUnit = 0.0;
        }

        $lineSubtotal = round($rawExcTax * $quantity, $currencyDecimals);
        $lineTax = round($rawTaxPerUnit * $quantity, $currencyDecimals);

        return new LineAmounts(
            unitPriceExcTax: round($rawExcTax, $currencyDecimals),
            taxAmountPerUnit: round($rawTaxPerUnit, $currencyDecimals),
            lineSubtotal: $lineSubtotal,
            lineTax: $lineTax,
            lineTotal: $lineSubtotal + $lineTax,
        );
    }
}

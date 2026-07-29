<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

use App\Enums\Erp\PriceBasis;
use App\Support\Money;

/**
 * The single source of truth for per-line tax and total arithmetic across the
 * buyer-quote and supplier-quote document types.
 *
 * Each model maps its own tax flags onto an explicit price basis and a taxable
 * boolean (the two families assign opposite meanings to is_tax_inclusive), so
 * this one engine reproduces both conventions without any second formula.
 *
 * Arithmetic is exact: amounts are Money (integer minor units), and the tax rate
 * and quantity are decimal strings, so no step passes through a binary float.
 * The previous float implementation accumulated error into margin, which is the
 * number this business sells.
 *
 * $roundingScale is a business rule, not a precision detail: buyer quotes round
 * to whole units, supplier quotes to four decimals. The per-unit and line
 * intermediates stay at Money::PRECISION (unrounded, scale-20) through the tax
 * calculation and the multiply by quantity, and are rounded to $roundingScale
 * exactly once, at the end, via Money::fromHighPrecision() — never before the
 * multiply. That is what the old float engine did too, and what
 * Money::fromHighPrecision()'s own docblock documents.
 *
 * Figures are not always identical to the old float engine: they differ in
 * roughly 0.02%-1.5% of fields, always by exactly one unit in the last place,
 * at exact .5 rounding ties — where this exact implementation is the
 * arithmetically correct one and the old float engine was not.
 */
final readonly class LineCalculator
{
    /**
     * @param  numeric-string  $taxRate
     * @param  numeric-string  $quantity
     */
    public function calculate(
        Money $unitPriceInput,
        PriceBasis $priceBasis,
        bool $taxable,
        string $taxRate,
        string $quantity,
        int $roundingScale,
    ): LineAmounts {
        $precision = Money::PRECISION;
        $currency = $unitPriceInput->currency;

        /** @var numeric-string $price */
        $price = $unitPriceInput->toDecimal();

        $applyTax = $taxable && bccomp($taxRate, '0', 8) === 1;

        if ($applyTax && $priceBasis === PriceBasis::GROSS) {
            // net = gross / (1 + rate/100)
            $divisor = bcadd('1', bcdiv($taxRate, '100', $precision), $precision);
            $rawExcTax = bcdiv($price, $divisor, $precision);
            $rawTaxPerUnit = bcsub($price, $rawExcTax, $precision);
        } elseif ($applyTax) {
            $rawExcTax = $price;
            $rawTaxPerUnit = bcdiv(bcmul($price, $taxRate, $precision), '100', $precision);
        } else {
            $rawExcTax = $price;
            $rawTaxPerUnit = '0';
        }

        $lineSubtotal = Money::fromHighPrecision(
            bcmul($rawExcTax, $quantity, $precision), $roundingScale, $currency,
        );
        $lineTax = Money::fromHighPrecision(
            bcmul($rawTaxPerUnit, $quantity, $precision), $roundingScale, $currency,
        );

        return new LineAmounts(
            unitPriceExcTax: Money::fromHighPrecision($rawExcTax, $roundingScale, $currency),
            taxAmountPerUnit: Money::fromHighPrecision($rawTaxPerUnit, $roundingScale, $currency),
            lineSubtotal: $lineSubtotal,
            lineTax: $lineTax,
            lineTotal: $lineSubtotal->plus($lineTax),
        );
    }
}

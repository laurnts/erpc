<?php

declare(strict_types=1);

namespace App\Services\Erp;

use App\Models\TaxCode;

final readonly class TaxCalculationService
{
    /**
     * Calculate the tax amount from a given amount.
     *
     * @param  float  $amount  The base amount
     * @param  float  $rate  The tax rate as a percentage (e.g., 11 for 11%)
     * @param  bool  $isInclusive  Whether the amount already includes tax
     * @return float The tax amount
     */
    public function calculateTaxAmount(float $amount, float $rate, bool $isInclusive = false): float
    {
        if ($rate <= 0) {
            return 0.0;
        }

        if ($isInclusive) {
            // Tax is already included in the amount
            // Formula: tax = amount - (amount / (1 + rate/100))
            return $amount - ($amount / (1 + $rate / 100));
        }

        // Tax is not included, calculate tax on top
        return $amount * ($rate / 100);
    }

    /**
     * Calculate the price with tax added (exclusive to inclusive).
     *
     * @param  float  $amount  The base amount (excluding tax)
     * @param  float  $rate  The tax rate as a percentage (e.g., 11 for 11%)
     * @return float The total amount including tax
     */
    public function calculatePriceWithTax(float $amount, float $rate): float
    {
        if ($rate <= 0) {
            return $amount;
        }

        return $amount * (1 + $rate / 100);
    }

    /**
     * Calculate the price without tax (inclusive to exclusive).
     *
     * @param  float  $amount  The total amount (including tax)
     * @param  float  $rate  The tax rate as a percentage (e.g., 11 for 11%)
     * @return float The base amount excluding tax
     */
    public function calculatePriceWithoutTax(float $amount, float $rate): float
    {
        if ($rate <= 0) {
            return $amount;
        }

        return $amount / (1 + $rate / 100);
    }

    /**
     * Calculate tax amount from a TaxCode model.
     *
     * @param  float  $amount  The amount
     * @param  TaxCode  $taxCode  The tax code model
     * @param  bool|null  $isInclusive  Override the tax code's default inclusivity setting
     * @return float The tax amount
     */
    public function calculateTaxAmountFromCode(float $amount, TaxCode $taxCode, ?bool $isInclusive = null): float
    {
        $isInclusive ??= $taxCode->is_inclusive_default;

        return $this->calculateTaxAmount($amount, (float) $taxCode->rate, $isInclusive);
    }

    /**
     * Calculate line item totals.
     *
     * @param  float  $quantity  The quantity
     * @param  float  $unitPrice  The unit price
     * @param  float  $taxRate  The tax rate as a percentage
     * @param  bool  $isInclusive  Whether the unit price includes tax
     * @return array{subtotal: float, tax_amount: float, total: float}
     */
    public function calculateLineTotal(
        float $quantity,
        float $unitPrice,
        float $taxRate,
        bool $isInclusive = false
    ): array {
        $lineAmount = $quantity * $unitPrice;

        if ($isInclusive) {
            // Unit price includes tax
            $total = $lineAmount;
            $subtotal = $this->calculatePriceWithoutTax($lineAmount, $taxRate);
            $taxAmount = $total - $subtotal;
        } else {
            // Unit price excludes tax
            $subtotal = $lineAmount;
            $taxAmount = $this->calculateTaxAmount($lineAmount, $taxRate, false);
            $total = $subtotal + $taxAmount;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Round a monetary value to a specified precision.
     *
     * @param  float  $value  The value to round
     * @param  int  $precision  The number of decimal places
     * @return float The rounded value
     */
    public function roundMoney(float $value, int $precision = 2): float
    {
        return round($value, $precision);
    }
}

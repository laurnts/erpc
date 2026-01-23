<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Facades\Filament;

/**
 * Trait for currency formatting in Filament resources.
 *
 * Uses the team's base currency for formatting values.
 */
trait FormatsCurrency
{
    /**
     * Format a currency value using the team's base currency.
     */
    protected function formatCurrency(float $value): string
    {
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant();
        $currency = $team?->getBaseCurrency();

        if ($currency === null) {
            return number_format($value, 2);
        }

        return $currency->format($value);
    }

    /**
     * Format a currency value with explicit currency code.
     */
    protected function formatWithCurrency(float $value, ?string $currencyCode = null): string
    {
        if ($currencyCode === null) {
            return $this->formatCurrency($value);
        }

        return sprintf('%s %s', $currencyCode, number_format($value, 2));
    }
}

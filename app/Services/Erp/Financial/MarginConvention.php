<?php

declare(strict_types=1);

namespace App\Services\Erp\Financial;

/**
 * The single canonical margin convention for the whole system: on-selling
 * (gross margin). No other margin formula may exist anywhere in the codebase.
 */
final readonly class MarginConvention
{
    /**
     * Gross margin percentage on the net selling price.
     *
     * margin% = (sellNet - cost) / sellNet * 100
     */
    public static function marginPercent(float $cost, float $sellNet): float
    {
        if ($sellNet <= 0.0) {
            return 0.0;
        }

        return ($sellNet - $cost) / $sellNet * 100;
    }

    /**
     * Net selling price for a target on-selling margin.
     *
     * sellNet = cost / (1 - margin/100)
     */
    public static function netUnitPrice(float $cost, float $marginPercent): float
    {
        if ($marginPercent >= 100.0) {
            return 0.0;
        }

        return $cost / (1 - $marginPercent / 100);
    }
}

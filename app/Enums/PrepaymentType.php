<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PrepaymentType: string implements HasColor, HasLabel
{
    case PERCENT = 'percent';
    case FIXED = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PERCENT => 'Percentage',
            self::FIXED => 'Fixed Amount',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PERCENT => 'info',
            self::FIXED => 'success',
        };
    }

    /**
     * Get the suffix to display after the value.
     */
    public function getSuffix(): string
    {
        return match ($this) {
            self::PERCENT => '%',
            self::FIXED => '',
        };
    }

    /**
     * Get the maximum allowed value for this type.
     */
    public function getMaxValue(): ?float
    {
        return match ($this) {
            self::PERCENT => 100.0,
            self::FIXED => null,
        };
    }
}

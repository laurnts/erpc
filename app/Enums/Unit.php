<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Unit: string implements HasLabel
{
    case PCS = 'pcs';
    case KG = 'kg';
    case MT = 'mt';
    case SET = 'set';
    case BOX = 'box';
    case ROLL = 'roll';
    case PAIR = 'pair';
    case L = 'l';
    case M = 'm';

    public function getLabel(): string
    {
        return match ($this) {
            self::PCS => 'Pieces',
            self::KG => 'Kilograms',
            self::MT => 'Metric Tons',
            self::SET => 'Sets',
            self::BOX => 'Boxes',
            self::ROLL => 'Rolls',
            self::PAIR => 'Pairs',
            self::L => 'Liters',
            self::M => 'Meters',
        };
    }

    /**
     * Get the abbreviation for display.
     */
    public function getAbbreviation(): string
    {
        return $this->value;
    }

    /**
     * Get whether this unit is typically used for weight.
     */
    public function isWeight(): bool
    {
        return in_array($this, [self::KG, self::MT], true);
    }

    /**
     * Get whether this unit is typically used for volume.
     */
    public function isVolume(): bool
    {
        return $this === self::L;
    }

    /**
     * Get whether this unit is typically used for length.
     */
    public function isLength(): bool
    {
        return $this === self::M;
    }
}

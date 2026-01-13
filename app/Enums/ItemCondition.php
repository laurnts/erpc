<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ItemCondition: string implements HasColor, HasIcon, HasLabel
{
    case GOOD = 'good';
    case DAMAGED = 'damaged';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::GOOD => 'Good',
            self::DAMAGED => 'Damaged',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GOOD => 'success',
            self::DAMAGED => 'warning',
            self::REJECTED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::GOOD => 'heroicon-o-check-circle',
            self::DAMAGED => 'heroicon-o-exclamation-triangle',
            self::REJECTED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if this condition is acceptable for inventory.
     */
    public function isAcceptable(): bool
    {
        return $this === self::GOOD;
    }

    /**
     * Check if this condition requires notes/documentation.
     */
    public function requiresNotes(): bool
    {
        return $this !== self::GOOD;
    }
}

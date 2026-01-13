<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RequestPriority: string implements HasColor, HasIcon, HasLabel
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::LOW => 'gray',
            self::NORMAL => 'info',
            self::HIGH => 'warning',
            self::URGENT => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::LOW => 'heroicon-o-arrow-down',
            self::NORMAL => 'heroicon-o-minus',
            self::HIGH => 'heroicon-o-arrow-up',
            self::URGENT => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Get the sort order for prioritization (higher = more urgent).
     */
    public function getSortOrder(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::NORMAL => 2,
            self::HIGH => 3,
            self::URGENT => 4,
        };
    }
}

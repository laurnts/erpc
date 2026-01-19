<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PnlStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case ORDERED = 'ordered';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ORDERED => 'Ordered',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ORDERED => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::ORDERED => 'heroicon-o-check-circle',
        };
    }
}

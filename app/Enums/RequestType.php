<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RequestType: string implements HasColor, HasIcon, HasLabel
{
    case GOODS = 'goods';
    case SERVICE = 'service';

    public function getLabel(): string
    {
        return match ($this) {
            self::GOODS => 'Goods',
            self::SERVICE => 'Service',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GOODS => 'primary',
            self::SERVICE => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::GOODS => 'heroicon-o-cube',
            self::SERVICE => 'heroicon-o-wrench-screwdriver',
        };
    }
}

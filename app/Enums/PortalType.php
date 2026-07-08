<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PortalType: string implements HasLabel
{
    case Buyer = 'buyer';
    case Supplier = 'supplier';

    public function getLabel(): string
    {
        return match ($this) {
            self::Buyer => 'Buyer Portal',
            self::Supplier => 'Supplier Portal',
        };
    }
}

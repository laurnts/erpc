<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShipmentType: string implements HasColor, HasIcon, HasLabel
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';

    public function getLabel(): string
    {
        return match ($this) {
            self::INBOUND => 'Inbound',
            self::OUTBOUND => 'Outbound',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::INBOUND => 'info',
            self::OUTBOUND => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::INBOUND => 'heroicon-o-arrow-down-tray',
            self::OUTBOUND => 'heroicon-o-arrow-up-tray',
        };
    }

    /**
     * Get a description of the shipment type.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::INBOUND => 'Receiving goods from suppliers',
            self::OUTBOUND => 'Shipping goods to buyers',
        };
    }
}

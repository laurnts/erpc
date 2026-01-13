<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SupplierQuoteStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case SELECTED = 'selected';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SELECTED => 'Selected',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SELECTED => 'success',
            self::REJECTED => 'danger',
            self::EXPIRED => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::SELECTED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
            self::EXPIRED => 'heroicon-o-exclamation-triangle',
        };
    }
}

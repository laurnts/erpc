<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PNLStatus: string implements HasColor, HasIcon, HasLabel
{
    case NEED_APPROVAL = 'need_approval';
    case APPROVED = 'approved';
    case PENDING = 'pending';
    case ORDERED = 'ordered';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEED_APPROVAL => 'Not Approved yet',
            self::APPROVED => 'Approved',
            self::PENDING => 'Pending',
            self::ORDERED => 'Ordered',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NEED_APPROVAL => 'warning',
            self::APPROVED => 'success',
            self::PENDING => 'gray',
            self::ORDERED => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::NEED_APPROVAL => 'heroicon-o-clock',
            self::APPROVED => 'heroicon-o-check-badge',
            self::PENDING => 'heroicon-o-clock',
            self::ORDERED => 'heroicon-o-shopping-cart',
        };
    }

    /**
     * Check if PNL can be approved.
     */
    public function canApprove(): bool
    {
        return $this === self::NEED_APPROVAL;
    }

    /**
     * Check if PNL is approved.
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum QEStatus: string implements HasColor, HasIcon, HasLabel
{
    case NEED_APPROVAL = 'need_approval';
    case APPROVED = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEED_APPROVAL => 'Pending',
            self::APPROVED => 'Approved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NEED_APPROVAL => 'warning',
            self::APPROVED => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::NEED_APPROVAL => 'heroicon-o-clock',
            self::APPROVED => 'heroicon-o-check-badge',
        };
    }

    /**
     * Check if QE can be approved.
     */
    public function canApprove(): bool
    {
        return $this === self::NEED_APPROVAL;
    }

    /**
     * Check if QE is approved.
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }
}

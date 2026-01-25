<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BuyerQuoteStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case SUPERSEDED = 'superseded';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
            self::SUPERSEDED => 'Superseded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::ACCEPTED => 'success',
            self::REJECTED => 'danger',
            self::EXPIRED => 'warning',
            self::SUPERSEDED => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-pencil',
            self::SENT => 'heroicon-o-paper-airplane',
            self::ACCEPTED => 'heroicon-o-check-badge',
            self::REJECTED => 'heroicon-o-x-circle',
            self::EXPIRED => 'heroicon-o-clock',
            self::SUPERSEDED => 'heroicon-o-arrow-path',
        };
    }

    /**
     * Check if quote can be edited.
     * Only draft quotes can be edited - sent quotes require versioning.
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if quote can be sent.
     */
    public function canSend(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if quote is active (not in terminal state).
     */
    public function isActive(): bool
    {
        return ! in_array($this, [self::ACCEPTED, self::REJECTED, self::EXPIRED, self::SUPERSEDED], true);
    }
}

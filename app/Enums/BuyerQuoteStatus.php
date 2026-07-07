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
     * Check if quote content can be edited.
     */
    public function canEdit(): bool
    {
        return match ($this) {
            self::DRAFT, self::SENT => true,
            self::ACCEPTED, self::REJECTED, self::EXPIRED, self::SUPERSEDED => false,
        };
    }

    /**
     * Check if quote can be sent.
     */
    public function canSend(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if a new version can be created from this quote.
     */
    public function canCreateNewVersion(): bool
    {
        return match ($this) {
            self::SENT, self::REJECTED, self::EXPIRED => true,
            self::DRAFT, self::ACCEPTED, self::SUPERSEDED => false,
        };
    }

    /**
     * Check if quote is active (not in terminal state).
     */
    public function isActive(): bool
    {
        return ! in_array($this, [self::ACCEPTED, self::REJECTED, self::EXPIRED, self::SUPERSEDED], true);
    }
}

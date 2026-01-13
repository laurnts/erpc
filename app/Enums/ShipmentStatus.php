<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShipmentStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case PARTIAL = 'partial';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_TRANSIT => 'In Transit',
            self::DELIVERED => 'Delivered',
            self::PARTIAL => 'Partial Delivery',
            self::FAILED => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::IN_TRANSIT => 'info',
            self::DELIVERED => 'success',
            self::PARTIAL => 'warning',
            self::FAILED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::IN_TRANSIT => 'heroicon-o-truck',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::PARTIAL => 'heroicon-o-minus-circle',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if this status is terminal (no further progression).
     */
    public function isTerminal(): bool
    {
        return $this === self::DELIVERED || $this === self::FAILED;
    }

    /**
     * Check if quantity tracking is relevant for this status.
     */
    public function allowsQuantityTracking(): bool
    {
        return match ($this) {
            self::DELIVERED, self::PARTIAL => true,
            default => false,
        };
    }

    /**
     * Get the allowed transitions from this status.
     *
     * @return list<self>
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::IN_TRANSIT, self::FAILED],
            self::IN_TRANSIT => [self::DELIVERED, self::PARTIAL, self::FAILED],
            self::PARTIAL => [self::DELIVERED, self::FAILED],
            self::DELIVERED => [],
            self::FAILED => [self::PENDING],
        };
    }

    /**
     * Check if transition to the given status is allowed.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->getAllowedTransitions(), true);
    }
}

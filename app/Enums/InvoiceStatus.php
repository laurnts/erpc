<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::PARTIAL => 'Partially Paid',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::PARTIAL => 'warning',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-pencil-square',
            self::SENT => 'heroicon-o-paper-airplane',
            self::PARTIAL => 'heroicon-o-clock',
            self::PAID => 'heroicon-o-check-circle',
            self::OVERDUE => 'heroicon-o-exclamation-triangle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Check if the invoice can be edited in this status.
     */
    public function canEdit(): bool
    {
        return match ($this) {
            self::DRAFT, self::SENT => true,
            self::PARTIAL, self::PAID, self::OVERDUE, self::CANCELLED => false,
        };
    }

    /**
     * Check if the invoice can transition to the given status.
     */
    public function canTransitionTo(self $targetStatus): bool
    {
        return match ($this) {
            self::DRAFT => in_array($targetStatus, [self::SENT, self::CANCELLED], true),
            self::SENT => in_array($targetStatus, [self::PARTIAL, self::PAID, self::OVERDUE, self::CANCELLED], true),
            self::PARTIAL => in_array($targetStatus, [self::PAID, self::OVERDUE, self::CANCELLED], true),
            self::OVERDUE => in_array($targetStatus, [self::PARTIAL, self::PAID, self::CANCELLED], true),
            self::PAID, self::CANCELLED => false,
        };
    }

    /**
     * Check if the invoice is in an active (non-terminal) status.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::DRAFT, self::SENT, self::PARTIAL, self::OVERDUE => true,
            self::PAID, self::CANCELLED => false,
        };
    }

    /**
     * Check if the invoice is in a terminal status.
     */
    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }

    /**
     * Check if payments can be recorded against this invoice.
     */
    public function canRecordPayment(): bool
    {
        return match ($this) {
            self::SENT, self::PARTIAL, self::OVERDUE => true,
            self::DRAFT, self::PAID, self::CANCELLED => false,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case CONFIRMED = 'confirmed';
    case APPROVED = 'approved';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case INVOICED = 'invoiced';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::CONFIRMED => 'Confirmed',
            self::APPROVED => 'Approved',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::INVOICED => 'Invoiced',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::CONFIRMED => 'info',
            self::APPROVED => 'success',
            self::PROCESSING => 'warning',
            self::SHIPPED => 'primary',
            self::DELIVERED => 'success',
            self::INVOICED => 'info',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-pencil',
            self::SENT => 'heroicon-o-paper-airplane',
            self::CONFIRMED => 'heroicon-o-check-circle',
            self::APPROVED => 'heroicon-o-check-badge',
            self::PROCESSING => 'heroicon-o-cog-6-tooth',
            self::SHIPPED => 'heroicon-o-truck',
            self::DELIVERED => 'heroicon-o-inbox-arrow-down',
            self::INVOICED => 'heroicon-o-document-text',
            self::COMPLETED => 'heroicon-o-check-badge',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Label for supplier orders, where CONFIRMED means internal
     * confirmation pending two approvals — not supplier acknowledgement.
     * Buyer orders keep getLabel(), where Confirmed is the buyer's action.
     */
    public function getSupplierOrderLabel(): string
    {
        if ($this === self::CONFIRMED) {
            return 'Awaiting Approval';
        }

        return $this->getLabel();
    }

    /**
     * Check if order can be edited (only drafts).
     */
    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if order can be sent.
     */
    public function canSend(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Check if order can be confirmed.
     */
    public function canConfirm(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT], true);
    }

    /**
     * Check if order can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT, self::CONFIRMED], true);
    }

    /**
     * Check if order can be approved.
     */
    public function canApprove(): bool
    {
        return $this === self::CONFIRMED;
    }

    /**
     * Check if order is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * Check if order is active (not in terminal state).
     */
    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Check if order can progress to next status.
     */
    public function canProgress(): bool
    {
        return ! in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * Get the next logical status in the workflow.
     */
    public function getNextStatus(): ?self
    {
        return match ($this) {
            self::DRAFT => self::SENT,
            self::SENT => self::CONFIRMED,
            self::CONFIRMED => self::APPROVED,
            self::APPROVED => self::PROCESSING,
            self::PROCESSING => self::SHIPPED,
            self::SHIPPED => self::DELIVERED,
            self::DELIVERED => self::INVOICED,
            self::INVOICED => self::COMPLETED,
            self::COMPLETED, self::CANCELLED => null,
        };
    }
}

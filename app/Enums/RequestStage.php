<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RequestStage: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case QUOTING_SUPPLIER = 'quoting_supplier';
    case QUOTING_BUYER = 'quoting_buyer';
    case QUOTE_SENT = 'quote_sent';
    case QUOTE_ACCEPTED = 'quote_accepted';
    case ORDERED = 'ordered';
    case IN_PROGRESS = 'in_progress';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case INVOICED = 'invoiced';
    case PAID = 'paid';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::QUOTING_SUPPLIER => 'Quoting Supplier',
            self::QUOTING_BUYER => 'Quoting Buyer',
            self::QUOTE_SENT => 'Quote Sent',
            self::QUOTE_ACCEPTED => 'Quote Accepted',
            self::ORDERED => 'Ordered',
            self::IN_PROGRESS => 'In Progress',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::INVOICED => 'Invoiced',
            self::PAID => 'Paid',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::QUOTING_SUPPLIER => 'info',
            self::QUOTING_BUYER => 'info',
            self::QUOTE_SENT => 'warning',
            self::QUOTE_ACCEPTED => 'success',
            self::ORDERED => 'primary',
            self::IN_PROGRESS => 'primary',
            self::SHIPPED => 'info',
            self::DELIVERED => 'success',
            self::INVOICED => 'warning',
            self::PAID => 'success',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-pencil-square',
            self::QUOTING_SUPPLIER => 'heroicon-o-document-magnifying-glass',
            self::QUOTING_BUYER => 'heroicon-o-document-text',
            self::QUOTE_SENT => 'heroicon-o-paper-airplane',
            self::QUOTE_ACCEPTED => 'heroicon-o-check-badge',
            self::ORDERED => 'heroicon-o-shopping-cart',
            self::IN_PROGRESS => 'heroicon-o-arrow-path',
            self::SHIPPED => 'heroicon-o-truck',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::INVOICED => 'heroicon-o-document-currency-dollar',
            self::PAID => 'heroicon-o-banknotes',
            self::COMPLETED => 'heroicon-o-flag',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get the allowed transitions from this stage.
     *
     * @return list<self>
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::QUOTING_SUPPLIER, self::CANCELLED],
            self::QUOTING_SUPPLIER => [self::QUOTING_BUYER, self::DRAFT, self::CANCELLED],
            self::QUOTING_BUYER => [self::QUOTE_SENT, self::QUOTING_SUPPLIER, self::CANCELLED],
            self::QUOTE_SENT => [self::QUOTE_ACCEPTED, self::QUOTING_BUYER, self::CANCELLED],
            self::QUOTE_ACCEPTED => [self::ORDERED, self::QUOTE_SENT, self::CANCELLED],
            self::ORDERED => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED, self::CANCELLED],
            self::DELIVERED => [self::INVOICED, self::CANCELLED],
            self::INVOICED => [self::PAID, self::CANCELLED],
            self::PAID => [self::COMPLETED],
            self::COMPLETED => [],
            self::CANCELLED => [self::DRAFT],
        };
    }

    /**
     * Check if transition to the given stage is allowed.
     */
    public function canTransitionTo(self $stage): bool
    {
        return in_array($stage, $this->getAllowedTransitions(), true);
    }

    /**
     * Check if this stage requires all items to be matched (have article_id).
     */
    public function requiresMatchedItems(): bool
    {
        return match ($this) {
            self::DRAFT => false,
            default => true,
        };
    }

    /**
     * Check if this is a terminal stage (no further progression).
     */
    public function isTerminal(): bool
    {
        return $this === self::COMPLETED || $this === self::CANCELLED;
    }

    /**
     * Check if this stage allows editing of request items.
     */
    public function allowsItemEditing(): bool
    {
        return match ($this) {
            self::DRAFT, self::QUOTING_SUPPLIER => true,
            default => false,
        };
    }
}

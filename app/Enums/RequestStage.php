<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RequestStage: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case AWAITING_SUPPLIER_RESPONSE = 'awaiting_supplier_response';
    case PREPARING_BUYER_QUOTE = 'preparing_buyer_quote';
    case AWAITING_BUYER_CONFIRMATION = 'awaiting_buyer_confirmation';
    case PREPARING_SUPPLIER_ORDER = 'preparing_supplier_order';
    case GOODS_RECEIVE = 'goods_receive';
    case AWAITING_SHIPMENT = 'awaiting_shipment';
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
            self::AWAITING_SUPPLIER_RESPONSE => 'Awaiting Supplier Response',
            self::PREPARING_BUYER_QUOTE => 'Preparing Buyer Quote',
            self::AWAITING_BUYER_CONFIRMATION => 'Awaiting Buyer Confirmation',
            self::PREPARING_SUPPLIER_ORDER => 'Preparing Supplier Order',
            self::GOODS_RECEIVE => 'Goods Receive',
            self::AWAITING_SHIPMENT => 'Awaiting Shipment',
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
            self::AWAITING_SUPPLIER_RESPONSE => 'warning',
            self::PREPARING_BUYER_QUOTE => 'info',
            self::AWAITING_BUYER_CONFIRMATION => 'warning',
            self::PREPARING_SUPPLIER_ORDER => 'info',
            self::GOODS_RECEIVE => 'info',
            self::AWAITING_SHIPMENT => 'warning',
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
            self::AWAITING_SUPPLIER_RESPONSE => 'heroicon-o-clock',
            self::PREPARING_BUYER_QUOTE => 'heroicon-o-document-text',
            self::AWAITING_BUYER_CONFIRMATION => 'heroicon-o-clock',
            self::PREPARING_SUPPLIER_ORDER => 'heroicon-o-shopping-cart',
            self::GOODS_RECEIVE => 'heroicon-o-archive-box',
            self::AWAITING_SHIPMENT => 'heroicon-o-clock',
            self::SHIPPED => 'heroicon-o-truck',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::INVOICED => 'heroicon-o-document-currency-dollar',
            self::PAID => 'heroicon-o-banknotes',
            self::COMPLETED => 'heroicon-o-flag',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get the phase name for this stage.
     */
    public function getPhase(): string
    {
        return match ($this) {
            self::DRAFT, self::AWAITING_SUPPLIER_RESPONSE => 'Sourcing',
            self::PREPARING_BUYER_QUOTE, self::AWAITING_BUYER_CONFIRMATION => 'Quoting',
            self::PREPARING_SUPPLIER_ORDER, self::GOODS_RECEIVE, self::AWAITING_SHIPMENT => 'Ordering',
            self::SHIPPED, self::DELIVERED => 'Delivery',
            self::INVOICED, self::PAID, self::COMPLETED => 'Closing',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get the step indicator (e.g., "1/6", "2/6") for the main workflow tabs.
     */
    public function getPhaseStep(): string
    {
        return match ($this) {
            self::DRAFT => '1/6',
            self::AWAITING_SUPPLIER_RESPONSE => '2/6',
            self::PREPARING_BUYER_QUOTE => '3/6',
            self::AWAITING_BUYER_CONFIRMATION => '4/6',
            self::PREPARING_SUPPLIER_ORDER => '5/7',
            self::GOODS_RECEIVE => '6/7',
            self::AWAITING_SHIPMENT => '7/7',
            self::SHIPPED, self::DELIVERED, self::INVOICED, self::PAID, self::COMPLETED => '✓',
            self::CANCELLED => '-',
        };
    }

    /**
     * Get the full label with phase step (e.g., "Draft (1/2)").
     */
    public function getLabelWithStep(): string
    {
        $step = $this->getPhaseStep();

        if ($step === '-') {
            return $this->getLabel();
        }

        return $this->getLabel().' ('.$step.')';
    }

    /**
     * Get the relation manager key associated with this stage.
     */
    public function getRelationManagerKey(): ?string
    {
        return match ($this) {
            self::DRAFT => 'items',
            self::AWAITING_SUPPLIER_RESPONSE => 'supplierQuotes',
            self::PREPARING_BUYER_QUOTE => 'buyerQuotes',
            self::AWAITING_BUYER_CONFIRMATION => 'buyerOrders',
            self::PREPARING_SUPPLIER_ORDER => 'supplierOrders',
            self::GOODS_RECEIVE => 'goodsReceive',
            self::AWAITING_SHIPMENT => 'shipments',
            self::DELIVERED => 'completionReports',
            default => null,
        };
    }

    /**
     * Get the stage for a given relation manager key.
     */
    public static function fromRelationManagerKey(string $key): ?self
    {
        return match ($key) {
            'items' => self::DRAFT,
            'supplierQuotes' => self::AWAITING_SUPPLIER_RESPONSE,
            'buyerQuotes' => self::PREPARING_BUYER_QUOTE,
            'buyerOrders' => self::AWAITING_BUYER_CONFIRMATION,
            'supplierOrders' => self::PREPARING_SUPPLIER_ORDER,
            'goodsReceive' => self::GOODS_RECEIVE,
            'shipments' => self::AWAITING_SHIPMENT,
            'completionReports' => self::DELIVERED,
            default => null,
        };
    }

    /**
     * Get the numeric order of this stage for comparison.
     */
    public function getOrder(): int
    {
        return match ($this) {
            self::DRAFT => 1,
            self::AWAITING_SUPPLIER_RESPONSE => 2,
            self::PREPARING_BUYER_QUOTE => 3,
            self::AWAITING_BUYER_CONFIRMATION => 4,
            self::PREPARING_SUPPLIER_ORDER => 5,
            self::GOODS_RECEIVE => 6,
            self::AWAITING_SHIPMENT => 7,
            self::SHIPPED => 8,
            self::DELIVERED => 9,
            self::INVOICED => 10,
            self::PAID => 11,
            self::COMPLETED => 12,
            self::CANCELLED => -1,
        };
    }

    /**
     * Check if this stage is before the given stage.
     */
    public function isBefore(self $stage): bool
    {
        return $this->getOrder() < $stage->getOrder() && $this->getOrder() > 0 && $stage->getOrder() > 0;
    }

    /**
     * Get the allowed transitions from this stage.
     *
     * @return list<self>
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::AWAITING_SUPPLIER_RESPONSE, self::CANCELLED],
            self::AWAITING_SUPPLIER_RESPONSE => [self::PREPARING_BUYER_QUOTE, self::DRAFT, self::CANCELLED],
            self::PREPARING_BUYER_QUOTE => [self::AWAITING_BUYER_CONFIRMATION, self::PREPARING_SUPPLIER_ORDER, self::AWAITING_SUPPLIER_RESPONSE, self::CANCELLED],
            self::AWAITING_BUYER_CONFIRMATION => [self::PREPARING_SUPPLIER_ORDER, self::PREPARING_BUYER_QUOTE, self::CANCELLED],
            self::PREPARING_SUPPLIER_ORDER => [self::GOODS_RECEIVE, self::AWAITING_BUYER_CONFIRMATION, self::CANCELLED],
            self::GOODS_RECEIVE => [self::AWAITING_SHIPMENT, self::PREPARING_SUPPLIER_ORDER, self::CANCELLED],
            self::AWAITING_SHIPMENT => [self::SHIPPED, self::GOODS_RECEIVE, self::CANCELLED],
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
            self::DRAFT, self::AWAITING_SUPPLIER_RESPONSE, self::PREPARING_BUYER_QUOTE => true,
            default => false,
        };
    }
}

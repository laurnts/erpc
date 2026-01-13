<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RequestActivityType: string implements HasColor, HasIcon, HasLabel
{
    case CREATED = 'created';
    case STATUS_CHANGED = 'status_changed';
    case STAGE_CHANGED = 'stage_changed';
    case QUOTE_RECEIVED = 'quote_received';
    case QUOTE_SELECTED = 'quote_selected';
    case QUOTE_SENT = 'quote_sent';
    case ORDER_CREATED = 'order_created';
    case SHIPMENT_RECEIVED = 'shipment_received';
    case SHIPMENT_SENT = 'shipment_sent';
    case INVOICE_CREATED = 'invoice_created';
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_SENT = 'payment_sent';
    case NOTE_ADDED = 'note_added';
    case COMPLETED = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::STATUS_CHANGED => 'Status Changed',
            self::STAGE_CHANGED => 'Stage Changed',
            self::QUOTE_RECEIVED => 'Quote Received',
            self::QUOTE_SELECTED => 'Quote Selected',
            self::QUOTE_SENT => 'Quote Sent',
            self::ORDER_CREATED => 'Order Created',
            self::SHIPMENT_RECEIVED => 'Shipment Received',
            self::SHIPMENT_SENT => 'Shipment Sent',
            self::INVOICE_CREATED => 'Invoice Created',
            self::PAYMENT_RECEIVED => 'Payment Received',
            self::PAYMENT_SENT => 'Payment Sent',
            self::NOTE_ADDED => 'Note Added',
            self::COMPLETED => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CREATED => 'success',
            self::STATUS_CHANGED => 'info',
            self::STAGE_CHANGED => 'primary',
            self::QUOTE_RECEIVED => 'info',
            self::QUOTE_SELECTED => 'success',
            self::QUOTE_SENT => 'warning',
            self::ORDER_CREATED => 'primary',
            self::SHIPMENT_RECEIVED => 'info',
            self::SHIPMENT_SENT => 'info',
            self::INVOICE_CREATED => 'warning',
            self::PAYMENT_RECEIVED => 'success',
            self::PAYMENT_SENT => 'warning',
            self::NOTE_ADDED => 'gray',
            self::COMPLETED => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::CREATED => 'heroicon-o-plus-circle',
            self::STATUS_CHANGED => 'heroicon-o-arrow-path',
            self::STAGE_CHANGED => 'heroicon-o-chevron-double-right',
            self::QUOTE_RECEIVED => 'heroicon-o-inbox-arrow-down',
            self::QUOTE_SELECTED => 'heroicon-o-check-badge',
            self::QUOTE_SENT => 'heroicon-o-paper-airplane',
            self::ORDER_CREATED => 'heroicon-o-shopping-cart',
            self::SHIPMENT_RECEIVED => 'heroicon-o-arrow-down-on-square',
            self::SHIPMENT_SENT => 'heroicon-o-truck',
            self::INVOICE_CREATED => 'heroicon-o-document-currency-dollar',
            self::PAYMENT_RECEIVED => 'heroicon-o-banknotes',
            self::PAYMENT_SENT => 'heroicon-o-credit-card',
            self::NOTE_ADDED => 'heroicon-o-chat-bubble-left-ellipsis',
            self::COMPLETED => 'heroicon-o-flag',
        };
    }
}

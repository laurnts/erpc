<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ActivityType: string implements HasColor, HasIcon, HasLabel
{
    // Request lifecycle
    case REQUEST_CREATED = 'request_created';
    case REQUEST_UPDATED = 'request_updated';
    case STAGE_CHANGED = 'stage_changed';

    // Item activities
    case ITEM_ADDED = 'item_added';
    case ITEM_UPDATED = 'item_updated';
    case ITEM_REMOVED = 'item_removed';
    case ITEM_MATCHED = 'item_matched';

    // Supplier quote activities
    case SUPPLIER_QUOTE_CREATED = 'supplier_quote_created';
    case SUPPLIER_QUOTE_RECEIVED = 'supplier_quote_received';
    case SUPPLIER_QUOTE_SELECTED = 'supplier_quote_selected';

    // Buyer quote activities
    case BUYER_QUOTE_CREATED = 'buyer_quote_created';
    case BUYER_QUOTE_SENT = 'buyer_quote_sent';
    case BUYER_QUOTE_ACCEPTED = 'buyer_quote_accepted';
    case BUYER_QUOTE_REJECTED = 'buyer_quote_rejected';

    // Buyer order activities
    case BUYER_ORDER_CREATED = 'buyer_order_created';
    case BUYER_ORDER_CONFIRMED = 'buyer_order_confirmed';

    // Supplier order activities
    case SUPPLIER_ORDER_CREATED = 'supplier_order_created';
    case SUPPLIER_ORDER_SENT = 'supplier_order_sent';

    // Shipment activities
    case SHIPMENT_CREATED = 'shipment_created';
    case SHIPMENT_IN_TRANSIT = 'shipment_in_transit';
    case SHIPMENT_DELIVERED = 'shipment_delivered';

    // Invoice activities
    case INVOICE_CREATED = 'invoice_created';
    case INVOICE_SENT = 'invoice_sent';
    case INVOICE_PAID = 'invoice_paid';

    // Payment activities
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_MADE = 'payment_made';

    // General activities
    case NOTE_ADDED = 'note_added';
    case TASK_COMPLETED = 'task_completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::REQUEST_CREATED => 'Request Created',
            self::REQUEST_UPDATED => 'Request Updated',
            self::STAGE_CHANGED => 'Stage Changed',
            self::ITEM_ADDED => 'Item Added',
            self::ITEM_UPDATED => 'Item Updated',
            self::ITEM_REMOVED => 'Item Removed',
            self::ITEM_MATCHED => 'Item Matched',
            self::SUPPLIER_QUOTE_CREATED => 'Supplier Quote Created',
            self::SUPPLIER_QUOTE_RECEIVED => 'Supplier Quote Received',
            self::SUPPLIER_QUOTE_SELECTED => 'Supplier Quote Selected',
            self::BUYER_QUOTE_CREATED => 'Buyer Quote Created',
            self::BUYER_QUOTE_SENT => 'Buyer Quote Sent',
            self::BUYER_QUOTE_ACCEPTED => 'Buyer Quote Accepted',
            self::BUYER_QUOTE_REJECTED => 'Buyer Quote Rejected',
            self::BUYER_ORDER_CREATED => 'Buyer Order Created',
            self::BUYER_ORDER_CONFIRMED => 'Buyer Order Confirmed',
            self::SUPPLIER_ORDER_CREATED => 'Supplier Order Created',
            self::SUPPLIER_ORDER_SENT => 'Supplier Order Sent',
            self::SHIPMENT_CREATED => 'Shipment Created',
            self::SHIPMENT_IN_TRANSIT => 'Shipment In Transit',
            self::SHIPMENT_DELIVERED => 'Shipment Delivered',
            self::INVOICE_CREATED => 'Invoice Created',
            self::INVOICE_SENT => 'Invoice Sent',
            self::INVOICE_PAID => 'Invoice Paid',
            self::PAYMENT_RECEIVED => 'Payment Received',
            self::PAYMENT_MADE => 'Payment Made',
            self::NOTE_ADDED => 'Note Added',
            self::TASK_COMPLETED => 'Task Completed',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::REQUEST_CREATED => 'heroicon-o-plus-circle',
            self::REQUEST_UPDATED => 'heroicon-o-pencil-square',
            self::STAGE_CHANGED => 'heroicon-o-chevron-double-right',
            self::ITEM_ADDED => 'heroicon-o-plus',
            self::ITEM_UPDATED => 'heroicon-o-pencil',
            self::ITEM_REMOVED => 'heroicon-o-minus',
            self::ITEM_MATCHED => 'heroicon-o-check-circle',
            self::SUPPLIER_QUOTE_CREATED => 'heroicon-o-document-plus',
            self::SUPPLIER_QUOTE_RECEIVED => 'heroicon-o-inbox-arrow-down',
            self::SUPPLIER_QUOTE_SELECTED => 'heroicon-o-check-badge',
            self::BUYER_QUOTE_CREATED => 'heroicon-o-document-plus',
            self::BUYER_QUOTE_SENT => 'heroicon-o-paper-airplane',
            self::BUYER_QUOTE_ACCEPTED => 'heroicon-o-hand-thumb-up',
            self::BUYER_QUOTE_REJECTED => 'heroicon-o-hand-thumb-down',
            self::BUYER_ORDER_CREATED => 'heroicon-o-shopping-cart',
            self::BUYER_ORDER_CONFIRMED => 'heroicon-o-check-badge',
            self::SUPPLIER_ORDER_CREATED => 'heroicon-o-clipboard-document-list',
            self::SUPPLIER_ORDER_SENT => 'heroicon-o-paper-airplane',
            self::SHIPMENT_CREATED => 'heroicon-o-cube',
            self::SHIPMENT_IN_TRANSIT => 'heroicon-o-truck',
            self::SHIPMENT_DELIVERED => 'heroicon-o-archive-box-arrow-down',
            self::INVOICE_CREATED => 'heroicon-o-document-text',
            self::INVOICE_SENT => 'heroicon-o-envelope',
            self::INVOICE_PAID => 'heroicon-o-check',
            self::PAYMENT_RECEIVED => 'heroicon-o-banknotes',
            self::PAYMENT_MADE => 'heroicon-o-credit-card',
            self::NOTE_ADDED => 'heroicon-o-chat-bubble-left-ellipsis',
            self::TASK_COMPLETED => 'heroicon-o-flag',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::REQUEST_CREATED => 'success',
            self::REQUEST_UPDATED => 'info',
            self::STAGE_CHANGED => 'primary',
            self::ITEM_ADDED => 'success',
            self::ITEM_UPDATED => 'info',
            self::ITEM_REMOVED => 'danger',
            self::ITEM_MATCHED => 'success',
            self::SUPPLIER_QUOTE_CREATED => 'info',
            self::SUPPLIER_QUOTE_RECEIVED => 'info',
            self::SUPPLIER_QUOTE_SELECTED => 'success',
            self::BUYER_QUOTE_CREATED => 'info',
            self::BUYER_QUOTE_SENT => 'warning',
            self::BUYER_QUOTE_ACCEPTED => 'success',
            self::BUYER_QUOTE_REJECTED => 'danger',
            self::BUYER_ORDER_CREATED => 'primary',
            self::BUYER_ORDER_CONFIRMED => 'success',
            self::SUPPLIER_ORDER_CREATED => 'primary',
            self::SUPPLIER_ORDER_SENT => 'warning',
            self::SHIPMENT_CREATED => 'info',
            self::SHIPMENT_IN_TRANSIT => 'warning',
            self::SHIPMENT_DELIVERED => 'success',
            self::INVOICE_CREATED => 'info',
            self::INVOICE_SENT => 'warning',
            self::INVOICE_PAID => 'success',
            self::PAYMENT_RECEIVED => 'success',
            self::PAYMENT_MADE => 'warning',
            self::NOTE_ADDED => 'gray',
            self::TASK_COMPLETED => 'success',
        };
    }
}

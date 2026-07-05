<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Result of sending a supplier order (purchase order) to its supplier.
 * The order is always marked as sent; this reports what happened with the
 * accompanying email.
 */
enum SupplierOrderSendOutcome: string
{
    case Sent = 'sent';
    case MarkedWithoutEmail = 'marked_without_email';
    case EmailFailed = 'email_failed';
}

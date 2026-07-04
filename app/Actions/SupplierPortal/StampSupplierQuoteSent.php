<?php

declare(strict_types=1);

namespace App\Actions\SupplierPortal;

use App\Enums\SupplierQuoteSubmissionMethod;
use App\Models\SupplierQuote;

/**
 * Stamps the supplier-portal visibility gate when a "Send to Suppliers" mail
 * is successfully dispatched. Re-sending to a supplier who had declined is a
 * fresh RFQ by staff intent: the decline and any prior submission stamps are
 * cleared before the gate is re-stamped.
 */
final readonly class StampSupplierQuoteSent
{
    public function execute(SupplierQuote $quote): void
    {
        $updates = ['sent_to_supplier_at' => now()];

        if ($quote->declined_at !== null) {
            $updates['declined_at'] = null;
            $updates['submitted_via'] = SupplierQuoteSubmissionMethod::Internal;
            $updates['submitted_at'] = null;
            $updates['submitted_by_user_id'] = null;
        }

        $quote->forceFill($updates)->saveQuietly();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\SupplierPortal;

use App\Enums\SupplierQuoteStatus;
use App\Models\SupplierQuote;

/**
 * Supplier-facing status vocabulary. Precedence: a decline stamp always wins
 * (a declined quote never reads "Expired"); pre-announcement evaluation
 * churn (RECEIVED/SELECTED/REJECTED) renders uniformly as
 * "Submitted — under review" so internal selection state never leaks.
 * Announced Won/Lost labels land with the outcome-announcement slice.
 */
final readonly class SupplierRfqStatusPresenter
{
    public function label(SupplierQuote $quote): string
    {
        if ($quote->declined_at !== null) {
            return 'Declined';
        }

        return match ($quote->status) {
            SupplierQuoteStatus::PENDING => $quote->is_expired ? 'Expired' : 'Awaiting your quote',
            SupplierQuoteStatus::EXPIRED => 'Expired',
            SupplierQuoteStatus::RECEIVED,
            SupplierQuoteStatus::SELECTED,
            SupplierQuoteStatus::REJECTED => 'Submitted — under review',
        };
    }

    public function color(SupplierQuote $quote): string
    {
        if ($quote->declined_at !== null) {
            return 'danger';
        }

        return match ($quote->status) {
            SupplierQuoteStatus::PENDING => $quote->is_expired ? 'gray' : 'warning',
            SupplierQuoteStatus::EXPIRED => 'gray',
            SupplierQuoteStatus::RECEIVED,
            SupplierQuoteStatus::SELECTED,
            SupplierQuoteStatus::REJECTED => 'info',
        };
    }
}

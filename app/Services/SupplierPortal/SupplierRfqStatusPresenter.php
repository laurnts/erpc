<?php

declare(strict_types=1);

namespace App\Services\SupplierPortal;

use App\Enums\SupplierQuoteStatus;
use App\Models\SupplierQuote;

/**
 * Supplier-facing status vocabulary. Precedence: a decline stamp always wins
 * (a declined quote never reads "Expired"); announced outcomes come next
 * ("Won" / "Not selected" — the internal REJECTED vocabulary never leaks);
 * pre-announcement evaluation churn (RECEIVED/SELECTED/REJECTED) renders
 * uniformly as "Submitted — under review" so internal selection state never
 * leaks before the explicit outcome announcement.
 */
final readonly class SupplierRfqStatusPresenter
{
    public function label(SupplierQuote $quote): string
    {
        if ($quote->declined_at !== null) {
            return 'Declined';
        }

        if ($quote->outcomes_announced_at !== null) {
            if ($quote->status === SupplierQuoteStatus::REJECTED) {
                return 'Not selected';
            }

            if ($quote->status === SupplierQuoteStatus::SELECTED || $quote->status === SupplierQuoteStatus::RECEIVED) {
                return 'Won';
            }
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

        if ($quote->outcomes_announced_at !== null) {
            if ($quote->status === SupplierQuoteStatus::REJECTED) {
                return 'gray';
            }

            if ($quote->status === SupplierQuoteStatus::SELECTED || $quote->status === SupplierQuoteStatus::RECEIVED) {
                return 'success';
            }
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

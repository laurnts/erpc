<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BuyerInvoice;
use App\Models\User;

final readonly class BuyerInvoiceObserver
{
    /**
     * Handle the BuyerInvoice "creating" event.
     */
    public function creating(BuyerInvoice $buyerInvoice): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($buyerInvoice->creator_id === null) {
                $buyerInvoice->creator_id = $user->getKey();
            }

            if ($buyerInvoice->team_id === null && $user->currentTeam !== null) {
                $buyerInvoice->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate invoice number if not provided
        /** @var string|null $invoiceNumber */
        $invoiceNumber = $buyerInvoice->invoice_number;
        if (($invoiceNumber === null || $invoiceNumber === '') && $buyerInvoice->team_id !== null) {
            $buyerInvoice->invoice_number = BuyerInvoice::generateNextNumber($buyerInvoice->team_id);
        }
    }
}

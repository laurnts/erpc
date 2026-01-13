<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BuyerPayment;
use App\Models\User;

final readonly class BuyerPaymentObserver
{
    /**
     * Handle the BuyerPayment "creating" event.
     */
    public function creating(BuyerPayment $buyerPayment): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($buyerPayment->creator_id === null) {
                $buyerPayment->creator_id = $user->getKey();
            }

            if ($buyerPayment->team_id === null && $user->currentTeam !== null) {
                $buyerPayment->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate payment number if not provided
        /** @var string|null $paymentNumber */
        $paymentNumber = $buyerPayment->payment_number;
        if (($paymentNumber === null || $paymentNumber === '') && $buyerPayment->team_id !== null) {
            $buyerPayment->payment_number = BuyerPayment::generateNextNumber($buyerPayment->team_id);
        }

        // Set payment date to today if not provided
        if ($buyerPayment->payment_date === null) {
            $buyerPayment->payment_date = now();
        }
    }

    /**
     * Handle the BuyerPayment "created" event.
     */
    public function created(BuyerPayment $buyerPayment): void
    {
        // Update the invoice payment status
        $buyerPayment->buyerInvoice->updatePaymentStatus();
    }

    /**
     * Handle the BuyerPayment "updated" event.
     */
    public function updated(BuyerPayment $buyerPayment): void
    {
        // Update the invoice payment status
        $buyerPayment->buyerInvoice->updatePaymentStatus();
    }

    /**
     * Handle the BuyerPayment "deleted" event.
     */
    public function deleted(BuyerPayment $buyerPayment): void
    {
        // Update the invoice payment status
        $buyerPayment->buyerInvoice->updatePaymentStatus();
    }

    /**
     * Handle the BuyerPayment "restored" event.
     */
    public function restored(BuyerPayment $buyerPayment): void
    {
        // Update the invoice payment status
        $buyerPayment->buyerInvoice->updatePaymentStatus();
    }
}

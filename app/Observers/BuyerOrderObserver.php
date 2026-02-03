<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\BuyerOrder;
use App\Models\User;

final readonly class BuyerOrderObserver
{
    /**
     * Handle the BuyerOrder "creating" event.
     */
    public function creating(BuyerOrder $buyerOrder): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($buyerOrder->creator_id === null) {
                $buyerOrder->creator_id = $user->getKey();
            }

            if ($buyerOrder->team_id === null && $user->currentTeam !== null) {
                $buyerOrder->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate order number if not provided
        /** @var string|null $orderNumber */
        $orderNumber = $buyerOrder->order_number;
        if ($orderNumber === null || $orderNumber === '') {
            $buyerOrder->order_number = BuyerOrder::generateNextNumber($buyerOrder->team_id);
        }

        // Set ordered_at if not provided
        if ($buyerOrder->ordered_at === null) {
            $buyerOrder->ordered_at = now();
        }
    }

    /**
     * Handle the BuyerOrder "updating" event.
     * 
     * Note: This observer handles credit restoration when status is changed directly via update() or save().
     * The cancel() and progressStatus() methods handle credit restoration themselves, so we check the stack trace
     * to avoid double restoration.
     */
    public function updating(BuyerOrder $buyerOrder): void
    {
        // Check if status is changing from CONFIRMED to another status
        if ($buyerOrder->isDirty('status')) {
            $originalStatusValue = $buyerOrder->getOriginal('status');
            
            // Handle both string and enum values from getOriginal()
            $originalStatus = $originalStatusValue instanceof OrderStatus 
                ? $originalStatusValue 
                : OrderStatus::from((string) $originalStatusValue);
            
            $newStatus = $buyerOrder->status;

            // If status changes from CONFIRMED to non-CONFIRMED
            // Check if this is coming from cancel() or progressStatus() methods (they handle it themselves)
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
            $calledFromCancel = false;
            $calledFromProgressStatus = false;

            foreach ($backtrace as $frame) {
                if (isset($frame['function'])) {
                    if ($frame['function'] === 'cancel' && isset($frame['class']) && str_contains($frame['class'], 'BuyerOrder')) {
                        $calledFromCancel = true;
                        break;
                    }
                    if ($frame['function'] === 'progressStatus' && isset($frame['class']) && str_contains($frame['class'], 'BuyerOrder')) {
                        $calledFromProgressStatus = true;
                        break;
                    }
                }
            }

            // Only restore credit if not called from cancel() or progressStatus() methods
            if ($originalStatus === OrderStatus::CONFIRMED && $newStatus !== OrderStatus::CONFIRMED && ! $calledFromCancel && ! $calledFromProgressStatus) {
                $orderTotal = (float) $buyerOrder->total;
                if ($orderTotal > 0) {
                    $buyerOrder->restoreCredit();
                }
            }
        }
    }
}

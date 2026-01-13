<?php

declare(strict_types=1);

namespace App\Observers;

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
}

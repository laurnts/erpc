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
     * Restores credit when a CONFIRMED order's status is changed directly via
     * update()/save(). The cancel() and progressStatus() methods restore credit
     * themselves and signal this by setting the transient creditRestoreHandled
     * flag, so this observer skips them to avoid double restoration.
     */
    public function updating(BuyerOrder $buyerOrder): void
    {
        if (! $buyerOrder->isDirty('status')) {
            return;
        }

        // cancel()/progressStatus() already restore credit; consume the flag and skip.
        if ($buyerOrder->creditRestoreHandled) {
            $buyerOrder->creditRestoreHandled = false;

            return;
        }

        $originalStatusValue = $buyerOrder->getOriginal('status');
        $originalStatus = $originalStatusValue instanceof OrderStatus
            ? $originalStatusValue
            : OrderStatus::from((string) $originalStatusValue);

        if ($originalStatus === OrderStatus::CONFIRMED
            && $buyerOrder->status !== OrderStatus::CONFIRMED
            && (float) $buyerOrder->total > 0) {
            $buyerOrder->restoreCredit();
        }
    }
}

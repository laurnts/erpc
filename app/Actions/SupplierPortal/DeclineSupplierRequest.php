<?php

declare(strict_types=1);

namespace App\Actions\SupplierPortal;

use App\Models\SupplierQuote;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SupplierQuoteDeclinedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Supplier declines to quote. The quote keeps its PENDING status: the
 * declined_at stamp alone drives presentation ("Declined" over "Expired"),
 * the reminder-job skip, and the expiry-sweep skip. A staff re-send clears it.
 */
final readonly class DeclineSupplierRequest
{
    public function execute(SupplierQuote $quote): SupplierQuote
    {
        $quote->forceFill(['declined_at' => now()])->save();

        $this->notifyTeam($quote);

        return $quote->refresh();
    }

    private function notifyTeam(SupplierQuote $quote): void
    {
        $team = $quote->team;

        if (! $team instanceof Team) {
            return;
        }

        $recipients = $team->allUsers()
            ->filter(fn (User $user): bool => $user->hasVerifiedEmail())
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SupplierQuoteDeclinedNotification($quote));
    }
}

<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\TeamErpSettings;
use App\Enums\BuyerQuoteStatus;
use App\Models\BuyerQuote;
use App\Models\Team;
use App\Models\User;
use App\Actions\CustomerPortal\NotifyPortalUsers;
use App\Notifications\PortalBuyerQuoteSentNotification;

final readonly class BuyerQuoteObserver
{
    /**
     * Handle the BuyerQuote "creating" event.
     */
    public function creating(BuyerQuote $buyerQuote): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($buyerQuote->creator_id === null) {
                $buyerQuote->creator_id = $user->getKey();
            }

            if ($buyerQuote->team_id === null && $user->currentTeam !== null) {
                $buyerQuote->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate quote number if not provided
        /** @var string|null $quoteNumber */
        $quoteNumber = $buyerQuote->quote_number;
        if ($quoteNumber === null || $quoteNumber === '') {
            $buyerQuote->quote_number = BuyerQuote::generateNextNumber($buyerQuote->team_id);
        }

        // Set default validity date if not provided
        if ($buyerQuote->valid_until === null) {
            $team = $buyerQuote->team ?? ($buyerQuote->team_id !== null ? Team::find($buyerQuote->team_id) : null);
            $settings = $team?->getErpSettings() ?? new TeamErpSettings;
            $buyerQuote->valid_until = now()->addDays($settings->quote_validity_days);
        }
    }

    public function updated(BuyerQuote $buyerQuote): void
    {
        if (! $buyerQuote->wasChanged('status')) {
            return;
        }

        if ($buyerQuote->status !== BuyerQuoteStatus::SENT) {
            return;
        }

        $buyerQuote->loadMissing('request');

        if ($buyerQuote->buyer_id === null) {
            return;
        }

        app(NotifyPortalUsers::class)->forCompany(
            $buyerQuote->buyer_id,
            new PortalBuyerQuoteSentNotification($buyerQuote),
        );
    }
}

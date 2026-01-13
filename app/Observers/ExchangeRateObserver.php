<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ExchangeRate;
use App\Models\User;

final readonly class ExchangeRateObserver
{
    /**
     * Handle the ExchangeRate "creating" event.
     */
    public function creating(ExchangeRate $exchangeRate): void
    {
        // Only set team_id and creator_id if not already set
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($exchangeRate->creator_id === null) {
                $exchangeRate->creator_id = $user->getKey();
            }

            if ($exchangeRate->team_id === null && $user->currentTeam !== null) {
                $exchangeRate->team_id = $user->currentTeam->getKey();
            }
        }
    }
}

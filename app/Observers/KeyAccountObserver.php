<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\KeyAccount;
use App\Models\User;
use Filament\Facades\Filament;

final readonly class KeyAccountObserver
{
    /**
     * Handle the KeyAccount "creating" event.
     */
    public function creating(KeyAccount $keyAccount): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($keyAccount->creator_id === null) {
                $keyAccount->creator_id = $user->getKey();
            }

            if ($keyAccount->team_id === null) {
                // Try Filament tenant first, then fall back to user's current team
                $tenant = Filament::getTenant();
                if ($tenant !== null) {
                    $keyAccount->team_id = $tenant->getKey();
                } elseif ($user->currentTeam !== null) {
                    $keyAccount->team_id = $user->currentTeam->getKey();
                }
            }
        }
    }
}

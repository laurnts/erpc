<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TaxCode;
use App\Models\User;

final readonly class TaxCodeObserver
{
    /**
     * Handle the TaxCode "creating" event.
     */
    public function creating(TaxCode $taxCode): void
    {
        // Only set team_id and creator_id if not already set
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($taxCode->creator_id === null) {
                $taxCode->creator_id = $user->getKey();
            }

            if ($taxCode->team_id === null && $user->currentTeam !== null) {
                $taxCode->team_id = $user->currentTeam->getKey();
            }
        }
    }

    /**
     * Handle the TaxCode "saved" event.
     * Ensures only one default tax code per team.
     */
    public function saved(TaxCode $taxCode): void
    {
        if ($taxCode->is_default && $taxCode->team_id) {
            // Unset other defaults in the same team
            TaxCode::withoutEvents(function () use ($taxCode): void {
                TaxCode::query()
                    ->where('team_id', $taxCode->team_id)
                    ->where('id', '!=', $taxCode->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            });
        }
    }
}

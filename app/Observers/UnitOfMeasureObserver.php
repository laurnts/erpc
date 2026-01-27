<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UnitOfMeasure;
use App\Models\User;

final readonly class UnitOfMeasureObserver
{
    /**
     * Handle the UnitOfMeasure "creating" event.
     */
    public function creating(UnitOfMeasure $unitOfMeasure): void
    {
        // Only set team_id and creator_id if not already set
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($unitOfMeasure->creator_id === null) {
                $unitOfMeasure->creator_id = $user->getKey();
            }

            if ($unitOfMeasure->team_id === null && $user->currentTeam !== null) {
                $unitOfMeasure->team_id = $user->currentTeam->getKey();
            }
        }
    }
}

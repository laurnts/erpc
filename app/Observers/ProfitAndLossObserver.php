<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ProfitAndLoss;
use App\Models\User;

final readonly class ProfitAndLossObserver
{
    /**
     * Handle the ProfitAndLoss "creating" event.
     */
    public function creating(ProfitAndLoss $profitAndLoss): void
    {
        // Only set team_id and creator_id if not already set and user is authenticated
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($profitAndLoss->creator_id === null) {
                $profitAndLoss->creator_id = $user->getKey();
            }

            if ($profitAndLoss->team_id === null && $user->currentTeam !== null) {
                $profitAndLoss->team_id = $user->currentTeam->getKey();
            }
        }

        // Auto-generate PNL number if not provided
        /** @var string|null $pnlNumber */
        $pnlNumber = $profitAndLoss->pnl_number;
        if (($pnlNumber === null || $pnlNumber === '') && $profitAndLoss->team_id !== null) {
            $profitAndLoss->pnl_number = ProfitAndLoss::generatePnlNumber($profitAndLoss->team_id);
        }
    }
}

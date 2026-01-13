<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Opportunity;
use App\Models\User;

final readonly class OpportunityObserver
{
    public function creating(Opportunity $opportunity): void
    {
        if (auth('web')->check()) {
            /** @var User $user */
            $user = auth('web')->user();
            /** @var int<0, max> $creatorId */
            $creatorId = (int) $user->getAuthIdentifier();
            $opportunity->creator_id = $creatorId;
            $opportunity->team_id = $user->currentTeam->getKey();
        }
    }

    /**
     * Handle the Opportunity "saved" event.
     * Invalidate AI summary when opportunity data changes.
     */
    public function saved(Opportunity $opportunity): void
    {
        $opportunity->invalidateAiSummary();
    }
}

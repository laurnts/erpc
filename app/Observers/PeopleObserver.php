<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\People;
use App\Models\User;

final readonly class PeopleObserver
{
    public function creating(People $people): void
    {
        if (auth('web')->check()) {
            /** @var User $user */
            $user = auth('web')->user();
            /** @var int<0, max> $creatorId */
            $creatorId = (int) $user->getAuthIdentifier();
            $people->creator_id = $creatorId;
            $people->team_id = $user->currentTeam->getKey();
        }
    }

    /**
     * Handle the People "saved" event.
     * Invalidate AI summary when person data changes.
     */
    public function saved(People $people): void
    {
        $people->invalidateAiSummary();
    }
}

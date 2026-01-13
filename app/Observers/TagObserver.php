<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tag;
use App\Models\User;

final readonly class TagObserver
{
    /**
     * Handle the Tag "creating" event.
     */
    public function creating(Tag $tag): void
    {
        // Only set team_id and creator_id if not already set
        if (auth()->check()) {
            /** @var User $user */
            $user = auth()->user();

            if ($tag->creator_id === null) {
                $tag->creator_id = $user->getKey();
            }

            if ($tag->team_id === null && $user->currentTeam !== null) {
                $tag->team_id = $user->currentTeam->getKey();
            }
        }
    }
}

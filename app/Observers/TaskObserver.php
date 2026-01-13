<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Task;
use App\Models\User;

final readonly class TaskObserver
{
    public function creating(Task $task): void
    {
        if (auth('web')->check()) {
            /** @var User $user */
            $user = auth('web')->user();
            /** @var int<0, max> $creatorId */
            $creatorId = (int) $user->getAuthIdentifier();
            $task->creator_id = $creatorId;
            $task->team_id = $user->currentTeam->getKey();
        }
    }

    public function saved(Task $task): void
    {
        $task->invalidateRelatedSummaries();
    }

    public function deleted(Task $task): void
    {
        $task->invalidateRelatedSummaries();
    }
}

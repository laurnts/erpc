<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActorType;
use App\Support\ActivityLogContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Team- and actor-aware replacement for Spatie's Activity model.
 *
 * Stamps the owning team and the actor type (staff / buyer / supplier /
 * admin / system) as each activity is written, so the Settings log viewer
 * can scope and filter without fragile cross-morph joins.
 *
 * @property int|null $team_id
 * @property ActorType|null $actor_type
 */
final class ActivityLog extends SpatieActivity
{
    protected static function booted(): void
    {
        self::creating(function (self $activity): void {
            if ($activity->team_id === null) {
                $activity->team_id = ActivityLogContext::currentTeamId();
            }

            if ($activity->actor_type === null) {
                $activity->actor_type = ActivityLogContext::currentActorType();
            }
        });
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Eloquent merges these with the parent Activity model's $casts property
     * via getCasts(), so the inherited `properties` collection cast is kept.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
        ];
    }
}

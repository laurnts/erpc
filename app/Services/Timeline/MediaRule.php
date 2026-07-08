<?php

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Enums\ActorType;

/**
 * Additive scoping rule for one media collection on the timeline.
 *
 * Media rows carry uploader identity in custom_properties (uploader_id +
 * actor_type, stamped at attach time). Collections without a rule are
 * denied by default; pre-stamp media (no actor_type) is only visible when
 * a rule explicitly opts in — portal parties are fail-closed.
 */
final readonly class MediaRule
{
    /**
     * @param  string  $collection  media collection name, '*' = every collection
     * @param  list<ActorType>|null  $uploaderActorTypes  allowed stamped uploader actor types, null = any stamped uploader
     * @param  bool  $allowUnstamped  whether media without an actor_type stamp is visible
     * @param  int|null  $uploaderCompanyId  restrict to uploads by members of this company (identity scope for supplier parties)
     */
    public function __construct(
        public string $collection,
        public ?array $uploaderActorTypes,
        public bool $allowUnstamped,
        public ?int $uploaderCompanyId = null,
    ) {}
}

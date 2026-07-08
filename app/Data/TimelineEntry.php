<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ActorType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * One rendered row of a per-request activity timeline.
 *
 * Entries are built by the timeline read sources (internal, buyer, later
 * supplier) after the audience helper has already selected and redacted the
 * underlying activity/media/credit rows, so a TimelineEntry never carries
 * more than its audience may see. Exact field diffs are not inlined; the
 * optional properties payload feeds the shared event-log detail modal.
 */
final class TimelineEntry extends Data
{
    /**
     * @param  string  $entryType  source lane category: TimelineAudience::ENTRY_ACTIVITY|ENTRY_MEDIA|ENTRY_CREDIT
     * @param  string  $event  specific verb (created, updated, deleted, sent, uploaded, ...)
     * @param  string  $subjectType  morph alias of the subject (request, buyer_quote, ...)
     * @param  string|null  $subjectNumber  display document number (e.g. BQ-2026-088)
     * @param  int  $changedFieldCount  number of audited fields in this save (0 for non-diff entries)
     * @param  string|null  $url  allow-listed link for the audience, null when denied
     * @param  array<string, mixed>|null  $properties  diff payload for the detail modal (attributes/old)
     * @param  string|null  $lane  visual lane marker (e.g. 'credit' for the credit-ledger lane)
     */
    public function __construct(
        public string $actorLabel,
        public ActorType $actorType,
        public string $entryType,
        public string $event,
        public string $headline,
        public string $subjectType,
        public ?int $subjectId,
        public ?string $subjectNumber,
        public int $changedFieldCount,
        public CarbonImmutable $occurredAt,
        public ?string $url = null,
        public ?array $properties = null,
        public ?string $lane = null,
    ) {}
}

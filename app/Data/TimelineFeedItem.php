<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Support\Collection;

/**
 * A single timeline row or a collapsed cluster of similar entries.
 *
 * @phpstan-type TimelineEntryCollection Collection<int, TimelineEntry>
 */
final readonly class TimelineFeedItem
{
    /**
     * @param  TimelineEntryCollection  $entries
     */
    public function __construct(
        public bool $isCluster,
        public string $key,
        public string $summaryHeadline,
        public Collection $entries,
    ) {}

    public function first(): TimelineEntry
    {
        /** @var TimelineEntry $entry */
        $entry = $this->entries->first();

        return $entry;
    }

    public function count(): int
    {
        return $this->entries->count();
    }
}

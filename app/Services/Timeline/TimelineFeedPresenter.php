<?php

declare(strict_types=1);

namespace App\Services\Timeline;

use App\Data\TimelineEntry;
use App\Data\TimelineFeedItem;
use App\Enums\TimelineHistoryFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shapes raw timeline entries for the glanceable history sidebar: filter,
 * day-group, and cluster repetitive same-type rows.
 */
final readonly class TimelineFeedPresenter
{
    /**
     * @param  Collection<int, TimelineEntry>  $entries
     * @return Collection<int, TimelineEntry>
     */
    public function filter(Collection $entries, TimelineHistoryFilter $filter, ?string $search = null): Collection
    {
        $filtered = $entries->filter(function (TimelineEntry $entry) use ($filter): bool {
            return match ($filter) {
                TimelineHistoryFilter::All => true,
                TimelineHistoryFilter::Documents => $entry->entryType === TimelineAudience::ENTRY_MEDIA,
                TimelineHistoryFilter::Quotes => in_array($entry->subjectType, [
                    'buyer_quote',
                    'supplier_quote',
                    'quotation_evaluation',
                    'buyer_quote_item',
                    'supplier_quote_item',
                ], true),
                TimelineHistoryFilter::Approvals => in_array($entry->subjectType, [
                    'quotation_evaluation',
                    'profit_and_loss',
                    'supplier_order',
                    'payment_document_approval',
                ], true) || str_contains(strtolower($entry->headline), 'approv'),
            };
        });

        if ($search === null || trim($search) === '') {
            return $filtered->values();
        }

        $needle = Str::lower(trim($search));

        return $filtered
            ->filter(fn (TimelineEntry $entry): bool => str_contains(Str::lower($entry->headline), $needle)
                || str_contains(Str::lower($entry->actorLabel), $needle)
                || ($entry->subjectNumber !== null && str_contains(Str::lower($entry->subjectNumber), $needle)))
            ->values();
    }

    /**
     * @param  Collection<int, TimelineEntry>  $entries
     * @return Collection<int, array{dayKey: string, label: string, items: Collection<int, TimelineFeedItem>}>
     */
    public function groupByDay(Collection $entries): Collection
    {
        return $entries
            ->groupBy(fn (TimelineEntry $entry): string => $entry->occurredAt->toDateString())
            ->map(fn (Collection $dayEntries, string $day): array => [
                'dayKey' => $day,
                'label' => $this->dayLabel($day),
                'items' => $this->clusterDayEntries($dayEntries->reverse()->values()),
            ])
            ->reverse()
            ->values();
    }

    /**
     * @param  Collection<int, TimelineEntry>  $dayEntries  oldest-first within the day
     * @return Collection<int, TimelineFeedItem>
     */
    private function clusterDayEntries(Collection $dayEntries): Collection
    {
        /** @var Collection<int, TimelineFeedItem> $items */
        $items = collect();
        $buffer = collect();
        $bufferKey = null;

        foreach ($dayEntries as $entry) {
            $clusterKey = $this->clusterKey($entry);

            if ($clusterKey === null) {
                if ($buffer->isNotEmpty()) {
                    $items->push($this->makeCluster($bufferKey, $buffer));
                    $buffer = collect();
                    $bufferKey = null;
                }

                $items->push(new TimelineFeedItem(
                    isCluster: false,
                    key: $this->entryKey($entry),
                    summaryHeadline: $entry->headline,
                    entries: collect([$entry]),
                ));

                continue;
            }

            if ($bufferKey === $clusterKey) {
                $buffer->push($entry);

                continue;
            }

            if ($buffer->isNotEmpty()) {
                $items->push($this->makeCluster($bufferKey, $buffer));
            }

            $bufferKey = $clusterKey;
            $buffer = collect([$entry]);
        }

        if ($buffer->isNotEmpty()) {
            $items->push($this->makeCluster($bufferKey, $buffer));
        }

        return $items->reverse()->values();
    }

    private function clusterKey(TimelineEntry $entry): ?string
    {
        if ($entry->entryType === TimelineAudience::ENTRY_NOTE) {
            return null;
        }

        if ($entry->event === 'updated' && $entry->entryType === TimelineAudience::ENTRY_ACTIVITY) {
            return sprintf('updated|%s|%s', $entry->subjectType, $entry->actorLabel);
        }

        if ($entry->event === 'uploaded' && $entry->entryType === TimelineAudience::ENTRY_MEDIA) {
            return sprintf('uploaded|%s|%s', $entry->subjectType, $entry->actorLabel);
        }

        return null;
    }

    /**
     * @param  Collection<int, TimelineEntry>  $entries
     */
    private function makeCluster(?string $clusterKey, Collection $entries): TimelineFeedItem
    {
        if ($entries->count() === 1) {
            $entry = $entries->first();

            return new TimelineFeedItem(
                isCluster: false,
                key: $this->entryKey($entry),
                summaryHeadline: $entry->headline,
                entries: $entries,
            );
        }

        $first = $entries->first();
        $subjectLabel = Str::headline(Str::plural($first->subjectType));
        $verb = $first->event === 'uploaded' ? 'uploaded' : 'updated';

        return new TimelineFeedItem(
            isCluster: true,
            key: (string) $clusterKey.'|'.$first->occurredAt->toDateString(),
            summaryHeadline: sprintf('%d %s %s', $entries->count(), $subjectLabel, $verb),
            entries: $entries->reverse()->values(),
        );
    }

    private function entryKey(TimelineEntry $entry): string
    {
        return implode('|', [
            $entry->entryType,
            $entry->subjectType,
            (string) ($entry->subjectId ?? ''),
            (string) $entry->occurredAt->getTimestamp(),
            $entry->headline,
        ]);
    }

    private function dayLabel(string $day): string
    {
        $date = Carbon::parse($day);

        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        return $date->format('j M Y');
    }
}

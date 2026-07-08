@php
    /** @var \App\Data\TimelineEntry $entry */
@endphp

<li
    @class([
        'min-w-0 max-w-full',
        'rounded-lg border border-gray-200 bg-gray-50/60 p-3 dark:border-white/10 dark:bg-white/5' => $entry->entryType === \App\Services\Timeline\TimelineAudience::ENTRY_NOTE,
        'border-t border-gray-200 pt-2 first:border-t-0 first:pt-0 dark:border-white/10' => $entry->entryType !== \App\Services\Timeline\TimelineAudience::ENTRY_NOTE,
    ])
    wire:key="entry-{{ $entry->entryType }}-{{ $entry->subjectType }}-{{ $entry->subjectId }}-{{ $entry->occurredAt->getTimestamp() }}-{{ $loop->index ?? 0 }}"
>
    <div class="flex items-start justify-between gap-2">
        <div class="flex min-w-0 flex-wrap items-center gap-1.5">
            @if ($entry->lane === 'credit')
                <x-filament::badge color="info" icon="heroicon-o-building-library">
                    Credit
                </x-filament::badge>
            @else
                <x-filament::badge :color="$entry->actorType->getColor()" :icon="$entry->actorType->getIcon()">
                    {{ $entry->actorLabel }} ({{ $entry->actorType->getLabel() }})
                </x-filament::badge>
            @endif

            @if ($entry->entryType === \App\Services\Timeline\TimelineAudience::ENTRY_NOTE)
                @php $noteVisibility = $entry->properties['visibility'] ?? 'internal'; @endphp
                <x-filament::badge
                    :color="match ($noteVisibility) { 'buyer' => 'success', 'supplier' => 'warning', default => 'gray' }"
                    icon="heroicon-o-chat-bubble-left-right"
                >
                    {{ match ($noteVisibility) { 'buyer' => 'Notes: To Buyer', 'supplier' => 'Notes: To Supplier', default => 'Notes: Internal' } }}
                </x-filament::badge>
            @endif
        </div>

        <span class="shrink-0 whitespace-nowrap text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $entry->occurredAt->format('H:i') }}</span>
    </div>

    <div class="mt-1 min-w-0">
        <div class="flex flex-wrap items-baseline gap-x-2">
            <span class="break-all text-gray-950 dark:text-white">{{ $entry->headline }}</span>

            @if ($entry->changedFieldCount > 0 && $entry->event === 'updated')
                <span class="shrink-0 text-gray-500 dark:text-gray-400">· {{ $entry->changedFieldCount }} {{ Str::plural('field', $entry->changedFieldCount) }}</span>
            @endif
        </div>

        @if ($entry->lane === 'credit')
            <p class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">
                @if ($entry->properties['caused_by'] ?? null)
                    caused by {{ $entry->properties['caused_by'] }}
                @endif
                @if ($entry->properties['recorded_by'] ?? null)
                    · recorded by {{ $entry->properties['recorded_by'] }}
                @endif
                @if (($entry->properties['caused_by'] ?? null) === null && ($entry->properties['recorded_by'] ?? null) === null)
                    {{ $entry->properties['description'] ?? '' }}
                @endif
            </p>
        @elseif ($entry->entryType === \App\Services\Timeline\TimelineAudience::ENTRY_ACTIVITY)
            @php
                $changedFields = $entry->event === 'deleted'
                    ? array_keys($entry->properties['old'] ?? [])
                    : array_keys($entry->properties['attributes'] ?? []);
            @endphp

            <p class="mt-0.5 flex flex-wrap items-baseline gap-x-2 break-words text-xs text-gray-500 dark:text-gray-400">
                @if ($entry->event === 'deleted' && $changedFields !== [])
                    <span>last values snapshotted</span>
                @elseif ($changedFields !== [])
                    <span>{{ implode(', ', array_slice($changedFields, 0, 6)) }}@if (count($changedFields) > 6), …@endif</span>
                @endif

                @if (($entry->properties['activity_id'] ?? null) !== null && ($changedFields !== [] || $entry->event === 'deleted'))
                    <span class="shrink-0">{{ ($this->detailsAction)(['activity' => $entry->properties['activity_id']]) }}</span>
                @endif
            </p>
        @endif
    </div>
</li>

@php
    /** @var array<int, \App\Data\TimelineEntry> $entries */
    $entries = $entries ?? [];

    $dayGroups = collect($entries)
        ->groupBy(fn (\App\Data\TimelineEntry $entry): string => $entry->occurredAt->toDateString())
        ->map(fn ($group, string $date): array => [
            'label' => \Illuminate\Support\Carbon::parse($date)->isToday()
                ? 'Today'
                : (\Illuminate\Support\Carbon::parse($date)->isYesterday()
                    ? 'Yesterday'
                    : \Illuminate\Support\Carbon::parse($date)->format('j M Y')),
            // Oldest-first within the day so the latest entry sits at the bottom.
            'entries' => $group->reverse()->values(),
        ])
        ->reverse()
        ->values();
@endphp

<div class="space-y-4 text-sm">
    @forelse ($dayGroups as $group)
        <div class="space-y-2" wire:key="portal-day-{{ $group['entries']->first()->occurredAt->toDateString() }}">
            <div class="flex items-center gap-3">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group['label'] }}</span>
                <div class="h-px flex-1 bg-gray-200 dark:bg-white/10"></div>
            </div>

            <ul class="space-y-2">
                @foreach ($group['entries'] as $entry)
                    <li
                        @class([
                            'flex flex-wrap items-start gap-x-3 gap-y-1',
                            'rounded-lg border border-gray-200 bg-gray-50/60 p-3 dark:border-white/10 dark:bg-white/5' => $entry->entryType === \App\Services\Timeline\TimelineAudience::ENTRY_NOTE,
                        ])
                        wire:key="portal-entry-{{ $entry->subjectType }}-{{ $entry->subjectId }}-{{ $entry->occurredAt->getTimestamp() }}-{{ $loop->index }}"
                    >
                        <x-filament::badge :color="$entry->actorType->getColor()" :icon="$entry->actorType->getIcon()">
                            {{ $entry->actorLabel }} ({{ $entry->actorType->getLabel() }})
                        </x-filament::badge>

                        @if ($entry->entryType === \App\Services\Timeline\TimelineAudience::ENTRY_NOTE)
                            @php $noteVisibility = \App\Enums\NoteVisibility::tryFrom((string) ($entry->properties['visibility'] ?? '')) ?? \App\Enums\NoteVisibility::Internal; @endphp
                            <x-filament::badge
                                :color="$noteVisibility->getColor()"
                                icon="heroicon-o-chat-bubble-left-right"
                            >
                                {{ $noteVisibility->getTimelineBadgeLabel() }}
                            </x-filament::badge>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                @if ($entry->url !== null && $entry->entryType === \App\Services\Timeline\TimelineAudience::ENTRY_MEDIA && ($entry->properties['file_name'] ?? null) !== null)
                                    <span class="text-gray-950 dark:text-white">
                                        uploaded
                                        <a href="{{ $entry->url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $entry->properties['file_name'] }}</a>
                                        → {{ $entry->properties['collection_label'] ?? '' }}
                                    </span>
                                @elseif ($entry->url !== null)
                                    <a href="{{ $entry->url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $entry->headline }}
                                    </a>
                                @else
                                    <span class="text-gray-950 dark:text-white">{{ $entry->headline }}</span>
                                @endif

                                @if ($entry->changedFieldCount > 0 && $entry->event === 'updated')
                                    <span class="text-gray-500 dark:text-gray-400">· {{ $entry->changedFieldCount }} {{ \Illuminate\Support\Str::plural('field', $entry->changedFieldCount) }}</span>
                                @endif
                            </div>
                        </div>

                        <span class="whitespace-nowrap text-xs tabular-nums text-gray-400 dark:text-gray-500">
                            {{ $entry->occurredAt->format('H:i') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">No activity recorded for this request yet.</p>
    @endforelse
</div>

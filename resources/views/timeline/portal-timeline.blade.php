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
            'entries' => $group,
        ])
        ->values();

    $actorTone = fn (\App\Enums\ActorType $actorType): string => match ($actorType->getColor()) {
        'success' => 'text-green-600 dark:text-green-400',
        'warning' => 'text-amber-600 dark:text-amber-400',
        'danger' => 'text-red-600 dark:text-red-400',
        'primary', 'info' => 'text-primary-600 dark:text-primary-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div class="space-y-6 text-sm">
    @forelse ($dayGroups as $group)
        <div class="space-y-3" wire:key="portal-day-{{ $group['entries']->first()->occurredAt->toDateString() }}">
            <div class="flex items-center gap-3">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group['label'] }}</span>
                <div class="h-px flex-1 bg-gray-200 dark:bg-white/10"></div>
            </div>

            <ul class="space-y-3">
                @foreach ($group['entries'] as $entry)
                    <li
                        class="flex items-start gap-3"
                        wire:key="portal-entry-{{ $entry->subjectType }}-{{ $entry->subjectId }}-{{ $entry->occurredAt->getTimestamp() }}-{{ $loop->index }}"
                    >
                        <span class="mt-0.5 shrink-0 {{ $actorTone($entry->actorType) }}">
                            @svg($entry->actorType->getIcon(), 'h-5 w-5')
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                @if ($entry->url !== null)
                                    <a href="{{ $entry->url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $entry->headline }}
                                    </a>
                                @else
                                    <span class="text-gray-950 dark:text-white">{{ $entry->headline }}</span>
                                @endif

                                @if ($entry->changedFieldCount > 0 && $entry->event === 'updated')
                                    <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $entry->changedFieldCount }} {{ \Illuminate\Support\Str::plural('field', $entry->changedFieldCount) }}</span>
                                @endif
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $entry->actorLabel }}
                            </p>
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

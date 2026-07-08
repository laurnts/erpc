<div class="space-y-4 text-sm">
    <p class="flex items-start gap-1.5 text-xs text-gray-500 dark:text-gray-400">
        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Line-level price changes appear after line-item logging lands; until then money edits show at document level.</span>
    </p>

    @forelse ($dayGroups as $group)
        <div class="space-y-2" wire:key="day-{{ $group['entries']->first()->occurredAt->toDateString() }}">
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
                        wire:key="entry-{{ $entry->entryType }}-{{ $entry->subjectType }}-{{ $entry->subjectId }}-{{ $entry->occurredAt->getTimestamp() }}-{{ $loop->index }}"
                    >
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

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-gray-950 dark:text-white">{{ $entry->headline }}</span>

                                @if ($entry->changedFieldCount > 0 && $entry->event === 'updated')
                                    <span class="text-gray-500 dark:text-gray-400">· {{ $entry->changedFieldCount }} {{ Str::plural('field', $entry->changedFieldCount) }}</span>
                                @endif
                            </div>

                            @if ($entry->lane === 'credit')
                                <p class="text-xs text-gray-500 dark:text-gray-400">
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

                                <p class="flex flex-wrap items-baseline gap-x-2 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($entry->event === 'deleted' && $changedFields !== [])
                                        <span>last values snapshotted</span>
                                    @elseif ($changedFields !== [])
                                        <span class="truncate">{{ implode(', ', array_slice($changedFields, 0, 6)) }}@if (count($changedFields) > 6), …@endif</span>
                                    @endif

                                    @if (($entry->properties['activity_id'] ?? null) !== null && ($changedFields !== [] || $entry->event === 'deleted'))
                                        {{ ($this->detailsAction)(['activity' => $entry->properties['activity_id']]) }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        <span class="whitespace-nowrap text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ $entry->occurredAt->format('H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="text-gray-500 dark:text-gray-400">No activity recorded for this request yet.</p>
    @endforelse

    @if ($paginator->lastPage() > 1)
        <div class="flex items-center justify-end gap-2 text-xs text-gray-500 dark:text-gray-400">
            <x-filament::button
                color="gray"
                size="xs"
                wire:click="nextPage"
                :disabled="! $paginator->hasMorePages()"
            >
                &lsaquo; Older
            </x-filament::button>

            <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

            <x-filament::button
                color="gray"
                size="xs"
                wire:click="previousPage"
                :disabled="$paginator->onFirstPage()"
            >
                Newer &rsaquo;
            </x-filament::button>
        </div>
    @endif

    <livewire:request-note-composer :request="$request" wire:key="internal-note-composer-{{ $request->getKey() }}" />

    <x-filament-actions::modals />
</div>

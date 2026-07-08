<div @class([
    'admin-request-history-timeline min-w-0 text-sm',
    'flex min-h-0 flex-1 flex-col' => ! $compact,
])>
    <div class="admin-request-history-filters mb-3 flex flex-wrap gap-1.5 p-2">
        @foreach ($filters as $chip)
            <button
                type="button"
                wire:click="setFilter('{{ $chip->value }}')"
                @class([
                    'rounded-full px-2.5 py-1 text-xs font-medium transition',
                    'bg-primary-600 text-white dark:bg-primary-500' => $activeFilter === $chip,
                    'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/15' => $activeFilter !== $chip,
                ])
            >
                {{ $chip->getLabel() }}
            </button>
        @endforeach
    </div>

    @unless ($compact)
        <div class="mb-3">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search activity…"
                />
            </x-filament::input.wrapper>
        </div>
    @endunless

    <div @class(['admin-request-history-entries min-h-0 flex-1 space-y-3 px-2', 'overflow-y-auto pr-1' => ! $compact])>
        @forelse ($dayGroups as $group)
            @php
                $isDayOpen = in_array($group['dayKey'], $expandedDays, true);
                $itemCount = $group['items']->sum(fn ($item) => $item->count());
            @endphp

            <div class="min-w-0" wire:key="day-{{ $group['dayKey'] }}">
                <button
                    type="button"
                    wire:click="toggleDay('{{ $group['dayKey'] }}')"
                    class="flex w-full items-center gap-2 text-left"
                >
                    <span
                        class="text-xs text-gray-400 transition-transform dark:text-gray-500"
                        @style(['transform: rotate(90deg)' => $isDayOpen])
                    >▸</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $group['label'] }}
                    </span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">({{ $itemCount }})</span>
                    <div class="h-px flex-1 bg-gray-200 dark:bg-white/10"></div>
                </button>

                @if ($isDayOpen)
                    <ul class="mt-2 space-y-2">
                        @foreach ($group['items'] as $item)
                            @if ($item->isCluster)
                                @php $clusterOpen = in_array($item->key, $expandedClusters, true); @endphp
                                <li class="min-w-0 border-t border-gray-200 pt-2 first:border-t-0 first:pt-0 dark:border-white/10" wire:key="cluster-{{ $item->key }}">
                                    <button
                                        type="button"
                                        wire:click="toggleCluster('{{ $item->key }}')"
                                        class="flex w-full items-start gap-2 text-left"
                                    >
                                        <span class="mt-0.5 text-xs text-gray-400" @style(['transform: rotate(90deg)' => $clusterOpen])>▸</span>
                                        <div class="min-w-0 flex-1">
                                            <x-filament::badge :color="$item->first()->actorType->getColor()" :icon="$item->first()->actorType->getIcon()">
                                                {{ $item->first()->actorLabel }} ({{ $item->first()->actorType->getLabel() }})
                                            </x-filament::badge>
                                            <p class="mt-1 break-words text-sm font-medium text-gray-950 dark:text-white">{{ $item->summaryHeadline }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $clusterOpen ? 'Hide details' : 'Show '.$item->count().' entries' }}</p>
                                        </div>
                                    </button>

                                    @if ($clusterOpen)
                                        <ul class="mt-2 space-y-2 border-l-2 border-gray-200 pl-3 dark:border-white/10">
                                            @foreach ($item->entries as $entry)
                                                @include('livewire.partials.request-history-entry', ['entry' => $entry])
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @else
                                @include('livewire.partials.request-history-entry', ['entry' => $item->first()])
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="text-gray-500 dark:text-gray-400">No history recorded for this request yet.</p>
        @endforelse
    </div>

    @if ($compact && $hasMore)
        <div class="mt-3 shrink-0 border-t border-gray-200 pt-3 dark:border-white/10 px-2">
            <button
                type="button"
                wire:click="mountAction('viewFullLog')"
                class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
            >
                View full activity log →
            </button>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Showing latest {{ \App\Livewire\RequestHistoryTimeline::COMPACT_LIMIT }} of {{ $totalCount }} events
            </p>
        </div>
    @endif

    @if (! $compact && $paginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $paginator->lastPage() > 1)
        <div class="mt-3 flex shrink-0 items-center justify-end gap-2 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
            <x-filament::button color="gray" size="xs" wire:click="previousPage" :disabled="$paginator->onFirstPage()">
                &lsaquo; Prev
            </x-filament::button>

            <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

            <x-filament::button color="gray" size="xs" wire:click="nextPage" :disabled="! $paginator->hasMorePages()">
                Next &rsaquo;
            </x-filament::button>
        </div>
    @endif

    @if ($showComposer)
        <div class="admin-request-history-composer mt-3 shrink-0 border-t border-gray-200 pt-3 dark:border-white/10">
            <livewire:request-note-composer :request="$request" wire:key="internal-note-composer-{{ $request->getKey() }}" />
        </div>
    @endif

    <x-filament-actions::modals />
</div>

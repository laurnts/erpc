@php
    $historyHint = 'Line-level price changes appear after line-item logging lands; until then money edits show at document level.';
@endphp

<div
    class="admin-request-history-sidebar"
    wire:key="request-history-sidebar-{{ $request->getKey() }}"
>
    <x-filament::section
        compact
        icon="heroicon-o-clock"
        heading="Activities"
        class="admin-request-history-card"
        :has-content-el="false"
    >
        <x-slot name="afterHeader">
            <x-filament::icon-button
                icon="heroicon-o-information-circle"
                color="gray"
                size="sm"
                :tooltip="$historyHint"
                label="{{ $historyHint }}"
            />
        </x-slot>

        <div class="admin-request-history-body">
            <div class="admin-request-history-scroll">
                @livewire(
                    \App\Livewire\RequestHistoryTimeline::class,
                    ['request' => $request, 'compact' => true, 'showComposer' => false],
                    'request-history-timeline-'.$request->getKey()
                )
            </div>

            <div class="admin-request-history-composer-outer shrink-0 border-t border-gray-200 pt-3 dark:border-white/10 px-2">
                <livewire:request-note-composer :request="$request" wire:key="internal-note-composer-{{ $request->getKey() }}" />
            </div>
        </div>
    </x-filament::section>
</div>

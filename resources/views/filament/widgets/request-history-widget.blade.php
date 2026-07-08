<x-filament-widgets::widget>
    <x-filament::section compact icon="heroicon-o-clock" heading="Activities">
        @livewire(
            \App\Livewire\RequestHistoryTimeline::class,
            ['request' => $record],
            'request-history-timeline-'.$record->getKey()
        )
    </x-filament::section>
</x-filament-widgets::widget>

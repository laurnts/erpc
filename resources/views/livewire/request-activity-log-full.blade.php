<div class="admin-request-activity-log-full">
    @livewire(
        \App\Livewire\RequestHistoryTimeline::class,
        ['request' => $request, 'compact' => false, 'showComposer' => true],
        'request-activity-log-full-'.$request->getKey()
    )
</div>

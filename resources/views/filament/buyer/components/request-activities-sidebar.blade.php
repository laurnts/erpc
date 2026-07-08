@php
    use App\Services\Portal\BuyerPortalContext;
    use App\Services\Timeline\PortalTimelineSource;
    use App\Services\Timeline\TimelineParty;

    /** @var \App\Models\Request $record */
    $record = $getRecord();
    $entries = app(PortalTimelineSource::class)->forParty(
        $record,
        TimelineParty::buyer(app(BuyerPortalContext::class)->companyId()),
    );
@endphp

<div class="buyer-request-activities-body">
    <div class="buyer-request-activities-scroll">
        @include('timeline.portal-timeline', ['entries' => $entries])
    </div>

    <div class="buyer-request-activities-composer shrink-0 border-t border-gray-200 pt-3 dark:border-white/10">
        <livewire:request-note-composer :request="$record" wire:key="buyer-note-composer-{{ $record->getKey() }}" />
    </div>
</div>

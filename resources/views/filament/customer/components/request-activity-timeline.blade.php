@php
    /** @var list<\App\Data\TimelineEntry> $entries */
    $entries = is_array($entries ?? null) ? $entries : ($getState() ?? []);
    $request = $getRecord();
@endphp

@include('timeline.portal-timeline', ['entries' => $entries])

@if ($request instanceof \App\Models\Request)
    <livewire:request-note-composer :request="$request" wire:key="buyer-note-composer-{{ $request->getKey() }}" />
@endif

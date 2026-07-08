@php
    $state = is_array($state ?? null) ? $state : ($getState() ?? []);
    /** @var list<\App\Data\TimelineEntry> $entries */
    $entries = $state['entries'] ?? (is_array($state) && ! array_key_exists('request', $state) ? $state : []);
    $request = $state['request'] ?? null;
@endphp

@include('timeline.portal-timeline', ['entries' => $entries])

@if ($request instanceof \App\Models\Request)
    <livewire:request-note-composer :request="$request" wire:key="supplier-note-composer-{{ $request->getKey() }}" />
@endif

@php
    /** @var list<\App\Data\TimelineEntry> $entries */
    $entries = is_array($entries ?? null) ? $entries : ($getState() ?? []);
@endphp

@include('timeline.portal-timeline', ['entries' => $entries])

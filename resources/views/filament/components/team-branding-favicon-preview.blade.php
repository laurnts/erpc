@php
    /** @var \App\Models\Team $team */
    $faviconUrl = $team->getFaviconUrl();
@endphp

@if($faviconUrl)
    <div class="inline-block rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800">
        <img src="{{ $faviconUrl }}" alt="Favicon" class="h-8 w-8 object-contain">
    </div>
@endif

@php
    /** @var \App\Models\Team $team */
    $logoUrl = $team->getCompanyLogoUrl();
@endphp

@if($logoUrl)
    <div class="inline-block rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800">
        <img src="{{ $logoUrl }}" alt="Company Logo" class="max-h-20 max-w-full object-contain">
    </div>
@endif

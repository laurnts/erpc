@php
    /** @var \App\Models\Team $team */
    $team = \Filament\Facades\Filament::getTenant();
    $settings = $team->getErpSettings();
    $logoUrl = $team->getEmailLogoUrl();
@endphp

@if($logoUrl)
    <div class="space-y-2">
        <div class="text-sm font-medium text-gray-700">Current Logo:</div>
        <div class="inline-block p-2 bg-gray-50 rounded-lg border border-gray-200">
            <img src="{{ $logoUrl }}" alt="Email Logo" class="max-h-20 max-w-full object-contain">
        </div>
    </div>
@endif

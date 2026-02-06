<x-filament-panels::page>
    @push('styles')
        <style>
            /* Hide clear button (X) on Select fields for email templates */
            .fi-input-wrp button[type="button"][aria-label*="Clear"],
            .fi-input-wrp button[type="button"][aria-label*="clear"],
            .fi-input-wrp button[aria-label*="Clear"],
            .fi-input-wrp button[aria-label*="clear"],
            button[aria-label*="Clear"][type="button"],
            button[aria-label*="clear"][type="button"] {
                display: none !important;
            }
        </style>
    @endpush

    <div class="space-y-6">
        {{-- Email Settings Section --}}
        <x-filament::section>
            <x-slot name="heading">
                Email Settings
            </x-slot>
            <x-slot name="description">
                Configure email templates, sender information, SMTP settings, and test your email connection.
            </x-slot>

            <form wire:submit="saveEmailSettings">
                {{ $this->emailForm }}

                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit">
                        Save Email Settings
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>

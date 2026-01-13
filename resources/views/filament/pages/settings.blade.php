<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Default Settings Section --}}
        <x-filament::section>
            <x-slot name="heading">
                Default Settings
            </x-slot>
            <x-slot name="description">
                Configure default values for currency, tax rates, quote validity, and payment terms.
            </x-slot>

            <form wire:submit="saveErpSettings">
                {{ $this->erpForm }}

                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit">
                        Save
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Document Number Prefixes Section --}}
        <x-filament::section>
            <x-slot name="heading">
                Document Number Prefixes
            </x-slot>
            <x-slot name="description">
                Configure the prefixes used for auto-generated document numbers. Numbers will be formatted as PREFIX-YYYY-NNNN.
            </x-slot>

            <form wire:submit="savePrefixSettings">
                {{ $this->prefixForm }}

                <div class="mt-4 flex justify-end">
                    <x-filament::button type="submit">
                        Save
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>

<div>
    {{-- QE Creation Form --}}
    <div class="space-y-6">
        {{-- A real <form> here would nest inside the Filament action modal's <form>,
             which is invalid HTML and makes the browser hoist the modal footer out
             of the modal window. Buttons below submit via wire:click instead. --}}
        <div>
            {{-- QE Information Section (from Filament form) --}}
            {{ $this->form }}

            {{-- Central Purchasing Section (manual) --}}
            <x-filament::section class="mt-6">
                <x-slot name="heading">Central Purchasing</x-slot>
                <x-slot name="description">Approval workflow personnel</x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Prepared By --}}
                    <div>
                        <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2">
                            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                Prepared By
                            </span>
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="preparedById">
                                <option value="">Select key account...</option>
                                @foreach($this->getKeyAccountOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    {{-- Dept Head of Sales --}}
                    <div>
                        <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2">
                            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                Dept Head of Sales
                            </span>
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="deptHeadSalesId">
                                <option value="">Select dept head of sales...</option>
                                @foreach($this->getDeptHeadSalesOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    {{-- Deputy Director --}}
                    <div>
                        <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2">
                            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                Deputy Director
                            </span>
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="deputyDirectorId">
                                <option value="">Select deputy director...</option>
                                @foreach($this->getDeputyDirectorOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    {{-- Approved By --}}
                    <div>
                        <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2">
                            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                Approved By
                            </span>
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="approvedById">
                                <option value="">Select director...</option>
                                @foreach($this->getApprovedByOptions() as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>
            </x-filament::section>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                <x-filament::button
                    type="button"
                    color="gray"
                    x-on:click="$dispatch('close-modal', { id: $el.closest('.fi-modal').id })"
                >
                    Cancel
                </x-filament::button>

                <x-filament::button
                    type="button"
                    icon="heroicon-o-document-check"
                    wire:click="save"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="save">Save QE</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </x-filament::button>
            </div>
        </div>

        <x-filament-actions::modals />
    </div>
</div>

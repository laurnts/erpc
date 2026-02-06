<div>
    @if($showKeyAccountForm)
        {{-- Key Account Creation Form --}}
        <div class="space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-gray-200 dark:border-gray-700">
                <x-filament::icon-button
                    icon="heroicon-o-arrow-left"
                    wire:click="cancelKeyAccountForm"
                    label="Back"
                />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Create Key Account
                </h3>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Name <span class="text-danger-500">*</span>
                        </span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="newKeyAccountName"
                            placeholder="Enter name"
                        />
                    </x-filament::input.wrapper>
                    @error('newKeyAccountName')
                        <p class="text-sm text-danger-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Email
                        </span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="email"
                            wire:model="newKeyAccountEmail"
                            placeholder="Enter email"
                        />
                    </x-filament::input.wrapper>
                    @error('newKeyAccountEmail')
                        <p class="text-sm text-danger-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 mb-2">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Phone
                        </span>
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="newKeyAccountPhone"
                            placeholder="Enter phone"
                        />
                    </x-filament::input.wrapper>
                    @error('newKeyAccountPhone')
                        <p class="text-sm text-danger-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="cancelKeyAccountForm"
                >
                    Cancel
                </x-filament::button>

                <x-filament::button
                    type="button"
                    wire:click="saveNewKeyAccount"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="saveNewKeyAccount">Save Key Account</span>
                    <span wire:loading wire:target="saveNewKeyAccount">Saving...</span>
                </x-filament::button>
            </div>
        </div>
    @else
        {{-- QE Creation Form --}}
        <div class="space-y-6">
            <form wire:submit="save">
                {{-- QE Information Section (from Filament form) --}}
                {{ $this->form }}

                {{-- Central Purchasing Section (manual) --}}
                <x-filament::section class="mt-6">
                    <x-slot name="heading">Central Purchasing</x-slot>
                    <x-slot name="description">Approval workflow personnel</x-slot>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Prepared By with Plus Button --}}
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
                                <x-slot name="suffix">
                                    <x-filament::icon-button
                                        icon="heroicon-o-plus"
                                        wire:click="openKeyAccountForm"
                                        label="Add new key account"
                                        class="-me-2"
                                    />
                                </x-slot>
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
                                    <option value="">Select key account...</option>
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
                                    <option value="">Select key account...</option>
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
                                    <option value="">Select key account...</option>
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
                        x-on:click="$dispatch('close-modal', { id: 'create-qe-modal' })"
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
            </form>

            <x-filament-actions::modals />
        </div>
    @endif
</div>

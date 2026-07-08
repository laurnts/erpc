<div class="admin-request-stage-bar" wire:key="request-stage-bar-{{ $request->getKey() }}-{{ $activeRelationManager }}">
    <x-filament::section compact class="admin-request-stage-bar-card">
        <nav aria-label="Request workflow stages">
            <ol class="admin-request-stage-bar-list m-0 list-none space-y-0 p-0">
                @foreach ($steps as $step)
                    <li
                        wire:key="stage-{{ $step['relationKey'] }}"
                        @class([
                            'admin-request-stage-bar-item',
                            'admin-request-stage-bar-item--completed' => $step['state'] === 'completed',
                            'admin-request-stage-bar-item--current' => $step['state'] === 'current',
                            'admin-request-stage-bar-item--upcoming' => $step['state'] === 'upcoming',
                            'admin-request-stage-bar-item--disabled' => $step['state'] === 'disabled',
                        ])
                    >
                        <button
                            type="button"
                            wire:click="goToTab('{{ $step['relationKey'] }}')"
                            @disabled($step['state'] === 'disabled')
                            @if ($step['tooltip'])
                                x-tooltip="{ content: @js($step['tooltip']), theme: $store.theme }"
                            @endif
                            class="admin-request-stage-bar-btn group flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left transition"
                        >
                            @if ($step['icon'])
                                <x-filament::icon
                                    :icon="$step['icon']"
                                    @class([
                                        'h-4 w-4 shrink-0',
                                        'text-primary-600 dark:text-primary-400' => $step['state'] === 'current',
                                        'text-gray-400 dark:text-gray-500' => in_array($step['state'], ['upcoming', 'disabled', 'completed'], true),
                                    ])
                                />
                            @endif

                            <span @class([
                                'min-w-0 flex-1 text-sm leading-snug',
                                'font-semibold text-primary-700 dark:text-primary-300' => $step['state'] === 'current',
                                'font-medium text-gray-800 dark:text-gray-100' => $step['state'] === 'completed',
                                'text-gray-400 dark:text-gray-500' => in_array($step['state'], ['upcoming', 'disabled'], true),
                            ])>
                                {{ $step['label'] }}
                            </span>

                            @if ($step['state'] === 'completed')
                                <span class="admin-request-stage-bar-check shrink-0" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.543 2.543 6.543-6.543a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>
    </x-filament::section>
</div>

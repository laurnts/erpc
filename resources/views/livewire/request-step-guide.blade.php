@php
    $storageKey = 'request-step-guide-dismissed';
@endphp

<div
    class="admin-request-step-guide"
    wire:key="request-step-guide-{{ $request->getKey() }}"
>
    @if ($guide['visible'])
        <div
            class="admin-request-step-guide-inner"
            x-data="{
            expanded: true,
            dismissed: false,
            init() {
                this.dismissed = localStorage.getItem(@js($storageKey)) === '1';
                this.expanded = ! this.dismissed;
            },
            dismiss() {
                this.dismissed = true;
                this.expanded = false;
                localStorage.setItem(@js($storageKey), '1');
            },
            toggle() {
                if (this.dismissed) {
                    this.dismissed = false;
                    this.expanded = true;
                    localStorage.removeItem(@js($storageKey));
                    return;
                }
                this.expanded = ! this.expanded;
            }
        }"
        wire:key="request-step-guide-inner-{{ $request->getKey() }}"
    >
        <x-filament::section class="admin-request-step-guide-card">
            <div class="flex items-start justify-between gap-2 cursor-pointer" @click="toggle()">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400" x-text="@js($guide['eyebrow'])"></p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $guide['title'] }}</p>
                </div>
                <span
                    class="shrink-0 text-xs text-gray-400 transition-transform"
                    :class="expanded && ! dismissed ? 'rotate-180' : ''"
                >▾</span>
            </div>

            <div x-show="expanded && ! dismissed" x-collapse class="mt-3 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                @if ($guide['summary'] !== '')
                    <p class="m-0">{{ $guide['summary'] }}</p>
                @endif

                @if ($guide['checklist'] !== [])
                    <ul class="m-0 list-disc space-y-1 pl-5">
                        @foreach ($guide['checklist'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($guide['next'] !== '')
                    <p class="border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400 mt-3">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Next:</span> {{ $guide['next'] }}
                    </p>
                @endif

                @if ($guide['relationKey'] !== null)
                    <x-filament::button
                        color="primary"
                        class="w-full justify-center"
                        wire:click="goToTab('{{ $guide['relationKey'] }}')"
                        x-on:click.stop
                    >
                        {{ $guide['ctaLabel'] }}
                    </x-filament::button>
                @endif
            </div>

            <p
                x-show="dismissed"
                x-cloak
                class="mt-2 text-xs text-gray-500 dark:text-gray-400"
            >
                {{ $guide['eyebrow'] }} · {{ $guide['title'] }}
            </p>
        </x-filament::section>
    </div>
    @endif
</div>

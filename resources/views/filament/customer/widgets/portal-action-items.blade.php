@php
    /** @var list<array{type: string, label: string, reference: string, description: string, url: string, icon: string, color: string}> $items */
@endphp

<div class="fi-wi-widget rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
        Needs Your Attention
    </h3>

    @if (count($items) === 0)
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
            You are all caught up. No pending actions right now.
        </p>
    @else
        <ul class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($items as $item)
                <li class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                    <div class="flex min-w-0 items-start gap-3">
                        <span @class([
                            'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-semibold',
                            'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => ($item['color'] ?? '') === 'warning',
                            'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-400' => ($item['color'] ?? '') === 'info',
                            'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => ($item['color'] ?? '') === 'primary',
                        ])>
                            {{ $item['icon'] }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $item['label'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $item['reference'] }}
                            </p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                {{ $item['description'] }}
                            </p>
                        </div>
                    </div>

                    <a
                        href="{{ $item['url'] }}"
                        class="shrink-0 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        View
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>

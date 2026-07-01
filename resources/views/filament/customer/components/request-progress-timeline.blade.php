@php
    /** @var list<array{stage: \App\Enums\RequestStage, label: string, completed: bool, current: bool}> $timeline */
    $timeline = is_array($timeline ?? null) ? $timeline : ($getState() ?? []);
@endphp

@if (count($timeline) === 0)
    <p class="text-sm text-gray-500 dark:text-gray-400">
        No progress information available.
    </p>
@else
    <ol class="m-0 list-none p-0">
        @foreach ($timeline as $step)
            <li class="flex items-stretch gap-4">
                <div class="flex w-8 shrink-0 flex-col items-center">
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold leading-none',
                        'border-primary-600 bg-primary-600 text-white shadow-sm' => $step['current'],
                        'border-success-600 bg-success-600 text-white' => $step['completed'] && ! $step['current'],
                        'border-gray-300 bg-white text-gray-400 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-500' => ! $step['completed'] && ! $step['current'],
                    ])>
                        @if ($step['completed'] && ! $step['current'])
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </span>

                    @if (! $loop->last)
                        <span
                            @class([
                                'my-1 w-0.5 min-h-6 flex-1 rounded-full',
                                'bg-success-200 dark:bg-success-900/40' => $step['completed'] && ! $step['current'],
                                'bg-gray-200 dark:bg-gray-700' => ! $step['completed'] || $step['current'],
                            ])
                            aria-hidden="true"
                        ></span>
                    @endif
                </div>

                <div @class([
                    'min-w-0 flex-1',
                    'pb-6' => ! $loop->last,
                    'pb-0' => $loop->last,
                ])>
                    <p @class([
                        'text-sm font-semibold leading-snug',
                        'text-primary-700 dark:text-primary-400' => $step['current'],
                        'text-gray-900 dark:text-white' => $step['completed'] && ! $step['current'],
                        'text-gray-500 dark:text-gray-400' => ! $step['completed'] && ! $step['current'],
                    ])>
                        {{ $step['label'] }}
                    </p>

                    @if ($step['current'])
                        <p class="mt-1 text-xs font-medium text-primary-600 dark:text-primary-400">
                            Current stage
                        </p>
                    @elseif ($step['completed'])
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Completed
                        </p>
                    @else
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            Upcoming
                        </p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif

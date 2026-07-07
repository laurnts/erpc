@php
    /** @var list<array{stage: \App\Enums\RequestStage, label: string, completed: bool, current: bool}> $timeline */
    $timeline = is_array($timeline ?? null) ? $timeline : ($getState() ?? []);
@endphp

@if (count($timeline) === 0)
    <p class="text-sm text-gray-500 dark:text-gray-400">
        No progress information available.
    </p>
@else
    <div class="overflow-x-auto pb-1">
        <ol class="m-0 flex min-w-[44rem] list-none items-start p-0">
            @foreach ($timeline as $step)
                <li class="flex min-w-0 flex-1 flex-col items-center px-1 text-center first:pl-0 last:pr-0">
                    <div class="flex w-full items-center">
                        @if ($loop->first)
                            <span class="flex-1" aria-hidden="true"></span>
                        @else
                            <span
                                @class([
                                    'h-0.5 flex-1 rounded-full',
                                    'bg-success-200 dark:bg-success-900/40' => $timeline[$loop->index - 1]['completed'] && ! $timeline[$loop->index - 1]['current'],
                                    'bg-gray-200 dark:bg-gray-700' => ! $timeline[$loop->index - 1]['completed'] || $timeline[$loop->index - 1]['current'],
                                ])
                                aria-hidden="true"
                            ></span>
                        @endif

                        <span @class([
                            'mx-2 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold leading-none',
                            'border-primary-600 bg-primary-600 text-white shadow-sm ring-4 ring-primary-100 dark:ring-primary-900/40' => $step['current'],
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

                        @if ($loop->last)
                            <span class="flex-1" aria-hidden="true"></span>
                        @else
                            <span
                                @class([
                                    'h-0.5 flex-1 rounded-full',
                                    'bg-success-200 dark:bg-success-900/40' => $step['completed'] && ! $step['current'],
                                    'bg-gray-200 dark:bg-gray-700' => ! $step['completed'] || $step['current'],
                                ])
                                aria-hidden="true"
                            ></span>
                        @endif
                    </div>

                    <p @class([
                        'mt-3 w-full px-1 text-xs font-semibold leading-snug',
                        'text-primary-700 dark:text-primary-400' => $step['current'],
                        'text-gray-900 dark:text-white' => $step['completed'] && ! $step['current'],
                        'text-gray-500 dark:text-gray-400' => ! $step['completed'] && ! $step['current'],
                    ])>
                        {{ $step['label'] }}
                    </p>

                    @if ($step['current'])
                        <p class="mt-1 text-[11px] font-medium text-primary-600 dark:text-primary-400">
                            Current stage
                        </p>
                    @elseif ($step['completed'])
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                            Completed
                        </p>
                    @else
                        <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                            Upcoming
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
@endif

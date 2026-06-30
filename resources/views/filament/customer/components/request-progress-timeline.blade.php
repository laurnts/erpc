@php
    /** @var list<array{stage: \App\Enums\RequestStage, label: string, completed: bool, current: bool}> $timeline */
@endphp

<div class="fi-section mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
        Request Progress
    </h3>

    <ol class="mt-4 space-y-3">
        @foreach ($timeline as $step)
            <li class="flex items-start gap-3">
                <span @class([
                    'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    'bg-primary-600 text-white' => $step['current'],
                    'bg-success-600 text-white' => $step['completed'] && ! $step['current'],
                    'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => ! $step['completed'] && ! $step['current'],
                ])>
                    @if ($step['completed'] && ! $step['current'])
                        ✓
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <div>
                    <p @class([
                        'text-sm font-medium',
                        'text-primary-600 dark:text-primary-400' => $step['current'],
                        'text-gray-950 dark:text-white' => ! $step['current'],
                    ])>
                        {{ $step['label'] }}
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
</div>

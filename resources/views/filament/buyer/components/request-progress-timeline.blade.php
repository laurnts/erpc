@php
    /** @var list<array{stage: \App\Enums\RequestStage, label: string, completed: bool, current: bool}> $timeline */
    $timeline = is_array($timeline ?? null) ? $timeline : ($getState() ?? []);

    $circleStyle = static function (array $step): string {
        if ($step['current']) {
            return 'width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;line-height:1;background:#2563eb;border:2px solid #2563eb;color:#ffffff;';
        }

        if ($step['completed']) {
            return 'width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;line-height:1;background:#059669;border:2px solid #059669;color:#ffffff;';
        }

        return 'width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;line-height:1;background:#ffffff;border:2px solid #d1d5db;color:#9ca3af;';
    };

    $labelStyle = static function (array $step): string {
        if ($step['current']) {
            return 'margin-top:8px;font-size:11px;line-height:1.35;font-weight:700;color:#1d4ed8;text-align:center;';
        }

        if ($step['completed']) {
            return 'margin-top:8px;font-size:11px;line-height:1.35;font-weight:500;color:#4b5563;text-align:center;';
        }

        return 'margin-top:8px;font-size:11px;line-height:1.35;font-weight:400;color:#9ca3af;text-align:center;';
    };

    $lineColor = static fn (bool $completed): string => $completed ? '#10b981' : '#e5e7eb';
@endphp

@if (count($timeline) === 0)
    <p style="font-size:14px;color:#6b7280;">No progress information available.</p>
@else
    <div style="overflow-x:auto;padding-bottom:4px;">
        <ol style="display:flex;min-width:640px;list-style:none;margin:0;padding:0;align-items:flex-start;">
            @foreach ($timeline as $step)
                <li style="position:relative;flex:1 1 0;display:flex;flex-direction:column;align-items:center;min-width:0;">
                    @if (! $loop->first)
                        <span
                            style="position:absolute;top:14px;left:0;right:50%;height:2px;background:{{ $lineColor($timeline[$loop->index - 1]['completed']) }};z-index:0;"
                            aria-hidden="true"
                        ></span>
                    @endif

                    @if (! $loop->last)
                        <span
                            style="position:absolute;top:14px;left:50%;right:0;height:2px;background:{{ $lineColor($step['completed']) }};z-index:0;"
                            aria-hidden="true"
                        ></span>
                    @endif

                    <span style="position:relative;z-index:1;{{ $circleStyle($step) }}">
                        @if ($step['completed'] && ! $step['current'])
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </span>

                    <p style="position:relative;z-index:1;width:100%;padding:0 2px;{{ $labelStyle($step) }}">
                        {{ $step['label'] }}
                    </p>
                </li>
            @endforeach
        </ol>
    </div>
@endif

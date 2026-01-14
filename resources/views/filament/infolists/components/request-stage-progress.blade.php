@php
    use App\Enums\RequestStage;

    $record = $getRecord();
    $currentStage = $record->stage;

    // Define the main workflow stages (excluding terminal states)
    $workflowStages = [
        RequestStage::DRAFT,
        RequestStage::AWAITING_SUPPLIER_RESPONSE,
        RequestStage::PREPARING_BUYER_QUOTE,
        RequestStage::AWAITING_BUYER_CONFIRMATION,
        RequestStage::PREPARING_SUPPLIER_ORDER,
        RequestStage::AWAITING_SHIPMENT,
        RequestStage::SHIPPED,
        RequestStage::DELIVERED,
        RequestStage::COMPLETED,
    ];

    $currentIndex = array_search($currentStage, $workflowStages, true);
    $isCancelled = $currentStage === RequestStage::CANCELLED;

    // Color mapping for badges
    $colorMap = [
        'gray' => 'bg-gray-500',
        'info' => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'success' => 'bg-success-500',
        'primary' => 'bg-primary-500',
        'danger' => 'bg-danger-500',
    ];
    $badgeColorClass = $colorMap[$currentStage->getColor()] ?? 'bg-gray-500';
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="py-2 overflow-hidden">
        @if($isCancelled)
            {{-- Cancelled State --}}
            <div class="text-center py-4">
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-danger-500 text-white">
                    <x-heroicon-m-x-circle class="w-4 h-4 mr-1.5" />
                    Cancelled
                </span>
            </div>
        @else
            {{-- Progress Bar with Dots and Lines --}}
            <div class="flex items-center mb-2">
                @foreach($workflowStages as $index => $stage)
                    @php
                        $isCompleted = $currentIndex !== false && $index < $currentIndex;
                        $isCurrent = $stage === $currentStage;
                        $isPending = $currentIndex === false || $index > $currentIndex;
                    @endphp

                    {{-- Stage Dot --}}
                    <div class="flex items-center justify-center shrink-0">
                        @if($isCompleted)
                            <div class="w-4 h-4 rounded-full bg-success-500 flex items-center justify-center">
                                <x-heroicon-m-check class="w-3 h-3 text-white" />
                            </div>
                        @elseif($isCurrent)
                            <div class="w-5 h-5 rounded-full {{ $badgeColorClass }} ring-4 ring-primary-100 dark:ring-primary-900"></div>
                        @else
                            <div class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                        @endif
                    </div>

                    {{-- Connector Line (except for last) --}}
                    @if(!$loop->last)
                        <div class="flex-1 h-0.5 mx-1 {{ $isCompleted ? 'bg-success-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @endif
                @endforeach
            </div>

            {{-- Stage Labels --}}
            <div class="flex items-start text-xs text-gray-500 dark:text-gray-400">
                @foreach($workflowStages as $stage)
                    @php
                        $isCurrent = $stage === $currentStage;
                        $step = $stage->getPhaseStep();
                    @endphp
                    <div class="flex-1 text-center {{ $isCurrent ? 'font-semibold text-primary-600 dark:text-primary-400' : '' }}">
                        <div>{{ $step }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Current Stage Display --}}
            <div class="mt-4 flex items-center justify-center gap-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $currentStage->getPhase() }}:</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $badgeColorClass }} text-white">
                    <x-dynamic-component :component="$currentStage->getIcon()" class="w-4 h-4 mr-1.5" />
                    {{ $currentStage->getLabelWithStep() }}
                </span>
            </div>
        @endif
    </div>
</x-dynamic-component>

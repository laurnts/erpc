@php
    use App\Enums\RequestStage;

    $currentStage = $getRecord()->stage;
    $stages = RequestStage::cases();

    // Define stage order for progress
    $stageOrder = [
        RequestStage::DRAFT->value => 0,
        RequestStage::QUOTING_SUPPLIER->value => 1,
        RequestStage::QUOTING_BUYER->value => 2,
        RequestStage::QUOTE_SENT->value => 3,
        RequestStage::QUOTE_ACCEPTED->value => 4,
        RequestStage::ORDERED->value => 5,
        RequestStage::IN_PROGRESS->value => 6,
        RequestStage::SHIPPED->value => 7,
        RequestStage::DELIVERED->value => 8,
        RequestStage::INVOICED->value => 9,
        RequestStage::PAID->value => 10,
        RequestStage::COMPLETED->value => 11,
        RequestStage::CANCELLED->value => -1,
    ];

    $currentIndex = $stageOrder[$currentStage->value] ?? 0;

    // Stage labels for display (abbreviated)
    $stageLabels = [
        RequestStage::DRAFT->value => 'Draft',
        RequestStage::QUOTING_SUPPLIER->value => 'S.Quote',
        RequestStage::QUOTING_BUYER->value => 'B.Quote',
        RequestStage::QUOTE_SENT->value => 'Sent',
        RequestStage::QUOTE_ACCEPTED->value => 'Accept',
        RequestStage::ORDERED->value => 'Order',
        RequestStage::IN_PROGRESS->value => 'Progress',
        RequestStage::SHIPPED->value => 'Ship',
        RequestStage::DELIVERED->value => 'Deliver',
        RequestStage::INVOICED->value => 'Invoice',
        RequestStage::PAID->value => 'Paid',
        RequestStage::COMPLETED->value => 'Done',
    ];

    // Filter out cancelled for progress display
    $displayStages = collect($stages)->filter(fn ($s) => $s !== RequestStage::CANCELLED);
@endphp

<div class="py-2 overflow-hidden">
    {{-- Progress Bar with Dots and Lines --}}
    <div class="flex items-center mb-2">
        @foreach($displayStages as $stage)
            @php
                $stageIndex = $stageOrder[$stage->value] ?? 0;
                $isCompleted = $stageIndex < $currentIndex;
                $isCurrent = $stage === $currentStage;
                $isPending = $stageIndex > $currentIndex;
            @endphp

            {{-- Stage Dot --}}
            <div class="flex items-center justify-center shrink-0">
                @if($isCompleted)
                    <div class="w-4 h-4 rounded-full bg-success-500 flex items-center justify-center">
                        <x-heroicon-m-check class="w-3 h-3 text-white" />
                    </div>
                @elseif($isCurrent)
                    <div class="w-5 h-5 rounded-full bg-primary-500 ring-4 ring-primary-100 dark:ring-primary-900"></div>
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
        @foreach($displayStages as $stage)
            @php
                $isCurrent = $stage === $currentStage;
            @endphp
            <div class="flex-1 text-center {{ $isCurrent ? 'font-semibold text-primary-600 dark:text-primary-400' : '' }}">
                {{ $stageLabels[$stage->value] ?? $stage->getLabel() }}
            </div>
        @endforeach
    </div>

    {{-- Current Stage Display --}}
    <div class="mt-4 text-center">
        <span class="text-sm text-gray-500 dark:text-gray-400">Current Stage:</span>
        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium"
              style="background-color: {{ $currentStage->getColor() === 'gray' ? 'rgb(107, 114, 128)' : '' }}; color: white;">
            {{ $currentStage->getLabel() }}
        </span>
    </div>
</div>

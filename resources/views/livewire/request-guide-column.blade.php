<div
    class="admin-request-guide-column"
    wire:key="request-guide-column-{{ $request->getKey() }}-{{ $activeRelationManager }}"
>
    @livewire(
        \App\Livewire\RequestStageBar::class,
        ['request' => $request, 'activeRelationManager' => $activeRelationManager],
        'request-stage-bar-'.$request->getKey().'-'.$activeRelationManager
    )

    @livewire(
        \App\Livewire\RequestStepGuide::class,
        ['request' => $request],
        'request-step-guide-'.$request->getKey().'-'.$request->stage->value
    )
</div>

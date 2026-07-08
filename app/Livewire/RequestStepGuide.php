<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\Request;
use App\Services\RequestDetail\RequestStepGuidePresenter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

final class RequestStepGuide extends BaseLivewireComponent
{
    use AuthorizesLivewireActions;

    public Request $request;

    public function mount(Request $request): void
    {
        $this->ensureTeamOwnership($request);

        $this->request = $request;
    }

    #[On('request-stage-updated')]
    public function refreshAfterStageUpdate(): void
    {
        $this->request->refresh();
    }

    public function goToTab(string $relationKey): void
    {
        $this->dispatch('request-guide-go-to-tab', relationKey: $relationKey)
            ->to(\App\Filament\Resources\RequestResource\Pages\ViewRequest::class);
    }

    public function render(): View
    {
        return view('livewire.request-step-guide', [
            'guide' => app(RequestStepGuidePresenter::class)->forRequest($this->request),
        ]);
    }
}

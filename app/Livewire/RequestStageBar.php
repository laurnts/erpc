<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Filament\Resources\RequestResource\Pages\ViewRequest;
use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\Request;
use App\Services\RequestDetail\RequestStageBarPresenter;
use Illuminate\Contracts\View\View;

final class RequestStageBar extends BaseLivewireComponent
{
    use AuthorizesLivewireActions;

    public Request $request;

    public int|string|null $activeRelationManager = null;

    public function mount(Request $request, int|string|null $activeRelationManager = null): void
    {
        $this->ensureTeamOwnership($request);

        $this->request = $request;
        $this->activeRelationManager = $activeRelationManager;
    }

    public function goToTab(string $relationKey): void
    {
        $this->dispatch('request-guide-go-to-tab', relationKey: $relationKey)
            ->to(ViewRequest::class);
    }

    public function render(): View
    {
        return view('livewire.request-stage-bar', [
            'steps' => app(RequestStageBarPresenter::class)->stepsFor(
                $this->request,
                $this->activeRelationManager,
            ),
        ]);
    }
}

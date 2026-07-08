<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\Request;
use Illuminate\Contracts\View\View;

final class RequestGuideColumn extends BaseLivewireComponent
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

    public function render(): View
    {
        return view('livewire.request-guide-column');
    }
}

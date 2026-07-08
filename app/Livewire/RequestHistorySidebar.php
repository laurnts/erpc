<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesLivewireActions;
use App\Models\Request;
use Illuminate\Contracts\View\View;

final class RequestHistorySidebar extends BaseLivewireComponent
{
    use AuthorizesLivewireActions;

    public Request $request;

    public function mount(Request $request): void
    {
        $this->ensureTeamOwnership($request);

        $this->request = $request;
    }

    public function render(): View
    {
        return view('livewire.request-history-sidebar');
    }
}

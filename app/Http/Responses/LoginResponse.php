<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Filament\Customer\Pages\Auth\CustomerLogin;
use App\Filament\Customer\Pages\CustomerDashboard;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\BuyerResource;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

final readonly class LoginResponse implements \Filament\Auth\Http\Responses\Contracts\LoginResponse
{
    /** @phpstan-ignore-next-line return.unusedType */
    public function toResponse($request): RedirectResponse|Redirector // @pest-ignore-type
    {
        $panel = Filament::getCurrentPanel();

        if ($panel?->getId() === 'sysadmin') {
            return redirect()->intended($panel->getUrl());
        }

        if ($this->isCustomerLoginResponse($request, $panel)) {
            return $this->redirectToCustomerHome();
        }

        return $this->redirectToAppHome($request);
    }

    private function isCustomerLoginResponse(mixed $request, ?Panel $panel): bool
    {
        if ($this->isLivewireAppLoginComponent($request)) {
            return false;
        }

        if ($panel?->getId() === 'customer') {
            return true;
        }

        if ($this->isLivewireCustomerLoginComponent($request)) {
            return true;
        }

        return $request->user('customer') !== null && $request->user('web') === null;
    }

    private function isLivewireAppLoginComponent(mixed $request): bool
    {
        foreach ($this->livewireSnapshots($request) as $snapshot) {
            if (($snapshot['memo']['name'] ?? null) === Login::class) {
                return true;
            }
        }

        return false;
    }

    private function isLivewireCustomerLoginComponent(mixed $request): bool
    {
        foreach ($this->livewireSnapshots($request) as $snapshot) {
            if (($snapshot['memo']['name'] ?? null) === CustomerLogin::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function livewireSnapshots(mixed $request): iterable
    {
        $components = $request->input('components', []);

        if (! is_array($components)) {
            return;
        }

        foreach ($components as $component) {
            if (! is_array($component) || ! is_string($component['snapshot'] ?? null)) {
                continue;
            }

            $snapshot = json_decode($component['snapshot'], true);

            if (is_array($snapshot)) {
                yield $snapshot;
            }
        }
    }

    private function redirectToCustomerHome(): RedirectResponse|Redirector
    {
        $customerHome = CustomerDashboard::getUrl(panel: 'customer');
        $intended = session()->pull('url.intended');

        if (is_string($intended) && str_contains($intended, $this->customerPortalPathNeedle())) {
            return redirect()->to($intended);
        }

        return redirect()->to($customerHome);
    }

    private function redirectToAppHome(mixed $request): RedirectResponse|Redirector
    {
        $user = $request->user('web');

        $default = ($user && $user->currentTeam)
            ? BuyerResource::getUrl('index', ['tenant' => $user->currentTeam->getKey()])
            : Filament::getPanel('app')->getUrl();

        $intended = session()->pull('url.intended');

        if (is_string($intended) && ! str_contains($intended, $this->customerPortalPathNeedle())) {
            return redirect()->to($intended);
        }

        return redirect()->to($default);
    }

    private function customerPortalPathNeedle(): string
    {
        return '/'.trim((string) config('app.customer_path', 'buyer'), '/');
    }
}

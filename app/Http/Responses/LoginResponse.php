<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Filament\Buyer\Pages\Auth\BuyerLogin;
use App\Filament\Buyer\Pages\BuyerDashboard;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\RequestResource;
use App\Filament\Supplier\Pages\Auth\SupplierLogin;
use App\Filament\Supplier\Pages\SupplierDashboard;
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

        if ($this->isBuyerLoginResponse($request, $panel)) {
            return $this->redirectToBuyerHome();
        }

        if ($this->isSupplierLoginResponse($request, $panel)) {
            return $this->redirectToSupplierHome();
        }

        return $this->redirectToAppHome($request);
    }

    private function isBuyerLoginResponse(mixed $request, ?Panel $panel): bool
    {
        if ($this->isLivewireAppLoginComponent($request)) {
            return false;
        }

        if ($panel?->getId() === 'buyer') {
            return true;
        }

        if ($this->isLivewireBuyerLoginComponent($request)) {
            return true;
        }

        return $request->user('buyer') !== null && $request->user('web') === null;
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

    private function isLivewireBuyerLoginComponent(mixed $request): bool
    {
        foreach ($this->livewireSnapshots($request) as $snapshot) {
            if (($snapshot['memo']['name'] ?? null) === BuyerLogin::class) {
                return true;
            }
        }

        return false;
    }

    private function isSupplierLoginResponse(mixed $request, ?Panel $panel): bool
    {
        if ($this->isLivewireAppLoginComponent($request)) {
            return false;
        }

        if ($panel?->getId() === 'supplier') {
            return true;
        }

        if ($this->isLivewireSupplierLoginComponent($request)) {
            return true;
        }

        return $request->user('supplier') !== null && $request->user('web') === null;
    }

    private function isLivewireSupplierLoginComponent(mixed $request): bool
    {
        foreach ($this->livewireSnapshots($request) as $snapshot) {
            if (($snapshot['memo']['name'] ?? null) === SupplierLogin::class) {
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

    private function redirectToBuyerHome(): RedirectResponse|Redirector
    {
        $buyerHome = BuyerDashboard::getUrl(panel: 'buyer');
        $intended = session()->pull('url.intended');

        if (is_string($intended) && str_contains($intended, $this->buyerPortalPathNeedle())) {
            return redirect()->to($intended);
        }

        return redirect()->to($buyerHome);
    }

    private function redirectToSupplierHome(): RedirectResponse|Redirector
    {
        $supplierHome = SupplierDashboard::getUrl(panel: 'supplier');
        $intended = session()->pull('url.intended');

        if (is_string($intended) && str_contains($intended, $this->supplierPortalPathNeedle())) {
            return redirect()->to($intended);
        }

        return redirect()->to($supplierHome);
    }

    private function redirectToAppHome(mixed $request): RedirectResponse|Redirector
    {
        $user = $request->user('web');

        $default = ($user && $user->currentTeam)
            ? RequestResource::getUrl('index', ['tenant' => $user->currentTeam->getKey()])
            : Filament::getPanel('app')->getUrl();

        $intended = session()->pull('url.intended');

        if (is_string($intended)
            && ! str_contains($intended, $this->buyerPortalPathNeedle())
            && ! str_contains($intended, $this->supplierPortalPathNeedle())) {
            return redirect()->to($intended);
        }

        return redirect()->to($default);
    }

    private function buyerPortalPathNeedle(): string
    {
        return '/'.trim((string) config('app.buyer_path', 'buyer'), '/');
    }

    private function supplierPortalPathNeedle(): string
    {
        return '/'.trim((string) config('app.supplier_path', 'supplier'), '/');
    }
}

<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Customer\Pages\AcceptPortalInvitation;
use App\Filament\Customer\Pages\Auth\CustomerLogin;
use App\Filament\Customer\Pages\CustomerDashboard;
use App\Http\Middleware\AuthenticatePanelUser;
use App\Http\Middleware\EnsureCustomerPortalEnabled;
use App\Http\Middleware\InitializeCustomerPortalContext;
use App\Services\Portal\CustomerPortalContext;
use App\Support\PanelDomain;
use App\Support\PortalPanelConfigurator;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

final class CustomerPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('customer')
            ->domain($this->resolveCustomerDomain())
            ->path(config('app.customer_path', 'buyer'))
            ->login(CustomerLogin::class)
            ->authGuard('customer')
            ->homeUrl(fn (): string => CustomerDashboard::getUrl(panel: 'customer'))
            ->brandName(fn (): string => $this->resolveBrandName())
            ->brandLogo(fn (): View|Factory|string|null => $this->resolveBrandLogo())
            ->favicon(fn (): string => $this->resolveFaviconUrl())
            ->discoverResources(
                in: app_path('Filament/Customer/Resources'),
                for: 'App\\Filament\\Customer\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Customer/Pages'),
                for: 'App\\Filament\\Customer\\Pages',
            )
            ->discoverWidgets(
                in: app_path('Filament/Customer/Widgets'),
                for: 'App\\Filament\\Customer\\Widgets',
            )
            ->pages([
                CustomerDashboard::class,
                AcceptPortalInvitation::class,
            ])
            ->userMenuItems([
                Action::make('switchPortalCompany')
                    ->label('Switch Company')
                    ->icon('heroicon-o-building-office-2')
                    ->visible(fn (): bool => Filament::auth()->check()
                        && app(CustomerPortalContext::class)->activeMemberships()->count() > 1)
                    ->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(fn (): array => app(CustomerPortalContext::class)
                                ->activeMemberships()
                                ->mapWithKeys(fn ($membership): array => [
                                    $membership->company_id => (string) $membership->company?->name,
                                ])
                                ->all())
                            ->default(fn (): int => app(CustomerPortalContext::class)->companyId())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data): void {
                        app(CustomerPortalContext::class)->setCompany((int) $data['company_id']);

                        Notification::make()
                            ->title('Company selected')
                            ->success()
                            ->send();

                        redirect(CustomerDashboard::getUrl());
                    }),
            ])
            ->middleware([
                EnsureCustomerPortalEnabled::class,
            ])
            ->authMiddleware([
                AuthenticatePanelUser::class,
                InitializeCustomerPortalContext::class,
            ]);

        return PortalPanelConfigurator::apply($panel);
    }

    private function resolveBrandName(): string
    {
        if (! Filament::auth()->check()) {
            return 'Buyer Portal';
        }

        try {
            return app(CustomerPortalContext::class)->company()->name;
        } catch (\Throwable) {
            return 'Buyer Portal';
        }
    }

    private function resolveBrandLogo(): View|Factory|string|null
    {
        if (! Filament::auth()->check()) {
            return view('filament.app.logo');
        }

        try {
            $portalContext = app(CustomerPortalContext::class);
            $team = $portalContext->team();
            $logoUrl = $team->getCompanyLogoUrl();

            return view('filament.customer.brand-logo', [
                'logoUrl' => $logoUrl,
                'brandName' => $portalContext->company()->name,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveFaviconUrl(): string
    {
        if (! Filament::auth()->check()) {
            return asset('favicon.svg');
        }

        try {
            return app(CustomerPortalContext::class)->team()->getFaviconUrl() ?? asset('favicon.svg');
        } catch (\Throwable) {
            return asset('favicon.svg');
        }
    }

    private function resolveCustomerDomain(): string
    {
        return PanelDomain::customerHost();
    }
}

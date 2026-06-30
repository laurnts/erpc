<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Customer\Pages\AcceptPortalInvitation;
use App\Filament\Customer\Pages\Auth\CustomerLogin;
use App\Filament\Customer\Pages\CustomerDashboard;
use App\Http\Middleware\AuthenticateAppPanel;
use App\Http\Middleware\AuthenticateCustomerPanel;
use App\Http\Middleware\EnsureCustomerPortalEnabled;
use App\Http\Middleware\InitializePortalContext;
use App\Http\Middleware\UseCustomerPanelSession;
use App\Services\CustomerPortal\PortalContext;
use App\Support\PanelDomain;
use Exception;
use Filament\Facades\Filament;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class CustomerPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('customer')
            ->domain($this->resolveCustomerDomain())
            ->path(config('app.customer_path', 'customer'))
            ->login(CustomerLogin::class)
            ->authGuard('customer')
            ->authPasswordBroker('users')
            ->passwordReset()
            ->emailVerification()
            ->strictAuthorization()
            ->databaseNotifications()
            ->homeUrl(fn (): string => CustomerDashboard::getUrl(panel: 'customer'))
            ->brandName(fn (): string => $this->resolveBrandName())
            ->brandLogo(fn (): View|Factory|string|null => $this->resolveBrandLogo())
            ->brandLogoHeight('2.6rem')
            ->favicon(fn (): string => $this->resolveFaviconUrl())
            ->colors([
                'primary' => Color::Blue,
            ])
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
                        && app(PortalContext::class)->activeMemberships()->count() > 1)
                    ->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(fn (): array => app(PortalContext::class)
                                ->activeMemberships()
                                ->mapWithKeys(fn ($membership): array => [
                                    $membership->company_id => (string) $membership->company?->name,
                                ])
                                ->all())
                            ->default(fn (): int => app(PortalContext::class)->companyId())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data): void {
                        app(PortalContext::class)->setCompany((int) $data['company_id']);

                        Notification::make()
                            ->title('Company selected')
                            ->success()
                            ->send();

                        redirect(CustomerDashboard::getUrl());
                    }),
            ])
            ->middleware([
                EnsureCustomerPortalEnabled::class,
                UseCustomerPanelSession::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateCustomerPanel::class,
                InitializePortalContext::class,
            ])
            ->viteTheme('resources/css/filament/app/theme.css');
    }

    private function resolveBrandName(): string
    {
        if (! Filament::auth()->check()) {
            return 'Customer Portal';
        }

        try {
            return app(PortalContext::class)->company()->name;
        } catch (\Throwable) {
            return 'Customer Portal';
        }
    }

    private function resolveBrandLogo(): View|Factory|string|null
    {
        if (! Filament::auth()->check()) {
            return view('filament.app.logo');
        }

        try {
            $portalContext = app(PortalContext::class);
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
            return app(PortalContext::class)->team()->getFaviconUrl() ?? asset('favicon.svg');
        } catch (\Throwable) {
            return asset('favicon.svg');
        }
    }

    private function resolveCustomerDomain(): string
    {
        return PanelDomain::customerHost();
    }
}

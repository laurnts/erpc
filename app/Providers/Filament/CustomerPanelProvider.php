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
use Filament\Panel;
use Filament\PanelProvider;

final class CustomerPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('customer')
            ->domain(PanelDomain::customerHost())
            ->path(config('app.customer_path', 'buyer'))
            ->login(CustomerLogin::class)
            ->authGuard('customer')
            ->homeUrl(fn (): string => CustomerDashboard::getUrl(panel: 'customer'))
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
            ->middleware([
                EnsureCustomerPortalEnabled::class,
            ])
            ->authMiddleware([
                AuthenticatePanelUser::class,
                InitializeCustomerPortalContext::class,
            ]);

        return PortalPanelConfigurator::apply(
            $panel,
            context: CustomerPortalContext::class,
            dashboard: CustomerDashboard::class,
            guestBrandName: 'Buyer Portal',
        );
    }
}

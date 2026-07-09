<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Buyer\Pages\AcceptPortalInvitation;
use App\Filament\Buyer\Pages\Auth\BuyerLogin;
use App\Filament\Buyer\Resources\BuyerRequestResource;
use App\Http\Middleware\AuthenticatePanelUser;
use App\Http\Middleware\EnsureBuyerPortalEnabled;
use App\Http\Middleware\InitializeBuyerPortalContext;
use App\Services\Portal\BuyerPortalContext;
use App\Support\PanelDomain;
use App\Support\PortalPanelConfigurator;
use Exception;
use Filament\Panel;
use Filament\PanelProvider;

final class BuyerPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('buyer')
            ->domain(PanelDomain::buyerHost())
            ->path(config('app.buyer_path', 'buyer'))
            ->login(BuyerLogin::class)
            ->authGuard('buyer')
            ->discoverResources(
                in: app_path('Filament/Buyer/Resources'),
                for: 'App\\Filament\\Buyer\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Buyer/Pages'),
                for: 'App\\Filament\\Buyer\\Pages',
            )
            ->pages([
                AcceptPortalInvitation::class,
            ])
            ->middleware([
                EnsureBuyerPortalEnabled::class,
            ])
            ->authMiddleware([
                AuthenticatePanelUser::class,
                InitializeBuyerPortalContext::class,
            ])
            ->font('Inter');

        return PortalPanelConfigurator::apply(
            $panel,
            context: BuyerPortalContext::class,
            homeResource: BuyerRequestResource::class,
            guestBrandName: 'Buyer Portal',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Supplier\Pages\AcceptPortalInvitation;
use App\Filament\Supplier\Pages\Auth\SupplierLogin;
use App\Filament\Supplier\Resources\SupplierRequestResource;
use App\Http\Middleware\AuthenticatePanelUser;
use App\Http\Middleware\EnsureSupplierPortalEnabled;
use App\Http\Middleware\InitializeSupplierPortalContext;
use App\Services\Portal\SupplierPortalContext;
use App\Support\PanelDomain;
use App\Support\PortalPanelConfigurator;
use Exception;
use Filament\Panel;
use Filament\PanelProvider;

final class SupplierPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('supplier')
            ->domain(PanelDomain::supplierHost())
            ->path(config('app.supplier_path', 'supplier'))
            ->login(SupplierLogin::class)
            ->authGuard('supplier')
            ->discoverResources(
                in: app_path('Filament/Supplier/Resources'),
                for: 'App\\Filament\\Supplier\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Supplier/Pages'),
                for: 'App\\Filament\\Supplier\\Pages',
            )
            ->pages([
                AcceptPortalInvitation::class,
            ])
            ->middleware([
                EnsureSupplierPortalEnabled::class,
            ])
            ->authMiddleware([
                AuthenticatePanelUser::class,
                InitializeSupplierPortalContext::class,
            ]);

        return PortalPanelConfigurator::apply(
            $panel,
            context: SupplierPortalContext::class,
            homeResource: SupplierRequestResource::class,
            guestBrandName: 'Supplier Portal',
        );
    }
}

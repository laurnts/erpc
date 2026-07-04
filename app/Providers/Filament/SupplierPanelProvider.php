<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Supplier\Pages\AcceptPortalInvitation;
use App\Filament\Supplier\Pages\Auth\SupplierLogin;
use App\Filament\Supplier\Pages\SupplierDashboard;
use App\Http\Middleware\AuthenticatePanelUser;
use App\Http\Middleware\EnsureSupplierPortalEnabled;
use App\Http\Middleware\InitializeSupplierPortalContext;
use App\Services\Portal\SupplierPortalContext;
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

final class SupplierPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('supplier')
            ->domain($this->resolveSupplierDomain())
            ->path(config('app.supplier_path', 'supplier'))
            ->login(SupplierLogin::class)
            ->authGuard('supplier')
            ->homeUrl(fn (): string => SupplierDashboard::getUrl(panel: 'supplier'))
            ->brandName(fn (): string => $this->resolveBrandName())
            ->brandLogo(fn (): View|Factory|null => $this->resolveBrandLogo())
            ->favicon(fn (): string => $this->resolveFaviconUrl())
            ->discoverResources(
                in: app_path('Filament/Supplier/Resources'),
                for: 'App\\Filament\\Supplier\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Supplier/Pages'),
                for: 'App\\Filament\\Supplier\\Pages',
            )
            ->discoverWidgets(
                in: app_path('Filament/Supplier/Widgets'),
                for: 'App\\Filament\\Supplier\\Widgets',
            )
            ->pages([
                SupplierDashboard::class,
                AcceptPortalInvitation::class,
            ])
            ->userMenuItems([
                Action::make('switchPortalCompany')
                    ->label('Switch Company')
                    ->icon('heroicon-o-building-office-2')
                    ->visible(fn (): bool => Filament::auth()->check()
                        && app(SupplierPortalContext::class)->activeMemberships()->count() > 1)
                    ->schema([
                        Select::make('company_id')
                            ->label('Company')
                            ->options(fn (): array => app(SupplierPortalContext::class)
                                ->activeMemberships()
                                ->mapWithKeys(fn ($membership): array => [
                                    $membership->company_id => (string) $membership->company?->name,
                                ])
                                ->all())
                            ->default(fn (): int => app(SupplierPortalContext::class)->companyId())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data): void {
                        app(SupplierPortalContext::class)->setCompany((int) $data['company_id']);

                        Notification::make()
                            ->title('Company selected')
                            ->success()
                            ->send();

                        redirect(SupplierDashboard::getUrl());
                    }),
            ])
            ->middleware([
                EnsureSupplierPortalEnabled::class,
            ])
            ->authMiddleware([
                AuthenticatePanelUser::class,
                InitializeSupplierPortalContext::class,
            ]);

        return PortalPanelConfigurator::apply($panel);
    }

    private function resolveBrandName(): string
    {
        if (! Filament::auth()->check()) {
            return 'Supplier Portal';
        }

        try {
            return app(SupplierPortalContext::class)->company()->name;
        } catch (\Throwable) {
            return 'Supplier Portal';
        }
    }

    private function resolveBrandLogo(): View|Factory|null
    {
        if (! Filament::auth()->check()) {
            return view('filament.app.logo');
        }

        try {
            $portalContext = app(SupplierPortalContext::class);
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
            return app(SupplierPortalContext::class)->team()->getFaviconUrl() ?? asset('favicon.svg');
        } catch (\Throwable) {
            return asset('favicon.svg');
        }
    }

    private function resolveSupplierDomain(): string
    {
        return PanelDomain::supplierHost();
    }
}

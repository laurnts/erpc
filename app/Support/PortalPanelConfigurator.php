<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\UsePanelSession;
use App\Services\Portal\PortalContext;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Notification;
use Filament\Panel;
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
use Throwable;

/**
 * Shared shell for external portal panels (customer, supplier): middleware
 * stack, theme, branding resolved from the portal context, and the
 * switch-company user menu. Call after panel-specific configuration; panel
 * middleware registered before this runs ahead of the shared stack.
 *
 * @phpstan-type TDashboard class-string<\Filament\Pages\Page>
 */
final readonly class PortalPanelConfigurator
{
    /**
     * @param  class-string<PortalContext>  $context
     * @param  class-string<\Filament\Pages\Page>  $dashboard
     */
    public static function apply(Panel $panel, string $context, string $dashboard, string $guestBrandName): Panel
    {
        return $panel
            ->authPasswordBroker('users')
            ->passwordReset()
            ->emailVerification()
            ->strictAuthorization()
            ->databaseNotifications()
            ->brandLogoHeight('2.6rem')
            ->brandName(fn (): string => self::resolveBrandName($context, $guestBrandName))
            ->brandLogo(fn (): View|Factory|null => self::resolveBrandLogo($context))
            ->favicon(fn (): string => self::resolveFaviconUrl($context))
            ->userMenuItems([
                self::switchCompanyAction($context, $dashboard),
            ])
            ->colors([
                'primary' => Color::Blue,
            ])
            ->middleware([
                UsePanelSession::class,
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
            ->viteTheme('resources/css/filament/app/theme.css');
    }

    /**
     * @param  class-string<PortalContext>  $context
     */
    private static function resolveBrandName(string $context, string $guestBrandName): string
    {
        if (! Filament::auth()->check()) {
            return $guestBrandName;
        }

        try {
            return app($context)->company()->name;
        } catch (Throwable) {
            return $guestBrandName;
        }
    }

    /**
     * @param  class-string<PortalContext>  $context
     */
    private static function resolveBrandLogo(string $context): View|Factory|null
    {
        if (! Filament::auth()->check()) {
            return view('filament.app.logo');
        }

        try {
            $portalContext = app($context);

            return view('filament.portal.brand-logo', [
                'logoUrl' => $portalContext->team()->getCompanyLogoUrl(),
                'brandName' => $portalContext->company()->name,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  class-string<PortalContext>  $context
     */
    private static function resolveFaviconUrl(string $context): string
    {
        if (! Filament::auth()->check()) {
            return asset('favicon.svg');
        }

        try {
            return app($context)->team()->getFaviconUrl() ?? asset('favicon.svg');
        } catch (Throwable) {
            return asset('favicon.svg');
        }
    }

    /**
     * @param  class-string<PortalContext>  $context
     * @param  class-string<\Filament\Pages\Page>  $dashboard
     */
    private static function switchCompanyAction(string $context, string $dashboard): Action
    {
        return Action::make('switchPortalCompany')
            ->label('Switch Company')
            ->icon('heroicon-o-building-office-2')
            ->visible(fn (): bool => Filament::auth()->check()
                && app($context)->activeMemberships()->count() > 1)
            ->schema([
                Select::make('company_id')
                    ->label('Company')
                    ->options(fn (): array => app($context)
                        ->activeMemberships()
                        ->mapWithKeys(fn ($membership): array => [
                            $membership->company_id => (string) $membership->company?->name,
                        ])
                        ->all())
                    ->default(fn (): int => app($context)->companyId())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data) use ($context, $dashboard): void {
                app($context)->setCompany((int) $data['company_id']);

                Notification::make()
                    ->title('Company selected')
                    ->success()
                    ->send();

                redirect($dashboard::getUrl());
            });
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\UsePanelSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Shared defaults for external portal panels (customer, supplier). Call after
 * panel-specific configuration; panel middleware registered before this runs
 * ahead of the shared stack.
 */
final readonly class PortalPanelConfigurator
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->authPasswordBroker('users')
            ->passwordReset()
            ->emailVerification()
            ->strictAuthorization()
            ->databaseNotifications()
            ->brandLogoHeight('2.6rem')
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
}

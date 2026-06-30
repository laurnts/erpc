<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\ApiTokens;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\CreateTeam;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\EditTeam;
use App\Filament\Resources\CompanyResource;
use App\Http\Middleware\ApplyTenantScopes;
use App\Http\Middleware\AuthenticateAppPanel;
use App\Support\PanelDomain;
use App\Listeners\SwitchTeam;
use App\Models\Team;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Filament\Actions\Action;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Jetstream\Features;
use Relaticle\CustomFields\CustomFieldsPlugin;

final class AppPanelProvider extends PanelProvider
{
    /**
     * Perform post-registration booting of components.
     */
    public function boot(): void
    {
        /**
         * Listen and switch team if tenant was changed
         */
        Event::listen(
            TenantSet::class,
            SwitchTeam::class,
        );

        Action::configureUsing(fn (Action $action): Action => $action->size(Size::Small)->iconPosition('before'));
        Section::configureUsing(fn (Section $section): Section => $section->compact());
        Table::configureUsing(fn (Table $table): Table => $table);
    }

    /**
     * Configure the Filament admin panel.
     *
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('app')
            ->domain(PanelDomain::appHost())
            ->homeUrl(fn (): string => CompanyResource::getUrl())
            ->brandName('Relaticle')
            ->login(Login::class)
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->passwordReset()
            ->emailVerification()
            ->strictAuthorization()
            ->databaseNotifications()
            ->brandLogoHeight('2.6rem')
            ->brandLogo(fn (): View|Factory => view('filament.app.logo'))
            ->favicon(function (): string {
                $tenant = Filament::getTenant();

                if ($tenant instanceof Team) {
                    return $tenant->getFaviconUrl() ?? asset('favicon.svg');
                }

                return asset('favicon.svg');
            })
            ->viteTheme('resources/css/app.css')
            ->colors([
                'primary' => [
                    50 => 'oklch(0.969 0.016 293.756)',
                    100 => 'oklch(0.943 0.028 294.588)',
                    200 => 'oklch(0.894 0.055 293.283)',
                    300 => 'oklch(0.811 0.101 293.571)',
                    400 => 'oklch(0.709 0.159 293.541)',
                    500 => 'oklch(0.606 0.219 292.717)',
                    600 => 'oklch(0.541 0.247 293.009)',
                    700 => 'oklch(0.491 0.241 292.581)',
                    800 => 'oklch(0.432 0.211 292.759)',
                    900 => 'oklch(0.380 0.178 293.745)',
                    950 => 'oklch(0.283 0.135 291.089)',
                    'DEFAULT' => 'oklch(0.541 0.247 293.009)',
                ],
            ])
            ->viteTheme('resources/css/filament/app/theme.css')
            ->font('Inter')
            ->userMenuItems([
                Action::make('profile')
                    ->label('Profile')
                    ->icon('heroicon-m-user-circle')
                    ->url(fn (): string => $this->shouldRegisterMenuItem()
                        ? url(EditProfile::getUrl())
                        : url($panel->getPath())),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
            ->pages([
                EditProfile::class,
                ApiTokens::class,
            ])
            ->spa()
            ->maxContentWidth(Width::ScreenTwoExtraLarge)
            ->breadcrumbs(false)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Workflow')
                    ->icon('heroicon-o-clipboard-document-list'),
                NavigationGroup::make()
                    ->label('Master Data')
                    ->icon('heroicon-o-circle-stack'),
                NavigationGroup::make()
                    ->label('Approval')
                    ->icon('heroicon-o-clipboard-document-check'),
                NavigationGroup::make()
                    ->label('Finance')
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make()
                    ->label('Workspace')
                    ->icon('heroicon-o-briefcase'),
                NavigationGroup::make()
                    ->label('Settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(),
            ])
            ->middleware([
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
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->authMiddleware([
                AuthenticateAppPanel::class,
            ])
            ->tenantMiddleware(
                [
                    ApplyTenantScopes::class,
                ],
                isPersistent: true
            )
            ->plugins([
                CustomFieldsPlugin::make()
                    ->authorize(fn () => Gate::check('update', Filament::getTenant())),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View|Factory => view('filament.app.analytics')
            );

        if (Features::hasApiFeatures()) {
            $panel->userMenuItems([
                Action::make('api_tokens')
                    ->label('API Tokens')
                    ->icon('heroicon-o-key')
                    ->url(fn (): string => $this->shouldRegisterMenuItem()
                        ? url(ApiTokens::getUrl())
                        : url($panel->getPath())),
            ]);
        }

        if (Features::hasTeamFeatures()) {
            $panel
                ->tenant(Team::class, ownershipRelationship: 'team')
                ->tenantRegistration(CreateTeam::class)
                ->tenantProfile(EditTeam::class)
                ->resolveTenantUsing(function (string $key): Team {
                    $customerPrefix = trim((string) config('app.customer_path', 'customer'), '/');

                    if ($key === $customerPrefix) {
                        throw (new ModelNotFoundException)->setModel(Team::class, [$key]);
                    }

                    $record = app(Team::class)->resolveRouteBinding($key, null);

                    if ($record === null) {
                        throw (new ModelNotFoundException)->setModel(Team::class, [$key]);
                    }

                    return $record;
                });
        }

        return $panel;
    }

    public function shouldRegisterMenuItem(): bool
    {
        $hasVerifiedEmail = Auth::user()?->hasVerifiedEmail();

        return Filament::hasTenancy()
            ? $hasVerifiedEmail && Filament::getTenant()
            : $hasVerifiedEmail;
    }
}

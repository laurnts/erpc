<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Customer\Pages\Auth\CustomerLogin;
use App\Filament\Pages\Auth\Login;
use App\Http\Responses\LoginResponse;
use App\Models\Company;
use App\Models\GoodsReceiveBatch;
use App\Models\Import;
use App\Models\Note;
use App\Models\People;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\GitHubService;
use App\Support\PanelDomain;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Relaticle\SystemAdmin\Models\SystemAdministrator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Filament\Auth\Http\Responses\Contracts\LoginResponse::class, LoginResponse::class);

        // Use custom ExportCompletion so the Export is refreshed before sending the
        // completion notification (ensures download links appear in the notification).
        $this->app->bind(
            \Filament\Actions\Exports\Jobs\ExportCompletion::class,
            \App\Filament\Exports\Jobs\ExportCompletion::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register mail view namespace
        if (is_dir(resource_path('views/vendor/mail'))) {
            Facades\View::addNamespace('mail', resource_path('views/vendor/mail'));
        }

        $this->configurePolicies();
        $this->configureModels();
        $this->configureFilament();
        $this->configureLegacyCustomerPortalRedirects();
        $this->configureGitHubStars();
        $this->configureLivewire();
    }

    private function configurePolicies(): void
    {
        // Manually register Media policy for Spatie Media Library
        Gate::policy(Media::class, \App\Policies\MediaPolicy::class);

        Gate::policy(GoodsReceiveBatch::class, \App\Policies\GoodsReceiveBatchPolicy::class);

        Gate::guessPolicyNamesUsing(function (string $modelClass): ?string {
            try {
                $currentPanelId = Filament::getCurrentPanel()?->getId();

                if ($currentPanelId === 'sysadmin') {
                    $modelName = class_basename($modelClass);
                    $systemAdminPolicy = "Relaticle\\SystemAdmin\\Policies\\{$modelName}Policy";

                    // Return SystemAdmin policy if it exists
                    if (class_exists($systemAdminPolicy)) {
                        return $systemAdminPolicy;
                    }
                }
            } catch (\Exception) {
                // Fallback for non-Filament contexts
            }

            // Use Laravel's default policy discovery logic
            return $this->getDefaultLaravelPolicyName($modelClass);
        });
    }

    private function getDefaultLaravelPolicyName(string $modelClass): ?string
    {
        // Replicate Laravel's default policy discovery logic from Gate.php:723-736
        $classDirname = str_replace('/', '\\', dirname(str_replace('\\', '/', $modelClass)));
        $classDirnameSegments = explode('\\', $classDirname);

        $candidates = collect();
        // Generate all possible policy paths
        $counter = count($classDirnameSegments);

        // Generate all possible policy paths
        for ($index = 0; $index < $counter; $index++) {
            $classDirname = implode('\\', array_slice($classDirnameSegments, 0, $index));
            $candidates->push($classDirname.'\\Policies\\'.class_basename($modelClass).'Policy');
        }

        // Add Models-specific paths if the model is in a Models directory
        if (str_contains($classDirname, '\\Models\\')) {
            $candidates = $candidates
                ->concat([str_replace('\\Models\\', '\\Policies\\', $classDirname).'\\'.class_basename($modelClass).'Policy'])
                ->concat([str_replace('\\Models\\', '\\Models\\Policies\\', $classDirname).'\\'.class_basename($modelClass).'Policy']);
        }

        // Return the first existing class, or fallback
        $existingPolicy = $candidates->reverse()->first(fn (string $class): bool => class_exists($class));

        return $existingPolicy ?: $classDirname.'\\Policies\\'.class_basename($modelClass).'Policy';
    }

    /**
     * Configure custom Livewire components.
     */
    private function configureLivewire(): void
    {
        // Custom Livewire components can be registered here
    }

    /**
     * Configure the models for the application.
     */
    private function configureModels(): void
    {
        Model::unguard();
        //        Model::shouldBeStrict(! $this->app->isProduction()); // TODO: Uncomment this line to enable strict mode in production

        Relation::enforceMorphMap([
            // Core CRM entities
            'team' => Team::class,
            'user' => User::class,
            'people' => People::class,
            'company' => Company::class,
            'task' => Task::class,
            'note' => Note::class,
            'system_administrator' => SystemAdministrator::class,
            'import' => Import::class,

            // ERP entities (will be created in Phase 1+)
            'buyer' => Company::class,
            'supplier' => Company::class,
            'article' => \App\Models\Article::class,
            'request' => \App\Models\Request::class,
            'quotation_evaluation' => \App\Models\QuotationEvaluation::class,
            'profit_and_loss' => \App\Models\ProfitAndLoss::class,
            'supplier_quote' => \App\Models\SupplierQuote::class,
            'buyer_quote' => \App\Models\BuyerQuote::class,
            'buyer_order' => \App\Models\BuyerOrder::class,
            'supplier_order' => \App\Models\SupplierOrder::class,
            'shipment' => \App\Models\Shipment::class,
            'buyer_invoice' => \App\Models\BuyerInvoice::class,
            'supplier_invoice' => \App\Models\SupplierInvoice::class,
            'buyer_payment' => \App\Models\BuyerPayment::class,
            'supplier_payment' => \App\Models\SupplierPayment::class,
            'project' => \App\Models\Project::class,
            'tax_code' => \App\Models\TaxCode::class,
            'currency' => \App\Models\Currency::class,
            'exchange_rate' => \App\Models\ExchangeRate::class,
        ]);

        // Bind our custom Import model to the Filament Import model
        $this->app->bind(\Filament\Actions\Imports\Models\Import::class, Import::class);
    }

    /**
     * Redirect legacy public-domain customer portal URLs to the app subdomain.
     */
    private function configureLegacyCustomerPortalRedirects(): void
    {
        if (! config('app.customer_portal_enabled', true)) {
            return;
        }

        $publicHost = PanelDomain::publicHost();
        $customerHost = PanelDomain::customerHost();

        if ($publicHost === $customerHost) {
            return;
        }

        $prefix = trim((string) config('app.customer_path', 'customer'), '/');

        Route::domain($publicHost)
            ->middleware('web')
            ->group(function () use ($prefix): void {
                Route::get($prefix, fn () => redirect()->away(url()->getCustomerPortalUrl()));

                Route::get($prefix.'/{path}', fn (string $path) => redirect()->away(
                    url()->getCustomerPortalUrl($path),
                ))->where('path', '.*');
            });
    }

    /**
     * Configure Filament.
     */
    private function configureFilament(): void
    {
        $slideOverActions = ['create', 'edit', 'view'];

        Action::configureUsing(function (Action $action) use ($slideOverActions): Action {
            if (in_array($action->getName(), $slideOverActions)) {
                return $action->slideOver();
            }

            return $action;
        });

        // Register CSS for disabled QE tabs
        FilamentAsset::register([
            Css::make('qe-disabled-tabs', resource_path('css/qe-disabled-tabs.css')),
            Css::make('equal-height-grid', resource_path('css/equal-height-grid.css')),
        ], 'app');

        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
            fn (): string => view('filament.hooks.login-autofill-sync')->render(),
            scopes: [
                Login::class,
                CustomerLogin::class,
            ],
        );
    }

    /**
     * Configure GitHub stars count.
     */
    private function configureGitHubStars(): void
    {
        // Share GitHub stars count with the header component
        Facades\View::composer('components.layout.header', function (View $view): void {
            $gitHubService = app(GitHubService::class);
            $starsCount = $gitHubService->getStarsCount();
            $formattedStarsCount = $gitHubService->getFormattedStarsCount();

            $view->with([
                'githubStars' => $starsCount,
                'formattedGithubStars' => $formattedStarsCount,
            ]);
        });
    }
}

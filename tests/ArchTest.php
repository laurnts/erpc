<?php

declare(strict_types=1);

use App\Filament\Exports\BaseExporter;
use App\Filament\Imports\BaseImporter;
use App\Livewire\BaseLivewireComponent;

arch()->preset()->php();

// arch()->preset()->strict();

arch()->preset()->security()->ignoring('assert');

arch()->preset()
    ->laravel()
    ->ignoring([
        'App\Providers\AppServiceProvider',
        'App\Providers\Filament\AppPanelProvider',
        'App\Providers\Filament\BuyerPanelProvider',
        'App\Providers\Filament\SupplierPanelProvider',
        // Extracted panel-provider configuration; references the shared panel
        // middleware stack just like the panel providers above.
        'App\Support\PortalPanelConfigurator',
        'Relaticle\Admin\AdminPanelProvider',
        'App\Enums\EnumValues',
        'App\Enums\CustomFields\CustomFieldTrait',
        // Mailables intentionally do NOT implement ShouldQueue: team SMTP mailers
        // are registered at runtime via config() in the sending process, so a
        // queue worker cannot resolve them. Queuing would require reworking team
        // SMTP to be re-established in the worker (tracked as a follow-up).
        'App\Mail',
    ]);

// Excluding App\Mail from the laravel preset above also drops its debug-output
// guard for mailables; restore it explicitly so config-heavy mailables can't ship
// a stray env()/dd()/dump().
arch('mailables avoid debug output')
    ->expect('App\Mail')
    ->not
    ->toUse(['env', 'dd', 'ddd', 'dump', 'ray', 'var_dump', 'exit']);

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('avoid open for extension')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring([
        BaseLivewireComponent::class,
        BaseImporter::class,
        BaseExporter::class,
    ]);

arch('ensure no extends')
    ->expect('App')
    ->classes()
    ->not
    ->toBeAbstract()
    ->ignoring([
        BaseLivewireComponent::class,
        BaseImporter::class,
        BaseExporter::class,
    ]);

arch('avoid mutation')
    ->expect('App')
    ->classes()
    ->toBeReadonly()
    ->ignoring([
        'App\Console\Commands',
        'App\Exceptions',
        'App\Filament',
        'App\Http\Requests',
        'App\Jobs',
        'App\Listeners',
        'App\Livewire',
        'App\Mail',
        'App\Models',
        'App\Data',
        'App\Notifications',
        'App\Providers',
        'App\Settings', // Spatie Settings requires mutable properties
        'App\View',
        'App\Services\Favicon\Drivers',
        'App\Providers\Filament',
        'App\Http\Middleware', // middleware extend framework base classes
    ]);

arch('avoid inheritance')
    ->expect('App')
    ->classes()
    ->toExtendNothing()
    ->ignoring([
        'App\Console\Commands',
        'App\Exceptions',
        'App\Filament',
        'App\Http\Requests',
        'App\Jobs',
        'App\Data',
        'App\Livewire',
        'App\Mail',
        'App\Models',
        'App\Notifications',
        'App\Providers',
        'App\Settings', // Spatie Settings requires extending Settings base class
        'App\View',
        'App\Http\Middleware', // middleware extend framework base classes (e.g. Filament Authenticate)
    ]);

// arch('annotations')
//    ->expect('App')
//    ->toHavePropertiesDocumented()
//    ->toHaveMethodsDocumented();

arch('main app must not depend on SystemAdmin module')
    ->expect('App')
    ->not
    ->toUse('Relaticle\SystemAdmin')
    ->ignoring([
        'App\Providers\AppServiceProvider',
        'App\Console\Commands\InstallCommand',
        'App\Console\Commands\CreateSystemAdminCommand',
    ]);

arch('SystemAdmin module must not depend on main app namespace')
    ->expect('Relaticle\SystemAdmin')
    ->not
    ->toUse('App')
    ->ignoring([
        'App\Models',
        'App\Enums',
    ]);

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'panel_domain' => env('APP_PANEL_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | System Administrator Panel Configuration
    |--------------------------------------------------------------------------
    |
    | These values configure how the system administrator panel is accessed.
    | You can either use a subdomain (sysadmin_domain) or a path (sysadmin_path).
    | If sysadmin_domain is set, it will be used; otherwise, sysadmin_path will be used.
    |
    */

    'sysadmin_domain' => env('SYSADMIN_DOMAIN'),
    'sysadmin_path' => env('SYSADMIN_PATH', 'sysadmin'),

    /*
    |--------------------------------------------------------------------------
    | Buyer Portal Configuration
    |--------------------------------------------------------------------------
    |
    | Path-based buyer portal on the app subdomain (e.g. app.example.com/buyer/login).
    | Optional BUYER_DOMAIN overrides the host; defaults to the app panel domain.
    |
    */

    'buyer_path' => env('BUYER_PATH', 'buyer'),
    'buyer_domain' => env('BUYER_DOMAIN'),
    'buyer_session_cookie' => $buyerSessionCookie = env('BUYER_SESSION_COOKIE', 'erpc_buyer_session'),
    'buyer_portal_enabled' => (bool) env('BUYER_PORTAL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Supplier Portal Configuration
    |--------------------------------------------------------------------------
    |
    | Path-based supplier portal on the app subdomain (e.g. app.example.com/supplier/login).
    | Optional SUPPLIER_DOMAIN overrides the host; defaults to the app panel domain.
    |
    */

    'supplier_path' => env('SUPPLIER_PATH', 'supplier'),
    'supplier_domain' => env('SUPPLIER_DOMAIN'),
    'supplier_session_cookie' => $supplierSessionCookie = env('SUPPLIER_SESSION_COOKIE', 'erpc_supplier_session'),
    'supplier_portal_enabled' => (bool) env('SUPPLIER_PORTAL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Panel Session Cookies
    |--------------------------------------------------------------------------
    |
    | Map of portal panel path prefix => dedicated session cookie, consumed by
    | the UsePanelSession middleware so portal logins never share a session
    | with the internal panel. Future portals add one entry here.
    |
    */

    'panel_session_cookies' => [
        env('BUYER_PATH', 'buyer') => $buyerSessionCookie,
        env('SUPPLIER_PATH', 'supplier') => $supplierSessionCookie,
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\PanelDomain;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class MacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        URL::macro('getAppUrl', function (string $path = ''): string {
            $parsed = parse_url((string) config('app.url'));
            $scheme = $parsed['scheme'] ?? 'https';
            $host = PanelDomain::appHost();

            return $scheme.'://'.$host.'/'.ltrim($path, '/');
        });

        URL::macro('getPublicUrl', function (string $path = ''): string {
            $baseUrl = config('app.url');
            $parsed = parse_url((string) $baseUrl);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? 'localhost';

            return $scheme.'://'.$host.'/'.ltrim($path, '/');
        });

        URL::macro('getCustomerPortalUrl', function (string $path = ''): string {
            $parsed = parse_url((string) config('app.url'));
            $scheme = $parsed['scheme'] ?? 'https';
            $host = PanelDomain::customerHost();
            $prefix = trim((string) config('app.customer_path', 'customer'), '/');

            if ($path === '') {
                return $scheme.'://'.$host.'/'.$prefix;
            }

            return $scheme.'://'.$host.'/'.$prefix.'/'.ltrim($path, '/');
        });

        URL::macro('getSupplierPortalUrl', function (string $path = ''): string {
            $parsed = parse_url((string) config('app.url'));
            $scheme = $parsed['scheme'] ?? 'https';
            $host = PanelDomain::supplierHost();
            $prefix = trim((string) config('app.supplier_path', 'supplier'), '/');

            if ($path === '') {
                return $scheme.'://'.$host.'/'.$prefix;
            }

            return $scheme.'://'.$host.'/'.$prefix.'/'.ltrim($path, '/');
        });
    }
}

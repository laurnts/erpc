<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Use a dedicated session cookie for the customer panel so admin and customer
 * logins can coexist in separate browser tabs on the same subdomain.
 *
 * Filament panel routes already include this middleware, but Livewire update
 * requests use the global "web" group only — so this must also run there when
 * the request originates from the customer panel.
 */
final class UseCustomerPanelSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldUseCustomerSession($request)) {
            config([
                'session.cookie' => (string) config('app.customer_session_cookie', 'erpc_customer_session'),
            ]);
        }

        return $next($request);
    }

    public static function shouldUseCustomerSession(Request $request): bool
    {
        $customerPath = trim((string) config('app.customer_path', 'customer'), '/');

        if ($customerPath !== '' && str_starts_with($request->path(), $customerPath)) {
            return true;
        }

        if (! self::isLivewireRequestPath($request->path())) {
            return false;
        }

        // Prefer referer over snapshot so admin /login submissions never reuse the
        // customer session left open from another tab.
        if ($request->headers->has('referer')) {
            return self::refererContainsCustomerPath($request, $customerPath);
        }

        return self::livewireSnapshotContainsCustomerPath($request, $customerPath);
    }

    private static function isLivewireRequestPath(string $path): bool
    {
        return (bool) preg_match('#^livewire-[a-f0-9]+/#', $path);
    }

    private static function refererContainsCustomerPath(Request $request, string $customerPath): bool
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '' || $customerPath === '') {
            return false;
        }

        $path = parse_url($referer, PHP_URL_PATH) ?? '';

        return self::pathContainsCustomerSegment($path, $customerPath);
    }

    private static function livewireSnapshotContainsCustomerPath(Request $request, string $customerPath): bool
    {
        if ($customerPath === '') {
            return false;
        }

        $components = $request->input('components', []);

        if (! is_array($components)) {
            return false;
        }

        foreach ($components as $component) {
            if (! is_array($component) || ! isset($component['snapshot']) || ! is_string($component['snapshot'])) {
                continue;
            }

            $snapshot = json_decode($component['snapshot'], true);

            if (! is_array($snapshot)) {
                continue;
            }

            $path = $snapshot['memo']['path'] ?? null;

            if (is_string($path) && self::pathContainsCustomerSegment('/'.$path, $customerPath)) {
                return true;
            }
        }

        return false;
    }

    private static function pathContainsCustomerSegment(string $path, string $customerPath): bool
    {
        return str_contains($path, '/'.$customerPath.'/')
            || str_ends_with(rtrim($path, '/'), '/'.$customerPath);
    }
}

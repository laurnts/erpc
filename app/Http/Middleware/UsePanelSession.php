<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Use a dedicated session cookie per portal panel so internal and portal
 * logins can coexist in separate browser tabs on the same subdomain. Driven
 * by the app.panel_session_cookies config map (panel path prefix => cookie).
 *
 * Filament panel routes already include this middleware, but Livewire update
 * requests use the global "web" group only — so this must also run there when
 * the request originates from a portal panel.
 */
final class UsePanelSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookie = self::cookieForRequest($request);

        if ($cookie !== null) {
            config(['session.cookie' => $cookie]);
        }

        return $next($request);
    }

    public static function cookieForRequest(Request $request): ?string
    {
        foreach (self::panelCookieMap() as $panelPath => $cookie) {
            if (self::requestTargetsPanel($request, $panelPath)) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function panelCookieMap(): array
    {
        $map = [];

        foreach ((array) config('app.panel_session_cookies', []) as $panelPath => $cookie) {
            $panelPath = trim((string) $panelPath, '/');

            if ($panelPath !== '' && is_string($cookie) && $cookie !== '') {
                $map[$panelPath] = $cookie;
            }
        }

        return $map;
    }

    private static function requestTargetsPanel(Request $request, string $panelPath): bool
    {
        $path = $request->path();

        if ($path === $panelPath || str_starts_with($path, $panelPath.'/')) {
            return true;
        }

        if (! self::isLivewireRequestPath($request->path())) {
            return false;
        }

        // Prefer referer over snapshot so internal /login submissions never reuse
        // a portal session left open from another tab.
        if ($request->headers->has('referer')) {
            return self::refererContainsPanelPath($request, $panelPath);
        }

        return self::livewireSnapshotContainsPanelPath($request, $panelPath);
    }

    private static function isLivewireRequestPath(string $path): bool
    {
        return (bool) preg_match('#^livewire-[a-f0-9]+/#', $path);
    }

    private static function refererContainsPanelPath(Request $request, string $panelPath): bool
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '') {
            return false;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        return is_string($path) && self::pathContainsPanelSegment($path, $panelPath);
    }

    private static function livewireSnapshotContainsPanelPath(Request $request, string $panelPath): bool
    {
        $components = $request->input('components', []);

        if (! is_array($components)) {
            return false;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            if (! isset($component['snapshot'])) {
                continue;
            }
            if (! is_string($component['snapshot'])) {
                continue;
            }
            $snapshot = json_decode($component['snapshot'], true);

            if (! is_array($snapshot)) {
                continue;
            }

            $path = $snapshot['memo']['path'] ?? null;

            if (is_string($path) && self::pathContainsPanelSegment('/'.$path, $panelPath)) {
                return true;
            }
        }

        return false;
    }

    private static function pathContainsPanelSegment(string $path, string $panelPath): bool
    {
        return str_contains($path, '/'.$panelPath.'/')
            || str_ends_with(rtrim($path, '/'), '/'.$panelPath);
    }
}

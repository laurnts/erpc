<?php

declare(strict_types=1);

namespace App\Support;

final class PanelDomain
{
    public static function publicHost(): string
    {
        $parsed = parse_url((string) config('app.url'));

        return $parsed['host'] ?? 'localhost';
    }

    public static function appHost(): string
    {
        $configured = config('app.panel_domain');

        if (! is_string($configured) || $configured === '') {
            return 'app.'.self::publicHost();
        }

        $parsed = parse_url(str_contains($configured, '://') ? $configured : 'https://'.$configured);

        return $parsed['host'] ?? 'app.'.self::publicHost();
    }

    public static function customerHost(): string
    {
        $configured = config('app.customer_domain');

        if (is_string($configured) && $configured !== '') {
            $parsed = parse_url(str_contains($configured, '://') ? $configured : 'https://'.$configured);

            return $parsed['host'] ?? self::appHost();
        }

        return self::appHost();
    }
}

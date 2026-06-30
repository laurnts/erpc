<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCustomerPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.customer_portal_enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Portal\CustomerPortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InitializeCustomerPortalContext
{
    public function __construct(
        private readonly CustomerPortalContext $portalContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->guard('customer')->check() && auth()->guard('customer')->user()?->hasActiveBuyerPortalAccess()) {
            try {
                $this->portalContext->companyId();
            } catch (\RuntimeException) {
                // Context will be resolved when the user selects a company.
            }
        }

        return $next($request);
    }
}

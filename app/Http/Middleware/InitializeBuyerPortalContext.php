<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Portal\BuyerPortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class InitializeBuyerPortalContext
{
    public function __construct(
        private BuyerPortalContext $portalContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->guard('buyer')->check() && auth()->guard('buyer')->user()?->hasActiveBuyerPortalAccess()) {
            try {
                $this->portalContext->companyId();
            } catch (\RuntimeException) {
                // Context will be resolved when the user selects a company.
            }
        }

        return $next($request);
    }
}

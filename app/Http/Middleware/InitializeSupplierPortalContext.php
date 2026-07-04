<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Portal\SupplierPortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InitializeSupplierPortalContext
{
    public function __construct(
        private readonly SupplierPortalContext $portalContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->guard('supplier')->check() && auth()->guard('supplier')->user()?->hasActiveSupplierPortalAccess()) {
            try {
                $this->portalContext->companyId();
            } catch (\RuntimeException) {
                // Context will be resolved when the user selects a company.
            }
        }

        return $next($request);
    }
}

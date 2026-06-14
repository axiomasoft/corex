<?php

declare(strict_types=1);

namespace CoreX\Http\Middleware;

use Closure;
use CoreX\Contracts\TenantResolver;
use CoreX\Contracts\TenantService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the request via the bound TenantResolver
 * (a product-supplied strategy) and stores it on the TenantService.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantService $tenants,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound(TenantResolver::class)) {
            $tenant = app(TenantResolver::class)->resolve($request);

            if ($tenant !== null) {
                $this->tenants->setCurrent($tenant);
            }
        }

        return $next($request);
    }
}

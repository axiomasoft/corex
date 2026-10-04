<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\TenancyServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pure marker — its only job is to be present in a route's middleware list
 * (checked by {@see DenyImpersonatedWrites} via route introspection, not
 * execution order: group-middleware run BEFORE route-middleware, so a
 * "permit" middleware attached to the route could never physically outrun
 * a "deny" middleware from the group). Precedent for an empty middleware
 * used purely as a route-context flag: the package's own `'tenant'` group
 * registration ({@see TenancyServiceProvider}).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AllowImpersonatedWrites
{
    /** @return Response|mixed */
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}

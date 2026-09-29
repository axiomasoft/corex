<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Closure;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * L1 — HTTP layer of the read-only invariant (D141, research/12 §2): an
 * unsafe method under an active impersonation is denied UNLESS the matched
 * route carries the {@see AllowImpersonatedWrites} marker (by alias or
 * FQCN). Reuses `tenancy.status_gate.read_only_methods` (already the
 * project's one definition of "these HTTP methods are read-only") rather
 * than inventing a second config surface for the same fact (D116/D121).
 *
 * @internal spec: B-11 §5.9, D141
 */
final class DenyImpersonatedWrites
{
    public function __construct(
        private readonly ImpersonationService $impersonation,
        private readonly StatusGatePolicy $statusGate,
    ) {}

    /** @return Response|mixed */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->impersonation->state() === null) {
            return $next($request);
        }

        if ($this->statusGate->isReadOnlyMethod($request->method())) {
            return $next($request);
        }

        $middleware = $request->route()?->middleware() ?? [];

        if (in_array('tenancy.impersonation.allow', $middleware, true) || in_array(AllowImpersonatedWrites::class, $middleware, true)) {
            return $next($request);
        }

        abort(403);
    }
}

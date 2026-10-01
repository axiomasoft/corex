<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * B-11 §5.1 access rules, read from the already-hydrated tenant model — no
 * root-DB round trip here. Fail-closed: `match` carries no default-pass
 * branch, an unknown status (including `pending`/`provisioning`, which
 * should never reach this middleware — cache/domain already reject them)
 * falls through to 403.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccountStatusGate
{
    private readonly StatusGatePolicy $statusGate;

    public function __construct(?StatusGatePolicy $statusGate = null)
    {
        $this->statusGate = $statusGate ?? app(StatusGatePolicy::class);
    }

    /** @return Response|mixed */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Account $account */
        $account = tenancy()->tenant;

        return match ($account->status) {
            'trial', 'active' => $next($request),
            'suspended' => $this->ownerOnRoute(
                $request,
                $next,
                $account,
                $this->statusGate->paymentRoute,
            ),
            'grace' => $this->graceGate($request, $next, $account),
            'exporting', 'deleted' => abort(410),
            default => abort(403),
        };
    }

    /** @return Response|mixed */
    private function ownerOnRoute(
        Request $request,
        Closure $next,
        Account $account,
        ?string $route,
    ): mixed {
        if (! $this->isOwner($account) || ! $this->matchesConfiguredRoute($request, $route)) {
            abort(403);
        }

        return $next($request);
    }

    /** @return Response|mixed */
    private function graceGate(Request $request, Closure $next, Account $account): mixed
    {
        if (! $this->isOwner($account)) {
            abort(403);
        }

        $readOnly = $this->statusGate->isReadOnlyMethod($request->method());
        $onExportRoute = $this->matchesConfiguredRoute($request, $this->statusGate->exportRoute);

        if (! $readOnly && ! $onExportRoute) {
            abort(403);
        }

        return $next($request);
    }

    private function isOwner(Account $account): bool
    {
        $userId = auth()->id();

        return $userId !== null && $account->owner_identity_id === $userId;
    }

    private function matchesConfiguredRoute(Request $request, mixed $routeConfig): bool
    {
        if (! is_string($routeConfig) || $routeConfig === '') {
            return false;
        }

        if ($request->route()?->getName() === $routeConfig) {
            return true;
        }

        return $request->is(ltrim($routeConfig, '/'));
    }
}

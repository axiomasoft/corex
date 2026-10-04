<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * B-11 §5.1 access rules, status read from the current {@see AccountRef}; the owner identity is
 * read through the central {@see AccountRead} port. Fail-closed: `match` carries no
 * default-pass branch, an unknown status (including `pending`/`provisioning`, which
 * should never reach this middleware — cache/domain already reject them)
 * falls through to 403.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccountStatusGate
{
    public function __construct(
        private readonly StatusGatePolicy $statusGate,
        private readonly TenantContextResolver $contexts,
        private readonly AccountRead $accounts,
        private readonly AuthFactory $auth,
        private readonly string $centralConnection,
    ) {}

    /** @return Response|mixed */
    public function handle(Request $request, Closure $next): mixed
    {
        $account = $this->contexts->current()->account;

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
        AccountRef $account,
        ?string $route,
    ): mixed {
        if (! $this->isOwner($account) || ! $this->matchesConfiguredRoute($request, $route)) {
            abort(403);
        }

        return $next($request);
    }

    /** @return Response|mixed */
    private function graceGate(Request $request, Closure $next, AccountRef $account): mixed
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

    private function isOwner(AccountRef $account): bool
    {
        $userId = $this->auth->guard()->id();

        return $userId !== null
            && $this->accounts->ownerIdentityId($account->id, $this->centralConnection) === $userId;
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

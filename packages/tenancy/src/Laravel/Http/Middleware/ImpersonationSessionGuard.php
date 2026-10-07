<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TTL + revocation + banner for an active impersonated session (D144/D147/
 * D149). Registered in the `'tenant'` group AFTER `EnforceSessionLifetime`
 * (same idiom, AC-15) — a session that has outlived either check must die
 * on THIS request, not on a scheduler's next tick. `revoked_at` is read
 * from root on EVERY request without caching (D144): it is the compliance
 * kill-switch, and a cached answer would survive the revocation for the
 * length of the cache TTL.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ImpersonationSessionGuard
{
    public function __construct(
        private ImpersonationService $impersonation,
        private TenancyOptions $options,
    ) {}

    /** @return Response|mixed */
    public function handle(Request $request, Closure $next): mixed
    {
        $state = $this->impersonation->state();

        if ($state === null) {
            return $next($request);
        }

        if ($state->expiresAt <= CarbonImmutable::now()) {
            $this->impersonation->stop();

            abort(401);
        }

        $revokedAt = DB::connection($this->options->centralConnection)
            ->table('root_impersonation_grants')
            ->where('id', $state->grantId)
            ->value('revoked_at');

        if ($revokedAt !== null) {
            $this->impersonation->stop();

            abort(401);
        }

        $response = $next($request);

        if (! $response instanceof \Symfony\Component\HttpFoundation\Response) {
            throw new RuntimeException('Impersonation middleware requires an HTTP response.');
        }
        $response->headers->set('X-CoreX-Impersonation', $state->grantId);

        return $response;
    }
}

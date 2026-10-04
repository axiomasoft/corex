<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the session to the account it was first used under. A first binding drops every
 * earlier attribute (so a central or foreign login cannot carry over) and rotates the token;
 * a session bound to another account is invalidated and denied. Runs after StartSession and
 * before any user resolution.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ScopeSessionToTenant
{
    private const string KEY = 'corex.tenancy.account_id';

    public function __construct(private readonly TenancyManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->manager->context() ?? throw TenantContextUnavailable::notInitialized();
        $session = $request->session();
        $bound = $session->get(self::KEY);

        if ($bound === null) {
            $session->invalidate();
            $session->regenerateToken();
            $session->put(self::KEY, $context->account->id);
        } elseif ($bound !== $context->account->id) {
            $session->invalidate();

            abort(403);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

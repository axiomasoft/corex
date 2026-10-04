<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Runtime\Exceptions\TenantCouldNotBeIdentifiedByHostException;
use CoreX\Tenancy\Runtime\Internal\HostAccountResolver;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;

/**
 * Kernel early-identification (B-11 §5.4 п.1–2). Host is read ONLY from
 * {@see Request::getHost()} — the edge (Caddy) is not a source of truth,
 * client-supplied `X-Account-*`/`X-Forwarded-Host` are never consulted.
 *
 * The request runs inside the account context and the context ends in `finally`, after the
 * session was saved and the response left every inner middleware. This is also the HTTP
 * boundary of a resident process: attributes of an already created session Store and the
 * resolved auth guards are dropped on entry and exit, so a request never inherits the previous
 * account's login (nested contexts and jobs do not pass through here).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ResolveTenantFromHost
{
    public function __construct(
        private readonly TenancyManager $manager,
        private readonly HostAccountResolver $hosts,
        private readonly AccountRead $accounts,
        private readonly Container $container,
        private readonly string $centralConnection,
    ) {}

    /** @return Response|mixed */
    public function handle(Request $request, Closure $next): mixed
    {
        $host = $request->getHost();
        $account = $this->accounts->find($this->hosts->resolve($host), $this->centralConnection)
            ?? throw TenantCouldNotBeIdentifiedByHostException::forHost($host);

        $this->dropResidentState();

        try {
            $this->manager->initialize($account);

            return $next($request);
        } finally {
            $this->manager->end();
            $this->dropResidentState();
        }
    }

    private function dropResidentState(): void
    {
        if ($this->container->resolved('session')) {
            /** @var SessionManager $sessions */
            $sessions = $this->container->make('session');
            /** @var array<string, Store> $stores */
            $stores = $sessions->getDrivers();

            foreach ($stores as $store) {
                $store->flush();
            }
        }

        if ($this->container->resolved(AuthFactory::class)) {
            self::forgetGuards($this->container->make(AuthFactory::class));
        }
    }

    /** A host may bind its own factory; only the framework manager keeps resolved guards. */
    private static function forgetGuards(object $auth): void
    {
        if ($auth instanceof AuthManager) {
            $auth->forgetGuards();
        }
    }
}

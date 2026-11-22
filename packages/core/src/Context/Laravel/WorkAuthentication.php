<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Closure;
use CoreX\Context\Internal\WorkAuthManager;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Install only the independently verified identity for this frame and restore every outer guard and resolver. */
#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final readonly class WorkAuthentication
{
    public function __construct(private Application $app) {}

    /** @template T
     * @param Closure():T $work
     * @return T */
    public function run(?Authenticatable $principal, Closure $work, ?UserProvider $provider = null): mixed
    {
        $outer = $this->app->make(AuthManager::class);
        $request = $this->app->make(Request::class);
        $userResolver = $request->getUserResolver();
        $priorDriver = $this->app->resolved('auth.driver') ? $this->app->make('auth.driver') : null;
        $guard = new RequestGuard(static fn (): ?Authenticatable => $principal, $request, $provider);
        $manager = new WorkAuthManager(app: $this->app, activeGuard: $guard);

        try {
            $request->setUserResolver(static fn (): ?Authenticatable => $principal);
            $this->app->instance('auth', $manager);
            $this->app->instance('auth.driver', $guard);
            Facade::clearResolvedInstance('auth');

            return $work();
        } finally {
            $request->setUserResolver($userResolver);
            $this->app->instance('auth', $outer);

            if ($priorDriver === null) {
                $this->app->forgetInstance('auth.driver');
            } else {
                $this->app->instance('auth.driver', $priorDriver);
            }
            Facade::clearResolvedInstance('auth');
        }
    }
}

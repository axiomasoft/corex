<?php

declare(strict_types=1);

namespace CoreX\Audit\Internal;

use Closure;
use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Data\ImpersonationState;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Packman\Attributes\Internal;
use Stringable;

/**
 * Actor/request snapshot for an audit entry built outside a queue worker
 * (context: ip/user_agent/request_id/module). Real agent/api_key actor
 * wiring lands with entity-scoped auth. Under an active impersonation
 * (D137/§5.9 п.3) the actor is `support`/staff — resolved from the
 * `ImpersonationService` CONTRACT via the container, never a `axiomasoft/corex-tenancy`
 * import (D139 — this class lives in ядро, level 0 must not depend on the
 * cloud package).
 *
 * @internal spec: B-10 §3.1, P2, D137/D139
 */
#[Internal]
final readonly class CurrentActor
{
    /** @param  Closure(): ?Request  $request  the request of the running cycle, null in console or without a request. */
    public function __construct(
        private AuthFactory $auth,
        private ImpersonationService $impersonation,
        private Closure $request,
    ) {}

    public function type(): ActorType
    {
        if ($this->state() !== null) {
            return ActorType::Support;
        }

        return $this->auth->guard()->check() ? ActorType::User : ActorType::System;
    }

    public function id(): ?string
    {
        $state = $this->state();

        if ($state !== null) {
            return $state->staffIdentityId;
        }

        $user = $this->auth->guard()->user();

        $identifier = $user?->getAuthIdentifier();
        assert(is_scalar($identifier) || $identifier === null || $identifier instanceof Stringable);

        return $user !== null ? (string) $identifier : null;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        $context = [];

        $state = $this->state();

        if ($state !== null) {
            $context['impersonation'] = [
                'grant' => $state->grantId,
                'target_user_id' => $state->targetUserId,
                'reason' => $state->reason,
            ];
        }

        $request = ($this->request)();

        if ($request === null) {
            return $context;
        }

        return array_filter([
            ...$context,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id'),
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function state(): ?ImpersonationState
    {
        return $this->impersonation->state();
    }
}

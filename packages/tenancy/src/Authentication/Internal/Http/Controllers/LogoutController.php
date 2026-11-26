<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Authentication\Internal\Http\Controllers;

use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/**
 * Global logout: revokes every unrevoked `root_idp_sessions` row of
 * the current identity, cascades into `root_oauth_refresh_tokens` by
 * `session_id`, then ends the tenant-local session. Both tables are
 * audited — this UPDATEs `revoked_at`, it NEVER `DELETE`s a row.
 * `root_idp_sessions` are not created here (that is the AS's job); this
 * controller only ever revokes.
 */
#[Internal]
final readonly class LogoutController
{
    public function __construct(
        private AuthFactory $auth,
        private ConnectionResolverInterface $connections,
        private ClockInterface $clock,
        private TenancyOptions $options,
    ) {}

    public function logout(Request $request): RedirectResponse
    {
        $guard = $this->statefulGuard();
        $identityId = $guard->id();

        if (is_string($identityId) && $identityId !== '') {
            $this->revokeIdentitySessions($identityId);
        }

        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function revokeIdentitySessions(string $identityId): void
    {
        $now = $this->clock->now();

        $sessionIds = $this->connections->connection($this->options->centralConnection)
            ->table('root_idp_sessions')
            ->where('identity_id', $identityId)
            ->whereNull('revoked_at')
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return;
        }

        $this->connections->connection($this->options->centralConnection)
            ->table('root_idp_sessions')
            ->where('identity_id', $identityId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        $this->connections->connection($this->options->centralConnection)
            ->table('root_oauth_refresh_tokens')
            ->whereIn('session_id', $sessionIds)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);
    }

    private function statefulGuard(): StatefulGuard
    {
        $guard = $this->auth->guard($this->guard());

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException("Auth guard [{$this->guard()}] must be stateful to end a session.");
        }

        return $guard;
    }

    private function guard(): string
    {
        return $this->options->oidcGuard;
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Identity\Internal\Models\User;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class RequireActiveMembership
{
    public function __construct(
        private readonly ?TenancyOptions $options = null,
    ) {}

    /** @return Response|mixed */
    public function handle(Request $request, Closure $next): mixed
    {
        $guard = ($this->options ?? app(TenancyOptions::class))->membershipGuard;
        $identityId = Auth::guard($guard)->id();

        $user = $identityId === null ? null : User::query()->find($identityId);

        if ($identityId !== null && ($user === null || ! $user->is_active)) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403);
        }

        return $next($request);
    }
}

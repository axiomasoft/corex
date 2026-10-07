<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Http\Middleware;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Workspace\Contracts\WorkspaceResolver;
use CoreX\Tenancy\Workspace\Exceptions\NoDefaultWorkspaceException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Third step of B-11 §5.4 request resolution: narrows the already-
 * initialized tenant context down to a workspace (AC-18). Registered as the
 * `tenancy.workspace` ALIAS (D131) — not a member of the `'tenant'` group —
 * so a route without the alias keeps the account-wide admin context, while
 * `->middleware(['tenant', 'tenancy.workspace'])` narrows AFTER `'tenant'`
 * has already proven the session belongs to this account. Every failure is
 * fail-closed 403 (D134): a pass-through would leave {@see
 * \CoreX\Tenancy\Laravel\Scopes\WorkspaceScope} unfiltered, showing every workspace
 * in the account.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ResolveWorkspace
{
    public function __construct(
        private readonly TenancyManager $manager,
        private readonly WorkspaceResolver $resolver,
        private readonly ?TenancyOptions $options = null,
    ) {}

    /** @return Response|mixed */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->manager->initialized()) {
            abort(403, 'Tenancy is not initialized.');
        }

        if (auth()->guard()->id() === null) {
            abort(403, 'Authentication required to resolve a workspace.');
        }

        $slug = $this->extractSlug($request);

        try {
            $workspace = $this->resolver->resolve($slug);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (ModelNotFoundException) {
            abort(403, 'Unknown workspace.');
        } catch (NoDefaultWorkspaceException $e) {
            abort(403, $e->getMessage());
        }

        $this->manager->setWorkspace($workspace);

        if ($this->manager->context()?->workspace?->id !== $workspace->id) {
            abort(403, 'Workspace narrowing did not take effect.');
        }

        return $next($request);
    }

    /** Marker's FIRST occurrence in `$request->segments()`; no marker → default (null). */
    private function extractSlug(Request $request): ?string
    {
        $configured = ($this->options ?? app(TenancyOptions::class))->workspacePathSegment;
        $marker = $configured ?? 'w';

        $segments = array_values($request->segments());
        $index = array_search($marker, $segments, true);

        if ($index === false) {
            return null;
        }

        if (! is_string($segments[$index + 1] ?? null)) {
            abort(403, 'Workspace marker present without a following slug.');
        }

        return $segments[$index + 1];
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Workspace\Internal;

use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver;
use CoreX\Tenancy\Workspace\Contracts\WorkspaceResolver;
use CoreX\Tenancy\Workspace\Exceptions\NoDefaultWorkspaceException;
use CoreX\Tenancy\Workspace\Internal\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Override;
use Packman\Attributes\Internal;

/**
 * Membership-checked `/w/{slug}` → {@see WorkspaceRef} resolver. Membership is looked up against the EXACT workspace being
 * resolved — a partner-workspace membership never grants implicit access to
 * its parent, because no path-based traversal happens here.
 *
 * @internal
 */
#[Internal]
final readonly class DatabaseWorkspaceResolver implements PrincipalWorkspaceResolver, WorkspaceResolver
{
    public function __construct(private AuthFactory $auth) {}

    #[Override]
    public function resolve(?string $slug): WorkspaceRef
    {
        $userId = $this->auth->guard()->id();

        if (! is_string($userId) && ! is_int($userId)) {
            throw new AuthorizationException('Authentication required to resolve a workspace.');
        }

        return $this->resolveFor(principalId: $userId, slug: $slug);
    }

    #[Override]
    public function resolveFor(string|int $principalId, ?string $slug): WorkspaceRef
    {
        if ((string) $principalId === '') {
            throw new AuthorizationException('A trusted principal is required to resolve a workspace.');
        }

        $workspace = $slug === null
            ? $this->defaultWorkspaceFor($principalId)
            : $this->workspaceBySlug(slug: $slug, userId: $principalId);

        return new WorkspaceRef(
            id: $workspace->id,
            slug: $workspace->slug,
            type: $workspace->type,
            path: $workspace->path,
            parentId: $workspace->parent_id,
        );
    }

    private function workspaceBySlug(string $slug, string|int $userId): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Workspace::query()->where('slug', $slug)->firstOr(
            static fn (): never => throw (new ModelNotFoundException)->setModel(Workspace::class, [$slug]),
        );

        $isMember = $workspace->members()->getQuery()->toBase()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            throw new AuthorizationException("Not an active member of workspace [{$slug}].");
        }

        return $workspace;
    }

    private function defaultWorkspaceFor(string|int $userId): Workspace
    {
        /** @var ?Workspace $workspace */
        $workspace = Workspace::query()
            ->whereHas('members', static function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where('is_default', true);
            })
            ->first();

        if ($workspace === null) {
            throw new NoDefaultWorkspaceException('No default workspace membership for the current user.');
        }

        return $workspace;
    }
}

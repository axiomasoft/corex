<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Internal;

use AzGuard\AzGuard;
use AzGuard\Kernel\Decision\AccessRequest;
use AzGuard\Kernel\Identity\PermissionKey;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Data\RecordContext;
use CoreX\Access\Enums\Scope;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\AccessAzguard\Contracts\AccessContextMapper;
use CoreX\AccessAzguard\Exceptions\ScopeProjectionNotSupported;
use CoreX\Context\Contracts\Principal;
use Illuminate\Database\Eloquent\Builder;
use Packman\Modules\Contracts\Entities\EntityRegistry;

/** @internal AzGuard owns both scalar decisions and exact query filtering. */
final readonly class AzGuardAccessChecker implements AccessChecker, ScopeResolver
{
    public function __construct(
        private AzGuard $azguard,
        private AccessContextMapper $contexts,
        private AuthOptions $options,
        private EntityRegistry $entities,
        private EntityActivity $activity,
    ) {}

    public function allowed(Principal $p, string $entityKey, string $action): bool
    {
        return $this->decide($p, $entityKey, $action);
    }

    public function scopeFor(Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Scope
    {
        if (! $this->activity->isActive($entityKey) || $this->contexts->scope($ctx) === null) {
            return Scope::None;
        }

        throw new ScopeProjectionNotSupported;
    }

    public function canOnRecord(Principal $p, string $action, object $record): bool
    {
        $entity = $this->entities->forModel($record::class);

        return $entity !== null && $this->decide($p, $entity->handle, $action, $record);
    }

    public function apply(Builder $q, Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Builder
    {
        $scope = $this->contexts->scope($ctx);
        $entity = $this->entities->forModel($q->getModel()::class);

        if ($scope === null || $entity?->handle !== $entityKey || ! $this->activity->isActive($entityKey)) {
            $q->getQuery()->whereRaw('1 = 0');

            return $q;
        }

        $panel = $this->azguard->panel($this->options->panel);

        return $panel->visibility()->visibleTo(
            $panel->definition(), $q, $this->contexts->subject($p),
            PermissionKey::of($this->options->panel, $entityKey.'.'.$action)->full(), $scope,
        );
    }

    private function decide(Principal $principal, string $entityKey, string $action, ?object $record = null): bool
    {
        $scope = $this->contexts->scope();

        if ($scope === null || ! $this->activity->isActive($entityKey)) {
            return false;
        }

        $request = AccessRequest::for(
            $this->contexts->subject($principal),
            PermissionKey::of($this->options->panel, $entityKey.'.'.$action),
        )->inScope($scope, $record);

        return $this->azguard->panel($this->options->panel)->decide($request)->allowed();
    }
}

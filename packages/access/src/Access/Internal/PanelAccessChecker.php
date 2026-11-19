<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\AzGuard;
use AzGuard\Contracts\Scopes\ProvidesAccessScope;
use AzGuard\Kernel\Decision\AccessRequest;
use AzGuard\Kernel\Identity\PermissionKey;
use AzGuard\Kernel\Identity\SubjectRef;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Data\RecordContext;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Laravel\ScopeResolver;
use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Context\Contracts\Principal;
use Illuminate\Database\Eloquent\Builder;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Entities\EntityRegistry;

#[Internal]
final readonly class PanelAccessChecker implements AccessChecker, ScopeResolver
{
    public function __construct(
        private AzGuard $authorization,
        private CoreXAccessContextMapper $contexts,
        private AuthOptions $options,
        private EntityRegistry $entities,
        private EntityActivity $activity,
        private MatrixPermissions $matrix,
        private ScopePredicate $predicate,
    ) {}

    public function allowed(Principal $p, string $entityKey, string $action): bool
    {
        return $this->decide($p, $entityKey, $action);
    }

    public function canOnRecord(Principal $p, string $action, object $record): bool
    {
        $entity = $this->entities->forModel($record::class);

        if ($entity === null || ! $this->decide($p, $entity->handle, $action, $record)) {
            return false;
        }

        return isset($this->options->policyPanels[$entity->handle.'.'.$action])
            || $this->matrix->directlyGranted($p, $entity->handle, $action)
            || $this->predicate->matches($this->matrix->scope($p, $entity->handle, $action), $record, $p);
    }

    public function apply(Builder $q, Principal $p, string $entityKey, string $action, ?RecordContext $ctx = null): Builder
    {
        $entity = $this->entities->forModel($q->getModel()::class);
        $scope = $entity?->handle === $entityKey ? $this->contexts->scope($ctx) : null;

        if ($scope === null || ! $this->activity->isActive($entityKey)) {
            $q->getQuery()->whereRaw('1 = 0');

            return $q;
        }

        $permission = $entityKey.'.'.$action;
        $owner = $this->options->policyPanels[$permission] ?? null;

        if ($owner !== null) {
            $panel = $this->authorization->panel($owner);

            return $panel->visibility()->visibleTo($panel->definition(), $q, $this->contexts->subject($p), PermissionKey::of($owner, $permission)->full(), $scope);
        }

        if (! $this->decide($p, $entityKey, $action)) {
            $q->getQuery()->whereRaw('1 = 0');

            return $q;
        }

        if ($this->matrix->directlyGranted($p, $entityKey, $action)) {
            return $q;
        }

        return $this->predicate->applyTo($q, $this->matrix->scope($p, $entityKey, $action, $ctx), $p);
    }

    private function decide(Principal $principal, string $entity, string $action, ?object $record = null): bool
    {
        if (! $this->entities->has($entity)) {
            return false;
        }

        $scope = $this->contexts->scope();

        if ($scope === null || ! $this->activity->isActive($entity)) {
            return false;
        }
        $subject = $this->contexts->subject($principal);
        $permission = $entity.'.'.$action;
        $owner = $this->options->policyPanels[$permission] ?? null;

        if ($owner !== null) {
            $request = AccessRequest::for($subject, PermissionKey::of($owner, $permission))->inScope($scope, $record);

            return $this->authorization->panel($owner)->decide($request)->allowed();
        }

        if ($record instanceof ProvidesAccessScope && ! $scope->equals($record->azguardScope())) {
            return false;
        }

        $panel = MatrixPanel::getId();
        $model = MatrixSubject::fromPrincipal($principal);
        $request = AccessRequest::for(SubjectRef::of($model->getMorphClass(), $subject->key()), PermissionKey::of($panel, MatrixPermission::Evaluate->value))
            ->on(null, new MatrixCell($principal, $entity, $action, $scope))
            ->withSubjectModel($model);

        return $this->authorization->panel($panel)->decide($request)->allowed();
    }
}

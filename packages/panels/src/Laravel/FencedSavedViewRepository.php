<?php

declare(strict_types=1);

namespace CoreX\Panels\Laravel;

use Closure;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Panels\Contracts\SavedViewInventory;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Internal;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Packman\Panels\SavedViews\Data\SavedQuery;
use Packman\Panels\SavedViews\Data\SavedViewRef;
use Packman\Panels\SavedViews\Data\SaveViewInput;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewScope;

#[Internal]
final readonly class FencedSavedViewRepository implements SavedViewRepository
{
    public function __construct(private SavedViewRepository $inner, private Application $app) {}

    public function viewsFor(ViewContext $context, ViewScope $scope): array
    {
        return $this->inner->viewsFor($context, $scope);
    }

    public function compile(ViewContext $context, string $viewId): SavedQuery
    {
        return $this->inner->compile($context, $viewId);
    }

    public function save(ViewContext $context, SaveViewInput $input): SavedViewRef
    {
        return $this->write(context: $context, operation: 'save', id: $input->id, input: $input, write: fn () => $this->inner->save($context, $input));
    }

    public function delete(ViewContext $context, string $viewId): void
    {
        $this->write(context: $context, operation: 'delete', id: $viewId, input: null, write: fn () => $this->inner->delete($context, $viewId));
    }

    public function setDefault(ViewContext $context, string $viewId): SavedViewRef
    {
        return $this->write(context: $context, operation: 'set-default', id: $viewId, input: null, write: fn () => $this->inner->setDefault($context, $viewId));
    }

    /** @template T
     * @param Closure():T $write
     * @return T */
    private function write(ViewContext $context, string $operation, ?string $id, ?SaveViewInput $input, Closure $write): mixed
    {
        $scope = ReferenceWrites::current();
        $viewActorKind = match ($scope->principalKind) {
            'service' => 'system',
            'user' => 'user',
            default => null,
        };

        if ($context->tenant->id() !== $scope->accountId || $context->actorKind !== $viewActorKind || $context->actorId !== $scope->principalId
            || ($input !== null && $input->scope->workspaceId !== $scope->workspaceId)) {
            throw new LifecycleBlocked('View write belongs to another current principal or scope.');
        }

        if (! $this->app->bound(SavedViewInventory::class)) {
            throw new LifecycleBlocked('Saved view write namespace unavailable.');
        }
        $inventory = $this->app->make(SavedViewInventory::class);
        $db = $inventory->connection($scope);

        return $db->transaction(function () use ($db, $scope, $inventory, $context, $operation, $id, $input, $write): mixed {
            $targets = $inventory->targets($scope, $context, $operation, $id, $input);
            $this->app->make(ReferenceAdmission::class)->enlist($scope, $db, new ModuleRef('axiomasoft/packman-panels'), ...$targets);

            return $write();
        });
    }
}

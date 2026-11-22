<?php

declare(strict_types=1);

namespace CoreX\Panels\Laravel;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ReferenceExclusion;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Panels\Contracts\SavedViewInventory;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Internal;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Throwable;

#[Internal]
final readonly class SavedViewModuleReferences implements ModuleReferences, ReferenceExclusion
{
    public function __construct(private Application $app) {}

    public function owner(): string
    {
        return 'axiomasoft/packman-panels';
    }

    public function hold(DataScope $scope, ModuleRef $module, LifecyclePlanRef $plan, ?OwnerLease $previous): OwnerLease
    {
        return $this->inventory()->hold(scope: $scope, module: $module, plan: $plan, previous: $previous);
    }

    public function verify(DataScope $scope, OwnerLease $lease): void
    {
        $this->inventory()->verify(scope: $scope, lease: $lease);
    }

    public function release(DataScope $scope, OwnerLease $lease): void
    {
        $this->inventory()->release(scope: $scope, lease: $lease);
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $host = $this->inventory();
        $directory = $host->scopes($scope);
        $revision = hash('sha256', json_encode([$scope, $module, $directory], JSON_THROW_ON_ERROR));
        $index = $after === null ? 0 : filter_var($after->position, FILTER_VALIDATE_INT);

        if ($index === false || $index < 0 || ($after !== null && ($after->revision !== $revision || ! isset($directory[$index])))) {
            throw new LifecycleBlocked('Saved view directory changed.');
        }
        $blockers = [];

        if (isset($directory[$index])) {
            [$context, $viewScope] = $directory[$index];

            if ($context->tenant->id() !== $scope->accountId || $viewScope->workspaceId !== $scope->workspaceId) {
                throw new LifecycleBlocked('Foreign saved view directory.');
            }
            $views = $this->app->make(SavedViewRepository::class);
            foreach ($views->viewsFor($context, $viewScope) as $view) {
                if (get_object_vars($view->scope) !== get_object_vars($viewScope)) {
                    throw new LifecycleBlocked('Mismatched saved view scope.');
                }

                try {
                    $views->compile($context, $view->id);
                    $targets = $host->targets(scope: $scope, context: $context, operation: 'inspect', id: $view->id, input: null);

                    if (! array_any($targets, static fn (ModuleRef $target): bool => $target->package === $module->package) && $module->package !== $this->owner()) {
                        continue;
                    }
                    $action = 'remove-or-rebind-saved-view';
                } catch (Throwable $error) {
                    report($error);
                    $action = 'restore-saved-view-provenance';
                }
                $blockers[] = new LifecycleBlocker(owner: $this->owner(), resourceKind: 'saved-view', resourceId: $view->id, scope: $scope, requiredAction: $action);
            }
        }

        return new ReferencePage(blockers: $blockers, revision: $revision, next: isset($directory[$index + 1]) ? new ReferenceCursor(position: (string) ($index + 1), revision: $revision) : null);
    }

    private function inventory(): SavedViewInventory
    {
        if (! $this->app->bound(SavedViewInventory::class)) {
            throw new LifecycleBlocked('Complete private and shared saved view inventory unavailable.');
        }

        return $this->app->make(SavedViewInventory::class);
    }
}

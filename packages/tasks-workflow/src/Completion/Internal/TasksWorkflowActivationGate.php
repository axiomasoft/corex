<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Completion\Internal;

use Closure;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Data\DefinitionRequest;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Application;
use Override;
use Packman\Attributes\Internal;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Lifecycle\ModuleActivationDecision;

#[Internal]
final readonly class TasksWorkflowActivationGate implements ModuleActivationGate
{
    /** @param Closure(): Container $container */
    public function __construct(
        private ModuleActivationGate $host,
        private Closure $container,
        private ?CompletionScope $scope,
        private string $definitionRef,
        private string $versionRef,
    ) {}

    #[Override]
    public function check(ContextScopeInterface $ctx, string $module, string $edition): ModuleActivationDecision
    {
        $container = ($this->container)();

        if ($container instanceof Application) {
            $scope = $container->make(CompletionScope::class);

            if ($scope !== $this->scope && $container->isShared($container->getAlias(ModuleActivationGate::class))) {
                $current = $this->currentGate($container);

                // A permanent gate stays current after a scope reset.
                if ($current !== $this) {
                    return $current->check($ctx, $module, $edition);
                }
            }
        }

        $decision = $this->host->check($ctx, $module, $edition);

        if (! $decision->allowed || $module !== TaskCompletionTrigger::MODULE) {
            return $decision;
        }

        if (! TaskCompletionTrigger::isDeclared($container->make(ModuleRegistry::class))) {
            return ModuleActivationDecision::deny('Task workflow completion requires the compiled tsk.task/completed trigger owned by Tasks.');
        }

        try {
            $context = $container->make(TenantContextResolver::class)->current();
        } catch (CentralContextException) {
            return ModuleActivationDecision::deny('Task workflow activation requires the current tenant context.');
        }

        if ($ctx->id() !== $context->id()
            || ($ctx instanceof TenantContext && $ctx->workspace?->id !== $context->workspace?->id)) {
            return ModuleActivationDecision::deny('Task workflow activation requires the current tenant context.');
        }

        $provider = $container->make(EngineAdapter::class)->provider(new DefinitionRequest(
            context: $context,
            module: TaskCompletionTrigger::MODULE,
            entityHandle: TaskCompletionTrigger::ENTITY,
            eventName: TaskCompletionTrigger::EVENT,
            definitionRef: $this->definitionRef,
            versionRef: $this->versionRef,
        ));

        return $provider === null
            ? ModuleActivationDecision::deny('The pinned task workflow definition and version have no active engine.')
            : $decision;
    }

    private function currentGate(Application $app): ModuleActivationGate
    {
        return $app->make(ModuleActivationGate::class);
    }
}

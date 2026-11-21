<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Completion\Internal;

use Closure;
use Illuminate\Foundation\Application;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;

#[Internal]
final class ActivationGateExtension
{
    /** @var array<string, true> */
    private array $transientBindings = [];

    /** @param Closure(ModuleActivationGate, Application): ModuleActivationGate $decorate */
    public function __construct(private readonly Closure $decorate) {}

    public function prepare(Application $app, string $binding): void
    {
        if (! $app->isShared($binding)) {
            if (! isset($this->transientBindings[$binding])) {
                $this->transientBindings[$binding] = true;
                $app->extend($binding, $this->decorate);
            }

            return;
        }

        $scope = $app->make(CompletionScope::class);

        if ($scope->attachingGate) {
            return;
        }

        // Resolve the requested shared gate and keep its slot occupied during extension.
        // Laravel may resolve it again to notify a native refresh subscriber.
        $scope->attachingGate = true;

        try {
            $current = $this->currentGate($app);

            if (! $current instanceof TasksWorkflowActivationGate) {
                $app->extend($binding, $this->decorate);
            }
        } finally {
            $scope->attachingGate = false;
        }
    }

    private function currentGate(Application $app): ModuleActivationGate
    {
        return $app->make(ModuleActivationGate::class);
    }
}

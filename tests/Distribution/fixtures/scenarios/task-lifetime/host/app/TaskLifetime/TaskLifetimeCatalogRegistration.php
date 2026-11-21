<?php

declare(strict_types=1);

namespace App\TaskLifetime;

use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Contracts\Registry\RegistryIntrospection;

final class TaskLifetimeCatalogRegistration extends ServiceProvider
{
    public function register(): void
    {
        $order = getenv('COREX_SCENARIO_TASKS_REGISTRATION_ORDER');

        if ($order === false) {
            return;
        }

        if ($this->app->isBooted() || $this->app->getProvider(TasksWorkflowServiceProvider::class) === null) {
            throw new LogicException('The registration probe requires the bridge before the catalogue provider.');
        }

        if ($order === 'reader') {
            $reader = $this->app->make(RegistryIntrospection::class);
            $reader->extensionPoints();
            $this->app->instance('task-lifetime.registration-reader', $reader);
        } else {
            $this->app->make(ModuleActivationGate::class);
        }

        $this->app->instance('task-lifetime.registration-order', $order);
    }
}

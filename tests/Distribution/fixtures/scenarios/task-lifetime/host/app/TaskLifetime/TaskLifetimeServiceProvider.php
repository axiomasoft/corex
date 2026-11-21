<?php

declare(strict_types=1);

namespace App\TaskLifetime;

use App\Scenario\Scenario;
use App\Scenario\ScenarioAuthorization;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Access\Contracts\TaskMutationAccess;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Workflow\Contracts\EngineAdapter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Contracts\Registry\RegistryIntrospection;

final class TaskLifetimeServiceProvider extends ServiceProvider
{
    public function boot(LaravelApplication $app): void
    {
        if (getenv('COREX_SCENARIO_TASKS_OUTER_GATE') !== '1') {
            return;
        }

        $decorate = static fn (ModuleActivationGate $next): ModuleActivationGate => $app->instance(TaskLifetimeOuterGate::class, new TaskLifetimeOuterGate($next));
        $binding = $app->getAlias(ModuleActivationGate::class);

        if ($app->resolved($binding) && $app->isShared($binding) && isset($app->getBindings()[$binding])) {
            // This host wants its policy on both the warmed object and future scoped builds.
            $current = $app->make(ModuleActivationGate::class);
            $app->forgetInstance($binding);
            $app->extend($binding, $decorate);
            $app->instance($binding, $decorate($current));

            return;
        }

        $app->extend($binding, $decorate);
    }

    public function register(): void
    {
        $this->app['config']->set('queue.default', 'sync');
        $this->app->scoped(TaskLifetimeAccess::class);
        $this->app->alias(TaskLifetimeAccess::class, TaskAccess::class);
        $this->app->alias(TaskLifetimeAccess::class, TaskMutationAccess::class);
        $this->app->scoped(FieldUsePolicy::class, ScenarioAuthorization::class);
        $tenantOnlyGate = getenv('COREX_SCENARIO_TASKS_TENANT_ONLY_GATE') === '1';
        $registrationOrder = getenv('COREX_SCENARIO_TASKS_REGISTRATION_ORDER');
        $permanentGate = $this->registerGate($registrationOrder !== false, $tenantOnlyGate);

        if (getenv('COREX_SCENARIO_TASKS_OUTER_GATE') === '1') {
            $missingOuter = static fn () => throw new LogicException('The public gate has not built the current outer policy.');

            if ($permanentGate) {
                $this->app->singleton(TaskLifetimeOuterGate::class, $missingOuter);
            } else {
                $this->app->scoped(TaskLifetimeOuterGate::class, $missingOuter);
            }
        }

        if ($registrationOrder === false && ! $tenantOnlyGate && getenv('COREX_SCENARIO_TASKS_LATE_REGISTRY') !== '1') {
            $this->app->booting(function (): void {
                $cache = $this->app['config']->get('packman.modules.cache_path') ?? $this->app['config']->get('modules.cache_path');

                if (! $this->app->configurationIsCached() || ! is_string($cache) || ! is_file($this->app->basePath($cache))) {
                    return;
                }

                $gateFirst = getenv('COREX_SCENARIO_TASKS_GATE_FIRST') === '1';

                if ($gateFirst) {
                    $this->app->make(ModuleActivationGate::class);
                }

                $reader = $this->app->make(RegistryIntrospection::class);
                $reader->extensionPoints();
                $this->app->instance('task-lifetime.boot-read-order', $gateFirst ? 'gate-first' : 'reader-first');
                $this->app->instance('task-lifetime.boot-reader', $reader);
                $this->app->instance('task-lifetime.introspection-gate', $this->app->make(ModuleActivationGate::class)::class);
            });
        }

        if ($this->app->bound(EngineAdapter::class)) {
            $this->app->scoped(TaskLifetimeWorkflowEngine::class);
            $this->app->alias(TaskLifetimeWorkflowEngine::class, EngineAdapter::class);
        }

        $trace = getenv('COREX_SCENARIO_TASKS_PORT_TRACE');

        if ($trace !== false) {
            $this->app->beforeResolving(ModuleActivationGate::class, static fn () => file_put_contents($trace, "policy\n", FILE_APPEND));
            $this->app->beforeResolving(EngineAdapter::class, static fn () => file_put_contents($trace, "engine\n", FILE_APPEND));
        }

        if (getenv('COREX_SCENARIO_DUPLICATE_COMPLETION') === '1') {
            $this->app->tag(DuplicateCompletionCapture::class, TaskCompletionCapture::TAG);
        }
    }

    private function registerGate(bool $registrationProbe, bool $tenantOnly): bool
    {
        $wiring = getenv('COREX_SCENARIO_TASKS_REGISTRATION_HOST');

        if ($registrationProbe && (! $this->app->bound('task-lifetime.registration-order') || $this->app->isBooted())) {
            throw new LogicException('The host policy must register after the catalogue provider and before boot.');
        }

        $binding = in_array($wiring, ['instance-alias', 'scoped-alias'], true) ? 'task-lifetime.host-policy' : ModuleActivationGate::class;
        $permanent = $registrationProbe && in_array($wiring, ['instance', 'instance-alias'], true);

        if ($permanent) {
            $gate = new TaskLifetimeActivationGate(getenv('COREX_SCENARIO_TASKS_REGISTRATION_DENY') !== '1');
            $this->app->instance($binding, $gate);
            $this->app->instance('task-lifetime.registered-host', $gate);
        } else {
            $this->app->scoped($binding, static function (Application $app) use ($tenantOnly, $registrationProbe): ModuleActivationGate {
                $context = $app->make(TenantContextResolver::class)->current();

                if ($tenantOnly || $registrationProbe) {
                    $app->make(WorkPrincipalAuthority::class)->current($context);
                }

                return new TaskLifetimeActivationGate($context->workspace?->id === Scenario::W1);
            });
        }

        if ($binding !== ModuleActivationGate::class) {
            $this->app->alias($binding, ModuleActivationGate::class);
        }

        return $permanent;
    }
}

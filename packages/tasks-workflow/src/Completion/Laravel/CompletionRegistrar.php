<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Completion\Laravel;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\TasksWorkflow\Completion\Internal\ActivationGateExtension;
use CoreX\TasksWorkflow\Completion\Internal\CompletionScope;
use CoreX\TasksWorkflow\Completion\Internal\ScopedRegistryIntrospection;
use CoreX\TasksWorkflow\Completion\Internal\TaskCompletionTrigger;
use CoreX\TasksWorkflow\Completion\Internal\TasksWorkflowActivationGate;
use CoreX\TasksWorkflow\Completion\Internal\TasksWorkflowOptions;
use CoreX\TasksWorkflow\Completion\Internal\WorkflowCompletionCapture;
use CoreX\Workflow\Contracts\WorkflowBinding;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Application as LaravelApplication;
use LogicException;
use Override;
use Packman\Attributes\Internal;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Contracts\Registry\RegistryIntrospection;
use Packman\Modules\Exceptions\Registry\RegistryInputUnavailable;
use RuntimeException;

#[Internal]
final class CompletionRegistrar implements PackageRegistrar
{
    /** @var array<string, true> */
    private array $readerBindings = [];

    private bool $retiredEarlyReaders = false;

    #[Override]
    public function register(Application $app): void
    {
        $options = $app->make(TasksWorkflowOptions::class);

        if ($options->definitionRef === null || $options->versionRef === null || ! $options->enabled) {
            return;
        }

        $app->scoped(WorkflowCompletionCapture::class, static fn (Application $app): WorkflowCompletionCapture => new WorkflowCompletionCapture(
            scopes: $app->make(CurrentWorkScope::class),
            modules: $app->make(ModuleRegistry::class),
            workflow: $app->make(WorkflowBinding::class),
            definitionRef: $options->definitionRef,
            versionRef: $options->versionRef,
        ));
        $app->tag(WorkflowCompletionCapture::class, TaskCompletionCapture::TAG);

        if ($app instanceof LaravelApplication) {
            $app->scoped(CompletionScope::class);
            $gate = new ActivationGateExtension(
                static fn (ModuleActivationGate $host, LaravelApplication $app): ModuleActivationGate => self::decorateActivationGate(host: $host, app: $app, definitionRef: $options->definitionRef, versionRef: $options->versionRef),
            );
            // Match the current alias because other providers can register it after this one.
            $app->beforeResolving(fn (string $binding, array $parameters, LaravelApplication $app) => $this->prepareResolution(app: $app, binding: $binding, gate: $gate));
            $app->booted(fn () => $this->assertEnabledBindings($app));
        }
    }

    #[Override]
    public function boot(Application $app): void
    {
        if ($app instanceof LaravelApplication) {
            return;
        }

        $options = $app->make(TasksWorkflowOptions::class);

        if ($options->definitionRef === null || $options->versionRef === null || ! $options->enabled) {
            return;
        }

        $app->extend(ModuleActivationGate::class, static fn (ModuleActivationGate $host, Application $app): ModuleActivationGate => self::decorateActivationGate(host: $host, app: $app, definitionRef: $options->definitionRef, versionRef: $options->versionRef));
        $app->booted(fn () => $this->assertEnabledBindings($app));
    }

    private function prepareResolution(LaravelApplication $app, string $binding, ActivationGateExtension $gate): void
    {
        if ($app->isBooted() && $binding === $app->getAlias(ModuleActivationGate::class)) {
            $gate->prepare(app: $app, binding: $binding);
        } elseif ($binding === $app->getAlias(RegistryIntrospection::class)) {
            $this->prepareReader(app: $app, binding: $binding);
        }
    }

    private function prepareReader(LaravelApplication $app, string $binding): void
    {
        if ($app->isBooted() && ! $this->retiredEarlyReaders) {
            $this->retiredEarlyReaders = true;

            foreach (array_keys($this->readerBindings) as $earlyBinding) {
                $app->forgetInstance($earlyBinding);
            }
        }

        if (isset($this->readerBindings[$binding])) {
            return;
        }

        $registration = $app->getBindings()[$binding] ?? null;

        if (! is_array($registration)) {
            return;
        }

        $this->readerBindings[$binding] = true;

        // The reader keeps the gate it receives, so a shared reader must expire with its tenant scope.
        if (($registration['shared'] ?? false) === true && ($registration['concrete'] ?? null) instanceof Closure) {
            $app->scoped($binding, $registration['concrete']);
        }

        $app->extend($binding, static fn (RegistryIntrospection $reader, LaravelApplication $app): RegistryIntrospection => new ScopedRegistryIntrospection(
            reader: $reader,
            scope: $app->make(CompletionScope::class),
            booted: $app->isBooted(),
        ));
    }

    private static function decorateActivationGate(ModuleActivationGate $host, Application $app, string $definitionRef, string $versionRef): ModuleActivationGate
    {
        if ($host instanceof TasksWorkflowActivationGate) {
            return $host;
        }

        $scope = $app instanceof LaravelApplication ? $app->make(CompletionScope::class) : null;

        return new TasksWorkflowActivationGate(host: $host, container: LaravelApplication::getInstance(...), scope: $scope, definitionRef: $definitionRef, versionRef: $versionRef);
    }

    private function assertEnabledBindings(Application $app): void
    {
        if (! $app->bound(WorkflowBinding::class) || ! $app->bound(ModuleRegistry::class)) {
            throw new LogicException('Enabled task workflow completion requires WorkflowBinding and the Packman module registry.');
        }

        $captures = $app->tagged(TaskCompletionCapture::TAG);

        if (! is_countable($captures) || count($captures) !== 1) {
            throw new LogicException('Enabled task workflow completion requires exactly one TaskCompletionCapture contributor.');
        }

        $this->assertTriggerDeclaration($app);
    }

    private function assertTriggerDeclaration(Application $app): void
    {
        $config = $app->make(Repository::class);
        $compileCommand = $config->get('packman.modules.compile_command') ?? $config->get('modules.compile_command');

        if ($app instanceof LaravelApplication
            && is_string($compileCommand)
            && $app->runningConsoleCommand($compileCommand, 'optimize', 'package:discover')) {
            TaskCompletionTrigger::assertInstalledDeclaration($app->make(ModuleDiscovery::class));

            return;
        }

        $registry = $app->make(ModuleRegistry::class);
        $cache = $config->get('packman.modules.cache_path') ?? $config->get('modules.cache_path');
        $cachePath = is_string($cache) ? $app->basePath($cache) : null;

        if ($app->runningInConsole() && $cachePath !== null && ! file_exists($cachePath) && ! is_link($cachePath)
            && $this->compiledModulesAreMissing(app: $app, registry: $registry)) {
            TaskCompletionTrigger::assertInstalledDeclaration($app->make(ModuleDiscovery::class));

            return;
        }

        TaskCompletionTrigger::assertDeclared($registry);
    }

    private function compiledModulesAreMissing(Application $app, ModuleRegistry $registry): bool
    {
        try {
            return $registry->all() === [];
        } catch (RegistryInputUnavailable) {
            return true;
        } catch (RuntimeException $exception) {
            // Packman's production file registry throws a plain RuntimeException for the missing artifact.
            if (! $app->environment('production') || $exception::class !== RuntimeException::class) {
                throw $exception;
            }

            return true;
        }
    }
}

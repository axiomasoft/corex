<?php

declare(strict_types=1);

namespace CoreX\Modules\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Internal\Audit\AuditCapabilityWiring;
use CoreX\Modules\Internal\Commands\CompileModulesCommand;
use CoreX\Modules\Internal\Commands\ExplainModuleCommand;
use CoreX\Modules\Internal\InMemoryEntityRegistry;
use CoreX\Modules\Internal\Registry\CompiledModuleRegistry;
use Illuminate\Console\Application as Console;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Artisan;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ModulesBootRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void {}

    #[Override]
    public function boot(Application $app): void
    {
        if ($app->runningInConsole()) {
            Console::starting(static function (Console $artisan): void {
                $artisan->resolveCommands([
                    CompileModulesCommand::class,
                    ExplainModuleCommand::class,
                ]);
            });

            // Reconcile a module's ownership registry with the real schema and
            // provide the single legal exit from the terminal `purged` state
            // — a thin wrapper over ModuleLifecycle::repair().
            Artisan::command(
                'corex:modules:repair {module : Composer name of the module to repair}',
                function (ModuleLifecycle $lifecycle, string $module): int {
                    $lifecycle->repair($module);
                    $this->info(sprintf('Reconciled module [%s] with the schema.', $module));

                    return 0;
                },
            )->purpose(
                'Reconcile mod_records with the schema and reset a purged module to available.',
            );
        }

        // Epoch memo reset: a long-running queue worker drops its
        // per-process compiled-file memo before each job, so a redeploy's
        // recompiled registry is picked up instead of served stale for hours.
        $app['events']->listen(JobProcessing::class, function () use ($app): void {
            // Typed as the CONTRACT on purpose: a consumer may rebind
            // ModuleRegistry to an implementation without flush(), so the
            // instanceof stays a real guard (and larastan does not narrow the
            // container binding to the concrete, flagging it always-true).
            /** @var ModuleRegistry $registry */
            $registry = $app->make(ModuleRegistry::class);

            if ($registry instanceof CompiledModuleRegistry) {
                $registry->flush();
            }
        });

        // Built after every module provider has booted and registered its
        // entities (ADR-003: EntityRegistry is the single morph-map source).
        $app->booted(function () use ($app): void {
            // The compiler creates the cache that this projection consumes.
            // Do not attempt to read it while its own command is booting on a
            // fresh production deploy.
            if ($this->isCompilingModules($app)) {
                return;
            }

            $arrayRegistry = $app->make(InMemoryEntityRegistry::class);

            // Project the persisted compiled registry into the runtime
            // EntityRegistry so module-declared entities reach the app —
            // not only entities a provider register()'d imperatively.
            $compiled = $app->make(ModuleRegistry::class)->compiled();

            foreach ($compiled->entities as $definition) {
                $arrayRegistry->register($definition);
            }

            $registry = $app->make(EntityRegistry::class);
            $map = [];

            // Morph map spans the WHOLE registry (all() без ctx), never just
            // the active modules: reading a persisted row of a currently
            // disabled module must still resolve its morph alias, or
            // Relation::morphMap loses the handle. The active-module filter in
            // all(?ctx) is a DIFFERENT call — not this one.
            foreach ($registry->all() as $definition) {
                $map[$definition->handle] = $definition->model;
            }

            if ($map !== []) {
                Relation::morphMap($map);
            }

            // AuditObserver lives in axiomasoft/corex (capability 'auditable') —
            // the wiring itself lives in axiomasoft/corex'
            // AuditCapabilityWiring because it needs EntityRegistry, and
            // axiomasoft/corex cannot depend on axiomasoft/corex (reverse of this
            // package's own require).
            AuditCapabilityWiring::wire($registry);
        });
    }

    private function isCompilingModules(Application $app): bool
    {
        return $app->runningInConsole()
            && ($_SERVER['argv'][1] ?? null) === 'corex:modules:compile';
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Commands;

use CoreX\Modules\Contracts\ModuleLifecycle;
use Illuminate\Console\Command;

/**
 * Reconciles a module's ownership registry with the real schema and provides the single legal
 * exit from the terminal `purged` state — a thin wrapper over ModuleLifecycle::repair().
 *
 * @internal
 */
final class RepairModuleCommand extends Command
{
    protected $signature = 'corex:modules:repair {module : Composer name of the module to repair}';

    protected $description = 'Reconcile mod_records with the schema and reset a purged module to available.';

    public function handle(ModuleLifecycle $lifecycle): int
    {
        $module = $this->argument('module');
        $lifecycle->repair($module);
        $this->info(sprintf('Reconciled module [%s] with the schema.', $module));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Internal\Planning;

use CoreX\Tasks\Contracts\TaskOperationReceipts;
use Illuminate\Support\ServiceProvider;

/** @internal Composition for the tasks-owned operation ledger. */
final class PlanningOperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaskOperationReceipts::class, DatabaseTaskOperationReceipts::class);
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Internal;

use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use Illuminate\Support\ServiceProvider;
use Packman\Attributes\Internal;

/** @internal Composition for the tasks-owned operation ledger. */
#[Internal]
final class PlanningOperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaskOperationReceipts::class, DatabaseTaskOperationReceipts::class);
    }
}

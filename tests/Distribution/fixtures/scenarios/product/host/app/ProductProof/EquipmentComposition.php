<?php

declare(strict_types=1);

namespace App\ProductProof;

use App\BulkTransfer\BulkTransferServiceProvider as InstalledBulk;
use App\FieldSearch\EquipmentSource;
use App\Scenario\WorkspaceTaskAccess;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use Illuminate\Support\ServiceProvider;

final class EquipmentComposition extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(InstalledBulk::class);
        $this->app['config']->set('corex-recurring-tasks.wakeup.enabled', true);
        $this->app->scoped(TaskAccess::class, WorkspaceTaskAccess::class);
        $this->app->scoped(EquipmentAccess::class);
        $this->app->alias(EquipmentAccess::class, DataScopeAuthorizer::class);
        $this->app->alias(EquipmentAccess::class, FieldUsePolicy::class);
        $this->app->bind(SearchSource::class, EquipmentSource::class);
        $this->app->tag(EquipmentSource::class, SearchProjectionContributor::class);
        $this->app->bind(PostgresCorpus::class, EquipmentCorpus::class);
        $this->app->extend(TaskRecurrenceCommand::class, static fn (TaskRecurrenceCommand $owner): MaintenanceReceipts => new MaintenanceReceipts($owner));
    }
}

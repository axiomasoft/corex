<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ModuleTableReferences;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Data\ReferencePage;
use CoreX\Foundation\Laravel\SqlReferenceInventory;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;

#[Internal]
final readonly class SeriesModuleReferences implements ModuleTableReferences
{
    public function __construct(private DatabaseManager $connections, private CurrentWorkScope $current) {}

    public function owner(): string
    {
        return 'axiomasoft/corex-recurring-tasks';
    }

    public function moduleTables(): array
    {
        return ['corex_task_series', 'corex_task_series_state', 'corex_task_occurrences'];
    }

    public function scan(ModuleRef $module, DataScope $scope, ?ReferenceCursor $after): ReferencePage
    {
        $tenant = $this->connections->connection();
        $this->current->assert($scope, $tenant);

        return SqlReferenceInventory::scan($this->owner(), [
            'corex_task_series' => [$tenant, []],
            'corex_task_series_state' => [$tenant, ['generation']],
            'corex_task_occurrences' => [$tenant, ['task_ref', 'state']],
        ], $module, $scope, $after);
    }
}

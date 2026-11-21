<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks;

use CoreX\RecurringTasks\Internal\RecurringTasksSchemaSets;
use Override;
use Packman\Attributes\Internal;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Internal]
final class RecurringTasksModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-recurring-tasks')
            ->title('Recurring Tasks')
            ->description('Recurrence-to-task materialization with durable wakeups.')
            ->tablePrefix($this->tablePrefix());
    }

    public function tablePrefix(): string
    {
        return 'twf';
    }

    /** @return list<class-string> */
    public function schemaSetProviders(): array
    {
        return [RecurringTasksSchemaSets::class];
    }
}

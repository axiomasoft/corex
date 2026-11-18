<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use Override;
use Packman\Attributes\Internal;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Internal]
final class TaskWorkflowIntegration extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-recurring-tasks')
            ->title('Tasks Workflow')
            ->description('Recurrence-to-task materialization with durable wakeup and optional workflow capture.')
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

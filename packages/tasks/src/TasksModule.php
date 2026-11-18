<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Tasks\Internal\TasksSchemaSets;
use CoreX\Tasks\Project\Internal\Models\Project;
use CoreX\Tasks\Task\Internal\Models\Task;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Entities\Entity;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TasksModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    public const string NAME = 'axiomasoft/corex-tasks';

    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make(self::NAME)
            ->title('CoreX Tasks')
            ->description('Workspace projects and tasks for DB-per-account applications.')
            ->tablePrefix($this->tablePrefix())
            ->extends([
                Entity::make(Project::class)
                    ->handle('tsk.project')
                    ->table('tsk_projects')
                    ->label(singular: 'Project', plural: 'Projects')
                    ->titleAttribute('name')
                    ->commentable()
                    ->documentable(),
                Entity::make(Task::class)
                    ->handle('tsk.task')
                    ->table('tsk_tasks')
                    ->label(singular: 'Task', plural: 'Tasks')
                    ->titleAttribute('title')
                    ->searchable()
                    ->auditable()
                    ->workflowable()
                    ->commentable()
                    ->documentable(),
            ]);
    }

    public function tablePrefix(): string
    {
        return 'tsk';
    }

    /** @return list<class-string> */
    public function schemaSetProviders(): array
    {
        return [TasksSchemaSets::class];
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\ModuleDefinition;
use CoreX\Modules\Data\Entity;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Tasks\Project\Internal\Models\Project;
use CoreX\Tasks\Task\Internal\Models\Task;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TasksModule extends ModuleDefinition
{
    public const string NAME = 'axiomasoft/corex-tasks';

    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make(self::NAME)
            ->title('CoreX Tasks')
            ->description('Workspace projects and tasks for DB-per-account applications.')
            ->tablePrefix('tsk')
            ->extends([
                Entity::make(Project::class)
                    ->handle('tsk.project')
                    ->table('tsk_projects')
                    ->label('Project', 'Projects')
                    ->titleAttribute('name')
                    ->commentable()
                    ->documentable(),
                Entity::make(Task::class)
                    ->handle('tsk.task')
                    ->table('tsk_tasks')
                    ->label('Task', 'Tasks')
                    ->titleAttribute('title')
                    ->searchable()
                    ->auditable()
                    ->workflowable()
                    ->commentable()
                    ->documentable(),
            ]);
    }
}

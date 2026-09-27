<?php

declare(strict_types=1);

namespace CoreX\Tasks;

use CoreX\Modules\Extend\Entity;
use CoreX\Modules\ModuleDefinition;
use CoreX\Modules\ModuleManifest;
use CoreX\Tasks\Models\Project;
use CoreX\Tasks\Models\Task;

final class TasksModule extends ModuleDefinition
{
    public const string NAME = 'corex/tasks';

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

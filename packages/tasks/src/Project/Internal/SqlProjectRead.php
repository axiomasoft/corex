<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Internal;

use CoreX\Tasks\Project\Contracts\ProjectRead;
use CoreX\Tasks\Project\Data\ProjectView;
use CoreX\Tasks\Project\Internal\Models\Project;
use Packman\Attributes\Internal;

#[Internal]
final class SqlProjectRead implements ProjectRead
{
    public function find(string $id, ?string $connectionName = null): ?ProjectView
    {
        $model = Project::on($connectionName)->find($id);

        return $model === null ? null : new ProjectView(id: $model->id, workspaceId: $model->workspace_id);
    }
}

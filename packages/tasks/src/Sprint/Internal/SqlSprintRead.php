<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Internal;

use CoreX\Tasks\Sprint\Contracts\SprintRead;
use CoreX\Tasks\Sprint\Data\SprintView;
use CoreX\Tasks\Sprint\Internal\Models\Sprint;
use Packman\Attributes\Internal;

#[Internal]
final class SqlSprintRead implements SprintRead
{
    public function find(string $id, ?string $connectionName = null): ?SprintView
    {
        $model = Sprint::on($connectionName)->find($id);

        return $model === null ? null : new SprintView(id: $model->id, projectId: $model->project_id);
    }
}

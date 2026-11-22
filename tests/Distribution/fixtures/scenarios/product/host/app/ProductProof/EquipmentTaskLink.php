<?php

declare(strict_types=1);

namespace App\ProductProof;

use CoreX\Tasks\Task\Laravel\TaskRelations;
use Illuminate\Database\Eloquent\Model;

final class EquipmentTaskLink extends Model
{
    protected $table = 'installed_equipment_task_receipts';

    protected $primaryKey = 'task_id';

    public $incrementing = false;

    public $timestamps = false;

    public function admittedTask(): array
    {
        $task = TaskRelations::belongsTo(model: $this, foreignKey: 'task_id', ownerKey: 'id', relation: 'task')
            ->where('workspace_id', $this->getAttribute('workspace_id'))->firstOrFail();

        return ['id' => $task->getAttribute('id'), 'title' => $task->getAttribute('title'), 'workspace_id' => $task->getAttribute('workspace_id')];
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Internal\Models;

use CoreX\Tasks\Task\Laravel\TaskRelations;
use CoreX\Tasks\Time\Enums\TimeLogSource;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $minutes
 * @property DateTimeInterface $spent_on
 */
final class TimeLog extends Model
{
    use HasUuids;

    protected $table = 'tsk_time_logs';

    /** @var list<string> */
    protected $fillable = ['task_id', 'user_id', 'minutes', 'spent_on', 'comment', 'source'];

    /** @var array<string, mixed> */
    protected $attributes = ['source' => 'manual'];

    /** @return BelongsTo<covariant Model, $this> */
    public function task(): BelongsTo
    {
        return TaskRelations::belongsTo(model: $this, foreignKey: 'task_id', ownerKey: null, relation: 'task');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['minutes' => 'integer', 'spent_on' => 'date', 'source' => TimeLogSource::class];
    }
}

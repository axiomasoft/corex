<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Internal\Models;

use CoreX\Tasks\Task\Laravel\TaskRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Timer extends Model
{
    protected $table = 'tsk_timers';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = ['user_id', 'task_id', 'started_at'];

    /** @return BelongsTo<covariant \Illuminate\Database\Eloquent\Model, $this> */
    public function task(): BelongsTo
    {
        return TaskRelations::belongsTo($this, 'task_id', null, 'task');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'datetime'];
    }
}

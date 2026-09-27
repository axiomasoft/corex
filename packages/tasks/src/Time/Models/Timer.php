<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

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

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'datetime'];
    }
}

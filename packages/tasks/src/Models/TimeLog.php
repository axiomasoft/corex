<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Concerns\HasConfiguredId;
use CoreX\Tasks\Enums\TimeLogSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TimeLog extends Model
{
    use HasConfiguredId;

    protected $table = 'tsk_time_logs';

    /** @var list<string> */
    protected $fillable = ['task_id', 'user_id', 'minutes', 'spent_on', 'comment', 'source'];

    /** @var array<string, mixed> */
    protected $attributes = ['source' => 'manual'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'spent_on' => 'date',
            'source' => TimeLogSource::class,
        ];
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $process_id
 * @property string $name
 * @property string $color
 * @property int $position
 * @property bool $is_final
 */
final class Stage extends BaseModel
{
    protected $table = 'tsk_stages';

    /** @var list<string> */
    protected $fillable = ['process_id', 'name', 'color', 'position', 'is_final'];

    /** @return BelongsTo<Process, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer', 'is_final' => 'boolean'];
    }
}

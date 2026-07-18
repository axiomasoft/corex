<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Models\BaseModel;
use CoreX\Tasks\Enums\ProjectStatus;
use CoreX\Tasks\Enums\ProjectType;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $process_id
 * @property string|null $company_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property ProjectType $type
 * @property ProjectStatus $status
 * @property array<string, mixed>|null $settings
 */
final class Project extends BaseModel
{
    protected $table = 'tsk_projects';

    /** @var list<string> */
    protected $fillable = ['process_id', 'company_id', 'name', 'code', 'description', 'type', 'status', 'settings'];

    /** @return HasMany<ProjectParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ProjectParticipant::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => ProjectType::class, 'status' => ProjectStatus::class, 'settings' => 'array'];
    }
}

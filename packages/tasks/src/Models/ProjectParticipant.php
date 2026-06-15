<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Models\BaseModel;
use CoreX\Tasks\Enums\ProjectParticipantRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $project_id
 * @property string $user_id
 * @property ProjectParticipantRole $role
 */
final class ProjectParticipant extends BaseModel
{
    protected $table = 'tsk_project_participants';

    /** @var list<string> */
    protected $fillable = ['project_id', 'user_id', 'role'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => ProjectParticipantRole::class];
    }
}

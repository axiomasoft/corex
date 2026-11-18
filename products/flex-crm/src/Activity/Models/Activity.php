<?php

declare(strict_types=1);

namespace Flex\Crm\Activity\Models;

use CoreX\Database\Laravel\BaseModel;
use Flex\Crm\Activity\Enums\ActivityType;
use Flex\Crm\Context\Models\Concerns\HasCrmContext;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Packman\Attributes\Internal;

/**
 * Polymorphic activity timeline entry attached to any CRM entity (lead, contact,
 * company, deal) via the `subject` morph.
 *
 * @property string $id
 * @property ActivityType $kind
 * @property string|null $subject
 * @property string $status
 * @property string|null $body
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $user_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $occurred_at
 */
#[Internal]
final class Activity extends BaseModel
{
    use HasCrmContext;

    protected $table = 'crm_activities';

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'kind', 'status', 'subject', 'body', 'direction', 'phone', 'duration_sec', 'location', 'email_meta', 'recording_file_id', 'transcript', 'starts_at', 'ends_at', 'owner_id'];

    /** @return HasMany<ActivityTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(ActivityTarget::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => ActivityType::class,
            'email_meta' => 'array',
            'created_by' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_sec' => 'integer',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Flex\Crm\Models;

use CoreX\Models\TenantModel;
use Flex\Crm\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Polymorphic activity timeline entry attached to any CRM entity (lead, contact,
 * company, deal) via the `subject` morph.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property ActivityType $type
 * @property string $summary
 * @property string|null $body
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $user_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $occurred_at
 */
final class Activity extends TenantModel
{
    protected $table = 'crm_activities';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'type', 'summary', 'body', 'user_id', 'metadata', 'occurred_at'];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}

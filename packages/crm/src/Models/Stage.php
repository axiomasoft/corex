<?php

declare(strict_types=1);

namespace Flex\Crm\Models;

use CoreX\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $pipeline_id
 * @property string $name
 * @property int $position
 * @property int $probability
 * @property bool $is_won
 * @property bool $is_lost
 */
final class Stage extends TenantModel
{
    protected $table = 'crm_stages';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'pipeline_id', 'name', 'position', 'probability', 'is_won', 'is_lost'];

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'probability' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }
}

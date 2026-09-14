<?php

declare(strict_types=1);

namespace Flex\Crm\Models;

use CoreX\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $pipeline_id
 * @property array<string, string> $name
 * @property int $position
 * @property int $probability
 * @property string $semantic
 * @property list<string> $required_fields
 */
final class Stage extends BaseModel
{
    protected $table = 'crm_stages';

    /** @var list<string> */
    protected $fillable = ['pipeline_id', 'code', 'name', 'color', 'semantic', 'position', 'probability', 'required_fields', 'settings'];

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
            'name' => 'array',
            'required_fields' => 'array',
            'settings' => 'array',
        ];
    }
}

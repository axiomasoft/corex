<?php

declare(strict_types=1);

namespace Flex\Crm\Deal\Models;

use CoreX\Database\Laravel\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Packman\Attributes\Internal;

/**
 * @property string $id
 * @property array<string, string> $name
 * @property string|null $workspace_id
 * @property string $entity_type
 * @property bool $is_default
 * @property int $position
 */
#[Internal]
final class Pipeline extends BaseModel
{
    protected $table = 'crm_pipelines';

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'entity_type', 'code', 'name', 'is_default', 'position', 'settings'];

    /** @return HasMany<Stage, $this> */
    public function stages(): HasMany
    {
        $relation = $this->hasMany(Stage::class);
        $relation->getQuery()->getQuery()->orderBy('position');

        return $relation;
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
            'name' => 'array',
            'settings' => 'array',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }
}

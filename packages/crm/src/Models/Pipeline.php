<?php

declare(strict_types=1);

namespace CoreX\Crm\Models;

use CoreX\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property bool $is_default
 * @property int $position
 */
final class Pipeline extends TenantModel
{
    protected $table = 'crm_pipelines';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'name', 'is_default', 'position'];

    /** @return HasMany<Stage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
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
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }
}

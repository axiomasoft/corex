<?php

declare(strict_types=1);

namespace CoreX\Tasks\Models;

use CoreX\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property bool $is_default
 * @property array<string, mixed>|null $settings
 * @property string|null $created_by_id
 */
final class Process extends TenantModel
{
    protected $table = 'tsk_processes';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'name', 'code', 'description', 'is_default', 'settings', 'created_by_id'];

    /** @return HasMany<Stage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'settings' => 'array'];
    }
}

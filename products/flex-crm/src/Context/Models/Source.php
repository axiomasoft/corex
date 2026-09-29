<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Models;

use CoreX\Database\HasConfiguredId;
use Illuminate\Database\Eloquent\Model;
use Override;

final class Source extends Model
{
    use HasConfiguredId;

    protected $table = 'crm_sources';

    protected $fillable = ['workspace_id', 'code', 'name', 'is_active', 'position'];

    #[Override]
    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean', 'position' => 'integer'];
    }
}

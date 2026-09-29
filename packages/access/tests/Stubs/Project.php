<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Stubs;

use CoreX\Database\BaseModel;

/**
 * Stub used as an authorization context entity.
 *
 * @property string $id
 * @property string $name
 */
final class Project extends BaseModel
{
    protected $guarded = [];
}

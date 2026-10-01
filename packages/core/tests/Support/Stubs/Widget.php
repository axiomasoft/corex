<?php

declare(strict_types=1);

namespace CoreX\Testing\Stubs;

use CoreX\Audit\Contracts\Auditable;
use CoreX\Database\Laravel\BaseModel;

/**
 * Test double exercising the kernel: ULID + soft deletes + audit (BaseModel)
 * without optional package casts.
 *
 * @property string $id
 * @property string $name
 */
final class Widget extends BaseModel implements Auditable
{
    protected $guarded = [];
}

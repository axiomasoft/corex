<?php

declare(strict_types=1);

namespace CoreX\Data\Data;

use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldUseRequest
{
    public function __construct(public FieldSelection $fields, public FieldUse $use) {}
}

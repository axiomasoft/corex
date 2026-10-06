<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum ResourceIdFormat
{
    case Uuid7;
    case Uuid;
    case Ulid;
    case Integer;
}

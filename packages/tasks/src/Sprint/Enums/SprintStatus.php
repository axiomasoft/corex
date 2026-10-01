<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Enums;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
}

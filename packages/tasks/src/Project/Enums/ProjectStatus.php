<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Enums;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum ProjectStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}

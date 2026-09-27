<?php

declare(strict_types=1);

namespace CoreX\Tasks\Enums;

enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
}

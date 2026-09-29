<?php

declare(strict_types=1);

namespace CoreX\Tasks\Sprint\Enums;

enum SprintStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Project\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}

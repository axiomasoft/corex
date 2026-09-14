<?php

declare(strict_types=1);

namespace CoreX\Tasks\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Time\Enums;

enum TimeLogSource: string
{
    case Manual = 'manual';
    case Timer = 'timer';
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Enums;

enum TimeLogSource: string
{
    case Manual = 'manual';
    case Timer = 'timer';
}

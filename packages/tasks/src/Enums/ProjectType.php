<?php

declare(strict_types=1);

namespace CoreX\Tasks\Enums;

enum ProjectType: string
{
    case Project = 'project';
    case Retainer = 'retainer';
    case Internal = 'internal';
}

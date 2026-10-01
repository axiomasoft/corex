<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
enum PlanningOperation: string
{
    case CreateProject = 'createProject';
    case CreateSprint = 'createSprint';
    case RecordTime = 'recordTime';
}

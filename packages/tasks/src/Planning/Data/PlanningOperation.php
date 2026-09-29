<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

enum PlanningOperation: string
{
    case CreateProject = 'createProject';
    case CreateSprint = 'createSprint';
    case RecordTime = 'recordTime';
}

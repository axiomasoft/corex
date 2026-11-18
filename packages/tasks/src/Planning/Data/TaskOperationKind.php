<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

use Packman\Attributes\Api;

/** Kinds accepted by the tasks-owned durable operation ledger. */
#[Api(since: '1.0')]
enum TaskOperationKind: string
{
    case CreateProject = 'createProject';
    case CreateSprint = 'createSprint';
    case RecordTime = 'recordTime';
    case Materialize = 'materialize';
    case CompletionCapture = 'completionCapture';
}

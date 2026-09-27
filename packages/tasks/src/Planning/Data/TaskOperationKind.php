<?php

declare(strict_types=1);

namespace CoreX\Tasks\Data;

/** Kinds accepted by the tasks-owned durable operation ledger. */
enum TaskOperationKind: string
{
    case CreateProject = 'createProject';
    case CreateSprint = 'createSprint';
    case RecordTime = 'recordTime';
    case Materialize = 'materialize';
    case CompletionCapture = 'completionCapture';
}

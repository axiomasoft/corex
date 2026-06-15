<?php

declare(strict_types=1);

namespace CoreX\Tasks\Enums;

enum TaskParticipantRole: string
{
    case Responsible = 'responsible';
    case Watcher = 'watcher';
    case Reviewer = 'reviewer';
}

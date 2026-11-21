<?php

declare(strict_types=1);

namespace App\TaskLifetime;

use CoreX\Tasks\Task\Contracts\TaskCompletionCapture;
use CoreX\Tasks\Task\Data\TaskCompletionAdmission;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use Illuminate\Database\ConnectionInterface;
use LogicException;

final class DuplicateCompletionCapture implements TaskCompletionCapture
{
    public function capture(TaskCompletionCandidate $candidate, ConnectionInterface $connection): TaskCompletionAdmission
    {
        throw new LogicException('Duplicate fixture contributor was invoked after the host booted.');
    }
}

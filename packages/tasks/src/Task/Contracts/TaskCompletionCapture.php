<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Tasks\Task\Data\TaskCompletionAdmission;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Optional contributor to the first admitted completion on the active Task owner transaction. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TaskCompletionCapture
{
    public const string TAG = 'corex.tasks.completion-captures';

    public function capture(TaskCompletionCandidate $candidate, ConnectionInterface $connection): TaskCompletionAdmission;
}

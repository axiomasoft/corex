<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tasks\Task\Data\TaskCompletionCandidate;

/** A synchronous save contributor; exceptions abort the caller's transaction. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TaskCompletionCapture
{
    public function capture(TaskCompletionCandidate $candidate): void;
}

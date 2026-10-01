<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tasks\Task\Data\TaskView;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class TaskCompleted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly TaskView $task,
    ) {}
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Events;

use CoreX\Tasks\Task\Data\TaskView;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class TaskUpdated implements ShouldDispatchAfterCommit
{
    /** @param list<string> $changed */
    public function __construct(
        public readonly TaskView $task,
        public readonly array $changed,
    ) {}
}

<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TaskReference
{
    public const string TYPE = 'tsk.task';

    public function __construct(public string $id, public string $type, public string $display) {}
}

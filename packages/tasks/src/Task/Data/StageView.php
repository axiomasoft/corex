<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class StageView
{
    /** @param array<string, string> $name */
    public function __construct(public string $id, public string $projectId, public array $name, public bool $isFinal) {}
}

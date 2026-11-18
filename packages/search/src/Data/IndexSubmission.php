<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class IndexSubmission
{
    public function __construct(public string $taskId, public string $generation) {}
}

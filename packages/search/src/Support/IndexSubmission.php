<?php

declare(strict_types=1);

namespace CoreX\Search\Support;

final readonly class IndexSubmission
{
    public function __construct(public string $taskId, public string $generation) {}
}

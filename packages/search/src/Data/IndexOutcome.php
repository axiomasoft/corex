<?php

declare(strict_types=1);

namespace CoreX\Search\Data;

final readonly class IndexOutcome
{
    public function __construct(public string $status, public ?string $errorCode = null) {}
}

<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

final readonly class StartResult
{
    public function __construct(public string $status, public ?string $instanceRef, public ?string $errorCode) {}
}

<?php

declare(strict_types=1);

namespace CoreX\Observability\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class OperationStarted
{
    public function __construct(
        public OperationContext $context,
        public DateTimeImmutable $occurredAt,
    ) {}
}

<?php

declare(strict_types=1);

namespace CoreX\Observability\Data;

use CoreX\Observability\Internal\OperationContext;
use DateTimeImmutable;

final readonly class OperationStarted
{
    public function __construct(
        public OperationContext $context,
        public DateTimeImmutable $occurredAt,
    ) {}
}

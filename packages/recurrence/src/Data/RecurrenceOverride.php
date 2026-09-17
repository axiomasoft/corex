<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

final readonly class RecurrenceOverride
{
    public function __construct(
        public string $originalRecurrenceId,
        public ?string $effectiveStart,
        public bool $cancelled,
    ) {}
}

<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

final readonly class Occurrence
{
    public function __construct(
        public string $key,
        public string $originalRecurrenceId,
        public string $valueType,
        public ?string $tzid,
        public string $nominal,
        public string $effectiveDateOrInstant,
        public ?int $offset,
        public string $tzdbVersion,
        public string $definitionHash,
    ) {}
}

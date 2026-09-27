<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

/** Bounded schema validation outcome without leaking raw arguments. */
final readonly class SchemaValidation
{
    /**
     * @param  list<array{code: string, pointer: string}>  $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors,
    ) {}
}

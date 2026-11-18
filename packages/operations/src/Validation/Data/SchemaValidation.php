<?php

declare(strict_types=1);

namespace CoreX\Operations\Validation\Data;

use Packman\Attributes\Api;

/** Bounded schema validation outcome without leaking raw arguments. */
#[Api(since: '1.0')]
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

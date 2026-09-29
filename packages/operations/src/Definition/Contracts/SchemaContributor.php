<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Contracts;

use CoreX\Operations\Definition\Operation;

interface SchemaContributor
{
    /** @return array<string, mixed> JSON Schema for this operation's data payload. */
    public function response(Operation $operation): array;
}

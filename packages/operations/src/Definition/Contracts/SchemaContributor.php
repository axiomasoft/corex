<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Operation;

interface SchemaContributor
{
    /** @return array<string, mixed> JSON Schema for this operation's data payload. */
    public function response(Operation $operation): array;
}

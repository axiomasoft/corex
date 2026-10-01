<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Definition\Data\Operation;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SchemaContributor
{
    /** @return array<string, mixed> JSON Schema for this operation's data payload. */
    public function response(Operation $operation): array;
}

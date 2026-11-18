<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Contracts;

use CoreX\Operations\Definition\Data\Operation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SchemaContributor
{
    /** @return array<string, mixed> JSON Schema for this operation's data payload. */
    public function response(Operation $operation): array;
}

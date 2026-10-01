<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Definition\Data\Operation;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface OperationRegistry
{
    /** @return list<Operation> Installed code catalogue; activation and authorization are checked on every dispatch. */
    public function all(string $version): array;

    /** @return array{Operation, array<string, string>} */
    public function match(string $version, string $method, string $path): array;
}

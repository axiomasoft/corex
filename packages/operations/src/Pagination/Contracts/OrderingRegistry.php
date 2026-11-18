<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface OrderingRegistry
{
    /**
     * @return array{direction: string, fields: list<string>, types: list<string>}|null
     */
    public function find(string $ordering): ?array;

    public function canonicalBytes(): string;
}

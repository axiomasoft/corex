<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Laravel;

use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Pagination\Contracts\OrderingRegistry;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OrderingFactory
{
    /** @param list<Operation> $operations */
    public static function fromOperations(array $operations): OrderingRegistry
    {
        return \CoreX\Operations\Pagination\Internal\OrderingRegistry::fromOperations($operations);
    }
}

<?php

declare(strict_types=1);

namespace CoreX\Data\Contracts;

use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DataScopeCodec
{
    /** @return array<string, scalar|null> */
    public function encode(DataScope $scope): array;

    /** @param array<string, mixed> $payload */
    public function decode(array $payload): DataScope;
}

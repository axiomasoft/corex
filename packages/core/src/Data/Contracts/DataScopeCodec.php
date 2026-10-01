<?php

declare(strict_types=1);

namespace CoreX\Data\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DataScopeCodec
{
    /** @return array<string, scalar|null> */
    public function encode(DataScope $scope): array;

    /** @param array<string, mixed> $payload */
    public function decode(array $payload): DataScope;
}

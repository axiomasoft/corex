<?php

declare(strict_types=1);

namespace CoreX\Data\Contracts;

use CoreX\Data\DataScope;

interface DataScopeCodec
{
    /** @return array<string, scalar|null> */
    public function encode(DataScope $scope): array;

    /** @param array<string, mixed> $payload */
    public function decode(array $payload): DataScope;
}

<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Support\Data\DataScope;

interface DataScopeCodec
{
    /** @return array<string, scalar|null> */
    public function encode(DataScope $scope): array;

    /** @param array<string, mixed> $payload */
    public function decode(array $payload): DataScope;
}

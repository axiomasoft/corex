<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Pagination\Data\CursorPosition;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface CursorCodec
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $fields
     * @param  list<string|int|float|bool|null>  $values
     */
    public function encode(OrderingRegistry $registry, string $accountId, ?string $workspaceId, string $apiMajor, string $operationId, array $filters, array $fields, string $orderingId, array $values): string;

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $fields
     */
    public function decode(string $cursor, OrderingRegistry $registry, string $accountId, ?string $workspaceId, string $apiMajor, string $operationId, array $filters, array $fields, string $orderingId): CursorPosition;
}

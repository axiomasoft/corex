<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Pagination\Contracts\CursorCodec;
use CoreX\Operations\Pagination\Contracts\OrderingRegistry;
use CoreX\Operations\Pagination\Data\CursorPage;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CursorPageBuilder
{
    /**
     * @param  list<mixed>  $items
     * @param  list<string|int|float|bool|null>|null  $nextValues
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $fields
     */
    public static function assemble(array $items, ?array $nextValues, CursorCodec $codec, OrderingRegistry $registry, string $accountId, ?string $workspaceId, string $apiMajor, string $operationId, array $filters, array $fields, string $orderingId): CursorPage
    {
        $next = $nextValues === null ? null : $codec->encode(registry: $registry, accountId: $accountId, workspaceId: $workspaceId, apiMajor: $apiMajor, operationId: $operationId, filters: $filters, fields: $fields, orderingId: $orderingId, values: $nextValues);

        return new CursorPage(items: JsonDocuments::capture($items), next: $next);
    }
}

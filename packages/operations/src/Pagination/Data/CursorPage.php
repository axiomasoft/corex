<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

use CoreX\Api\Internal\OrderingRegistry;
use CoreX\Api\Internal\SignedCursorCodec;

/** Assembled page. The next cursor is signed only after the item list exists. */
final readonly class CursorPage
{
    /**
     * @param  list<mixed>  $items
     */
    public function __construct(
        public array $items,
        public ?string $next,
    ) {}

    /**
     * @param  list<mixed>  $items
     * @param  list<string|int|float|bool|null>|null  $nextValues
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $fields
     */
    public static function assemble(
        array $items,
        ?array $nextValues,
        SignedCursorCodec $codec,
        OrderingRegistry $registry,
        string $accountId,
        ?string $workspaceId,
        string $apiMajor,
        string $operationId,
        array $filters,
        array $fields,
        string $orderingId,
    ): self {
        $next = $nextValues === null
            ? null
            : $codec->encode(
                registry: $registry,
                accountId: $accountId,
                workspaceId: $workspaceId,
                apiMajor: $apiMajor,
                operationId: $operationId,
                filters: $filters,
                fields: $fields,
                orderingId: $orderingId,
                values: $nextValues,
            );

        return new self($items, $next);
    }
}

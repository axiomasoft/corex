<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Data;

use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

/** Assembled page. The next cursor is signed only after the item list exists. */
#[Api(since: '1.0')]
final readonly class CursorPage
{
    public function __construct(
        public JsonDocument $items,
        public ?string $next,
    ) {}

}

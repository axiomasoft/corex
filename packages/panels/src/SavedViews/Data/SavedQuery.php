<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;

#[Api(since: '1.0')]
final readonly class SavedQuery
{
    /**
     * @param  list<array{field: string, direction: string}>  $sort
     * @param  list<string>  $projection
     */
    public function __construct(public string $schemaVersion, public JsonDocument $filters, public array $sort, public array $projection, public ?string $groupBy) {}
}

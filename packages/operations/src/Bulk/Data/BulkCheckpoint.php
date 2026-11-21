<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class BulkCheckpoint
{
    public function __construct(public int $sequence, public JsonDocument $ownerState)
    {
        if ($sequence < 1 || strlen(json_encode(JsonDocuments::map($ownerState), JSON_THROW_ON_ERROR)) > 20 * 1024) {
            throw new InvalidArgumentException('Invalid bulk checkpoint.');
        }
    }
}

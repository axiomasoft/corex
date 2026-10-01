<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;

#[Api(since: '1.0')]
final readonly class ReferencePage
{
    public function __construct(public string $status, public JsonDocument $records, public ?ReferenceCursor $next, public ?string $snapshotId, public JsonDocument $provenance) {}
}

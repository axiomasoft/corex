<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DatasetReceipt
{
    public function __construct(
        public string $status,
        public ?string $snapshotId,
        public int $generation,
        public ?string $errorCode,
    ) {}
}

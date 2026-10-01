<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ReferenceCursor
{
    public function __construct(
        public string $schema,
        public string $snapshotId,
        public string $queryHash,
        public string $lastNormalizedName,
        public string $lastNamespace,
        public string $lastSourceId,
        public string $proof,
    ) {}
}

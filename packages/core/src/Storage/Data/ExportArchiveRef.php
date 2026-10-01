<?php

declare(strict_types=1);

namespace CoreX\Storage\Data;

use CoreX\Attributes\Api;
use DateTimeImmutable;

#[Api(since: '1.0')]
final readonly class ExportArchiveRef
{
    public function __construct(
        public string $storagePath,
        public int $sizeBytes,
        public DateTimeImmutable $createdAt,
    ) {}
}

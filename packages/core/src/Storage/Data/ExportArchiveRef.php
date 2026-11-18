<?php

declare(strict_types=1);

namespace CoreX\Storage\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ExportArchiveRef
{
    public function __construct(
        public string $storagePath,
        public int $sizeBytes,
        public DateTimeImmutable $createdAt,
    ) {}
}

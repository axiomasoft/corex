<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

final readonly class DatasetReceipt
{
    public function __construct(
        public string $status,
        public ?string $snapshotId,
        public int $generation,
        public ?string $errorCode,
    ) {}
}

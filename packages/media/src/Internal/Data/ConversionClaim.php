<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Data;

final readonly class ConversionClaim
{
    public function __construct(
        public int $operationId,
        public int $conversionId,
        public string $fileId,
        public int $epoch,
        public int $fileVersion,
        public string $sourceBlobId,
        public string $accountId,
        public string $sourceMimeType,
        public string $profileKey,
        public string $profileDigest,
    ) {}
}

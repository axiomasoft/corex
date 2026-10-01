<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ConversionClaim
{
    public function __construct(
        public int $operationId,
        public int $conversionId,
        public string $fileId,
        public int $epoch,
        public int $fileVersion,
        public string $sourceKey,
        public string $sourceMimeType,
        public string $profileKey,
        public string $profileDigest,
        public string $attemptObjectKey,
    ) {}
}

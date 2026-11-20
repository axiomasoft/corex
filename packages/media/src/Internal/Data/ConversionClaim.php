<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Data;

use CoreX\Data\Data\DataScope;
use Packman\Attributes\Internal;

#[Internal]
final readonly class ConversionClaim
{
    public function __construct(
        public int $operationId,
        public DataScope $scope,
        public string $token,
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

<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\Context\Data\OperationKey;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class BulkContext
{
    public function __construct(
        public DataScope $scope,
        public string $operationRef,
        public string $kind,
        public string $contractFingerprint,
        public OperationKey $key,
        public string $clientId,
        public JsonDocument $inputRef,
        public JsonDocument $extensionStamp,
    ) {}
}

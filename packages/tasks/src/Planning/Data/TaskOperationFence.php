<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\ScopeIdentity;
use Packman\Attributes\Api;

/** Physical owner identity at the time the result was recorded. */
#[Api(since: '2.0.0')]
final readonly class TaskOperationFence
{
    public function __construct(
        public string $deploymentId,
        public string $bindingId,
        public int $bindingGeneration,
        public string $accountIncarnation,
        public string $restoreEpoch,
    ) {
        ScopeIdentity::physical($deploymentId, $bindingId, $bindingGeneration, $accountIncarnation, $restoreEpoch);
    }

    public static function fromScope(DataScope $scope): self
    {
        return new self($scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->accountIncarnation, $scope->restoreEpoch);
    }
}

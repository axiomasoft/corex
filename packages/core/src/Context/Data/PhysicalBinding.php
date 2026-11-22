<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\ScopeIdentity;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class PhysicalBinding
{
    public function __construct(
        public string $deploymentId,
        public string $bindingId,
        public int $bindingGeneration,
        public string $accountIncarnation,
        public string $restoreEpoch,
        public string $mode = 'open',
        public int $controlRevision = 1,
    ) {
        ScopeIdentity::physical(deployment: $deploymentId, binding: $bindingId, generation: $bindingGeneration, incarnation: $accountIncarnation, epoch: $restoreEpoch);

        if ($controlRevision < 1 || ! in_array($mode, ['open', 'closing', 'closed'], true)) {
            throw new InvalidArgumentException('Invalid physical binding admission state.');
        }
    }

    public function assertScope(DataScope $scope): void
    {
        if ($this->deploymentId !== $scope->deploymentId || $this->bindingId !== $scope->bindingId
            || $this->bindingGeneration !== $scope->bindingGeneration || $this->accountIncarnation !== $scope->accountIncarnation
            || ! hash_equals($this->restoreEpoch, $scope->restoreEpoch)) {
            throw new ScopeStale('Work belongs to a stale physical binding.');
        }
    }
}

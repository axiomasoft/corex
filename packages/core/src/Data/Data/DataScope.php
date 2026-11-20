<?php

declare(strict_types=1);

namespace CoreX\Data\Data;

use CoreX\Messaging\Contracts\DurablePayload;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class DataScope implements DurablePayload
{
    public const int VERSION = 3;

    public function __construct(
        public string $deploymentId,
        public string $accountId,
        public ?string $workspaceId,
        public string $bindingId,
        public int $bindingGeneration,
        public string $accountIncarnation,
        public string $restoreEpoch,
        public string $principalKind,
        public string $principalId,
    ) {
        ScopeIdentity::physical($deploymentId, $bindingId, $bindingGeneration, $accountIncarnation, $restoreEpoch);
        ScopeIdentity::principal($principalKind, $principalId);
        ScopeIdentity::opaque($accountId);

        if ($workspaceId !== null) {
            ScopeIdentity::opaque($workspaceId);
        }
    }
}

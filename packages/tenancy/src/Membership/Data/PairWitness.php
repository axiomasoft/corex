<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Data;

use CoreX\Attributes\Api;
use CoreX\Tenancy\Membership\Internal\Transport\Value;

#[Api(since: '1.0')]
final readonly class PairWitness
{
    public function __construct(
        public string $revision,
        public string $revokeGeneration,
        public string $localEpoch,
        public string $operationFence,
        public ?string $applicationFence,
    ) {
        Value::counter($revision);
        Value::counter($revokeGeneration);
        Value::counter($localEpoch);
        Value::counter($operationFence);

        if ($applicationFence !== null) {
            Value::counter($applicationFence);
        }
    }
}

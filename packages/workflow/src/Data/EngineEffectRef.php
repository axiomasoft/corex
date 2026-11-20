<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Data\Data\DataScope;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class EngineEffectRef
{
    public function __construct(
        public DataScope $scope,
        public string $providerNamespace,
        public string $effectKey,
        public ?string $instanceRef = null,
    ) {}
}

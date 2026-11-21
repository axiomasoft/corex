<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class CleanupAction
{
    public function __construct(public string $owner, public string $namespace, public string $resourceKind, public string $resourceId, public string $version, public string $effectKey) {}
}

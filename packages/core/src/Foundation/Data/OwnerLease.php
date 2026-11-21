<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class OwnerLease
{
    public function __construct(public string $owner, public string $namespace, public string $revision, public string $token) {}
}

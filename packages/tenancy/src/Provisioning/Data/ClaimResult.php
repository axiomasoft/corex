<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ClaimResult
{
    public function __construct(
        public string $accountId,
        public bool $replayed,
    ) {}
}

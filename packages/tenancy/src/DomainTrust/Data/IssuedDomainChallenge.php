<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Data;

use Packman\Attributes\Api;
use SensitiveParameter;

#[Api(since: '1.0')]
final readonly class IssuedDomainChallenge
{
    public function __construct(
        public DomainChallenge $challenge,
        #[SensitiveParameter]
        public string $token,
    ) {}
}

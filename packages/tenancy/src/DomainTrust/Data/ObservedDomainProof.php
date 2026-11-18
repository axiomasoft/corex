<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Data;

use Packman\Attributes\Api;
use SensitiveParameter;

#[Api(since: '1.0')]
final readonly class ObservedDomainProof
{
    /** @param list<string> $facts */
    public function __construct(
        public array $facts,
        #[SensitiveParameter]
        public ?string $challengeToken = null,
    ) {}
}

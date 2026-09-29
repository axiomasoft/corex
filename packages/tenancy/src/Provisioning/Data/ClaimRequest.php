<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Data;

final readonly class ClaimRequest
{
    public function __construct(
        public string $requestKey,
        public string $clusterId,
        public string $templateId,
        public string $requestDigest,
    ) {}
}

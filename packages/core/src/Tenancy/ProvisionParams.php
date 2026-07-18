<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

/**
 * Canonical shape — B-11 §3.2 (owner: B-11). Fixes embedding_model/dim on
 * the account row at provisioning time (AC-10, R-16) — never read from
 * global config on the fly.
 */
final readonly class ProvisionParams
{
    public function __construct(
        public string $accountId,
        public string $clusterId,
        public int $templateVersion,
        public string $embeddingModel,
        public int $embeddingDim,
        public ?string $verticalCode = null,
        public string $locale = 'ru',
    ) {}
}

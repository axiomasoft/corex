<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use Packman\Attributes\Api;

/**
 * Fixes embedding_model/dim on the account row at provisioning time — never
 * read from global config on the fly.
 */
#[Api(since: '1.0')]
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

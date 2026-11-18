<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

use DateTimeImmutable;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RateSnapshot
{
    public function __construct(
        public string $schema,
        public string $base,
        public string $quote,
        public string $rateDecimal,
        public string $validOn,
        public DateTimeImmutable $observedAt,
        public string $source,
        public string $publicationId,
        public string $publicationRevision,
        public string $payloadSha256,
        public string $id,
    ) {}
}

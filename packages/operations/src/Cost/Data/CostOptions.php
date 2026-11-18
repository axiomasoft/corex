<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use Packman\Attributes\Api;
use Packman\Config\Data\SecretRef;

/** Immutable cost-control activation policy read only by the package registrar. */
#[Api(since: '1.0')]
final readonly class CostOptions
{
    /** @param list<string> $enforcedLevels */
    public function __construct(
        public bool $enabled,
        public string $installationId,
        public SecretRef $scopeHmacSecret,
        public int $capacity,
        public int $restoreRate,
        public array $enforcedLevels,
        public int $reservationTtlSeconds,
        public int $terminalRetentionSeconds,
    ) {}

}

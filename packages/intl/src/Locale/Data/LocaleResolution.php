<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class LocaleResolution
{
    /** @param list<string> $fallbackChain */
    public function __construct(
        public ?string $requested,
        public string $used,
        public string $origin,
        public array $fallbackChain,
        public bool $wasFallback,
        public string $policyHash,
        public ?string $icuVersion,
    ) {}
}

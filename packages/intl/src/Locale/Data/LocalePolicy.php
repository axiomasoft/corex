<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Data;

final readonly class LocalePolicy
{
    /** @param list<string> $supportedTags | @param list<string> $explicitFallbackTags */
    public function __construct(
        public array $supportedTags,
        public string $appTag,
        public array $explicitFallbackTags,
        public bool $allowParent,
        public string $policyHash,
    ) {}
}

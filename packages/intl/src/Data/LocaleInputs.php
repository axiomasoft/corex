<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

final readonly class LocaleInputs
{
    public function __construct(
        public ?string $projectedUserTag,
        public ?string $accountSettingsTag,
        public string $requestContextKey,
    ) {}
}

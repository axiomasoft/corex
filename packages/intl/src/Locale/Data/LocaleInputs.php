<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class LocaleInputs
{
    public function __construct(
        public ?string $projectedUserTag,
        public ?string $accountSettingsTag,
        public string $requestContextKey,
    ) {}
}

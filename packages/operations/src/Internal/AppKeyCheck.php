<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal;

use CoreX\Config\Contracts\RuntimeConfigCheck;
use Override;

/** The idempotency store derives its key hash from `app.key`; production must not start without it. */
final readonly class AppKeyCheck implements RuntimeConfigCheck
{
    public function __construct(private mixed $appKey) {}

    #[Override]
    public function violations(bool $production): array
    {
        if (! $production || (is_string($this->appKey) && $this->appKey !== '')) {
            return [];
        }

        return ['app.key: must be configured (APP_KEY) for the idempotency store'];
    }
}
